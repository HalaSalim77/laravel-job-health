<?php

declare(strict_types=1);

return [
    'enabled' => env('JOB_HEALTH_ENABLED', true),
    'path' => env('JOB_HEALTH_PATH', 'job-health'),
    'middleware' => ['web', 'auth'],
    'history_days' => 30,
    'counter_timezone' => 'UTC',
    'prune_days' => 30,
    'missed' => [
        'grace_minutes' => 30,     // no run within this of expected time → missed
        'stale_intervals' => 2,    // last SUCCESS older than N scheduled intervals → missed
    ],
    'groups' => [
        // 'backup' => 'Backups',
    ],
    'jobs' => [
        /*
        'sitemap' => [
            'name' => 'Generate sitemap',
            'group' => 'general',
            'cron' => '0 5 * * *',
            'timezone' => 'UTC',
            'schedule_label' => 'Daily 05:00',
            'record_schedule_events' => true, // false when the queued job records itself
            'meta' => ['tracks_counts' => false],
        ],
        */
    ],
];
