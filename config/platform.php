<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product identity
    |--------------------------------------------------------------------------
    |
    | Change these values (or the matching environment variables) to rename
    | the product without touching application code or Blade templates.
    |
    */

    'name' => env('PLATFORM_NAME', 'SAHA Observability'),
    'short_name' => env('PLATFORM_SHORT_NAME', 'SAHA'),

    'retention' => [
        'metrics_days' => (int) env('PLATFORM_METRIC_RETENTION_DAYS', 30),
        'logs_days' => (int) env('PLATFORM_LOG_RETENTION_DAYS', 90),
        'audit_days' => (int) env('PLATFORM_AUDIT_RETENTION_DAYS', 365),
    ],

    'auth' => [
        'login_max_attempts' => (int) env('PLATFORM_LOGIN_MAX_ATTEMPTS', 5),
    ],

    'agents' => [
        'heartbeat_interval_seconds' => (int) env('PLATFORM_AGENT_HEARTBEAT_INTERVAL', 10),
        'offline_after_minutes' => (int) env('PLATFORM_AGENT_OFFLINE_AFTER', 5),
        'heartbeat_rate_limit' => (int) env('PLATFORM_AGENT_HEARTBEAT_RATE_LIMIT', 60),
        'register_rate_limit' => (int) env('PLATFORM_AGENT_REGISTER_RATE_LIMIT', 10),
        'metrics_rate_limit' => (int) env('PLATFORM_AGENT_METRICS_RATE_LIMIT', 60),
        'logs_rate_limit' => (int) env('PLATFORM_AGENT_LOGS_RATE_LIMIT', 60),
        'apm_rate_limit' => (int) env('PLATFORM_AGENT_APM_RATE_LIMIT', 60),
    ],

    'metrics' => [
        'warning_percent' => (int) env('PLATFORM_METRIC_WARNING_PERCENT', 80),
        'critical_percent' => (int) env('PLATFORM_METRIC_CRITICAL_PERCENT', 90),
        'max_points_per_request' => (int) env('PLATFORM_METRIC_MAX_POINTS', 40),
    ],

    'apm' => [
        'warning_error_rate' => (float) env('PLATFORM_APM_WARNING_ERROR_RATE', 1),
        'critical_error_rate' => (float) env('PLATFORM_APM_CRITICAL_ERROR_RATE', 5),
        'warning_p95_ms' => (int) env('PLATFORM_APM_WARNING_P95_MS', 1000),
        'critical_p95_ms' => (int) env('PLATFORM_APM_CRITICAL_P95_MS', 2000),
        'max_applications_per_request' => (int) env('PLATFORM_APM_MAX_APPLICATIONS', 20),
    ],

    'logs' => [
        'max_entries_per_request' => (int) env('PLATFORM_LOG_MAX_ENTRIES', 100),
        'export_limit' => (int) env('PLATFORM_LOG_EXPORT_LIMIT', 1000),
    ],

];
