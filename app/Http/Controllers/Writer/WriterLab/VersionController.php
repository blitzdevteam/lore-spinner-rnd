<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\WriterLab;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\WriterLabVersion;
use App\Support\WriterLab\WriterLabLog;
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
     *
     * For chapter-kind snapshots (the modern default), this is an idempotent
     * full-chapter rewrite:
     *   1. Upsert every snapshotted event back to its original row by id.
     *   2. Delete any events currently in the chapter that aren't in the snapshot
     *      (these came from a split that this restore is rolling back).
     *   3. Re-apply every session_adaptation row that was captured.
     *
     * Session-kind (legacy) snapshots use the older partial-restore path.
     */
    public function restore(Story $story, WriterLabVersion $version): RedirectResponse
    {
        if ($version->story_id !== $story->id) {
            abort(403);
        }

        $logContext = [
            'story_id'       => $story->id,
            'version_id'     => $version->id,
            'version_number' => $version->version_number,
            'snapshot_kind'  => $version->snapshot_kind ?? 'session',
            'chapter_id'     => $version->chapter_id,
        ];

        DB::transaction(function () use ($version, $story, $logContext): void {
            if (($version->snapshot_kind ?? 'session') === 'chapter') {
                $this->restoreFullChapter($version);
            } else {
                $this->restoreLegacySession($version);
            }

            // Scope the active flag by the matching scope (chapter for new, session for legacy)
            $query = WriterLabVersion::where('story_id', $story->id);
            if ($version->chapter_id !== null) {
                $query->where('chapter_id', $version->chapter_id);
            } else {
                $query->where('session_number', $version->session_number);
            }
            $query->update(['is_active' => false]);

            $version->update(['is_active' => true]);

            WriterLabLog::info('version.restore.done', $logContext);
        });

        return to_route('writer.writer-lab.versions.index', $story->id)
            ->with('success', "Version {$version->version_number} restored.");
    }

    /**
     * Full-chapter idempotent restore. Upsert every snapshotted event, delete
     * orphans, re-apply every captured session adaptation.
     */
    private function restoreFullChapter(WriterLabVersion $version): void
    {
        $snapEvents = $version->snapshot_events ?? [];
        $snapIds    = array_values(array_filter(array_map(
            static fn ($e) => is_array($e) && isset($e['id']) ? (int) $e['id'] : null,
            $snapEvents,
        )));

        foreach ($snapEvents as $snap) {
            if (! is_array($snap) || ! isset($snap['id'])) continue;
            $this->upsertEventRow($snap);
        }

        // Drop any events currently in the chapter that aren't in the snapshot
        // (e.g. events created by a split this restore is rolling back).
        if ($version->chapter_id !== null) {
            Event::query()
                ->where('chapter_id', $version->chapter_id)
                ->when(! empty($snapIds), fn ($q) => $q->whereNotIn('id', $snapIds))
                ->delete();
        }

        // Re-apply each captured session adaptation by id
        foreach (($version->snapshot_adaptations ?? []) as $snap) {
            if (! is_array($snap) || ! isset($snap['id'])) continue;
            $sa = SessionAdaptation::find($snap['id']);
            if ($sa !== null) {
                $sa->update([
                    'entry_point_diagnosis'  => $snap['entry_point_diagnosis']  ?? $sa->entry_point_diagnosis,
                    'session_architecture'   => $snap['session_architecture']   ?? $sa->session_architecture,
                    'session_choice_design'  => $snap['session_choice_design']  ?? $sa->session_choice_design,
                    'choice_consequence_map' => $snap['choice_consequence_map'] ?? $sa->choice_consequence_map,
                    'session_close_design'   => $snap['session_close_design']   ?? $sa->session_close_design,
                ]);
            }
        }
    }

    /**
     * Legacy session-kind restore — partial scope, kept for back-compat.
     */
    private function restoreLegacySession(WriterLabVersion $version): void
    {
        foreach ($version->snapshot_events as $snap) {
            if (! is_array($snap) || ! isset($snap['id'])) continue;
            $this->upsertEventRow($snap);
        }
        if ($version->snapshot_adaptation) {
            $snap = $version->snapshot_adaptation;
            $sa   = SessionAdaptation::find($snap['id'] ?? null);
            if ($sa !== null) {
                $sa->update([
                    'entry_point_diagnosis'  => $snap['entry_point_diagnosis']  ?? $sa->entry_point_diagnosis,
                    'session_architecture'   => $snap['session_architecture']   ?? $sa->session_architecture,
                    'session_choice_design'  => $snap['session_choice_design'] ?? $sa->session_choice_design,
                    'choice_consequence_map' => $snap['choice_consequence_map'] ?? $sa->choice_consequence_map,
                    'session_close_design'   => $snap['session_close_design']  ?? $sa->session_close_design,
                ]);
            }
        }
    }

    /**
     * Upsert one event row by id from a snapshot dict.
     *
     * @param  array<string, mixed>  $snap
     */
    private function upsertEventRow(array $snap): void
    {
        $existing = Event::find($snap['id']);
        if ($existing !== null) {
            $existing->update([
                'content'         => $snap['content'] ?? $existing->content,
                'objectives'      => $snap['objectives'] ?? $existing->objectives,
                'attributes'      => $snap['attributes'] ?? $existing->attributes,
                'position'        => $snap['position'] ?? $existing->position,
                'title'           => $snap['title'] ?? $existing->title,
                'session_number'  => $snap['session_number'] ?? $existing->session_number,
                'requires_choice' => $snap['requires_choice'] ?? true,
            ]);
            return;
        }
        DB::table('events')->insert([
            'id'              => $snap['id'],
            'chapter_id'      => $snap['chapter_id'],
            'position'        => $snap['position'],
            'title'           => $snap['title'],
            'content'         => $snap['content'],
            'objectives'      => $snap['objectives'],
            'attributes'      => is_string($snap['attributes'] ?? null)
                ? $snap['attributes']
                : json_encode($snap['attributes'] ?? []),
            'session_number'  => $snap['session_number'],
            'requires_choice' => $snap['requires_choice'] ?? true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
