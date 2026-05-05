<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn() => response()->json(['service' => 'KiliSmart API', 'version' => '1.0', 'status' => 'running']));
Route::get('/up', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));
