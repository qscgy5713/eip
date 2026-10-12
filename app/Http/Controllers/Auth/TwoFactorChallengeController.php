<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** 登入第二步:密碼通過後,輸入驗證器 6 位數驗證碼或一次性復原碼 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('two_factor.user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('two_factor.user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);

            return redirect()->route('login');
        }

        $request->validate(['code' => ['nullable', 'string', 'max:20'], 'recovery_code' => ['nullable', 'string', 'max:20']]);
        if (! $request->filled('code') && ! $request->filled('recovery_code')) {
            throw ValidationException::withMessages(['code' => '請輸入驗證碼或復原碼。']);
        }

        $key = 'two-factor:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['code' => "嘗試次數過多,請於 {$seconds} 秒後再試。"]);
        }

        $ok = false;
        if ($request->filled('code')) {
            $step = $this->twoFactor->verify($user->two_factor_secret, $request->input('code'), $user->two_factor_last_step);
            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                $ok = true;
            }
        } elseif ($this->twoFactor->consumeRecoveryCode($user, $request->input('recovery_code'))) {
            $ok = true;
            AuditLog::log(action: 'two_factor_recovery_login', description: "同仁 {$user->name} 使用復原碼完成雙因素登入", auditable: $user);
        }

        if (! $ok) {
            RateLimiter::hit($key);
            app(\App\Services\LoginActivityService::class)->recordFailure($user->email, $user, $request, \App\Models\LoginEvent::TWO_FACTOR_FAILED);
            throw ValidationException::withMessages([
                $request->filled('code') ? 'code' : 'recovery_code' => '驗證碼不正確或已使用過。',
            ]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $request->session()->forget('two_factor.user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        app(\App\Services\LoginActivityService::class)->recordSuccess($user, $request);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
