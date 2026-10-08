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
        'clock_in_lat',
        'clock_in_lng',
        'clock_in_distance',
        'clock_in_type',
        'clock_out_at',
        'clock_out_ip',
        'clock_out_location',
        'clock_out_lat',
        'clock_out_lng',
        'clock_out_distance',
        'clock_out_type',
        'status',
        'work_hours',
        'note',
        'field_work_note',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_in_lat' => 'decimal:7',
        'clock_in_lng' => 'decimal:7',
        'clock_in_distance' => 'integer',
        'clock_out_at' => 'datetime',
        'clock_out_lat' => 'decimal:7',
        'clock_out_lng' => 'decimal:7',
        'clock_out_distance' => 'integer',
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
