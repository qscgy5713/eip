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
     * 發送單一 Webhook Payload
     */
    public static function send(Webhook $webhook, string $event, array $data, string $summary = ''): bool
    {
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
