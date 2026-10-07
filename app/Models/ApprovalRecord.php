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
        'approver_id',
        'status',
        'comment',
        'actioned_at',
    ];

    protected $casts = [
        'step' => 'integer',
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
}
