<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\IpTrimmingMergeAgent;
use App\ChaosMode\ChaosStoryConfig;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use App\Support\Adaptation\SessionAdaptationChain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline Upgrade V2 — IP Trimming merge pass.
 *
 * Runs after all IpTrimmingChapterJob instances complete. Collects chapter
 * fragments from the Laravel cache and:
 *
 *   1. PHP-merges world_rules (union, keyed by rule text to deduplicate),
 *      content_triage_log (ordered concat), interactive_conversion_notes
 *      (ordered concat), and trimmed_source_text (chapter-marker concat
 *      with per-chapter segment index).
 *   2. Makes one small synthesis API call (IpTrimmingMergeAgent) over the
 *      collected story_spine_fragments to produce the unified story_spine.
 *   3. Writes the final Deliverable 7 package to story_adaptations.ip_trimming.
 *   4. Dispatches FormatDetectionJob, which chains IpAuditJob → VoiceLock batch.
 */
final class IpTrimmingMergeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private bool $continuePipeline = true,
        /** @var array<int, int> */
        private array $rerunSessionNumbers = [],
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;
        $chapters = $this->story->chapters()->orderBy('position')->get();

        $fragments = [];
        foreach ($chapters as $chapter) {
            $key = "ip_trimming_fragment:{$this->story->id}:{$chapter->id}";
            $fragment = Cache::get($key);
            if ($fragment !== null) {
                $fragments[] = $fragment;
                Cache::forget($key);
            }
        }

        Log::info('ip_trimming.merge_start', [
            'story_id' => $this->story->id,
            'fragments_collected' => count($fragments),
            'chapters_total' => $chapters->count(),
        ]);

        if (empty($fragments)) {
            Log::error('ip_trimming.merge_failed: no fragments in cache', ['story_id' => $this->story->id]);
            $adaptation->update(['adaptation_status' => AdaptationStatusEnum::FAILED]);

            return;
        }

        if (! $this->continuePipeline && count($fragments) < $chapters->count()) {
            Log::warning('ip_trimming.repair_merge_partial', [
                'story_id' => $this->story->id,
                'fragments_collected' => count($fragments),
                'chapters_total' => $chapters->count(),
                'note' => 'Proceeding with partial merge — missing chapters will be re-attempted on next repair run.',
            ]);
        }

        // Sort fragments by chapter_position to ensure correct order.
        usort($fragments, fn ($a, $b) => ($a['chapter_position'] ?? 0) <=> ($b['chapter_position'] ?? 0));

        // --- PHP merge: world_rules (union by rule text, case-insensitive) ---
        $worldRules = [
            'physics_technology' => [],
            'creatures_entities' => [],
            'geography_locations' => [],
            'social_systems' => [],
            'what_cannot_exist' => [],
        ];
        foreach ($fragments as $fragment) {
            foreach (array_keys($worldRules) as $category) {
                $key = $category === 'what_cannot_exist' ? 'thing' : 'rule';
                foreach ((array) ($fragment['world_rules_fragments'][$category] ?? []) as $entry) {
                    $text = mb_strtolower(trim((string) ($entry[$key] ?? '')));
                    if ($text === '') {
                        continue;
                    }
                    $isDuplicate = false;
                    foreach ($worldRules[$category] as $existing) {
                        if (mb_strtolower(trim((string) ($existing[$key] ?? ''))) === $text) {
                            $isDuplicate = true;
                            break;
                        }
                    }
                    if (! $isDuplicate) {
                        $worldRules[$category][] = $entry;
                    }
                }
            }
        }

        // --- PHP merge: triage log + conversion notes (ordered concat) ---
        $triageLog = array_merge(...array_map(
            fn ($f) => (array) ($f['content_triage_log'] ?? []),
            $fragments
        ));
        $conversionNotes = array_merge(...array_map(
            fn ($f) => (array) ($f['interactive_conversion_notes'] ?? []),
            $fragments
        ));

        // --- PHP merge: trimmed source text with chapter markers + segment index ---
        $chapterSegments = [];
        $trimmedParts = [];
        foreach ($fragments as $fragment) {
            $chId = (int) ($fragment['chapter_id'] ?? 0);
            $chPos = (int) ($fragment['chapter_position'] ?? 0);
            $text = (string) ($fragment['trimmed_chapter_text'] ?? '');
            $chapterSegments[] = [
                'chapter_id' => $chId,
                'chapter_position' => $chPos,
                'text' => $text,
            ];
            $trimmedParts[] = "--- [CHAPTER {$chPos}] ---\n" . $text;
        }
        $mergedTrimmedText = implode("\n\n", $trimmedParts);

        $originalLength = mb_strlen($this->story->getScriptContent());
        $trimmedLength = mb_strlen($mergedTrimmedText);
        $reductionPct = $originalLength > 0
            ? round((1 - $trimmedLength / $originalLength) * 100) . '%'
            : '0%';

        // --- API call: synthesize unified story_spine from all spine fragments ---
        $spineFragments = array_map(
            fn ($f) => $f['story_spine_fragment'] ?? [],
            $fragments
        );

        $chaosConfig = ChaosStoryConfig::find($this->story->slug);

        $spineResponse = (new IpTrimmingMergeAgent)->prompt(
            view('ai.agents.adaptation.ip-trimming.merge-prompt', [
                'title'               => $this->story->title,
                'author'              => $this->story->creator?->full_name ?? 'Unknown Author',
                'totalChapters'       => count($fragments),
                'spineFragments'      => $spineFragments,
                'playableProtagonist' => $chaosConfig['protagonist'] ?? null,
            ])->render()
        );

        $unifiedSpine = $spineResponse->toArray();

        // Hard-lock: if this story has a registered Chaos protagonist, enforce it
        // regardless of what the AI wrote — the config is the source of truth.
        if (!empty($chaosConfig['protagonist'])) {
            $unifiedSpine['protagonist'] = $chaosConfig['protagonist'];
        }

        // --- Assemble final Deliverable 7 package ---
        $ipTrimming = [
            'story_spine' => $unifiedSpine,
            'world_rules' => $worldRules,
            'content_triage_log' => $triageLog,
            'interactive_conversion_notes' => $conversionNotes,
            'trimmed_source_text' => [
                'original_length_estimate' => number_format($originalLength) . ' chars',
                'trimmed_length_estimate' => number_format($trimmedLength) . ' chars',
                'reduction_percentage' => $reductionPct,
                'text' => $mergedTrimmedText,
                'chapter_segments' => $chapterSegments,
            ],
        ];

        $adaptation->update(['ip_trimming' => $ipTrimming]);

        Log::info('ip_trimming.merge_complete', [
            'story_id' => $this->story->id,
            'reduction' => $reductionPct,
            'world_rules_count' => array_sum(array_map('count', $worldRules)),
            'triage_entries' => count($triageLog),
            'conversion_notes' => count($conversionNotes),
            'chapter_segments' => count($chapterSegments),
            'repair_mode' => ! $this->continuePipeline,
        ]);

        if ($this->continuePipeline) {
            if (count($fragments) < $chapters->count()) {
                Log::error('ip_trimming.pipeline_blocked', [
                    'story_id'           => $this->story->id,
                    'fragments_collected' => count($fragments),
                    'chapters_total'      => $chapters->count(),
                    'note'               => 'IP trimming incomplete — pipeline halted. Run repair to retry missing chapters.',
                ]);
                $adaptation->update(['adaptation_status' => AdaptationStatusEnum::FAILED]);

                return;
            }

            FormatDetectionJob::dispatch($this->story)->onQueue('adaptation');

            return;
        }

        if ($this->rerunSessionNumbers !== []) {
            $storyId = $this->story->id;

            SessionAdaptationChain::dispatchBatch(
                $this->story,
                $this->rerunSessionNumbers,
                fn () => AdaptationStatusReconciliationJob::dispatch(Story::findOrFail($storyId))->onQueue('adaptation'),
            );

            return;
        }

        AdaptationStatusReconciliationJob::dispatch($this->story)->onQueue('adaptation');
    }
}
