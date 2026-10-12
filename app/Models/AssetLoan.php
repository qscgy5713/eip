<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'user_id',
        'borrowed_at',
        'expected_return_date',
        'returned_at',
        'status',
        'purpose',
        'return_notes',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'expected_return_date' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * 判定借用是否逾期未還
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'returned' || $this->returned_at) {
            return false;
        }

        if (! $this->expected_return_date) {
            return false;
        }

        return $this->expected_return_date->endOfDay()->isPast();
    }
}
