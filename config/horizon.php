<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */
    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */
    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */
    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds (seconds)
    |--------------------------------------------------------------------------
    */
    'waits' => [
        'rabbitmq:notifications.high'   => 60,
        'rabbitmq:notifications.normal' => 120,
        'rabbitmq:notifications.low'    => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times (minutes)
    |--------------------------------------------------------------------------
    */
    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080, // 7 days
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    */
    'silenced' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */
    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (Megabytes)
    |--------------------------------------------------------------------------
    */
    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Workers process queues in the order listed.
    | notifications.high is always drained before .normal and .low.
    |
    */
    'environments' => [

        'production' => [
            'supervisor-high' => [
                'connection'  => 'rabbitmq',
                'queue'       => ['notifications.high'],
                'balance'     => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses'=> 10,
                'minProcesses'=> 2,
                'maxTime'     => 0,
                'maxJobs'     => 0,
                'memory'      => 128,
                'tries'       => 4,
                'timeout'     => 30,
                'nice'        => 0,
            ],

            'supervisor-normal' => [
                'connection'  => 'rabbitmq',
                'queue'       => ['notifications.normal'],
                'balance'     => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses'=> 5,
                'minProcesses'=> 1,
                'maxTime'     => 0,
                'maxJobs'     => 0,
                'memory'      => 128,
                'tries'       => 4,
                'timeout'     => 30,
                'nice'        => 0,
            ],

            'supervisor-low' => [
                'connection'  => 'rabbitmq',
                'queue'       => ['notifications.low'],
                'balance'     => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses'=> 3,
                'minProcesses'=> 1,
                'maxTime'     => 0,
                'maxJobs'     => 0,
                'memory'      => 128,
                'tries'       => 4,
                'timeout'     => 30,
                'nice'        => 0,
            ],
        ],

        'local' => [
            'supervisor-all' => [
                'connection'  => 'rabbitmq',
                'queue'       => ['notifications.high', 'notifications.normal', 'notifications.low'],
                'balance'     => 'simple',
                'processes'   => 3,
                'maxTime'     => 0,
                'maxJobs'     => 0,
                'memory'      => 128,
                'tries'       => 4,
                'timeout'     => 30,
                'nice'        => 0,
            ],
        ],
    ],

];
