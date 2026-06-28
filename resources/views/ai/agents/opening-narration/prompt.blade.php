Generate the cinematic opening narration for this story:

STORY TITLE: {{ $title }}

TEASER: {{ $teaser }}

@if(!empty($characterName))
PLAYABLE CHARACTER: {{ $characterName }}
@endif

@if(!empty($toneAndStyle))
TONE & STYLE: {{ $toneAndStyle }}
@endif

@if(!empty($worldRules))
KEY WORLD RULES (for atmospheric hints only: do NOT list these directly):
@foreach($worldRules as $rule)
- {{ $rule }}
@endforeach
@endif

@if(!empty($firstChapterTitle))
OPENING CHAPTER: {{ $firstChapterTitle }}
@endif

@if(!empty($firstEventContent))
OPENING SCENE CONTEXT (use for setting/atmosphere details):
{{ $firstEventContent }}
@endif
