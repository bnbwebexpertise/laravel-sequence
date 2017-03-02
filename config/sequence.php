<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Automatic sequence generation
    |--------------------------------------------------------------------------
    |
    | If true will dispatch the sequence generation when the model is saved.
    | Otherwise the sequence update job must be run (via cron task or manually).
    |
    */

    'dispatch' => env('SEQUENCE_AUTO_DISPATCH', true),

    /*
    |--------------------------------------------------------------------------
    | Start values
    |--------------------------------------------------------------------------
    |
    | Configure the sequence prefix and suffix
    |
    */

    'start' => env('SEQUENCE_START', 1),

    /*
    |--------------------------------------------------------------------------
    | Qeue
    |--------------------------------------------------------------------------
    |
    | The queue to push the jobs onto. Should be run by a single worker to avoid
    | concurrency.
    |
    */

    'queue' => [
        'connection' => env('SEQUENCE_QUEUE_CONNECTION', config('queue.default')),
        'name' => env('SEQUENCE_QUEUE_NAME', config(sprintf('queue.connections.%s.queue', config('queue.default')))),
    ],
];