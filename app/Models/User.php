<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'department_id', 'employee_no', 'job_title', 'role', 'phone', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function formRequests(): HasMany
    {
        return $this->hasMany(FormRequest::class);
    }

    public function approvalRecords(): HasMany
    {
        return $this->hasMany(ApprovalRecord::class, 'approver_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'uploader_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * 我指派出去的職務代理人
     */
    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class, 'user_id');
    }

    /**
     * 別人指派我為代理人的記錄
     */
    public function delegatedToMe(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegate_id');
    }

    /**
     * 檢查是否目前有權代理指定主管
     */
    public function canActAsDelegateFor(int|User $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->delegatedToMe()
            ->where('user_id', $userId)
            ->currentlyActive()
            ->exists();
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function attendedBookings(): BelongsToMany
    {
        return $this->belongsToMany(RoomBooking::class, 'room_booking_attendees')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHr(): bool
    {
        return in_array($this->role, ['admin', 'hr']);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }
}

