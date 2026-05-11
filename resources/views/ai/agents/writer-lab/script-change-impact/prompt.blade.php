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
The writer has edited the event content above. Review all adaptation layers and identify which ones are now stale. Provide minimal, targeted revisions for each affected layer. Preserve the structural choice architecture — only update surface language to match the new content.

Respond with structured JSON only.
