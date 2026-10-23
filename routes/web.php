<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => config('app.name', 'PolicyFlow'),
        'status' => 'operational',
        'phase' => 'Phase 1 - Database Implementation & Domain Core',
    ]);
});
