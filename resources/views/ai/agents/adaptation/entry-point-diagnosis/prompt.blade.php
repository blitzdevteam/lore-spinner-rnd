@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Sections 1+2+3: Voice DNA + Master Rule 1 bans + Voice Anchor exemplars + Anchor Card (the cold open you write must match the Voice Anchor rhythm, diction, and compression; obey all bans and the Anchor Card)',
])

PHASE 1 AUDIT: {{ json_encode($ipAudit, JSON_PRETTY_PRINT) }}

STORY SESSION MAP (this session's allocation):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

PROTAGONIST: {{ $protagonist }}
FORMAT: {{ $format }}

STORY ENTRY OVERRIDE:
prefer_literal_opening: {{ $preferLiteralOpening ? 'true' : 'false' }}

If prefer_literal_opening is true, and this is Session 1 of a SCREENPLAY source, strongly prefer the earliest available session event that represents the literal source opening, unless it is genuinely non-playable. Do not move forward to a louder later beat.

SESSION NUMBER: {{ $sessionNumber }}

EVENT NUMBERING CONVENTION:
Events below use the story-global ordinal (same numbering as the Story Session Map's `event_range`).
The `start_event_position` you return MUST be one of the story-global Event numbers shown below.
Do NOT use per-chapter positions.

EVENTS IN THIS SESSION (use these positions to identify your cut point):
@foreach($sessionEvents as $ev)
- Event {{ $ev['story_position'] }} (Chapter {{ $ev['chapter_position'] }}, local pos {{ $ev['position'] }}): {{ $ev['title'] }}@if(!empty($ev['objectives'])) | {{ $ev['objectives'] }}@endif

@endforeach

SOURCE PAGES FOR THIS SESSION:
{{ $sessionSourcePages }}
