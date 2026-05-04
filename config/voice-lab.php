<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Story Grounding
    |--------------------------------------------------------------------------
    |
    | Slug of the story used to pull character/world/tone context for the
    | conversational agent. The Story model is read-only here — Voice Lab
    | never mutates it. Set to null/empty to disable story grounding.
    |
    */
    'story_slug' => env('VOICELAB_STORY_SLUG', 'alices-adventures-in-wonderland'),

    /*
    |--------------------------------------------------------------------------
    | ElevenLabs (TTS)
    |--------------------------------------------------------------------------
    |
    | Dedicated TTS configuration for the Voice Lab module. Isolated from
    | config/services.php so that changing a value here never affects any
    | other part of the app.
    |
    */
    'api_key' => env('ELEVENLABS_API_KEY'),
    'voice_id' => env('VOICELAB_VOICE_ID', env('ELEVENLABS_VOICE_ID')),
    // Default to turbo_v2_5 for sub-400ms first-byte latency. Use eleven_flash_v2_5
    // for even faster (~150ms) at the cost of some expressive range.
    'model_id' => env('VOICELAB_MODEL_ID', env('ELEVENLABS_MODEL_ID', 'eleven_turbo_v2_5')),
    'output_format' => env('VOICELAB_OUTPUT_FORMAT', 'mp3_44100_128'),

    'voice_settings' => [
        'stability' => (float) env('VOICELAB_STABILITY', 0.50),
        'similarity_boost' => (float) env('VOICELAB_SIMILARITY_BOOST', 0.75),
        'style' => (float) env('VOICELAB_STYLE', 0.0),
        'speed' => (float) env('VOICELAB_SPEED', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI (Whisper transcription)
    |--------------------------------------------------------------------------
    */
    'openai_api_key' => env('VOICELAB_OPENAI_API_KEY', env('OPENAI_API_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Conversation
    |--------------------------------------------------------------------------
    |
    | history_size     — how many past turns to include in the AI context.
    | greeting_enabled — whether the first turn auto-emits an opening narration.
    |
    */
    'history_size' => (int) env('VOICELAB_HISTORY_SIZE', 6),
    'greeting_enabled' => (bool) env('VOICELAB_GREETING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Intro (pre-baked Session 1 cold open)
    |--------------------------------------------------------------------------
    |
    | The first tap of the orb should play an instant, cinematic narration
    | with zero server round-trip. This section drives that: the text is
    | TTS-baked once to an MP3 on the public disk via `php artisan
    | voicelab:bake-intro`, and the same text + choices are stored as the
    | opening narrator turn so conversation history stays consistent.
    |
    | `text`      — HTML with <p> per paragraph; chunked for hallucination-safe TTS.
    | `choices`   — sentence-form choice buttons (6–14 words each).
    | `audio_path`— path on the `public` disk where the baked MP3 lives.
    | `audio_url` — public URL used by the frontend to fetch the MP3.
    |
    */
    'intro' => [
        'enabled' => (bool) env('VOICELAB_INTRO_ENABLED', true),
        // Bumped to v2 to invalidate any cached MP3 from the previous intro
        // text. Re-run `php artisan voicelab:bake-intro --force` after changing.
        'audio_path' => env('VOICELAB_INTRO_AUDIO_PATH', 'voicelab/session-1-opening-v2.mp3'),
        'audio_url' => env('VOICELAB_INTRO_AUDIO_URL', '/storage/voicelab/session-1-opening-v2.mp3'),
        // Cold open and choice options sourced verbatim from
        // database/exports/adapptation-third-try.json (Session 1 →
        // entry_point_diagnosis.cold_open and session_choice_design.branching_choice_1).
        'text' => <<<'HTML'
<p>Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-listening to your sister's page-turning.</p>
<p>Then a White Rabbit flashes past so close you catch the clean, sharp scent of crushed clover — and it mutters, plainly, like a person: "Oh dear! Oh dear! I shall be late!"</p>
<p>You don't freeze. You lunge up, skirt snagging on a thistle, because the Rabbit has a waistcoat-pocket. Because it pulls out a watch and checks it with frantic dignity, as if being late could matter to a rabbit.</p>
<p>Is this a trick? A dream? Or the first real thing that's happened all day?</p>
<p>The Rabbit darts toward the hedge, and the shadow beneath it looks like a mouth. You break into a run before you can talk yourself out of it.</p>
HTML,
        'choices' => [
            'You sprint after him and dive for the rabbit-hole the instant you see it.',
            'You keep him in sight but slow just long enough to clock landmarks and the shape of the hedge.',
            'You call out to him first and watch how he reacts before you commit to the hole.',
        ],
    ],

];
