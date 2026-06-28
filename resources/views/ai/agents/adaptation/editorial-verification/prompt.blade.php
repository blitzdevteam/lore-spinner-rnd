COMPLETE SESSION DESIGN (Phases 3-7 outputs in order):
{{ json_encode($completeSessionDesign, JSON_PRETTY_PRINT) }}

@include('ai.agents.adaptation._voice-profile-context', [
    'voiceProfile' => $voiceProfile,
    'voiceProfileLabel' => 'All three sections: Voice DNA + Master Rule 1 bans + 14-Point Audit Protocol (feed Q11–Q17)',
])

STORYGUARD CANON (Phase 2 Task 8: use for Q18-Q21):
{{ json_encode($storyGuardCanon, JSON_PRETTY_PRINT) }}

PERSISTENT STATE SCHEMA (Phase 2 Task 6: use for Q23):
{{ json_encode($persistentStateSchema, JSON_PRETTY_PRINT) }}

WORLD REACTIVITY RULES (Phase 2 Task 7: use for Q22):
{{ json_encode($worldReactivityRules, JSON_PRETTY_PRINT) }}

STORY SESSION MAP (for cross-session verification):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

SESSION NUMBER: {{ $sessionNumber }}
