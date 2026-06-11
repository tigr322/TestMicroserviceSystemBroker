<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Idempotency TTL
    |--------------------------------------------------------------------------
    | How long (in seconds) an idempotency key is remembered.
    | Default: 86 400 seconds = 24 hours
    */
    'idempotency_ttl' => (int) env('IDEMPOTENCY_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Priority Queues
    |--------------------------------------------------------------------------
    | The worker processes these queues in order.
    | Changing the order here also requires updating the Horizon config.
    */
    'queues' => [
        'high'   => 'notifications.high',
        'normal' => 'notifications.normal',
        'low'    => 'notifications.low',
    ],
];
