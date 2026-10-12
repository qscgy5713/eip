<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'assigned_to',
        'category',
        'priority',
        'status',
        'title',
        'description',
        'location',
        'attachments',
        'resolved_at',
        'closed_at',
        'satisfaction_rating',
        'satisfaction_feedback',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'satisfaction_rating' => 'integer',
        ];
    }

    protected $appends = [
        'status_label',
        'category_label',
        'priority_label',
        'resolution_duration_hours',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * 判定同仁是否有權管理工單 (IT專員或管理者)
     */
    public function canManage(User $user): bool
    {
        return $user->isAdmin() || $user->isHr() || $this->assigned_to === $user->id;
    }

    /**
     * 判定同仁是否有權檢視工單
     */
    public function canView(User $user): bool
    {
        return $this->user_id === $user->id || $this->canManage($user);
    }

    /**
     * 自動產生工單唯一流水編號：TK-YYYYMMDD-XXXX
     */
    public static function generateTicketNumber(): string
    {
        $prefix = 'TK-' . date('Ymd') . '-';
        $latest = static::where('ticket_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $seq = (int) substr($latest->ticket_number, -4) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => '待處理',
            'in_progress' => '處理中',
            'pending_user' => '待同仁確認',
            'resolved' => '已完修',
            'closed' => '已結案',
            default => $this->status,
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'it_hardware' => '電腦硬體',
            'it_software' => '軟體與帳號',
            'it_network' => '網路與通訊',
            'facility' => '水電與環境設施',
            'stationery' => '行政與耗材',
            'other' => '其他叫修',
            default => $this->category,
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => '特急件',
            'high' => '高優先',
            'normal' => '一般',
            'low' => '低優先',
            default => $this->priority,
        };
    }

    /**
     * 計算完修耗時 (小時數，保留一位小數)
     */
    public function getResolutionDurationHoursAttribute(): ?float
    {
        if (! $this->resolved_at) {
            return null;
        }

        return round($this->created_at->diffInMinutes($this->resolved_at) / 60, 1);
    }
}
