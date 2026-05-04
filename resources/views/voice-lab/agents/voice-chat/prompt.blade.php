@if(!empty($conversationHistory))
=== CONVERSATION SO FAR ===
@foreach($conversationHistory as $turn)
@if($turn['role'] === 'narrator')
[NARRATOR]: {!! $turn['text'] !!}
@else
[LISTENER]: {{ $turn['text'] }}
@endif
@endforeach

@endif
@if(!empty($playerAction))
=== LISTENER'S LATEST ACTION ===
{{ $playerAction }}

=== TURN DIRECTIVE ===
The previous narrator beat is FINISHED. Do NOT repeat it, do NOT rephrase it,
do NOT linger inside it.

Step the world FORWARD by exactly one beat:

- If the listener's action matches an authored option from the storyline arc,
  treat it as that option and narrate the immediate consequence, then move
  toward the next anchor in the arc.
- If the listener's action is a free-form / off-script attempt, honor the
  specific thing they claimed (acknowledge it in-world, with a small concrete
  consequence) for THIS turn — and on the next 1–2 turns let the scene's
  gravity pull them back toward the next anchor in the storyline arc.
- If the listener said something incoherent or empty, pick the most plausible
  reading and advance the world anyway. Never ask them to repeat themselves.

Open this reply with a fresh sensory beat (heat, breath, sound, texture)
that proves time has passed. Then weave 2–3 next directions into the prose.
End on a threshold.
@else
=== TURN DIRECTIVE ===
This is the OPENING moment. Greet the listener inside the world with a short
cinematic scene grounded in the cold open's setting, and end by weaving 2–3
natural next directions into the prose — using AUTHORED CHOICE 1 (S1_C1)
options where appropriate.
@endif
