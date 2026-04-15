STORY SESSION MAP (this session's allocation):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

PHASE 1 AUDIT RESULT:
{{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

SESSION NUMBER: {{ $sessionNumber }}

EVENTS IN THIS SESSION (use these positions to identify your cut point):
@foreach($sessionEvents as $ev)
- Position {{ $ev['position'] }}: {{ $ev['title'] }}@if(!empty($ev['objectives'])) — {{ $ev['objectives'] }}@endif

@endforeach

SOURCE PAGES FOR THIS SESSION:
{{ $sessionSourcePages }}
