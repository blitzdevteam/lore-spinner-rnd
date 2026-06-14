@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Sections 1+2 — Voice DNA + Master Rule 1 bans (the cold open you write must embody this profile)',
])

STORY SESSION MAP (this session's allocation):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

PHASE 1 AUDIT RESULT:
{{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

SESSION NUMBER: {{ $sessionNumber }}

EVENT NUMBERING CONVENTION:
Events below use the story-global ordinal (same numbering as the Story Session Map's `event_range`).
The `start_event_position` you return MUST be one of the story-global Event numbers shown below.
Do NOT use per-chapter positions.

EVENTS IN THIS SESSION (use these positions to identify your cut point):
@foreach($sessionEvents as $ev)
- Event {{ $ev['story_position'] }} (Chapter {{ $ev['chapter_position'] }}, local pos {{ $ev['position'] }}): {{ $ev['title'] }}@if(!empty($ev['objectives'])) — {{ $ev['objectives'] }}@endif

@endforeach

SOURCE PAGES FOR THIS SESSION:
{{ $sessionSourcePages }}
