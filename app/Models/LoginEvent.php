<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'email', 'event', 'ip', 'user_agent', 'device_hash'])]
class LoginEvent extends Model
{
    use SerializesLocalDates;

    public const UPDATED_AT = null;

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const TWO_FACTOR_FAILED = 'two_factor_failed';

    /** 把 User-Agent 簡化成「Chrome · macOS」這種人看得懂的字串 */
    public static function describeAgent(?string $ua): string
    {
        if (! $ua) {
            return '未知裝置';
        }
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => '其他瀏覽器',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => '其他系統',
        };

        return "{$browser} · {$os}";
    }
}
