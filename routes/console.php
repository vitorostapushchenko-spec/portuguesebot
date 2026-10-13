<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('lessons:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->timezone(config('course.timezone'));

Schedule::command('teacher:daily-summary')
    ->dailyAt(config('course.daily_summary_time'))
    ->timezone(config('course.timezone'))
    ->withoutOverlapping();
