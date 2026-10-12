<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 為所有回應補上基本安全標頭。
 * - Referrer-Policy:跨站只送出來源網域,不外洩完整路徑與查詢字串
 * - Permissions-Policy:僅開放定位(打卡 GPS 圍欄需要),關閉相機/麥克風/付款等用不到的能力
 * - Strict-Transport-Security:僅在 HTTPS 連線時送出
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
