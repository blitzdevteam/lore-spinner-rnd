<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID', 'pNInz6obpgDQGcFmaJgB'),
        'model_id' => env('ELEVENLABS_MODEL_ID', 'eleven_v3'),
    ],

    'deepgram' => [
        'api_key' => env('DEEPGRAM_API_KEY'),
        // Aura voices — see https://developers.deepgram.com/docs/tts-models
        // e.g. aura-2-thalia-en, aura-2-orion-en, aura-2-zeus-en
        'voice_model' => env('DEEPGRAM_VOICE_MODEL', 'aura-2-thalia-en'),
    ],

    'speechify' => [
        'api_key'  => env('SPEECHIFY_API_KEY'),
        // Built-in voices — see https://docs.speechify.ai/voices
        // e.g. george, henry, oliver, aria, jane, kristy, snoop, ...
        'voice_id' => env('SPEECHIFY_VOICE_ID', 'george'),
        // simba-english | simba-multilingual | simba-3.0 (low-latency)
        'model'    => env('SPEECHIFY_MODEL', 'simba-english'),
    ],

    // Set TTS_PROVIDER to "deepgram", "speechify", or "elevenlabs" (default: elevenlabs)
    'tts_provider' => env('TTS_PROVIDER', 'elevenlabs'),

];
