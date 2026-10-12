<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WelfareRedemption extends Model
{
    use \App\Models\Concerns\SerializesLocalDates;
    use HasFactory;

    protected $fillable = [
        'redemption_code',
        'user_id',
        'welfare_reward_id',
        'points_spent',
        'status',
        'fulfilled_at',
        'notes',
    ];

    protected $casts = [
        'points_spent' => 'integer',
        'fulfilled_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(WelfareReward::class, 'welfare_reward_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => '待發放',
            'fulfilled' => '已發放',
            'cancelled' => '已取消退點',
            default => $this->status,
        };
    }

    /**
     * 生成兌換序號 WR-YYYYMMDD-XXXX
     */
    public static function generateCode(): string
    {
        $prefix = 'WR-' . Carbon::now()->format('Ymd') . '-';
        $latest = static::where('redemption_code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->value('redemption_code');

        $number = $latest ? intval(substr($latest, -4)) + 1 : 1;

        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
