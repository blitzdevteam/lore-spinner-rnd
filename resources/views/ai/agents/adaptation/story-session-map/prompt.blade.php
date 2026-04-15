PHASE 1 AUDIT:
{{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

FORMAT DETECTION:
{{ $formatDetection }}

ESTIMATED SESSION COUNT FROM FORMAT DETECTION: {{ $estimatedSessionCount }}

EXTRACTED CHAPTERS:
@foreach($chapters as $chapter)
Chapter {{ $chapter['position'] }}: {{ $chapter['title'] }}
@endforeach

EXTRACTED EVENTS:
@foreach($events as $event)
Event {{ $event['position'] }} (Chapter {{ $event['chapter_position'] }}): {{ $event['title'] }}
@if(!empty($event['objectives']))
  Objectives: {{ $event['objectives'] }}
@endif
@endforeach
