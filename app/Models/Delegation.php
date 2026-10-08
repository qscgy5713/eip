<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'delegate_id',
        'start_date',
        'end_date',
        'reason',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /**
     * 篩選目前生效中的代理關係
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }

    /**
     * 檢查該代理是否在今天生效中
     */
    public function isCurrentlyActive(): bool
    {
        $today = Carbon::today()->toDateString();
        $startDate = is_string($this->start_date) ? $this->start_date : $this->start_date?->toDateString();
        $endDate = is_string($this->end_date) ? $this->end_date : $this->end_date?->toDateString();

        return $this->is_active
            && $startDate <= $today
            && $endDate >= $today;
    }
}
