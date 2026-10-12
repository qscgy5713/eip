<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', new \App\Rules\NotRecentlyUsedPassword($request->user())],
        ]);

        app(\App\Services\PasswordPolicyService::class)->remember($request->user());

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // 改密碼後,其他裝置的登入一律失效(本機維持登入)
        \Illuminate\Support\Facades\Auth::logoutOtherDevices($validated['password']);

        return back();
    }
}
