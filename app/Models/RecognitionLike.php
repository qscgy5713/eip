<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecognitionLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'recognition_post_id',
        'user_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(RecognitionPost::class, 'recognition_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
