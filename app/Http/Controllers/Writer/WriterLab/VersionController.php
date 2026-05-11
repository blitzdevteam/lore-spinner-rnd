<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\WriterLab;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\WriterLabVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

final class VersionController extends Controller
{
    /**
     * List all version snapshots for a story, grouped by session.
     */
    public function index(Story $story): Response
    {
        $versions = WriterLabVersion::where('story_id', $story->id)
            ->orderBy('session_number')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (WriterLabVersion $v): array => [
                'id'             => $v->id,
                'session_number' => $v->session_number,
                'version_number' => $v->version_number,
                'is_active'      => $v->is_active,
                'note'           => $v->note,
                'event_count'    => count($v->snapshot_events),
                'created_at'     => $v->created_at,
            ]);

        return inertia('WriterLab/Versions', [
            'story'    => ['id' => $story->id, 'title' => $story->title, 'slug' => $story->slug],
            'versions' => $versions,
        ]);
    }

    /**
     * Restore a version snapshot.
     * Re-upserts event rows by id and re-applies the adaptation snapshot.
     * Any events that were absorbed (deleted) by the activate this snapshot captured
     * will be re-inserted at their original positions.
     */
    public function restore(Story $story, WriterLabVersion $version): RedirectResponse
    {
        if ($version->story_id !== $story->id) {
            abort(403);
        }

        DB::transaction(function () use ($version, $story): void {
            foreach ($version->snapshot_events as $snap) {
                $existing = Event::find($snap['id']);

                if ($existing !== null) {
                    $existing->update([
                        'content'         => $snap['content'],
                        'objectives'      => $snap['objectives'],
                        'attributes'      => $snap['attributes'],
                        'position'        => $snap['position'],
                        'title'           => $snap['title'],
                        'session_number'  => $snap['session_number'],
                        'requires_choice' => $snap['requires_choice'] ?? true,
                    ]);
                } else {
                    // Re-insert absorbed event with its original id and position
                    DB::table('events')->insert([
                        'id'              => $snap['id'],
                        'chapter_id'      => $snap['chapter_id'],
                        'position'        => $snap['position'],
                        'title'           => $snap['title'],
                        'content'         => $snap['content'],
                        'objectives'      => $snap['objectives'],
                        'attributes'      => json_encode($snap['attributes']),
                        'session_number'  => $snap['session_number'],
                        'requires_choice' => $snap['requires_choice'] ?? true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            // Restore adaptation layer
            if ($version->snapshot_adaptation) {
                $snap = $version->snapshot_adaptation;
                $sa   = SessionAdaptation::find($snap['id'] ?? null);
                if ($sa !== null) {
                    $sa->update([
                        'entry_point_diagnosis' => $snap['entry_point_diagnosis'] ?? $sa->entry_point_diagnosis,
                        'session_architecture'  => $snap['session_architecture'] ?? $sa->session_architecture,
                        'session_choice_design' => $snap['session_choice_design'] ?? $sa->session_choice_design,
                        'choice_consequence_map' => $snap['choice_consequence_map'] ?? $sa->choice_consequence_map,
                        'session_close_design'  => $snap['session_close_design'] ?? $sa->session_close_design,
                    ]);
                }
            }

            // Mark this version as active, deactivate others for same story + session
            WriterLabVersion::where('story_id', $story->id)
                ->where('session_number', $version->session_number)
                ->update(['is_active' => false]);

            $version->update(['is_active' => true]);
        });

        return to_route('writer.writer-lab.versions.index', $story->id)
            ->with('success', "Version {$version->version_number} restored.");
    }
}
