@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Sections 1+2: Voice DNA + Master Rule 1 bans (resolution_prose must embody this profile)',
])

PHASE 5: BRANCHING CHOICE #4 | SESSION-END HOOK (this choice is already fully designed; execute it exactly, do not redesign it):
{{ json_encode($branchingChoice3Design, JSON_PRETTY_PRINT) }}

PHASE 6: CHOICE #4 CONSEQUENCE MAP:
{{ json_encode($choice3ConsequenceMap, JSON_PRETTY_PRINT) }}

THIS SESSION'S PRIMARY GOAL: {{ $sessionPrimaryGoal }}

SESSION NUMBER: {{ $sessionNumber }}

SESSION EVENT LIST (select ONE story_position for session_close_trigger_event_position):
@foreach($sessionEvents as $ev)
- story_position={{ $ev['story_position'] }} | title="{{ $ev['title'] }}" | objectives="{{ $ev['objectives'] }}"
@endforeach

SOURCE PAGES FOR RESOLUTION MOMENT:
{{ $resolutionSourcePages }}
