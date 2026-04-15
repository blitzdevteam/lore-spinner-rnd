PHASE 4 BEAT MAP:
{{ json_encode($beatMap, JSON_PRETTY_PRINT) }}

STORY SESSION MAP (including branch dimensions):
{{ json_encode($storySessionMap, JSON_PRETTY_PRINT) }}

PROTAGONIST CORE TRAIT: {{ $protagonistCoreTrait }}

EMOTIONAL PROMISE: {{ $emotionalPromise }}

SESSION NUMBER: {{ $sessionNumber }}

SOURCE PAGES FOR CHOICE MOMENTS:
{{ $choiceMomentPages }}
