<?php

return [
    'heartbeat_key' => 'operations:scheduler-heartbeat',
    'heartbeat_max_age_seconds' => (int) env('OPERATIONS_HEARTBEAT_MAX_AGE_SECONDS', 180),
    'storage_path' => env('OPERATIONS_STORAGE_PATH', storage_path('app/public')),
    'min_free_disk_mb' => (int) env('OPERATIONS_MIN_FREE_DISK_MB', 2048),
    'retention_enabled' => filter_var(env('RETENTION_ENABLED', false), FILTER_VALIDATE_BOOL),
    'retention_chunk' => 100,
    'moderation_review_hours' => 24,
    'moderation_appeal_days' => 7,
    'identity_days' => 90,
    'report_days' => 180,
    'negotiation_days' => 180,
    'history_days' => 365,
];
