SOURCE IP: {{ $title ?? 'Untitled' }}
@isset($author)
Author: {{ $author }}
@endisset
Total chapters: {{ $totalChapters }}

STORY SPINE FRAGMENTS (one per chapter, in chapter order):

@foreach($spineFragments as $i => $fragment)
--- CHAPTER {{ $i + 1 }} FRAGMENT ---
{{ json_encode($fragment, JSON_PRETTY_PRINT) }}

@endforeach

Synthesize these fragments into a single unified story_spine. Return the protagonist, dramatic_question, world, major_turning_points (in chronological order, deduplicated), climax, resolution, and irreversible_events.
