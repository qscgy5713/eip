<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recognition_quota',
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
     * 取得或自動初始化同仁的福利錢包
     */
    public static function getOrCreateForUser(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            [
                'recognition_quota' => 100,
                'reward_points' => 0,
            ]
        );
    }
}
