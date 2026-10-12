<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 依 EIP_REQUIRE_2FA_ROLES 設定,指定角色未啟用 2FA 時只能進入個人帳號設定完成啟用。 */
class EnsureTwoFactorForRequiredRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $roles = config('eip.require_2fa_roles', []);

        if ($user && $roles && in_array($user->role, $roles, true) && ! $user->hasTwoFactorEnabled()
            && ! $request->routeIs('profile.*', 'two-factor.*', 'password.*', 'logout')) {
            return redirect()->route('profile.edit')->with('error', '依公司資安政策,您的角色必須先啟用雙因素驗證 (2FA) 才能使用系統。');
        }

        return $next($request);
    }
}
