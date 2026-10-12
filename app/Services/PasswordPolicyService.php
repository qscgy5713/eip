<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** 密碼到期與歷史:到期後沿用「強制更換密碼」流程;改密碼時記下舊 hash,供禁止重用檢查。 */
class PasswordPolicyService
{
    public static function maxAgeDays(): int
    {
        return max(0, (int) config('eip.password_max_age_days', 0));
    }

    public static function historyCount(): int
    {
        return max(0, (int) config('eip.password_history', 5));
    }

    /** 密碼是否已過期(未啟用到期政策、或從未記錄過更換時間且帳號太新則不算)。 */
    public function isExpired(User $user): bool
    {
        $days = self::maxAgeDays();
        if ($days === 0) {
            return false;
        }
        $changedAt = $user->password_changed_at ?? $user->created_at;

        return $changedAt !== null && $changedAt->lt(now()->subDays($days));
    }

    public function daysUntilExpiry(User $user): ?int
    {
        $days = self::maxAgeDays();
        if ($days === 0) {
            return null;
        }
        $changedAt = $user->password_changed_at ?? $user->created_at;

        return $changedAt ? (int) now()->diffInDays($changedAt->copy()->addDays($days), false) : null;
    }

    public function wasUsedRecently(User $user, string $plain): bool
    {
        $keep = self::historyCount();
        if ($keep === 0) {
            return false;
        }
        if (Hash::check($plain, $user->password)) {
            return true;
        }

        $hashes = DB::table('password_histories')->where('user_id', $user->id)
            ->orderByDesc('id')->limit($keep)->pluck('password_hash');
        foreach ($hashes as $hash) {
            if (Hash::check($plain, $hash)) {
                return true;
            }
        }

        return false;
    }

    /** 在密碼被換掉「之前」呼叫,把舊 hash 存進歷史,並只保留最近 N 筆。 */
    public function remember(User $user): void
    {
        $keep = self::historyCount();
        if ($keep === 0 || ! $user->password) {
            return;
        }
        DB::table('password_histories')->insert(['user_id' => $user->id, 'password_hash' => $user->password, 'created_at' => now()]);

        $stale = DB::table('password_histories')->where('user_id', $user->id)->orderByDesc('id')->skip($keep)->take(1000)->pluck('id');
        if ($stale->isNotEmpty()) {
            DB::table('password_histories')->whereIn('id', $stale)->delete();
        }
    }
}
