<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
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

        // 密碼強度:至少 8 碼且同時包含英文字母與數字
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // 系統信件使用繁體中文
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('重設密碼通知')
                ->greeting('您好!')
                ->line('我們收到了重設您帳號密碼的請求。')
                ->action('重設密碼', $url)
                ->line("此連結將於 {$minutes} 分鐘後失效。")
                ->line('若您並未提出此請求,請忽略這封信,無需採取任何動作。')
                ->salutation('— '.\App\Services\Branding::name());
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('請驗證您的電子郵件')
                ->greeting('您好!')
                ->line('請點擊下方按鈕驗證您的電子郵件。')
                ->action('驗證電子郵件', $url)
                ->line('若您並未建立此帳號,請忽略這封信。')
                ->salutation('— '.\App\Services\Branding::name());
        });

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
