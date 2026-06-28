@if(!empty($conversationHistory))
CONVERSATION SO FAR:
@foreach($conversationHistory as $turn)
@if($turn['role'] === 'narrator')
[NARRATOR]: {!! $turn['text'] !!}
@else
[PLAYER]: {{ $turn['text'] }}
@endif
@endforeach

@endif
@if(!empty($playerAction))
PLAYER'S ACTION: {{ $playerAction }}
@else
This is the OPENING of the event. Narrate the scene cinematically and present the first set of choices.
@endif
@if(!empty($deterministicMatch))

DETERMINISTIC AUTHORED-CHOICE MATCH: option {{ $deterministicMatch['option'] }}@if(!empty($deterministicMatch['choice_id'])) (choice_id: {{ $deterministicMatch['choice_id'] }})@endif | "{{ $deterministicMatch['text'] }}"
The runtime has high-confidence matched the player's input to this authored branching option. You MUST set:
- input_classification = "authored_choice"
- mapped_option = "{{ $deterministicMatch['option'] }}"
@if(!empty($deterministicMatch['choice_id']))- mapped_choice_id = "{{ $deterministicMatch['choice_id'] }}"
@endif
Narrate the consequence of this branch. Do NOT contradict this routing.
@endif
