<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\WriterLab;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\WriterLabDraft;
use Inertia\Response;

final class WriterLabController extends Controller
{
    /**
     * List all stories with their adaptation status and chapter/session counts.
     */
    public function index(): Response
    {
        $stories = Story::query()
            ->with(['adaptation'])
            ->withCount('chapters')
            ->orderBy('title')
            ->get()
            ->map(fn (Story $story): array => [
                'id'                => $story->id,
                'slug'              => $story->slug,
                'title'             => $story->title,
                'adaptation_status' => $story->adaptation?->adaptation_status,
                'chapters_count'    => $story->chapters_count,
                'session_count'     => $story->adaptation?->sessionAdaptations()->count() ?? 0,
            ]);

        return inertia('WriterLab/Index', ['stories' => $stories]);
    }

    /**
     * Show a story's chapters with event counts and session coverage.
     */
    public function show(Story $story): Response
    {
        $chapters = $story->chapters()
            ->orderBy('position')
            ->withCount('events')
            ->get()
            ->map(fn (Chapter $chapter): array => [
                'id'           => $chapter->id,
                'position'     => $chapter->position,
                'title'        => $chapter->title,
                'events_count' => $chapter->events_count,
                'sessions'     => Event::where('chapter_id', $chapter->id)
                    ->whereNotNull('session_number')
                    ->distinct('session_number')
                    ->pluck('session_number')
                    ->sort()
                    ->values(),
            ]);

        return inertia('WriterLab/Show', [
            'story'    => ['id' => $story->id, 'title' => $story->title, 'slug' => $story->slug],
            'chapters' => $chapters,
        ]);
    }

    /**
     * Open a chapter in the two-panel editor.
     * Left panel: source events ordered by position.
     * Right panel: session adaptation data (cold_open, beat_map, choices).
     */
    public function chapter(Story $story, Chapter $chapter): Response
    {
        $rawEvents = Event::where('chapter_id', $chapter->id)
            ->orderBy('position')
            ->get();

        $sessionNumbers = $rawEvents->pluck('session_number')->filter()->unique()->sort()->values();

        $sessionAdaptationModels = SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
            ->whereIn('session_number', $sessionNumbers)
            ->get()
            ->keyBy('session_number');

        // Best-effort match: each event's beat_type comes from the beat_map entry
        // whose `moment` text has the highest word overlap with the event's title + content.
        // Beat map is per-session and contains fewer entries than events, so multiple
        // events may share the same matched beat_type (correct — beats span events).
        $events = $rawEvents->map(function (Event $event) use ($sessionAdaptationModels): array {
            $beatMatch = null;
            $sessionNum = $event->session_number;
            if ($sessionNum !== null) {
                $sa = $sessionAdaptationModels[$sessionNum] ?? null;
                $beatMap = $sa?->session_architecture['beat_map'] ?? [];
                if (!empty($beatMap)) {
                    $beatMatch = $this->bestBeatMatch($event, $beatMap);
                }
            }

            return [
                'id'              => $event->id,
                'position'        => $event->position,
                'title'           => $event->title,
                'content'         => $event->content,
                'objectives'      => $event->objectives,
                'attributes'      => $event->attributes,
                'session_number'  => $event->session_number,
                'requires_choice' => $event->requires_choice,
                // Extracted from session_architecture.beat_map — see bestBeatMatch()
                'beat_type'       => $beatMatch['beat_type'] ?? null,
                'beat_moment'     => $beatMatch['moment'] ?? null,
            ];
        });

        $sessionAdaptations = $sessionAdaptationModels->mapWithKeys(
            fn (SessionAdaptation $sa): array => [
                $sa->session_number => [
                    'session_number'      => $sa->session_number,
                    'session_status'      => $sa->session_status,
                    'cold_open'           => $sa->entry_point_diagnosis['cold_open'] ?? null,
                    'start_event_id'      => $sa->entry_point_diagnosis['start_event_id'] ?? null,
                    'beat_map'            => $sa->session_architecture['beat_map'] ?? null,
                    'session_choice_design' => $sa->session_choice_design,
                    'choice_consequence_map' => $sa->choice_consequence_map,
                    'session_close_design' => $sa->session_close_design,
                ],
            ]
        );

        $prevChapter = Chapter::where('story_id', $story->id)
            ->where('position', '<', $chapter->position)
            ->orderByDesc('position')
            ->first(['id', 'position', 'title']);

        $nextChapter = Chapter::where('story_id', $story->id)
            ->where('position', '>', $chapter->position)
            ->orderBy('position')
            ->first(['id', 'position', 'title']);

        $activeDrafts = WriterLabDraft::where('chapter_id', $chapter->id)
            ->active()
            ->orderByDesc('created_at')
            ->get(['id', 'type', 'status', 'source_event_ids', 'beat_type', 'requires_choice', 'rewritten_content', 'derived_objectives', 'derived_attributes', 'adaptation_patch', 'created_at']);

        return inertia('WriterLab/Chapter', [
            'story'              => ['id' => $story->id, 'title' => $story->title, 'slug' => $story->slug],
            'chapter'            => ['id' => $chapter->id, 'position' => $chapter->position, 'title' => $chapter->title],
            'prevChapter'        => $prevChapter,
            'nextChapter'        => $nextChapter,
            'events'             => $events,
            'sessionAdaptations' => $sessionAdaptations,
            'activeDrafts'       => $activeDrafts,
        ]);
    }

    /**
     * Pick the beat_map entry whose `moment` text most resembles this event's title + content.
     * Returns ['beat_type' => 'SETUP', 'moment' => '...'] (lowercased beat_type) or null.
     *
     * Heuristic: token-overlap score on lowercase alphanumeric word tokens, with stopword
     * filter. Fast and stable; doesn't need an LLM call.
     *
     * @param array<int, array<string, mixed>> $beatMap
     */
    private function bestBeatMatch(Event $event, array $beatMap): ?array
    {
        $eventTokens = $this->tokenize($event->title . ' ' . $event->content);
        if (empty($eventTokens)) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($beatMap as $entry) {
            $momentText = (string) ($entry['moment'] ?? '');
            if ($momentText === '') {
                continue;
            }
            $beatTokens = $this->tokenize($momentText);
            $overlap    = count(array_intersect($eventTokens, $beatTokens));
            if ($overlap > $bestScore) {
                $bestScore = $overlap;
                $best      = $entry;
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            // Lowercase to match the runtime convention used by Vue's editBeatType select
            'beat_type' => strtolower((string) ($best['beat_type'] ?? '')),
            'moment'    => (string) ($best['moment'] ?? ''),
        ];
    }

    /** @return array<int, string> */
    private function tokenize(string $text): array
    {
        $stopwords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'be', 'been',
            'being', 'to', 'of', 'in', 'on', 'at', 'for', 'with', 'by', 'as', 'it', 'its',
            'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'they', 'we', 'my',
            'your', 'his', 'her', 'their', 'our', 'has', 'have', 'had', 'do', 'does', 'did',
            'will', 'would', 'should', 'could', 'can', 'may', 'might', 'shall', 'so', 'if',
            'than', 'then', 'into', 'out', 'up', 'down', 'over', 'under', 'from', 'about',
        ];
        $lower = strtolower($text);
        $words = preg_split('/[^a-z0-9]+/i', $lower) ?: [];
        $filtered = array_filter(
            $words,
            fn (string $w): bool => $w !== '' && strlen($w) > 2 && !in_array($w, $stopwords, strict: true),
        );
        return array_values(array_unique($filtered));
    }
}
