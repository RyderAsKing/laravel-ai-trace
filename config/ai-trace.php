<?php

return [
    'enabled' => env('AI_TRACE_ENABLED', true),

    'track_ai_sdk' => env('AI_TRACE_TRACK_AI_SDK', true),

    'record_content_mode' => env('AI_TRACE_RECORD_CONTENT_MODE', 'redacted'),

    'sample_rate' => (float) env('AI_TRACE_SAMPLE_RATE', 1.0),

    'dedup_ttl_seconds' => (int) env('AI_TRACE_DEDUP_TTL_SECONDS', 300),

    'retention_days' => (int) env('AI_TRACE_RETENTION_DAYS', 90),

    'dashboard' => [
        'enabled' => env('AI_TRACE_DASHBOARD_ENABLED', true),
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
