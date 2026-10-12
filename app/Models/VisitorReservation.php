<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_code',
        'host_id',
        'visitor_name',
        'visitor_company',
        'visitor_email',
        'visitor_phone',
        'visit_date',
        'expected_arrival_time',
        'purpose',
        'meeting_room_id',
        'visitor_count',
        'status',
        'actual_check_in_at',
        'actual_check_out_at',
        'badge_number',
        'notes',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'actual_check_in_at' => 'datetime',
        'actual_check_out_at' => 'datetime',
        'visitor_count' => 'integer',
    ];

    protected $appends = [
        'purpose_label',
        'status_label',
        'status_color',
    ];

    /**
     * 接待員工關聯
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * 預約會議室關聯
     */
    public function meetingRoom(): BelongsTo
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    /**
     * 產生唯一的預約通行碼 (VR-YYYYMMDD-XXXX)
     */
    public static function generateCode(): string
    {
        $prefix = 'VR-' . date('Ymd') . '-';
        $latest = static::where('reservation_code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest && preg_match('/-(\d{4})$/', $latest->reservation_code, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 來訪目的中文說明
     */
    public function getPurposeLabelAttribute(): string
    {
        return match ($this->purpose) {
            'business' => '商務洽談',
            'interview' => '人才面試',
            'audit' => '稽核查檢',
            'vendor' => '協力廠商/工程',
            default => '其他事由',
        };
    }

    /**
     * 狀態中文說明
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'expected' => '預約來訪中',
            'checked_in' => '已簽到會談中',
            'checked_out' => '已離廠簽退',
            'cancelled' => '已取消預約',
            default => '未知狀態',
        };
    }

    /**
     * 狀態徽章色彩
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'expected' => 'blue',
            'checked_in' => 'emerald',
            'checked_out' => 'gray',
            'cancelled' => 'rose',
            default => 'gray',
        };
    }

    /**
     * 檢查同仁是否具備管理此預約之權限
     */
    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return (bool) ($user->is_admin || $user->id === $this->host_id);
    }
}
