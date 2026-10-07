<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in_at',
        'clock_in_ip',
        'clock_in_location',
        'clock_out_at',
        'clock_out_ip',
        'clock_out_location',
        'status',
        'work_hours',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'work_hours' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 計算並更新工時與出勤狀態
     */
    public function calculateWorkHours(): void
    {
        if ($this->clock_in_at && $this->clock_out_at) {
            $minutes = $this->clock_in_at->diffInMinutes($this->clock_out_at);
            $this->work_hours = round($minutes / 60, 2);

            // 判定異常：早上超過 09:30 視為遲到
            $inTime = Carbon::parse($this->clock_in_at)->format('H:i');
            if ($inTime > '09:30') {
                $this->status = 'late';
            } elseif ($this->work_hours < 8.0) {
                $this->status = 'early_leave';
            } else {
                $this->status = 'normal';
            }
        }
    }
}
