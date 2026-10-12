<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecognitionPost extends Model
{
    use \App\Models\Concerns\SerializesLocalDates;
    use HasFactory;

    public const CORE_VALUES = [
        'teamwork' => '團隊協同',
        'excellence' => '追求卓越',
        'helpfulness' => '熱心助人',
        'innovation' => '創新突破',
        'customer_first' => '客戶導向',
    ];

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'points',
        'core_value',
        'message',
        'is_anonymous',
        'likes_count',
    ];

    protected $casts = [
        'points' => 'integer',
        'is_anonymous' => 'boolean',
        'likes_count' => 'integer',
    ];

    protected $appends = [
        'core_value_label',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(RecognitionLike::class);
    }

    public function getCoreValueLabelAttribute(): string
    {
        return self::CORE_VALUES[$this->core_value] ?? $this->core_value;
    }
}
