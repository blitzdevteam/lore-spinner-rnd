<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\IpTrimmingChapterAgent;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline Upgrade V2 — IP Trimming per-chapter pass.
 *
 * Processes one chapter at a time and stores its fragment in the Laravel cache
 * under the key `ip_trimming_fragment:{story_id}:{chapter_id}`.
 *
 * IpTrimmingMergeJob collects all fragments, PHP-merges world_rules /
 * triage_log / conversion_notes / trimmed_text, makes a single small synthesis
 * call for the story_spine, and writes the final package to
 * story_adaptations.ip_trimming.
 *
 * Fragment cache TTL: 24 hours (enough to survive any queue backpressure).
 */
final class IpTrimmingChapterJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private Chapter $chapter,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * Chapters above this character count get gpt-5.4 instead of gpt-5.4-mini.
     * Mini hits its output token ceiling on long screenplay chapters, producing
     * incomplete JSON and a "max tokens exceeded" failure.
     */
    private const int LARGE_CHAPTER_CHAR_THRESHOLD = 8_000;

    /**
     * Hard cap on chapter content passed to the model.
     * Verbatim trimmed_chapter_text in the structured output mirrors input length,
     * so an unbounded chapter can push the response past any model's output token
     * ceiling. Content beyond this cap is noted with a TRUNCATED marker.
     * Raw chapter text is always preserved in chapters.content.
     */
    private const int MAX_CHAPTER_INPUT_CHARS = 12_000;

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $totalChapters = $this->story->chapters()->count();
        $prevChapter = $this->story->chapters()->where('position', $this->chapter->position - 1)->first();
        $nextChapter = $this->story->chapters()->where('position', $this->chapter->position + 1)->first();

        $rawContent = $this->chapter->content ?? '';
        $chapterLength = mb_strlen($rawContent);
        $model = $chapterLength > self::LARGE_CHAPTER_CHAR_THRESHOLD ? 'gpt-5.4' : null;

        $truncated = $chapterLength > self::MAX_CHAPTER_INPUT_CHARS;
        $chapterContent = $truncated
            ? mb_substr($rawContent, 0, self::MAX_CHAPTER_INPUT_CHARS)
              . "\n\n[TRUNCATED: " . ($chapterLength - self::MAX_CHAPTER_INPUT_CHARS) . " chars omitted — raw text preserved in DB]"
            : $rawContent;

        Log::info('ip_trimming.chapter_start', [
            'story_id' => $this->story->id,
            'chapter_id' => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'chapter_title' => $this->chapter->title,
            'chapter_chars' => $chapterLength,
            'truncated' => $truncated,
            'model' => $model ?? 'gpt-5.4-mini (default)',
        ]);

        if ($truncated) {
            Log::warning('ip_trimming.chapter_truncated', [
                'story_id' => $this->story->id,
                'chapter_id' => $this->chapter->id,
                'chapter_position' => $this->chapter->position,
                'original_chars' => $chapterLength,
                'sent_chars' => self::MAX_CHAPTER_INPUT_CHARS,
            ]);
        }

        $response = (new IpTrimmingChapterAgent)->prompt(
            view('ai.agents.adaptation.ip-trimming.chapter-prompt', [
                'title' => $this->story->title,
                'author' => $this->story->creator?->full_name ?? 'Unknown Author',
                'format' => $this->story->adaptation?->format_detection['detected_format'] ?? 'UNKNOWN',
                'chapterId' => $this->chapter->id,
                'chapterPosition' => $this->chapter->position,
                'chapterTitle' => $this->chapter->title,
                'totalChapters' => $totalChapters,
                'previousChapterTitle' => $prevChapter?->title ?? '',
                'nextChapterTitle' => $nextChapter?->title ?? '',
                'chapterContent' => $chapterContent,
            ])->render(),
            model: $model,
        );

        $fragment = $response->toArray();

        Cache::put(
            "ip_trimming_fragment:{$this->story->id}:{$this->chapter->id}",
            $fragment,
            now()->addHours(24)
        );

        Log::info('ip_trimming.chapter_complete', [
            'story_id' => $this->story->id,
            'chapter_id' => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'chapter_chars' => $chapterLength,
            'model_used' => $model ?? 'gpt-5.4-mini (default)',
            'triage_entries' => count($fragment['content_triage_log'] ?? []),
            'trimmed_chars' => mb_strlen($fragment['trimmed_chapter_text'] ?? ''),
        ]);
    }
}
