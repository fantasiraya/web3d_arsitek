<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
    ],

    'image_render' => [
        'provider' => env('IMAGE_RENDER_PROVIDER', 'gemini'),
        'model' => env('IMAGE_RENDER_MODEL', 'imagen-3.0-generate-002'),
    ],

    'vision_ai' => [
        'provider' => env('VISION_AI_PROVIDER', 'gemini'),
        'providers' => [
            'gemini' => [
                'api_key' => env('GEMINI_API_KEY'),
                'model'   => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            ],
            'openai' => [
                'api_key'  => env('OPENAI_API_KEY'),
                'model'    => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            ],
            'claude' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'model'   => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
            ],
            'ollama' => [
                'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
                'model'    => env('OLLAMA_MODEL', 'llava'),
            ],
        ],
    ],
];
