<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ForcePasswordChange');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password', new \App\Rules\NotRecentlyUsedPassword($request->user())],
        ], [
            'password.different' => '新密碼不可與目前的初始密碼相同。',
        ]);

        $user = $request->user();
        app(\App\Services\PasswordPolicyService::class)->remember($user);
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();
        \Illuminate\Support\Facades\Auth::logoutOtherDevices($validated['password']);

        AuditLog::log(
            action: 'change_initial_password',
            description: "同仁 {$user->name} 已完成首次登入的密碼更換",
            auditable: $user
        );

        return redirect()->route('dashboard')->with('success', '密碼已更新,歡迎使用系統。');
    }
}
