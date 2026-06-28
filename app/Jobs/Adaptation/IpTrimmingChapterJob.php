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

        Log::info('ip_trimming.chapter_start', [
            'story_id' => $this->story->id,
            'chapter_id' => $this->chapter->id,
            'chapter_position' => $this->chapter->position,
            'chapter_title' => $this->chapter->title,
        ]);

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
                'chapterContent' => $this->chapter->content ?? '',
            ])->render()
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
            'triage_entries' => count($fragment['content_triage_log'] ?? []),
            'trimmed_chars' => mb_strlen($fragment['trimmed_chapter_text'] ?? ''),
        ]);
    }
}
