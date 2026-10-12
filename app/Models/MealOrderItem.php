<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealOrderItem extends Model
{
    use \App\Models\Concerns\SerializesLocalDates;
    use HasFactory;

    protected $fillable = [
        'meal_order_id',
        'user_id',
        'item_name',
        'specification',
        'options',
        'unit_price',
        'quantity',
        'subtotal',
        'notes',
        'payment_status',
        'paid_at',
    ];

    protected static function booted()
    {
        static::saving(function (MealOrderItem $item) {
            $quantity = max(1, (int) ($item->quantity ?: 1));
            $unitPrice = (float) ($item->unit_price ?: 0);
            $item->quantity = $quantity;
            $item->subtotal = round($unitPrice * $quantity, 2);
        });
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'quantity' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected $appends = ['payment_status_label', 'options'];

    public function getOptionsAttribute(): ?string
    {
        return $this->attributes['specification'] ?? null;
    }

    public function setOptionsAttribute($value): void
    {
        $this->attributes['specification'] = $value;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MealOrder::class, 'meal_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => '已完成付款',
            'unpaid' => '尚未付款',
            default => $this->payment_status,
        };
    }
}
