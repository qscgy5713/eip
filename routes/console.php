<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 登入紀錄只留 180 天
Schedule::call(fn () => \App\Models\LoginEvent::where('created_at', '<', now()->subDays(180))->delete())->weeklyOn(0, '03:30')->name('eip-prune-login-events');

// 排程器心跳(系統健康頁用)
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('eip:scheduler_heartbeat', time(), 600))->everyMinute()->name('eip-scheduler-heartbeat');

// 每天早上 9 點提醒簽核停留過久的單據(時區依 APP_TIMEZONE);需要 scheduler 容器運行
Schedule::command('eip:remind-pending-approvals')->dailyAt('09:00')->withoutOverlapping()->onOneServer();
