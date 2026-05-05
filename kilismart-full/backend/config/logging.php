<?php
return [
    'default' => env('LOG_CHANNEL', 'daily'),
    'channels' => [
        'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug')],
        'daily'  => ['driver' => 'daily',  'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug'), 'days' => 14],
        'stack'  => ['driver' => 'stack',  'channels' => ['single'], 'ignore_exceptions' => false],
        'null'   => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class],
    ],
];
