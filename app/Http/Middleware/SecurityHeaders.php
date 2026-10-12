<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * 為所有回應補上基本安全標頭。
 * - Referrer-Policy:跨站只送出來源網域,不外洩完整路徑與查詢字串
 * - Permissions-Policy:僅開放定位(打卡 GPS 圍欄需要),關閉相機/麥克風/付款等用不到的能力
 * - Strict-Transport-Security:僅在 HTTPS 連線時送出
 * - Content-Security-Policy:腳本只允許同源檔案與帶有每次請求專屬 nonce 的內嵌腳本(不使用 'unsafe-inline')
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // 每個請求一組 nonce:Blade 內嵌腳本與 Vite 產生的標籤都會帶上它
        $nonce = base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Vite 開發伺服器(public/hot)使用外部 HMR 位址,開發時不套 CSP;下載/預覽若已自帶 CSP(如 sandbox)則不覆蓋
        if (! $response->headers->has('Content-Security-Policy') && ! is_file(public_path('hot'))) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
                "font-src 'self' data: https://fonts.bunny.net",
                "img-src 'self' data: blob:",
                "connect-src 'self'",
                "object-src 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
            ]));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
