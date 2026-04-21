PHASE 1 AUDIT:
{{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

FORMAT DETECTION:
{{ $formatDetection }}

ESTIMATED SESSION COUNT FROM FORMAT DETECTION: {{ $estimatedSessionCount }}

EXTRACTED CHAPTERS:
@foreach($chapters as $chapter)
Chapter {{ $chapter['position'] }}: {{ $chapter['title'] }}
@endforeach

EVENT NUMBERING CONVENTION:
Events below are numbered 1..{{ $totalEvents }} across the entire story (story-global ordinal).
All `event_range` values AND `event_position` values you return MUST reference the story-global Event number shown below (e.g. "1-8", "48-58").
Do NOT use per-chapter positions. Do NOT emit ranges that exceed {{ $totalEvents }}.

EXTRACTED EVENTS (story-global ordinal | chapter context):
@foreach($events as $event)
Event {{ $event['story_position'] }} of {{ $totalEvents }} (Chapter {{ $event['chapter_position'] }}, local pos {{ $event['position'] }}): {{ $event['title'] }}
@if(!empty($event['objectives']))
  Objectives: {{ $event['objectives'] }}
@endif
@endforeach
