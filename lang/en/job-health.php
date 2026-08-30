<?php

declare(strict_types=1);

return [
    'title' => 'Job Health',
    'success' => 'Success',
    'failed' => 'Failed',
    'missed' => 'Missed',
    'running' => 'Running',
    'never' => 'Never',
    'source_schedule' => 'Schedule',
    'source_manual' => 'Manual',
    'source_custom' => 'Custom',
    'never_ran' => 'No run recorded — expected at :time',
    'counts' => ':ok/:total succeeded · :fail failed',
    'columns' => [
        'time' => 'Time',
        'source' => 'Source',
        'by' => 'By',
        'duration' => 'Duration',
        'status' => 'Status',
    ],
    'not_scheduled' => 'Not scheduled',
    'last_run_never' => '—',
];
