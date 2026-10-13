<?php

namespace App\Passkeys;

use App\Services\LoginActivityService;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;

/** Passkey 登入成功後:寫入登入紀錄(新裝置提醒照常運作),再導向原本要去的頁面。 */
class EipPasskeyLoginResponse implements PasskeyLoginResponse
{
    public function toResponse($request)
    {
        app(LoginActivityService::class)->recordSuccess($request->user(), $request);

        $target = redirect()->intended(route('dashboard', absolute: false));

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $target->getTargetUrl()], 200)
            : $target;
    }
}
