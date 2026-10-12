<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'twoFactor' => $this->twoFactorState($request),
            'mailNotifications' => (bool) $request->user()->mail_notifications,
            'mailNotificationsAvailable' => (bool) config('eip.mail_notifications'),
        ]);
    }

    private function twoFactorState(Request $request): array
    {
        $user = $request->user();
        $pending = $user->two_factor_secret && ! $user->hasTwoFactorEnabled();

        return [
            'enabled' => $user->hasTwoFactorEnabled(),
            'required' => in_array($user->role, config('eip.require_2fa_roles', []), true),
            'pending' => $pending ? [
                'secret' => $user->two_factor_secret,
                'uri' => app(\App\Services\TwoFactorService::class)->otpAuthUri($user, $user->two_factor_secret),
            ] : null,
            'recoveryCodes' => session('two_factor_recovery_codes'),
            'recoveryRemaining' => $user->hasTwoFactorEnabled() ? count($user->two_factor_recovery_codes ?? []) : 0,
        ];
    }

    /** 切換是否同步以 Email 接收系統通知 */
    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate(['mail_notifications' => ['required', 'boolean']]);
        $request->user()->update(['mail_notifications' => $validated['mail_notifications']]);

        return Redirect::route('profile.edit')->with('success', $validated['mail_notifications'] ? '已開啟 Email 通知。' : '已關閉 Email 通知(站內通知不受影響)。');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
