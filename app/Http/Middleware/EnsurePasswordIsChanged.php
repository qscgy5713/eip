<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理員建立帳號或重設密碼後,該同仁必須先自行更換密碼才能使用系統其他功能。
 * 僅放行「更換密碼頁」與登出。
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 密碼使用超過期限:標記為必須更換,走同一套更換流程
        if ($user && ! $user->must_change_password && app(\App\Services\PasswordPolicyService::class)->isExpired($user)) {
            $user->forceFill(['must_change_password' => true])->save();
        }

        if ($user && $user->must_change_password && ! $request->routeIs('password.force', 'password.force.update', 'logout')) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => '請先更換初始密碼。'], 403);
            }

            return redirect()->route('password.force');
        }

        return $next($request);
    }
}
