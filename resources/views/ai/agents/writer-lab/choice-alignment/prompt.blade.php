STORY STYLE PROFILE:
{{ json_encode($styleProfile, JSON_PRETTY_PRINT) }}

@if(!empty($coldOpen))
ESTABLISHED COLD OPEN (register reference):
{{ $coldOpen }}

@endif
EDITED EVENT: "{{ $eventTitle }}"
New content:
{{ $editedContent }}

EXISTING SESSION CHOICE DESIGN (the branching choices this session uses):
{{ json_encode($choiceDesign, JSON_PRETTY_PRINT) }}

TASK: Review the choice design above. Identify the branching choice slot most relevant to the edited event. Check whether the question and A/B/C options still feel naturally earned by the new event content. If they need updating, suggest revised versions. If they already fit, return them unchanged with changes_significant = false.

Respond with structured JSON only.
