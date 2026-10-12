<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyReport extends Model
{
    use \App\Models\Concerns\SerializesLocalDates;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'year',
        'week_number',
        'start_date',
        'end_date',
        'summary',
        'achievements',
        'plans_next_week',
        'challenges_or_support',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'submitted_at' => 'datetime',
            'year' => 'integer',
            'week_number' => 'integer',
        ];
    }

    protected $appends = ['status_label'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WeeklyReportComment::class);
    }

    /**
     * 判定使用者是否有權調閱此週報
     */
    public function canAccess(User $user): bool
    {
        if ($user->isAdmin() || $user->isHr()) {
            return true;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        // 部門主管可查閱所轄部門成員之週報
        if ($user->isManager() && $this->department_id && $user->department_id === $this->department_id) {
            return true;
        }

        return false;
    }

    /**
     * 判定使用者是否可編輯此週報
     */
    public function canEdit(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->user_id === $user->id) {
            return $this->status !== 'reviewed';
        }

        return false;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => '草稿中',
            'submitted' => '已提交',
            'reviewed' => '主管已點評',
            default => $this->status,
        };
    }
}
