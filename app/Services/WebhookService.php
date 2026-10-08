<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Webhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * 觸發特定事件並分發給所有訂閱的 Webhook
     */
    public static function dispatch(string $event, array $data, string $summary = ''): void
    {
        $webhooks = Webhook::where('is_active', true)->get();

        foreach ($webhooks as $webhook) {
            if ($webhook->subscribesTo($event)) {
                self::send($webhook, $event, $data, $summary);
            }
        }
    }

    /**
     * 檢驗 Webhook URL 是否安全，嚴格阻絕 SSRF 內網與中繼資料攻擊 (SEC-06)
     */
    public static function isSafeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        // 僅允許 http 與 https 協議
        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parsed['host']);

        // 阻擋 localhost 與常見私有/本地主機別名
        if (in_array($host, ['localhost', 'ip6-localhost', 'ip6-loopback'], true)) {
            return false;
        }

        // 解析 IP 位址
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // 檢查是否為私有保留網段或回環位址 (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, 169.254.0.0/16 等)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return true;
    }

    /**
     * 發送單一 Webhook Payload
     */
    public static function send(Webhook $webhook, string $event, array $data, string $summary = ''): bool
    {
        // 發送前二次校驗，防止 DNS 重綁定 (DNS Rebinding) 或內部 SSRF 穿透
        if (!self::isSafeUrl($webhook->url)) {
            Log::warning("Webhook 發送攔截：URL 涉及私有網段或非外部位址 [{$webhook->name}]: {$webhook->url}");
            $webhook->update([
                'last_triggered_at' => now(),
                'last_status' => 'failed',
            ]);
            return false;
        }

        $payload = [
            'event' => $event,
            'summary' => $summary ?: "EIP 系統事件: {$event}",
            'text' => $summary ?: "EIP 系統事件: {$event}", // Slack 格式相容
            'content' => $summary ?: "EIP 系統事件: {$event}", // Discord 格式相容
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        $jsonPayload = json_encode($payload);
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'EIP-Webhook-Client/1.0',
            'X-EIP-Event' => $event,
        ];

        // 簽章生成
        if (!empty($webhook->secret)) {
            $headers['X-EIP-Signature'] = hash_hmac('sha256', $jsonPayload, $webhook->secret);
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(3)
                ->post($webhook->url, $payload);

            $isSuccess = $response->successful();

            $webhook->update([
                'last_triggered_at' => now(),
                'last_status' => $isSuccess ? 'success' : 'failed',
            ]);

            return $isSuccess;
        } catch (\Throwable $e) {
            Log::warning("Webhook 發送失敗 [{$webhook->name}]: " . $e->getMessage());

            $webhook->update([
                'last_triggered_at' => now(),
                'last_status' => 'failed',
            ]);

            return false;
        }
    }
}
