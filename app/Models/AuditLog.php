<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 快速記錄審計日誌輔助函式
     */
    public static function log(
        string $action,
        string $description,
        ?Model $auditable = null,
        ?array $details = null,
        ?User $user = null
    ): self {
        $currentUser = $user ?? auth()->user();
        $request = request();

        return self::create([
            'user_id' => $currentUser?->id,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr($request->userAgent() ?? '', 0, 500) : null,
            'details' => $details,
        ]);
    }
}
