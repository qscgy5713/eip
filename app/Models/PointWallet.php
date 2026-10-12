<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointWallet extends Model
{
    use HasFactory;

    /** 每月固定可贈送感謝幣額度 */
    public const MONTHLY_QUOTA = 100;

    protected $fillable = [
        'user_id',
        'recognition_quota',
        'quota_month',
        'reward_points',
    ];

    protected $casts = [
        'recognition_quota' => 'integer',
        'reward_points' => 'integer',
    ];

    /**
     * 錢包所屬同仁關聯
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 取得或自動初始化同仁的福利錢包 (跨月自動重置感謝幣額度)
     */
    public static function getOrCreateForUser(User $user): self
    {
        $wallet = static::firstOrCreate(
            ['user_id' => $user->id],
            [
                'recognition_quota' => self::MONTHLY_QUOTA,
                'quota_month' => now()->format('Y-m'),
                'reward_points' => 0,
            ]
        );

        return $wallet->refreshQuotaForCurrentMonth();
    }

    /**
     * 跨月時將感謝幣額度重置為每月固定額度
     */
    public function refreshQuotaForCurrentMonth(): self
    {
        $month = now()->format('Y-m');

        if ($this->quota_month !== $month) {
            $this->forceFill([
                'recognition_quota' => self::MONTHLY_QUOTA,
                'quota_month' => $month,
            ])->save();
        }

        return $this;
    }
}
