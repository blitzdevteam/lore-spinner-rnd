@if(!empty($conversationHistory))
STORY SO FAR:
@foreach($conversationHistory as $turn)
@if(($turn['role'] ?? '') === 'narrator')
[NARRATOR]: {{ $turn['text'] ?? '' }}
@else
[ALICE]: {{ $turn['text'] ?? '' }}
@endif
@endforeach

@endif
@if(!empty($playerAction))
ALICE'S ACTION: {{ $playerAction }}

Now narrate Wonderland's response. Absorb her action. Move the scene by however much her action deserves. End at the next natural moment of player choice. Return the full structured object.
@else
This is the opening of the adventure. Render the authored cold open (provided in CURRENT OPENING SCENE) in your own Carroll voice — do not quote it verbatim — and end at the first natural moment of player choice. Return the full structured object.
@endif
