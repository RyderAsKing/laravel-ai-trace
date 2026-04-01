<?php

use RyderAsKing\LaravelAiTrace\Http\Middleware\AuthorizeDashboard;
use RyderAsKing\LaravelAiTrace\Http\Middleware\EnsureDashboardEnabled;

return [
    'enabled' => env('AI_TRACE_ENABLED', true),

    'track_ai_sdk' => env('AI_TRACE_TRACK_AI_SDK', true),

    'record_content_mode' => env('AI_TRACE_RECORD_CONTENT_MODE', 'redacted'),

    'redaction' => [
        'callback' => null,
    ],

    'sample_rate' => (float) env('AI_TRACE_SAMPLE_RATE', 1.0),

    'dedup_ttl_seconds' => (int) env('AI_TRACE_DEDUP_TTL_SECONDS', 300),

    'stream' => [
        'max_events_per_invocation' => (int) env('AI_TRACE_STREAM_MAX_EVENTS_PER_INVOCATION', 1000),
    ],

    'retention_days' => (int) env('AI_TRACE_RETENTION_DAYS', 90),

    'dashboard' => [
        'enabled' => env('AI_TRACE_DASHBOARD_ENABLED', true),
        'domain' => env('AI_TRACE_DASHBOARD_DOMAIN'),
        'path' => env('AI_TRACE_DASHBOARD_PATH', 'ai-trace'),
        'middleware' => [
            'web',
            EnsureDashboardEnabled::class,
            AuthorizeDashboard::class,
        ],
        'authorization_gate' => env('AI_TRACE_DASHBOARD_AUTHORIZATION_GATE', 'viewAiTrace'),
    ],

    'pulse' => [
        'enabled' => env('AI_TRACE_PULSE_ENABLED', true),
    ],

    'pricing' => [
        'sync_enabled' => env('AI_TRACE_PRICING_SYNC_ENABLED', false),
    ],

    'alerts' => [
        'enabled' => env('AI_TRACE_ALERTS_ENABLED', false),
        'cooldown_minutes' => (int) env('AI_TRACE_ALERTS_COOLDOWN_MINUTES', 1440),
    ],
];
