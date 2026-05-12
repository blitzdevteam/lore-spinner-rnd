<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\WriterLab;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\WriterLabNote;
use App\Support\WriterLab\WriterLabLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Collaboration notes endpoint — minimal, JSON-only.
 *
 * One controller for chapter-scoped notes, with optional event_id pinning. The
 * Vue NotesPanel polls index() and posts new notes via store().
 */
final class NoteController extends Controller
{
    public function index(Story $story, Chapter $chapter): JsonResponse
    {
        $notes = WriterLabNote::where('chapter_id', $chapter->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (WriterLabNote $n): array => [
                'id'          => $n->id,
                'event_id'    => $n->event_id,
                'author_name' => $n->author_name,
                'body'        => $n->body,
                'is_resolved' => $n->is_resolved,
                'created_at'  => $n->created_at?->toIso8601String(),
            ]);

        return response()->json(['notes' => $notes]);
    }

    public function store(Request $request, Story $story, Chapter $chapter): JsonResponse
    {
        $data = $request->validate([
            'event_id'    => ['nullable', 'integer'],
            'author_name' => ['required', 'string', 'max:80'],
            'body'        => ['required', 'string', 'max:2000'],
        ]);

        $writer = Auth::guard('writer')->user();

        $note = WriterLabNote::create([
            'story_id'    => $story->id,
            'chapter_id'  => $chapter->id,
            'event_id'    => $data['event_id'] ?? null,
            'writer_id'   => $writer?->id,
            'author_name' => trim($data['author_name']),
            'body'        => trim($data['body']),
        ]);

        WriterLabLog::info('note.create', [
            'story_id'   => $story->id,
            'chapter_id' => $chapter->id,
            'note_id'    => $note->id,
            'event_id'   => $note->event_id,
            'body_bytes' => strlen($note->body),
        ]);

        return response()->json([
            'note' => [
                'id'          => $note->id,
                'event_id'    => $note->event_id,
                'author_name' => $note->author_name,
                'body'        => $note->body,
                'is_resolved' => $note->is_resolved,
                'created_at'  => $note->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function toggleResolved(Story $story, Chapter $chapter, WriterLabNote $note): JsonResponse
    {
        if ($note->chapter_id !== $chapter->id) {
            return response()->json(['error' => 'Note does not belong to this chapter.'], 422);
        }
        $note->update(['is_resolved' => ! $note->is_resolved]);
        WriterLabLog::info('note.toggle_resolved', [
            'chapter_id'  => $chapter->id,
            'note_id'     => $note->id,
            'is_resolved' => $note->is_resolved,
        ]);
        return response()->json(['is_resolved' => $note->is_resolved]);
    }

    public function destroy(Story $story, Chapter $chapter, WriterLabNote $note): JsonResponse
    {
        if ($note->chapter_id !== $chapter->id) {
            return response()->json(['error' => 'Note does not belong to this chapter.'], 422);
        }

        $writerId = Auth::guard('writer')->id();
        if ($note->writer_id !== null && (int) $note->writer_id !== (int) $writerId) {
            return response()->json(['error' => 'You can only delete notes you posted.'], 403);
        }

        $note->delete();
        WriterLabLog::info('note.destroy', [
            'chapter_id' => $chapter->id,
            'note_id'    => $note->id,
        ]);
        return response()->json(['ok' => true]);
    }
}
