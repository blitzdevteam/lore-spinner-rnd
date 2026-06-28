@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'Section 1: Voice DNA (beat placement and posture shift design must serve this author\'s rhythm and signature techniques)',
])

STORY SESSION MAP:
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

PHASE 3: ENTRY POINT DIAGNOSIS:
{{ json_encode($entryPointDiagnosis, JSON_PRETTY_PRINT) }}

SESSION NUMBER: {{ $sessionNumber }}

SOURCE PAGES FOR THIS SESSION:
{{ $sessionSourcePages }}
