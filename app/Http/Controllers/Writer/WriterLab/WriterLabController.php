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
        $events = Event::where('chapter_id', $chapter->id)
            ->orderBy('position')
            ->get()
            ->map(fn (Event $event): array => [
                'id'              => $event->id,
                'position'        => $event->position,
                'title'           => $event->title,
                'content'         => $event->content,
                'objectives'      => $event->objectives,
                'attributes'      => $event->attributes,
                'session_number'  => $event->session_number,
                'requires_choice' => $event->requires_choice,
            ]);

        $sessionNumbers = $events->pluck('session_number')->filter()->unique()->sort()->values();

        $sessionAdaptations = SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
            ->whereIn('session_number', $sessionNumbers)
            ->get()
            ->mapWithKeys(fn (SessionAdaptation $sa): array => [
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
            ]);

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
}
