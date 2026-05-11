STORY STYLE PROFILE:
{{ json_encode($styleProfile, JSON_PRETTY_PRINT) }}

ESTABLISHED COLD OPEN (tone reference — your rewrite must feel continuous with this register):
{{ $coldOpen ?? '(no cold open established for this session yet)' }}

@if(!empty($beatMap))
SESSION BEAT MAP (for beat_type determination):
{{ json_encode($beatMap, JSON_PRETTY_PRINT) }}

@endif
@if(!empty($choiceDesign))
SESSION CHOICE DESIGN (check if any source event contains a branching choice slot):
{{ json_encode($choiceDesign, JSON_PRETTY_PRINT) }}

@endif
CANONICAL ANCHORS (every item MUST appear in rewritten_content):
@foreach($canonicalAnchors as $anchor)
- {{ $anchor }}
@endforeach

SOURCE EVENTS TO COMBINE ({{ count($sourceEvents) }} events):
@foreach($sourceEvents as $i => $event)

--- Event {{ $i + 1 }} (position: {{ $event['position'] }}, title: {{ $event['title'] }}) ---
Content:
{{ $event['content'] }}
@if(!empty($event['objectives']))
Objectives: {{ $event['objectives'] }}
@endif
@if(!empty($event['attributes']))
Attributes: {{ json_encode($event['attributes']) }}
@endif
@endforeach

TASK: Compress all {{ count($sourceEvents) }} source events above into a single combined prose block following the system rules. The output must be third-person body text the narrator will render interactively. Every canonical anchor must appear explicitly. Match the style_profile voice and be consistent with the cold_open register.
