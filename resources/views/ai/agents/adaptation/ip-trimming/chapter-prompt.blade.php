SOURCE IP: {{ $title ?? 'Untitled' }}
@isset($author)
Author: {{ $author }}
@endisset
@isset($format)
Format: {{ $format }}
@endisset

CHAPTER BEING PROCESSED:
Chapter {{ $chapterPosition }} of {{ $totalChapters }}: "{{ $chapterTitle }}"
Chapter ID: {{ $chapterId }}

@if(!empty($previousChapterTitle))
CONTEXT ONLY — DO NOT OUTPUT content from this. The previous chapter is:
Chapter {{ $chapterPosition - 1 }}: "{{ $previousChapterTitle }}"
@endif

@if(!empty($nextChapterTitle))
CONTEXT ONLY — DO NOT OUTPUT content from this. The next chapter is:
Chapter {{ $chapterPosition + 1 }}: "{{ $nextChapterTitle }}"
@endif

----- BEGIN CHAPTER {{ $chapterPosition }} CONTENT -----
{!! $chapterContent !!}
----- END CHAPTER {{ $chapterPosition }} CONTENT -----

Process only the content between the BEGIN/END markers. Return a JSON fragment with chapter_id={{ $chapterId }}, chapter_position={{ $chapterPosition }}, and all five task outputs (story_spine_fragment, world_rules_fragments, content_triage_log, interactive_conversion_notes, trimmed_chapter_text).
