<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_request_id',
        'step',
        'step_title',
        'approver_id',
        'delegated_from_id',
        'transferred_to_id',
        'transferred_from_id',
        'add_signed_by_id',
        'is_add_sign',
        'status',
        'comment',
        'signature',
        'actioned_at',
    ];

    protected $casts = [
        'step' => 'integer',
        'is_add_sign' => 'boolean',
        'actioned_at' => 'datetime',
    ];

    public function formRequest(): BelongsTo
    {
        return $this->belongsTo(FormRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_from_id');
    }

    public function transferredTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_to_id');
    }

    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_from_id');
    }

    public function addSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'add_signed_by_id');
    }
}
