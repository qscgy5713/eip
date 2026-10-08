<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * 檢核當前登入使用者之在職狀態，若已被設為停權 (suspended) 或離職 (resigned)，
     * 立即終止其活躍會話並強制登出，防止離職/停權同仁持續存取內部系統 (SEC-03)。
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user && in_array($user->status, ['suspended', 'resigned'], true)) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $statusLabel = $user->status === 'suspended' ? '暫時停權' : '已離職';

                return redirect()->route('login')->withErrors([
                    'email' => "您的帳號目前處於【{$statusLabel}】狀態，會話已即時終止。請洽詢人事管理員。",
                ]);
            }
        }

        return $next($request);
    }
}
