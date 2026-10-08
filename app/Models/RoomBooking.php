<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoomBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_room_id',
        'user_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'attendees_count',
        'equipment_needed',
        'status',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'attendees_count' => 'integer',
        'equipment_needed' => 'array',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_booking_attendees')
            ->withTimestamps();
    }

    /**
     * 檢查特定會議室在指定時間段內是否存在有效預約衝突
     */
    public static function hasConflict(int $roomId, $startTime, $endTime, ?int $ignoreBookingId = null): bool
    {
        $query = static::where('meeting_room_id', $roomId)
            ->where('status', 'confirmed')
            ->where(function ($q) use ($startTime, $endTime) {
                // 區間有交集：既有開始時間早於新結束時間，且既有結束時間晚於新開始時間
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            });

        if ($ignoreBookingId) {
            $query->where('id', '!=', $ignoreBookingId);
        }

        return $query->exists();
    }
}
