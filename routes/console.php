<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| FPL Data Fetch Schedule
|--------------------------------------------------------------------------
|
| Schedule the FPL bootstrap data fetch to run daily at 11:59 PM (WITA time).
| This will queue the job in the background for processing.
|
*/

Schedule::command('fpl:fetch-bootstrap')
    ->dailyAt('23:59')
    ->timezone('Asia/Makassar')
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('FPL data fetch scheduled task triggered successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('FPL data fetch scheduled task failed to trigger');
    });
