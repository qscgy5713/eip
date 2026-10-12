<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use \App\Models\Concerns\SerializesLocalDates;
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'owner_id',
        'department_id',
        'status',
        'priority',
        'start_date',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Check if a user can access this project.
     */
    public function canAccess(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($this->owner_id === $user->id) {
            return true;
        }

        if ($this->relationLoaded('members')) {
            if ($this->members->contains('id', $user->id)) {
                return true;
            }
        } elseif ($this->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return $this->tasks()->where('assignee_id', $user->id)->exists();
    }

    /**
     * Check if a user can manage this project.
     */
    public function canManage(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $this->owner_id === $user->id;
    }
}
