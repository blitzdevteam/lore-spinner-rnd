EVENT: "{{ $eventTitle }}" (Session {{ $sessionNumber }}, Position {{ $eventPosition }})

=== ORIGINAL CONTENT ===
{{ $originalContent }}

=== EDITED CONTENT (WRITER'S NEW VERSION) ===
{{ $editedContent }}

=== CURRENT EVENT METADATA ===
Objectives: {{ $currentObjectives ?? '(none)' }}
Attributes: {{ json_encode($currentAttributes ?? []) }}

=== SESSION BEAT MAP ===
{{ json_encode($beatMap, JSON_PRETTY_PRINT) }}

=== SESSION CHOICE DESIGN ===
{{ json_encode($choiceDesign, JSON_PRETTY_PRINT) }}

=== CHOICE CONSEQUENCE MAP ===
{{ json_encode($consequenceMap, JSON_PRETTY_PRINT) }}

@if(!empty($nextSessionAwareness))
=== NEXT SESSION AWARENESS (cross-session seeds planted) ===
{{ json_encode($nextSessionAwareness, JSON_PRETTY_PRINT) }}
@endif

@if(!empty($nextSessionColdOpen))
=== DOWNSTREAM SESSION COLD OPEN (what the next session expects to be "planted") ===
{{ $nextSessionColdOpen }}
@endif

=== TASK ===
The writer has rewritten the event script above. You must:
1. Compare old vs. new content and determine what factually or tonally changed
2. Check each adaptation layer — only flag layers that are genuinely stale as a result
3. For choice design: look at the `source_moment` field in each slot and identify which slot (if any) is anchored to THIS event. Only suggest changes for that slot. If no slot's source_moment maps to this event, set choice_design_needs_update = false.
4. Return severity "clean" if the edit is cosmetic (prose polish, same facts, same beats).

Preserve the structural choice architecture. Only update surface language to match new content.

Respond with structured JSON only.
