<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** 手機/瀏覽器推播(Web Push + VAPID)。推播內容刻意精簡,不放敏感細節。 */
class WebPushService
{
    public function enabled(): bool
    {
        return (bool) config('eip.webpush.public_key') && (bool) config('eip.webpush.private_key');
    }

    /** 這位同仁會不會收到推播(全域已設定金鑰、本人沒關、且至少有一台裝置訂閱) */
    public function shouldPush(User $user): bool
    {
        return $this->enabled() && ($user->push_notifications ?? true) && $user->pushSubscriptions()->exists();
    }

    /**
     * 對同仁的所有裝置送出推播;推播服務回報訂閱已失效(404/410)時自動移除。
     *
     * @return int 成功送達的裝置數
     */
    public function sendToUser(User $user, string $title, string $body, ?string $url = null): int
    {
        if (! $this->shouldPush($user)) {
            return 0;
        }

        $subs = $user->pushSubscriptions()->get();
        $payload = json_encode([
            'title' => mb_strimwidth($title, 0, 80, '…'),
            'body' => mb_strimwidth($body, 0, 140, '…'),
            'url' => $this->safeUrl($url),
        ], JSON_UNESCAPED_UNICODE);

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => config('eip.webpush.subject'),
                'publicKey' => config('eip.webpush.public_key'),
                'privateKey' => config('eip.webpush.private_key'),
            ]], ['TTL' => 3600 * 12, 'urgency' => 'normal']);

            foreach ($subs as $s) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $s->endpoint,
                    'keys' => ['p256dh' => $s->p256dh, 'auth' => $s->auth],
                ]), $payload);
            }

            $byEndpoint = $subs->keyBy('endpoint');
            $sent = 0;
            foreach ($webPush->flush() as $report) {
                $sub = $byEndpoint->get($report->getEndpoint());
                if ($report->isSuccess()) {
                    $sent++;
                    $sub?->forceFill(['last_used_at' => now()])->save();
                } elseif ($report->isSubscriptionExpired()) {
                    $sub?->delete();
                } else {
                    Log::warning('Web Push 失敗:' . $report->getReason(), ['user_id' => $user->id]);
                }
            }

            return $sent;
        } catch (\Throwable $e) {
            // 推播只是附加通道,失敗不影響站內通知與 Email
            Log::warning('Web Push 例外:' . $e->getMessage(), ['user_id' => $user->id]);

            return 0;
        }
    }

    /** 點通知要開的網址只允許本站相對路徑或同網域,避免被拿來導向外部網站 */
    private function safeUrl(?string $url): string
    {
        $url = (string) $url;
        if ($url === '') {
            return '/dashboard';
        }
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return '/dashboard';
        }

        return (parse_url($url, PHP_URL_PATH) ?: '/dashboard') . (($q = parse_url($url, PHP_URL_QUERY)) ? "?{$q}" : '');
    }
}
