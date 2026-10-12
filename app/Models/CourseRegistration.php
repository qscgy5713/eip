<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_course_id',
        'user_id',
        'status',
        'registered_at',
        'attended_at',
        'rating',
        'feedback',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'attended_at' => 'datetime',
        'rating' => 'integer',
    ];

    protected $appends = [
        'status_label',
        'status_color',
    ];

    /**
     * 所屬內訓課程關聯
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /**
     * 報名學員關聯
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 報名狀態中文顯示
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'registered' => '正取報名',
            'waitlist' => '候補中',
            'attended' => '完訓合格 (已獲學分)',
            'absent' => '未出席',
            'cancelled' => '已取消報名',
            default => '未知',
        };
    }

    /**
     * 狀態 Badge 顏色
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'registered' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700',
            'waitlist' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-300 dark:border-amber-700',
            'attended' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-300 dark:border-blue-700',
            'absent' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border border-red-300 dark:border-red-700',
            'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
