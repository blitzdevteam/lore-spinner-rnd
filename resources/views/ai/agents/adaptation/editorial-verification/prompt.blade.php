COMPLETE SESSION DESIGN (Phases 3-7 outputs in order):
{{ json_encode($completeSessionDesign, JSON_PRETTY_PRINT) }}

VOICE PROFILE (Voice Lock Phase output — feed the 14-Point Audit Protocol from this for Q11-Q17):
{{ json_encode($voiceProfile, JSON_PRETTY_PRINT) }}

STORYGUARD CANON (Phase 2 Task 8 — use for Q18-Q21):
{{ json_encode($storyGuardCanon, JSON_PRETTY_PRINT) }}

PERSISTENT STATE SCHEMA (Phase 2 Task 6 — use for Q23):
{{ json_encode($persistentStateSchema, JSON_PRETTY_PRINT) }}

WORLD REACTIVITY RULES (Phase 2 Task 7 — use for Q22):
{{ json_encode($worldReactivityRules, JSON_PRETTY_PRINT) }}

STORY SESSION MAP (for cross-session verification):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

SESSION NUMBER: {{ $sessionNumber }}
