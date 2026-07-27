<?php

// إعدادات منصة الذكاء الاصطناعي
return [

    /*
    |--------------------------------------------------------------------------
    | Default Routing Strategy
    |--------------------------------------------------------------------------
    | priority: الأعلى أولوية أولاً
    | cost: الأرخص أولاً
    | quality: أعلى جودة أولاً
    */
    'routing_strategy' => env('AI_ROUTING_STRATEGY', 'priority'),

    /*
    |--------------------------------------------------------------------------
    | Async Threshold (seconds)
    |--------------------------------------------------------------------------
    | أي طلب يُتوقع أن يستغرق أكثر من هذه القيمة يُوضع في Queue
    */
    'async_threshold_seconds' => env('AI_ASYNC_THRESHOLD', 3),

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    */
    'queue_connection' => env('AI_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
    'queue_name'       => env('AI_QUEUE_NAME', 'ai-platform'),

    /*
    |--------------------------------------------------------------------------
    | Secret Encryption
    |--------------------------------------------------------------------------
    | مفتاح التشفير لـ API Keys — لا يُستخدم APP_KEY مباشرة
    */
    'secret_key' => env('AI_SECRET_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled'    => env('AI_DASHBOARD_ENABLED', true),
        'prefix'     => env('AI_DASHBOARD_PREFIX', 'ai-platform'),
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'prefix'     => env('AI_API_PREFIX', 'api/v1/ai'),
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel'    => env('AI_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'level'      => env('AI_LOG_LEVEL', 'info'),
        'log_inputs' => env('AI_LOG_INPUTS', false), // تحذير: قد يحتوي بيانات حساسة
    ],

    /*
    |--------------------------------------------------------------------------
    | Archiving (Retention Policy)
    |--------------------------------------------------------------------------
    */
    'archiving' => [
        'usage_logs_days'    => env('AI_ARCHIVE_USAGE_DAYS', 90),
        'router_logs_days'   => env('AI_ARCHIVE_ROUTER_DAYS', 30),
        'audit_logs_days'    => env('AI_ARCHIVE_AUDIT_DAYS', 180),
        'archive_enabled'    => env('AI_ARCHIVE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    */
    'health_check' => [
        'enabled'             => env('AI_HEALTH_CHECK_ENABLED', true),
        'interval_minutes'    => env('AI_HEALTH_CHECK_INTERVAL', 60),
        'failure_threshold'   => env('AI_HEALTH_FAILURE_THRESHOLD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory TTL defaults (seconds)
    |--------------------------------------------------------------------------
    */
    'memory' => [
        'session_ttl'      => env('AI_MEMORY_SESSION_TTL', 3600),       // 1 hour
        'conversation_ttl' => env('AI_MEMORY_CONV_TTL', 86400 * 30),    // 30 days
        'user_ttl'         => null,                                      // لا تنتهي
    ],

];
