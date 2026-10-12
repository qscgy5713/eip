<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'priority',
        'assignee_id',
        'creator_id',
        'due_date',
        'estimated_hours',
        'actual_hours',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'estimated_hours' => 'decimal:1',
            'actual_hours' => 'decimal:1',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'done') {
            return false;
        }

        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->endOfDay()->isPast();
    }
}
