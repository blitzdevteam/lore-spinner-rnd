PHASE 5 — BRANCHING CHOICE #3 DESIGN:
{{ json_encode($branchingChoice3Design, JSON_PRETTY_PRINT) }}

PHASE 6 — CHOICE #3 CONSEQUENCE MAP:
{{ json_encode($choice3ConsequenceMap, JSON_PRETTY_PRINT) }}

THIS SESSION'S PRIMARY GOAL: {{ $sessionPrimaryGoal }}

SESSION NUMBER: {{ $sessionNumber }}

SESSION EVENT LIST (select ONE story_position for session_close_trigger_event_position):
@foreach($sessionEvents as $ev)
- story_position={{ $ev['story_position'] }} | title="{{ $ev['title'] }}" | objectives="{{ $ev['objectives'] }}"
@endforeach

SOURCE PAGES FOR RESOLUTION MOMENT:
{{ $resolutionSourcePages }}
