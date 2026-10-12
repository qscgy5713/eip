<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每天早上 9 點提醒簽核停留過久的單據(時區依 APP_TIMEZONE);需要 scheduler 容器運行
Schedule::command('eip:remind-pending-approvals')->dailyAt('09:00')->withoutOverlapping()->onOneServer();
