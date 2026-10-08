<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasFactory;

    public const TYPE_ANNUAL = 'annual';
    public const TYPE_SICK = 'sick';
    public const TYPE_PERSONAL = 'personal';
    public const TYPE_COMPENSATORY = 'compensatory';
    public const TYPE_OFFICIAL = 'official';
    public const TYPE_MARRIAGE_FUNERAL = 'marriage_funeral';

    public const LEAVE_TYPES = [
        self::TYPE_ANNUAL => [
            'name' => '特休假',
            'is_hard_quota' => true,
            'default_allocated' => 7.0,
            'description' => '勞基法特別休假，按年資核給，具備硬性額度管制',
        ],
        self::TYPE_COMPENSATORY => [
            'name' => '補休',
            'is_hard_quota' => true,
            'default_allocated' => 0.0,
            'description' => '平日延長工時或休假日專案加班累計之時數/天數換算',
        ],
        self::TYPE_SICK => [
            'name' => '病假',
            'is_hard_quota' => false,
            'default_allocated' => 30.0,
            'description' => '法定普通傷病假年度上限（未住院 30 日，折合半薪）',
        ],
        self::TYPE_PERSONAL => [
            'name' => '事假',
            'is_hard_quota' => false,
            'default_allocated' => 14.0,
            'description' => '法定普通事假年度上限（14 日無薪）',
        ],
        self::TYPE_OFFICIAL => [
            'name' => '公假',
            'is_hard_quota' => false,
            'default_allocated' => 0.0,
            'description' => '代表公司出勤或法定兵役公假等',
        ],
        self::TYPE_MARRIAGE_FUNERAL => [
            'name' => '婚喪假',
            'is_hard_quota' => false,
            'default_allocated' => 8.0,
            'description' => '法定婚假與喪假專屬配額',
        ],
    ];

    protected $fillable = [
        'user_id',
        'year',
        'leave_type',
        'allocated_days',
        'used_days',
        'pending_days',
        'note',
    ];

    protected $casts = [
        'year' => 'integer',
        'allocated_days' => 'float',
        'used_days' => 'float',
        'pending_days' => 'float',
    ];

    protected $appends = [
        'available_days',
        'type_label',
        'is_hard_quota',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 計算剩餘可用天數 (總核給 - 已休 - 審核中扣留)
     */
    public function getAvailableDaysAttribute(): float
    {
        $avail = (float) $this->allocated_days - (float) $this->used_days - (float) $this->pending_days;
        return round(max(0, $avail), 1);
    }

    public function getRemainingDaysAttribute(): float
    {
        return $this->getAvailableDaysAttribute();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::LEAVE_TYPES[$this->leave_type]['name'] ?? $this->leave_type;
    }

    public function getIsHardQuotaAttribute(): bool
    {
        return self::LEAVE_TYPES[$this->leave_type]['is_hard_quota'] ?? false;
    }

    /**
     * 標準化假別名稱（相容中英文或簡稱輸入）
     */
    public static function normalizeType(string $input): string
    {
        $trimmed = trim($input);
        return match ($trimmed) {
            '特休假', '特休', '特別休假', 'annual', 'annual_leave' => self::TYPE_ANNUAL,
            '補休', 'compensatory', 'comp_leave' => self::TYPE_COMPENSATORY,
            '病假', '傷病假', 'sick', 'sick_leave' => self::TYPE_SICK,
            '事假', 'personal', 'personal_leave' => self::TYPE_PERSONAL,
            '公假', 'official', 'official_leave' => self::TYPE_OFFICIAL,
            '婚喪假', '婚假', '喪假', 'marriage_funeral' => self::TYPE_MARRIAGE_FUNERAL,
            default => strtolower(str_replace(' ', '_', $trimmed)),
        };
    }
}
