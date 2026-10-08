<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'creator_id',
        'title',
        'description',
        'is_multiple_choice',
        'is_anonymous',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_multiple_choice' => 'boolean',
            'is_anonymous' => 'boolean',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function voters(): HasMany
    {
        return $this->hasMany(PollVoter::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function isClosed(): bool
    {
        if ($this->status === 'closed') {
            return true;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return true;
        }

        return false;
    }

    public function hasVoted(User $user): bool
    {
        return $this->voters()->where('user_id', $user->id)->exists();
    }
}
