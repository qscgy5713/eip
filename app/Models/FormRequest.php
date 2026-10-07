<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'user_id',
        'request_no',
        'title',
        'data',
        'status',
        'current_step',
    ];

    protected $casts = [
        'data' => 'array',
        'current_step' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvalRecords(): HasMany
    {
        return $this->hasMany(ApprovalRecord::class)->orderBy('step');
    }
}
