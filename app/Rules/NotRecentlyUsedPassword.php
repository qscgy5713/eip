<?php

namespace App\Rules;

use App\Models\User;
use App\Services\PasswordPolicyService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** 新密碼不可與目前密碼或最近 N 組歷史密碼相同(N 由 EIP_PASSWORD_HISTORY 設定,0 = 不檢查)。 */
class NotRecentlyUsedPassword implements ValidationRule
{
    public function __construct(private ?User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->user && app(PasswordPolicyService::class)->wasUsedRecently($this->user, (string) $value)) {
            $keep = PasswordPolicyService::historyCount();
            $fail("新密碼不可與目前或最近 {$keep} 組用過的密碼相同。");
        }
    }
}
