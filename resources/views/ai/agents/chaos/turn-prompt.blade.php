@if(!empty($conversationHistory))
STORY SO FAR:
@foreach($conversationHistory as $turn)
@if($turn['role'] === 'narrator')
[NARRATOR]: {{ $turn['text'] }}
@else
[ALICE]: {{ $turn['text'] }}
@endif
@endforeach

@endif
@if(!empty($playerAction))
ALICE'S ACTION: {{ $playerAction }}
@else
This is the opening of the adventure. Begin in Carroll's voice: set the scene at the bottom of the rabbit-hole and open the hall of doors before Alice. End at the first natural moment of player choice.
@endif
