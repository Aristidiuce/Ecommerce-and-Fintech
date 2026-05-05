<?php
use Illuminate\Support\Str;
return [
    'default' => env('CACHE_DRIVER', 'redis'),
    'stores' => [
        'file'  => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],
        'redis' => ['driver' => 'redis', 'connection' => 'cache', 'lock_connection' => 'default'],
        'array' => ['driver' => 'array', 'serialize' => false],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'kilismart'), '_').'_cache_'),
];
