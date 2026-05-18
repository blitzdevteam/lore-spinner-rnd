@php
    $protagonistName = $protagonist ?? 'the protagonist';
    $protagonistUpper = strtoupper($protagonistName);
@endphp
@if(!empty($conversationHistory))
STORY SO FAR:
@foreach($conversationHistory as $turn)
@if(($turn['role'] ?? '') === 'narrator')
[NARRATOR]: {{ $turn['text'] ?? '' }}
@else
[{{ strtoupper((string) ($turn['protagonist'] ?? $protagonistName)) }}]: {{ $turn['text'] ?? '' }}
@endif
@endforeach

@endif
@if(!empty($playerAction))
{{ $protagonistUpper }}'S ACTION: {{ $playerAction }}

Now narrate the world's response. Absorb the action fully. Move the scene forward by however much the action deserves. End at the next natural moment of player choice. Return the full structured object.
@else
This is the opening of the adventure. Render the scene context provided in CURRENT OPENING SCENE in your own narrator voice — do not quote it verbatim — and end at the first natural moment of player choice. Return the full structured object.
@endif
