<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Execute automated reconciliation of uncertain and stale policies every 5 minutes
Schedule::command('policyflow:reconcile --limit=50')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
