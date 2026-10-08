<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'last_triggered_at',
        'last_status',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * 判斷是否訂閱了某事件
     */
    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }
}
