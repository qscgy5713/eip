<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** 個人設定頁的雙因素驗證 (2FA) 啟用 / 確認 / 停用 / 重新產生復原碼 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    /** 產生密鑰(尚未啟用,須輸入驗證碼確認後才生效) */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return back()->withErrors(['password' => '已啟用雙因素驗證。']);
        }

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return back();
    }

    /** 以驗證器產生的 6 位數驗證碼確認,正式啟用並顯示一次性復原碼 */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $user = $request->user();
        if (! $user->two_factor_secret || $user->hasTwoFactorEnabled()) {
            return back()->withErrors(['code' => '請先產生密鑰。']);
        }

        $step = $this->twoFactor->verify($user->two_factor_secret, $request->input('code'));
        if ($step === null) {
            throw ValidationException::withMessages(['code' => '驗證碼不正確,請確認手機時間正確後再試一次。']);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes['hashed'],
            'two_factor_last_step' => $step,
        ])->save();

        AuditLog::log(action: 'enable_two_factor', description: "同仁 {$user->name} 啟用了雙因素驗證", auditable: $user);

        return redirect()->route('profile.edit')->with('two_factor_recovery_codes', $codes['plain']);
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        if (in_array($user->role, config('eip.require_2fa_roles', []), true) && $user->hasTwoFactorEnabled()) {
            return back()->withErrors(['password' => '您的角色依公司政策必須啟用雙因素驗證,無法停用。']);
        }

        $this->clear($user);
        AuditLog::log(action: 'disable_two_factor', description: "同仁 {$user->name} 停用了雙因素驗證", auditable: $user);

        return back();
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        if (! $user->hasTwoFactorEnabled()) {
            return back()->withErrors(['password' => '尚未啟用雙因素驗證。']);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes['hashed']])->save();

        AuditLog::log(action: 'regenerate_two_factor_recovery_codes', description: "同仁 {$user->name} 重新產生了雙因素復原碼", auditable: $user);

        return redirect()->route('profile.edit')->with('two_factor_recovery_codes', $codes['plain']);
    }

    private function clear($user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }
}
