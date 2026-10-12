<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_code',
        'title',
        'description',
        'instructor',
        'instructor_title',
        'category',
        'location',
        'start_time',
        'end_time',
        'max_participants',
        'credits',
        'hours',
        'status',
        'creator_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'max_participants' => 'integer',
        'credits' => 'integer',
        'hours' => 'decimal:1',
    ];

    protected $appends = [
        'category_label',
        'status_label',
        'status_color',
        'registered_count',
        'waitlist_count',
        'is_full',
    ];

    /**
     * 主辦/建立同仁關聯
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * 學員報名清單關聯
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    /**
     * 生成格式化課程代碼 TC-YYYYMM-XXXX
     */
    public static function generateCourseCode(): string
    {
        $prefix = 'TC-' . Carbon::now()->format('Ym') . '-';
        $latest = static::where('course_code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->value('course_code');

        if ($latest) {
            $number = intval(substr($latest, -4)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 課程領域類別中文名稱
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'technical' => '專業技術與研發',
            'management' => '主管領導與管理',
            'compliance' => '法規遵循與資安',
            'general' => '職場通識與軟實力',
            default => '通用學習',
        };
    }

    /**
     * 課程狀態中文名稱
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => '草稿未發布',
            'upcoming' => '熱烈報名中',
            'in_progress' => '研習進行中',
            'completed' => '已完訓結案',
            'cancelled' => '已停辦取消',
            default => '未定義',
        };
    }

    /**
     * 狀態 Badge 顏色樣式
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'upcoming' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700',
            'in_progress' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700',
            'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-300 dark:border-blue-700',
            'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border border-red-300 dark:border-red-700',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * 目前已報名正取人數
     */
    public function getRegisteredCountAttribute(): int
    {
        return $this->registrations()
            ->whereIn('status', ['registered', 'attended'])
            ->count();
    }

    /**
     * 目前候補人數
     */
    public function getWaitlistCountAttribute(): int
    {
        return $this->registrations()
            ->where('status', 'waitlist')
            ->count();
    }

    /**
     * 是否已額滿
     */
    public function getIsFullAttribute(): bool
    {
        if ($this->max_participants <= 0) {
            return false;
        }

        return $this->registered_count >= $this->max_participants;
    }

    /**
     * 檢查同仁是否具備管理此課程之權限 (Admin, HR 或開課人)
     */
    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->hasRole('hr') || $this->creator_id === $user->id;
    }
}
