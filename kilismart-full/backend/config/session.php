<?php
use Illuminate\Support\Str;
return [
    'driver'          => env('SESSION_DRIVER', 'redis'),
    'lifetime'        => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt'         => false,
    'files'           => storage_path('framework/sessions'),
    'connection'      => env('SESSION_CONNECTION'),
    'table'           => 'sessions',
    'lottery'         => [2, 100],
    'cookie'          => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'kilismart'), '_').'_session'),
    'path'            => '/',
    'domain'          => env('SESSION_DOMAIN'),
    'secure'          => env('SESSION_SECURE_COOKIE', true),
    'http_only'       => true,
    'same_site'       => 'lax',
];
