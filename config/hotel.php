<?php

return [
    'name' => 'Hotel Management Report System',
    'property' => 'Swiss-Belhotel Waterfront Makassar',

    /*
    |--------------------------------------------------------------------------
    | Testing "Login As" Selector (Strictly Disabled in Production)
    |--------------------------------------------------------------------------
    */
    'testing_login_as' => env('APP_ENV', 'production') !== 'production'
        && (bool) env('APP_TESTING_LOGIN', false),

    /*
    |--------------------------------------------------------------------------
    | POMEC Temporary Preview Retention (Hours)
    |--------------------------------------------------------------------------
    */
    'pomec_temp_ttl_hours' => (int) env('POMEC_TEMP_TTL_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | AI Chatbot Provider Configuration (Local / Ollama / Cloud OpenAI-Compatible)
    |--------------------------------------------------------------------------
    | Supported providers: "local" (keyword engine only), "ollama" (on-premise),
    | "gemini" (Google Gemini API), "openrouter", or "openai". Even with a
    | cloud provider enabled, Laravel runs RBAC-controlled DB queries first
    | and strips IT technical topology/secrets before any external request.
    */
    'chatbot' => [
        'provider' => env('CHATBOT_PROVIDER', 'local'),
        'api_key' => env('CHATBOT_API_KEY'),
        'model' => env('CHATBOT_MODEL', 'qwen3:4b-instruct'),
        'endpoint' => env('CHATBOT_ENDPOINT'),
        'providers' => [
            'ollama' => [
                'endpoint' => env('OLLAMA_ENDPOINT'),
                'model' => env('OLLAMA_MODEL'),
            ],
            'gemini' => [
                'api_key' => env('GEMINI_API_KEY'),
                'model' => env('GEMINI_MODEL'),
                'endpoint' => env('GEMINI_ENDPOINT'),
            ],
            'openrouter' => [
                'api_key' => env('OPENROUTER_API_KEY'),
                'model' => env('OPENROUTER_MODEL'),
                'endpoint' => env('OPENROUTER_ENDPOINT'),
            ],
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_MODEL'),
                'endpoint' => env('OPENAI_ENDPOINT'),
            ],
        ],
        // Seconds. Narration ("kenapa…") may take longer than reading the intent of a question.
        'timeout' => (int) env('CHATBOT_TIMEOUT', 35),
        'timeout_intent' => (int) env('CHATBOT_TIMEOUT_INTENT', 12),
        // Circuit breaker: after N failures within 60s, skip the AI for this many seconds (keyword answers only).
        'breaker_failures' => (int) env('CHATBOT_BREAKER_FAILURES', 3),
        'breaker_cooldown' => (int) env('CHATBOT_BREAKER_COOLDOWN', 120),
        // Days to keep chatbot turns (question excerpts, ratings, latency) — spec 16.4 retention.
        'retention_days' => (int) env('CHATBOT_RETENTION_DAYS', 90),
        // Business thresholds used in conclusions; confirm with Engineering / management.
        'thresholds' => [
            'review_sla_days' => (int) env('CHATBOT_REVIEW_SLA_DAYS', 2),
            'change_pct' => (float) env('CHATBOT_CHANGE_PCT', 10),
            'wbp_share_pct' => (float) env('CHATBOT_WBP_SHARE_PCT', 25),
        ],
    ],
];
