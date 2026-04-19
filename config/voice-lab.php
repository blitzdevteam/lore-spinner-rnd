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
    'model_id' => env('VOICELAB_MODEL_ID', env('ELEVENLABS_MODEL_ID', 'eleven_v3')),
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

];
