@if(!empty($conversationHistory))
CONVERSATION SO FAR:
@foreach($conversationHistory as $turn)
@if($turn['role'] === 'narrator')
[NARRATOR]: {!! $turn['text'] !!}
@else
[LISTENER]: {{ $turn['text'] }}
@endif
@endforeach

@endif
@if(!empty($playerAction))
LISTENER'S ACTION: {{ $playerAction }}
@else
This is the OPENING moment. Greet the listener inside the world with a short
cinematic scene, and end by weaving 2-3 natural next directions into the prose.
@endif
