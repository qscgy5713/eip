<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 放在反向代理/負載平衡器/Cloudflare 後面時,用 TRUSTED_PROXIES 指定代理 IP(逗號分隔,或 * 代表全部),
        // 才能正確判斷 HTTPS 與使用者真實 IP(登入紀錄、失敗鎖定都依賴它)。
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->web(prepend: [
            \App\Http\Middleware\SanitizeQueryParameters::class,
        ]);

        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
            \App\Http\Middleware\EnsureTwoFactorForRequiredRoles::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 以 Inertia 頁面呈現 HTTP 錯誤,取代 Laravel 預設無樣式頁面
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            $handled = in_array($status, [403, 404, 419], true)
                || (in_array($status, [500, 503], true) && ! app()->hasDebugModeEnabled());

            if ($handled && ! $request->expectsJson() && ! $request->is('api/*')) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
