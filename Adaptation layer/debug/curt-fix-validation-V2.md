php "Adaptation layer/debug/wsb-validation.php"


=== WS-B validation: Alice 6-turn scenario ===

[1] matchAuthoredChoice
  ok   verbatim-ish input maps to option B
  ok   lowercased verbatim maps to option A
  ok   unrelated input does not match any option
  ok   empty input does not match
  ok   short paraphrase matches via token overlap

[2] applyStateDelta cumulative buildup (6 turns)
  turn 1 applied; objects=0 conditions=0 knowledge=1 location=long hallway with the small door
  turn 2 applied; objects=1 conditions=0 knowledge=1 location=long hallway with the small door
  turn 3 applied; objects=1 conditions=1 knowledge=2 location=long hallway with the small door
  turn 4 applied; objects=2 conditions=1 knowledge=2 location=long hallway with the small door
  turn 5 applied; objects=2 conditions=1 knowledge=3 location=doorway to a small garden
  turn 6 applied; objects=2 conditions=1 knowledge=3 location=doorway to a small garden
  ok   bottle object retained across turns
  ok   bottle qualifier transformed to drained
  ok   golden key retained
  ok   small condition was removed in turn 6
  ok   frustrated condition added in turn 6
  ok   last location_changed wins
  ok   cumulative knowledge T1 retained
  ok   cumulative knowledge T3 retained
  ok   cumulative knowledge T5 retained
  ok   flag from T1 retained
  ok   flag from T5 retained

[3] mergeTrackedDimensions accumulates path votes
  ok   curiosity_vs_caution dimension recorded
  ok   three curiosity votes accumulated across turns 2,3,5
  ok   all three votes are "curiosity"

[4] appendBranchingChoice idempotency
  ok   second call updates existing entry rather than duplicating
  ok   second call overwrote advanced=true
  ok   different event creates a new entry
  ok   empty option does not append

[5] appendBranchResolutionLog grows monotonically and trims
  ok   branch_resolution_log trims to 200 entries

[6] resolveCurrentBeatType walks the beat map on advance
  ok   starts at cold_open when current is null and advancing
  ok   advances cold_open → rising_curiosity
  ok   no advance → unchanged
  ok   past last beat clamps to last

=== summary ===
failures: 0



-------------------------


php "Adaptation layer/debug/curt-fix-validation-runner.php" step5


Using DEFAULT_VALIDATION_GAME_ID=01kpe60znegetqss98x1kvxrb7 (Curt historical game_id in curt-game-log.json: 01kpv313jddy575ct6bv6cak4j). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.
reset to event=1
note: events.id is numeric in many seeds; first narration is NOT created until step5b or POST user/games/{id}/begin.



--------------
php "Adaptation layer/debug/curt-fix-validation-runner.php" step11

Using DEFAULT_VALIDATION_GAME_ID=01kpe60znegetqss98x1kvxrb7 (Curt historical game_id in curt-game-log.json: 01kpv313jddy575ct6bv6cak4j). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.
agent_class=App\Ai\Agents\NarrationAgent
system_prompt_bytes=487
status=ok
response_bytes=735
response_first_300=The river lies slack and bright, a long ribbon of light cutting through reeds that barely bother to sway. Heat presses down like a hand on the back of your neck; even the insects seem to move lazily, choosing short flights and long rests. Your boots sink a fraction into the damp edge where mud gives
choices_count=3
input_classification=opening
state_delta_keys=objects_acquired,objects_lost,objects_transformed,conditions_added,conditions_removed,location_changed,knowledge_gained,relationship_changes,tracked_path_update,flags_set


------------


php "Adaptation layer/debug/curt-fix-validation-runner.php" step5b



Using DEFAULT_VALIDATION_GAME_ID=01kpe60znegetqss98x1kvxrb7 (Curt historical game_id in curt-game-log.json: 01kpv313jddy575ct6bv6cak4j). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.
first_prompt_created=yes prompt_id=01kqata9qhv6n4w6y4h2zjp18p
first_response_first_400=
Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-listening to your sister’s page-turning.Then a White Rabbit flashes past so close you catch the clean, sharp scent of crushed clover—and it mutters, plainly, like a person: “Oh dear! Oh dear! I shall be late!”You don’t freeze. You lunge up, skirt snagging on a thistle, because th



-------------

Using DEFAULT_VALIDATION_GAME_ID=01kpe60znegetqss98x1kvxrb7 (Curt historical game_id in curt-game-log.json: 01kpv313jddy575ct6bv6cak4j). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.
session=1
choice_design_keys=branching_choice_1,expressive_choices,branching_choice_2,branching_choice_3
extracted_candidates=18
  [0] option=A choice_id=S1_C1 text="You sprint after him and dive for the rabbit-hole the instant you see it."
  [1] option=B choice_id=S1_C1 text="You keep him in sight but slow just long enough to clock landmarks and the shape"
  [2] option=C choice_id=S1_C1 text="You call out to him first and watch how he reacts before you commit to the hole."

INPUT: Sprint after him and dive for the rabbit-hole
MATCH: {"option":"A","choice_id":"S1_C1","text":"You sprint after him and dive for the rabbit-hole the instant you see it."}

INPUT: Keep him in sight but slow just long enough to clock landmarks
MATCH: {"option":"B","choice_id":"S1_C1","text":"You keep him in sight but slow just long enough to clock landmarks and the shape of the hedge."}

INPUT: Shout into the void
MATCH: (none)



-----------
tail -n 60 storage/logs/narration-$(date +%F).log




[2026-04-28 19:50:57] production.INFO: narration.cold_open_audit {"story_id":1,"event_id":1,"session_adaptation_resolved":true,"session_number":1,"cold_open_present":true,"cold_open_first_120":"Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-"} 
[2026-04-28 19:51:03] production.INFO: narration.llm_success {"site":"first_narration","story_id":1,"event_id":1,"response_bytes":813,"choices_count":3,"system_prompt_bytes":24016,"cold_open_present":true} 
[2026-04-28 19:53:24] production.INFO: narration.cold_open_audit {"story_id":1,"event_id":1,"session_adaptation_resolved":true,"session_number":1,"cold_open_present":true,"cold_open_first_120":"Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-"} 
[2026-04-28 19:53:31] production.INFO: narration.llm_success {"site":"first_narration","story_id":1,"event_id":1,"response_bytes":813,"choices_count":3,"system_prompt_bytes":24016,"cold_open_present":true} 
[2026-04-28 19:53:54] production.INFO: narration.llm_success {"response_bytes":904,"state_delta_keys_present":["objects_acquired","objects_lost","objects_transformed","conditions_added","conditions_removed","location_changed","knowledge_gained","relationship_changes","tracked_path_update","flags_set"],"input_classification":"authored_choice","mapped_option":"C","mapped_choice_id":"S1_C1","system_prompt_bytes":22158,"history_turns":2} 
[2026-04-28 19:53:54] production.INFO: narration.turn {"game_id":"01kpe60znegetqss98x1kvxrb7","event_id_before":1,"event_id_after":2,"session_number_before":1,"session_number_after":1,"game_current_session_number_after":1,"turn_count":1,"is_first_turn_in_event":false,"advance_event_returned":true,"force_advanced":false,"is_continue":false,"input_classification":"authored_choice","mapped_choice_id":"S1_C1","mapped_option":"C","deterministic_match":true,"state_delta_summary":{"objects_acquired":0,"objects_lost":0,"objects_transformed":0,"conditions_added":0,"conditions_removed":0,"location_changed":"under the hedge (large rabbit-hole)","knowledge_gained":2,"relationship_changes":0,"tracked_path_update":1,"flags_set":1},"world_state_object_count":0,"world_state_condition_count":0,"player_input_first_120":"You call out to him first and watch how he reacts before you commit to the hole.","narrator_response_first_120":"Your voice flies ahead of you, bright and a little breathless, cutting across the field before your feet quite decide wh","choices_returned":["Rush to the rabbit-hole and peer down into the dark.","Circle the hedge and examine the hole’s edges before getting closer.","Step back into the field and fix the spot in your mind before you decide."],"system_prompt_hash":"a57b05ec368f97147bc158109bdd1d4f596e47aa254124d8a0d4c84bc1b0a411","logged_at":"2026-04-28T19:53:54+00:00"} 
[2026-04-28 19:55:44] production.INFO: narration.llm_success {"response_bytes":753,"state_delta_keys_present":["objects_acquired","objects_lost","objects_transformed","conditions_added","conditions_removed","location_changed","knowledge_gained","relationship_changes","tracked_path_update","flags_set"],"input_classification":"unsupported","mapped_option":"","mapped_choice_id":"","system_prompt_bytes":25514,"history_turns":4} 
[2026-04-28 19:55:44] production.INFO: narration.turn {"game_id":"01kpe60znegetqss98x1kvxrb7","event_id_before":2,"event_id_after":3,"session_number_before":1,"session_number_after":1,"game_current_session_number_after":1,"turn_count":1,"is_first_turn_in_event":false,"advance_event_returned":true,"force_advanced":false,"is_continue":false,"input_classification":"unsupported","mapped_choice_id":null,"mapped_option":null,"deterministic_match":false,"state_delta_summary":{"objects_acquired":0,"objects_lost":0,"objects_transformed":0,"conditions_added":1,"conditions_removed":0,"location_changed":"at the rabbit-hole mouth beneath the hedge","knowledge_gained":2,"relationship_changes":0,"tracked_path_update":0,"flags_set":1},"world_state_object_count":0,"world_state_condition_count":1,"player_input_first_120":"I pull out my gun and shoot at the rabbit","narrator_response_first_120":"Your hand goes, with sudden certainty, to where a gun ought to be—and finds only your own skirt, your own pocket, the sm","choices_returned":["Dive into the rabbit-hole after him.","Drop to your knees and peer into the darkness, listening for movement.","Circle the hedge and probe for another opening or a safer angle down."],"system_prompt_hash":"7163c020c15a3e69d58944bd116bc5016809c895dce3a1dc541fce2a525647d0","logged_at":"2026-04-28T19:55:44+00:00"} 
[2026-04-28 19:57:02] production.INFO: narration.llm_success {"response_bytes":1070,"state_delta_keys_present":["objects_acquired","objects_lost","objects_transformed","conditions_added","conditions_removed","location_changed","knowledge_gained","relationship_changes","tracked_path_update","flags_set"],"input_classification":"unsupported","mapped_option":"","mapped_choice_id":"","system_prompt_bytes":22474,"history_turns":6} 
[2026-04-28 19:57:02] production.INFO: narration.turn {"game_id":"01kpe60znegetqss98x1kvxrb7","event_id_before":3,"event_id_after":4,"session_number_before":1,"session_number_after":1,"game_current_session_number_after":1,"turn_count":1,"is_first_turn_in_event":false,"advance_event_returned":true,"force_advanced":false,"is_continue":false,"input_classification":"unsupported","mapped_choice_id":null,"mapped_option":null,"deterministic_match":false,"state_delta_summary":{"objects_acquired":0,"objects_lost":0,"objects_transformed":0,"conditions_added":0,"conditions_removed":0,"location_changed":"","knowledge_gained":0,"relationship_changes":0,"tracked_path_update":0,"flags_set":0},"world_state_object_count":0,"world_state_condition_count":1,"player_input_first_120":"i turn back and go back to our house and call my buddy","narrator_response_first_120":"You twist away from the hedge as if you can un-see it by leaving—skirts brushing the grass, heart still hammering with t","choices_returned":["Commit to the rabbit-hole and drop in after the White Rabbit.","Circle the hedge and test the hole’s edges for a safer way down.","Call out again—loudly—demanding the Rabbit come back and explain himself."],"system_prompt_hash":"0f65728505456e206f5ae6f0a0f699d944f4c0f4639dcedd2b3d00691343208a","logged_at":"2026-04-28T19:57:02+00:00"} 
[2026-04-28 19:58:31] production.INFO: narration.llm_success {"response_bytes":1054,"state_delta_keys_present":["objects_acquired","objects_lost","objects_transformed","conditions_added","conditions_removed","location_changed","knowledge_gained","relationship_changes","tracked_path_update","flags_set"],"input_classification":"emergent","mapped_option":"","mapped_choice_id":"","system_prompt_bytes":22807,"history_turns":8} 
[2026-04-28 19:58:31] production.INFO: narration.turn {"game_id":"01kpe60znegetqss98x1kvxrb7","event_id_before":4,"event_id_after":4,"session_number_before":1,"session_number_after":1,"game_current_session_number_after":1,"turn_count":1,"is_first_turn_in_event":false,"advance_event_returned":false,"force_advanced":false,"is_continue":false,"input_classification":"emergent","mapped_choice_id":null,"mapped_option":null,"deterministic_match":false,"state_delta_summary":{"objects_acquired":0,"objects_lost":0,"objects_transformed":0,"conditions_added":0,"conditions_removed":0,"location_changed":"","knowledge_gained":1,"relationship_changes":0,"tracked_path_update":0,"flags_set":1},"world_state_object_count":0,"world_state_condition_count":1,"player_input_first_120":"Call out again—loudly—demanding the Rabbit come back and explain himself.","narrator_response_first_120":"You whirl back to the hedge and throw your voice into the green as if volume alone could pin the impossible in place. Th","choices_returned":["Drop to your knees and peer into the rabbit-hole, calling down after him.","Push aside the roots and lower yourself into the rabbit-hole feet-first.","Mark the spot carefully with a torn strip of cloth from your hem. "],"system_prompt_hash":"7a450dbc84a9d72254f1f68d984e0a6b5536944da1a7f0bf5a9f88cbf96eb763","logged_at":"2026-04-28T19:58:31+00:00"} 


-------------------------

php artisan game:trace 01kpe60znegetqss98x1kvxrb7


=== GAME ===
id:                    01kpe60znegetqss98x1kvxrb7
story_id:              1
current_event_id:      18
current_session_number: 1

=== TURN TRACE (20 rows) ===

--- TURN 1 (logged 2026-04-28T19:53:54+00:00) ---
  event_id:            1 -> 2
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         a57b05ec368f
  player_input:        "You call out to him first and watch how he reacts before you commit to the hole."
  narrator_response:   "Your voice flies ahead of you, bright and a little breathless, cutting across the field before your feet quite decide wh"
  choices_returned:
    A) Rush to the rabbit-hole and peer down into the dark.
    B) Circle the hedge and examine the hole’s edges before getting closer.
    C) Step back into the field and fix the spot in your mind before you decide.
  rules:               ALL GREEN (4/4)

--- TURN 2 (logged 2026-04-28T19:55:44+00:00) ---
  event_id:            2 -> 3
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         7163c020c15a
  player_input:        "I pull out my gun and shoot at the rabbit"
  narrator_response:   "Your hand goes, with sudden certainty, to where a gun ought to be—and finds only your own skirt, your own pocket, the sm"
  choices_returned:
    A) Dive into the rabbit-hole after him.
    B) Drop to your knees and peer into the darkness, listening for movement.
    C) Circle the hedge and probe for another opening or a safer angle down.
  rules:               ALL GREEN (4/4)

--- TURN 3 (logged 2026-04-28T19:57:02+00:00) ---
  event_id:            3 -> 4
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         0f6572850545
  player_input:        "i turn back and go back to our house and call my buddy"
  narrator_response:   "You twist away from the hedge as if you can un-see it by leaving—skirts brushing the grass, heart still hammering with t"
  choices_returned:
    A) Commit to the rabbit-hole and drop in after the White Rabbit.
    B) Circle the hedge and test the hole’s edges for a safer way down.
    C) Call out again—loudly—demanding the Rabbit come back and explain himself.
  rules:               ALL GREEN (4/4)

--- TURN 4 (logged 2026-04-28T19:58:31+00:00) ---
  event_id:            4 -> 4
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    false
  is_continue:         false
  prompt_hash:         7a450dbc84a9
  player_input:        "Call out again—loudly—demanding the Rabbit come back and explain himself."
  narrator_response:   "You whirl back to the hedge and throw your voice into the green as if volume alone could pin the impossible in place. Th"
  choices_returned:
    A) Drop to your knees and peer into the rabbit-hole, calling down after him.
    B) Push aside the roots and lower yourself into the rabbit-hole feet-first.
    C) Mark the spot carefully with a torn strip of cloth from your hem. 
  rules:               ALL GREEN (4/4)

--- TURN 5 (logged 2026-05-02T17:30:07+00:00) ---
  event_id:            4 -> 5
  session_number:      1 -> 1
  turn_count_in_event: 2
  advance_returned:    true
  is_continue:         false
  prompt_hash:         49028a24089b
  player_input:        "You sprint after him and dive for the rabbit-hole the instant you see it."
  narrator_response:   "You don’t give the afternoon time to become sensible again. You move—all knees and breath and sudden decision—crossing t"
  choices_returned:
    A) Reach out and feel for anything to grab as you slide.
    B) Tuck your chin and control your tumble, trying to steady your breathing.
    C) Call out into the dark again, demanding the Rabbit answer you.
  rules:               ALL GREEN (4/4)

--- TURN 6 (logged 2026-05-02T17:33:38+00:00) ---
  event_id:            5 -> 6
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         true
  prompt_hash:         11132ba56ecb
  player_input:        "__continue__"
  narrator_response:   "You keep going because stopping isn’t an option the hole understands. The darkness slips past you in cool bands, and you"
  choices_returned:
    A) Reach out and grab at the nearest shelf to slow yourself.
    B) Scan the passing ledges for anything useful you can snatch as you go.
    C) Call down into the dark after the Rabbit and demand he wait for you.
  rules:               ALL GREEN (4/4)

--- TURN 7 (logged 2026-05-02T17:35:53+00:00) ---
  event_id:            6 -> 7
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         true
  prompt_hash:         22384b5e8a76
  player_input:        "__continue__"
  narrator_response:   "You let the passage take you, and the descent keeps unspooling—absurdly long, almost courteous about it. Lamplight stead"
  choices_returned:
    A) Reach for the nearest shelf and grab something solid to steady yourself.
    B) Angle your body toward the brighter lamplight and prepare to land on your feet.
    C) Call out into the passage again and demand the White Rabbit answer you.
  rules:               ALL GREEN (4/4)

--- TURN 8 (logged 2026-05-02T17:38:46+00:00) ---
  event_id:            7 -> 8
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         6fa7b56d3ee4
  player_input:        "Angle your body toward the brighter lamplight and prepare to land on your feet."
  narrator_response:   "You twist in the long, impossible drop, shoulders tight, chin tucked—angling yourself toward the warmer spill of lamplig"
  choices_returned:
    A) Run down the passage toward the sense of hurry.
    B) Steady your breath and walk forward, keeping to the lamplight.
    C) Check your pockets and pat yourself down for anything useful before moving on.
  rules:               ALL GREEN (4/4)

--- TURN 9 (logged 2026-05-02T17:42:47+00:00) ---
  event_id:            8 -> 9
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         22d24b582288
  player_input:        "Check your pockets and pat yourself down for anything useful before moving on."
  narrator_response:   "You run your hands over yourself with brisk, practical urgency—pockets first, then the seams, then the little hiding-pla"
  choices_returned:
    A) Hurry down the lamp-lit passage toward the feeling of movement ahead.
    B) Search the shelves and ledges along the wall for anything you can use.
    C) Test the nearest lamp and wall for a switch, seam, or hidden door.
  rules:               ALL GREEN (4/4)

--- TURN 10 (logged 2026-05-02T17:45:31+00:00) ---
  event_id:            9 -> 10
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         09739d6c64c7
  player_input:        "I search the shelve and oh wow. i have found a bottle of water i drink from it. I keep it in my skirt for later thirst a"
  narrator_response:   "You reach for the nearest shelf—half expecting a jar to turn into a clue under your fingers—and meet only dusty glass an"
  choices_returned:
    A) Sprint harder toward the fleeing sense of movement ahead.
    B) Slow to a cautious jog and listen for where the hurried sound is coming from.
    C) Turn back and scan the shelves again for anything actually useful before you go farther.
  rules:               ALL GREEN (4/4)

--- TURN 11 (logged 2026-05-02T17:46:55+00:00) ---
  event_id:            10 -> 10
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    false
  is_continue:         true
  prompt_hash:         737e4bf2b610
  player_input:        "__continue__"
  narrator_response:   "You push on through the lamp-lit passage until it opens—almost politely—into a wide, echoing hall, bright with a clean, "
  choices_returned:
    A) Kneel and press your face to the tiny doorway to study the garden beyond.
    B) Try the nearest full-sized door along the hall wall and test if any will open.
    C) Search the glass table and the floor around it for anything else you can use.
  rules:               ALL GREEN (4/4)

--- TURN 12 (logged 2026-05-02T17:48:58+00:00) ---
  event_id:            10 -> 10
  session_number:      1 -> 1
  turn_count_in_event: 2
  advance_returned:    false
  is_continue:         false
  prompt_hash:         946ad34e96f5
  player_input:        "Search the glass table and the floor around it for anything else you can use."
  narrator_response:   "You sweep your hands across the glass tabletop, palms sliding on its cold, too-smooth surface. Dust smears into faint gr"
  choices_returned:
    A) Try the little door anyway and press your face to the opening for a better look
    B) Scan the hall’s doors and test one at random with your free hand
    C) Pull the curtain aside fully and inspect the tiny doorway and lock up close
  rules:               ALL GREEN (4/4)

--- TURN 13 (logged 2026-05-02T17:53:09+00:00) ---
  event_id:            10 -> 11
  session_number:      1 -> 1
  turn_count_in_event: 3
  advance_returned:    true
  is_continue:         true
  prompt_hash:         837af08f4db7
  player_input:        "__continue__"
  narrator_response:   "You try to press on as though forward motion might solve what the hall refuses to explain. But “forward” here is a short"
  choices_returned:
    A) Try the garden door again with the key, forcing yourself as close as you can to the opening.
    B) Shift your weight and crawl toward higher ground by the glass table to keep the key and your hands out of the water.
    C) Wipe your face and speak aloud to steady yourself, naming what you can do next despite the flooding.
  rules:               ALL GREEN (4/4)

--- TURN 14 (logged 2026-05-02T17:55:23+00:00) ---
  event_id:            11 -> 12
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         true
  prompt_hash:         02766e738fdc
  player_input:        "__continue__"
  narrator_response:   "You try again—because trying is the only thing that feels like movement—but the hall has become treacherous with your ow"
  choices_returned:
    A) You chase after him calling “sir” and ask for help as politely as you can manage.
    B) You shout after him and demand he stop treating you like a problem to run from.
    C) You stay silent and put on the gloves while you fan yourself, taking whatever advantage his role can buy you.
  rules:               ALL GREEN (4/4)

--- TURN 15 (logged 2026-05-02T17:58:32+00:00) ---
  event_id:            12 -> 13
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         true
  prompt_hash:         3189e214d1f3
  player_input:        "__continue__"
  narrator_response:   "You force yourself onward—because surely there must be an after to all this—but the hall gives you only the same glossy,"
  choices_returned:
    A) Put on the gloves and take up the fan to cool yourself.
    B) Pick up only the fan and start fanning hard to steady your breathing.
    C) Pick up only the gloves and examine them for any label or clue.
  rules:               ALL GREEN (4/4)

--- TURN 16 (logged 2026-05-02T18:00:21+00:00) ---
  event_id:            13 -> 14
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         true
  prompt_hash:         24ad4e682b71
  player_input:        "__continue__"
  narrator_response:   "You shove at the hall with sheer will, trying to make forward mean something more than scraping along stone and water. T"
  choices_returned:
    A) Grab the fan and start fanning yourself steadily.
    B) Slip on the Rabbit’s little white kid glove and test your grip on the key.
    C) Wade to the nearest wall and brace yourself to stand more securely on the slick floor.
  rules:               ALL GREEN (4/4)

--- TURN 17 (logged 2026-05-02T18:02:02+00:00) ---
  event_id:            14 -> 15
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         true
  prompt_hash:         dd1794f05570
  player_input:        "__continue__"
  narrator_response:   "You try to make forward into a spell—one more push, one more scrape of shoe on slick stone—but the hall only answers wit"
  choices_returned:
    A) Grab the fan and begin fanning yourself hard.
    B) Snatch up the gloves and pull them on.
    C) Wade back to the glass table and try to reach its edge despite the water.
  rules:               ALL GREEN (4/4)

--- TURN 18 (logged 2026-05-02T18:02:55+00:00) ---
  event_id:            15 -> 16
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         aaa6d3691636
  player_input:        "Grab the fan and begin fanning yourself hard."
  narrator_response:   "Your fingers close around the fan’s damp ribs, and the flimsy thing suddenly feels like a tool—ridiculous, but real. You"
  choices_returned:
    A) Keep fanning until the change settles.
    B) Snatch up the white kid gloves before they drift out of reach.
    C) Clamp the fan still and check your size against the waterline and the key.
  rules:               ALL GREEN (4/4)

--- TURN 19 (logged 2026-05-02T18:04:26+00:00) ---
  event_id:            16 -> 17
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         true
  prompt_hash:         28c2619dc4b4
  player_input:        "__continue__"
  narrator_response:   "You try to translate forward into something your body can do, but the beat of the fan is still in your bones—air against"
  choices_returned:
    A) Push through the water toward the shore of the pool, leading the crowded creatures with you.
    B) Slip your hands into the white kid gloves to steady your grip as you move.
    C) Pocket the little golden key securely and stop fanning for a moment to control your shrinking.
  rules:               ALL GREEN (4/4)

--- TURN 20 (logged 2026-05-02T18:06:14+00:00) ---
  event_id:            17 -> 18
  session_number:      1 -> 1
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         dfa50ef8df93
  player_input:        "Slip your hands into the white kid gloves to steady your grip as you move."
  narrator_response:   "You crouch—lower than you meant to, because the world keeps stealing inches from you—and scoop the white kid gloves from"
  choices_returned:
    A) Hurry toward the little door while you’re still shrinking
    B) Slow your fanning and test your footing, controlling the pace of the change
    C) Raise your gloved hands and call to the Mouse’s circle for help drying off
  rules:               ALL GREEN (4/4)

=== SUMMARY ===
rows_shown:       20
total_violations: 0
All rule checks green for every turn.