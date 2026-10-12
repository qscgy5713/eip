<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // JSON 日期帶上應用時區位移(如 2026-10-12T00:00:00+08:00),前端截取 YYYY-MM-DD 才不會因 UTC 轉換差一天
        Carbon::serializeUsing(fn (Carbon $date) => $date->format('Y-m-d\TH:i:sP'));

        // 模型綁定參數一律限數字:避免 /assets/create 這類非數字網址在 PostgreSQL 觸發型別錯誤而回 500,改為正常 404
        foreach ([
            'announcement', 'asset', 'booking', 'delegation', 'department', 'document', 'form', 'formRequest',
            'mealOrder', 'meetingRoom', 'payslip', 'poll', 'project', 'recognitionPost', 'registration', 'task',
            'ticket', 'trainingCourse', 'user', 'vehicleDispatch', 'visitorReservation', 'webhook', 'weeklyReport',
            'welfareRedemption', 'welfareReward',
        ] as $param) {
            Route::pattern($param, '[0-9]+');
        }
    }
}
