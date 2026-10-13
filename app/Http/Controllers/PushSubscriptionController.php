<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /** 只接受主流瀏覽器的推播服務網域:伺服器之後會對這個網址送資料,不能讓使用者指定任意網址(SSRF) */
    private const ALLOWED_HOST_SUFFIXES = [
        'fcm.googleapis.com', 'googleapis.com',
        'push.services.mozilla.com',
        'push.apple.com',
        'notify.windows.com',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        abort_unless($this->allowedEndpoint($data['endpoint']), 422, '不支援的推播服務。');

        $hash = PushSubscription::hashEndpoint($data['endpoint']);
        // 同一台裝置換人登入時,訂閱改歸屬目前登入者
        PushSubscription::updateOrCreate(['endpoint_hash' => $hash], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'],
            'auth' => $data['keys']['auth'],
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
        $request->user()->update(['push_notifications' => true]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        $request->user()->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request, WebPushService $push): JsonResponse
    {
        $sent = $push->sendToUser($request->user(), '推播測試', '收到這則訊息,代表這台裝置的推播已經設定完成。', '/profile');

        return response()->json(['sent' => $sent], $sent > 0 ? 200 : 422);
    }

    private function allowedEndpoint(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }
}
