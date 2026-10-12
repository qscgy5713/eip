<?php

namespace App\Services;

use App\Models\LoginEvent;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Illuminate\Http\Request;

/** 登入紀錄、新裝置通知與連續失敗警示 */
class LoginActivityService
{
    /** 連續失敗幾次通知本人 / 通知管理員 */
    public const NOTIFY_USER_AT = 5;

    public const NOTIFY_ADMINS_AT = 10;

    /** 計算「連續失敗」的時間窗(分鐘) */
    private const WINDOW_MINUTES = 60;

    public function recordSuccess(User $user, Request $request): void
    {
        $hash = $this->deviceHash($request);
        $seenBefore = LoginEvent::where('user_id', $user->id)->where('event', LoginEvent::SUCCESS)->exists();
        $knownDevice = LoginEvent::where('user_id', $user->id)->where('event', LoginEvent::SUCCESS)->where('device_hash', $hash)->exists();

        LoginEvent::create([
            'user_id' => $user->id, 'email' => $user->email, 'event' => LoginEvent::SUCCESS,
            'ip' => $request->ip(), 'user_agent' => $this->agent($request), 'device_hash' => $hash,
        ]);

        // 有過登入紀錄、但這是第一次看到的瀏覽器 → 提醒本人
        if ($seenBefore && ! $knownDevice) {
            $user->notify(new EipSystemNotification(
                title: '【資安提醒】您的帳號在新裝置登入',
                message: '剛剛有一個新的裝置(' . LoginEvent::describeAgent($this->agent($request)) . ",IP {$request->ip()})登入了您的帳號。如果不是您本人,請立即更改密碼並到個人帳號設定登出其他裝置。",
                type: 'security',
                actionUrl: route('profile.edit'),
                senderName: '系統資安',
            ));
        }
    }

    public function recordFailure(string $email, ?User $user, Request $request, string $event = LoginEvent::FAILED): void
    {
        LoginEvent::create([
            'user_id' => $user?->id, 'email' => mb_substr($email, 0, 100), 'event' => $event,
            'ip' => $request->ip(), 'user_agent' => $this->agent($request), 'device_hash' => $this->deviceHash($request),
        ]);

        if (! $user) {
            return;
        }

        $lastSuccess = LoginEvent::where('user_id', $user->id)->where('event', LoginEvent::SUCCESS)->max('created_at');
        $count = LoginEvent::where('user_id', $user->id)
            ->whereIn('event', [LoginEvent::FAILED, LoginEvent::TWO_FACTOR_FAILED])
            ->where('created_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->when($lastSuccess, fn ($q) => $q->where('created_at', '>', $lastSuccess))
            ->count();

        if ($count === self::NOTIFY_USER_AT) {
            $user->notify(new EipSystemNotification(
                title: '【資安提醒】有人多次嘗試登入您的帳號失敗',
                message: "過去 " . self::WINDOW_MINUTES . " 分鐘內,您的帳號已有 {$count} 次登入失敗(最近一次來自 IP {$request->ip()})。如果不是您本人操作,建議立即更改密碼並啟用雙因素驗證。",
                type: 'security',
                actionUrl: route('profile.edit'),
                senderName: '系統資安',
            ));
        }

        if ($count === self::NOTIFY_ADMINS_AT) {
            User::where('role', 'admin')->where('status', 'active')->get()->each(fn (User $admin) => $admin->notify(new EipSystemNotification(
                title: '【資安警示】疑似暴力破解登入',
                message: "帳號「{$user->name}」({$user->email})過去 " . self::WINDOW_MINUTES . " 分鐘內連續 {$count} 次登入失敗,最近一次來自 IP {$request->ip()}。",
                type: 'security',
                actionUrl: route('audit-logs.index'),
                senderName: '系統資安',
            )));
        }
    }

    private function agent(Request $request): string
    {
        return mb_substr((string) $request->userAgent(), 0, 255);
    }

    private function deviceHash(Request $request): string
    {
        return hash('sha256', (string) $request->userAgent());
    }
}
