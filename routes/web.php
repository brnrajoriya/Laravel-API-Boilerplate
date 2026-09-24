<?php

use Illuminate\Support\Facades\Route;

// API-only application: the root just describes the API.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
    'docs' => url('/docs'),
    'health' => url('/up'),
]));
