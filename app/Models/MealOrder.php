<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealOrder extends Model
{
    use \App\Models\Concerns\SerializesLocalDates;
    use HasFactory;

    protected $fillable = [
        'creator_id',
        'user_id',
        'title',
        'store_name',
        'restaurant_name',
        'store_phone',
        'restaurant_phone',
        'menu_url',
        'description',
        'cutoff_time',
        'subsidy_per_person',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_time' => 'datetime',
            'subsidy_per_person' => 'decimal:2',
        ];
    }

    protected $appends = [
        'status_label',
        'total_amount',
        'total_quantity',
        'participants_count',
        'is_expired',
        'restaurant_name',
        'restaurant_phone',
    ];

    public function getRestaurantNameAttribute(): ?string
    {
        return $this->attributes['store_name'] ?? null;
    }

    public function setRestaurantNameAttribute($value): void
    {
        $this->attributes['store_name'] = $value;
    }

    public function getRestaurantPhoneAttribute(): ?string
    {
        return $this->attributes['store_phone'] ?? null;
    }

    public function setRestaurantPhoneAttribute($value): void
    {
        $this->attributes['store_phone'] = $value;
    }

    public function setUserIdAttribute($value): void
    {
        $this->attributes['creator_id'] = $value;
    }

    public function getUserIdAttribute()
    {
        return $this->attributes['creator_id'] ?? null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealOrderItem::class);
    }

    public function canManage(User $user): bool
    {
        return $user->isAdmin() || $user->isHr() || $this->creator_id === $user->id;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => '揪團點餐中',
            'closed' => '已結單統計中',
            'completed' => '餐點已送達',
            'cancelled' => '活動已取消',
            default => $this->status,
        };
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->items()->sum('subtotal');
    }

    public function getTotalQuantityAttribute(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function getParticipantsCountAttribute(): int
    {
        return (int) $this->items()->distinct('user_id')->count('user_id');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->cutoff_time ? Carbon::now()->isAfter($this->cutoff_time) : false;
    }
}
