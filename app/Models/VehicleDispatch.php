<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatch_number',
        'user_id',
        'vehicle_id',
        'approved_by',
        'start_time',
        'end_time',
        'destination',
        'purpose',
        'passenger_count',
        'start_mileage',
        'end_mileage',
        'status',
        'rejection_reason',
        'actual_start_at',
        'actual_return_at',
        'return_notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'actual_start_at' => 'datetime',
        'actual_return_at' => 'datetime',
        'passenger_count' => 'integer',
        'start_mileage' => 'integer',
        'end_mileage' => 'integer',
    ];

    protected $appends = [
        'purpose_label',
        'status_label',
        'status_color',
        'mileage_traveled',
    ];

    /**
     * 借調員工關聯
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 公務車輛關聯
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * 審核簽署人關聯
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * 自動產生唯一的派車單號 (VD-YYYYMMDD-XXXX)
     */
    public static function generateNumber(): string
    {
        $prefix = 'VD-' . date('Ymd') . '-';
        $latest = static::where('dispatch_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($latest && preg_match('/-(\d{4})$/', $latest->dispatch_number, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 用車目的中文標籤
     */
    public function getPurposeLabelAttribute(): string
    {
        return match ($this->purpose) {
            'client_visit' => '客戶拜訪 / 業務洽談',
            'airport_pickup' => '機場高鐵 / 貴賓接送',
            'vendor_meeting' => '協力廠商 / 外包訪查',
            'official_errand' => '機關公務 / 送件洽公',
            default => '其他外勤出差事由',
        };
    }

    /**
     * 狀態中文標籤
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => '待審批簽核',
            'approved' => '已核准 (待出車)',
            'active' => '出勤行駛中',
            'completed' => '已完成還車',
            'rejected' => '已駁回申請',
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
            'pending' => 'amber',
            'approved' => 'blue',
            'active' => 'emerald',
            'completed' => 'gray',
            'rejected' => 'rose',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * 計算本次出差行駛里程數 (km)
     */
    public function getMileageTraveledAttribute(): ?int
    {
        if ($this->start_mileage !== null && $this->end_mileage !== null && $this->end_mileage >= $this->start_mileage) {
            return $this->end_mileage - $this->start_mileage;
        }

        return null;
    }

    /**
     * 檢查權限是否可管理此派車單
     */
    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return (bool) ($user->isAdmin() || $user->isManager() || $user->id === $this->user_id);
    }
}
