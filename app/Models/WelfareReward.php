<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WelfareReward extends Model
{
    use \App\Models\Concerns\SerializesLocalDates;
    use HasFactory;

    public const CATEGORIES = [
        'gift_card' => '便利禮券',
        'beverage' => '咖啡飲品',
        'leisure' => '休閒育樂',
        'leave_credit' => '榮譽休假',
    ];

    protected $fillable = [
        'name',
        'description',
        'points_cost',
        'stock',
        'category',
        'is_active',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'category_label',
        'is_sold_out',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(WelfareRedemption::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? (string) $this->category;
    }

    /**
     * 庫存 -1 代表無限量；0 代表已兌換完畢
     */
    public function getIsSoldOutAttribute(): bool
    {
        return $this->stock === 0;
    }
}
