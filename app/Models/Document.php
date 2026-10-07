<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'description',
        'department_id',
        'uploader_id',
        'current_version',
        'download_count',
        'restricted_roles',
    ];

    protected $casts = [
        'restricted_roles' => 'array',
        'current_version' => 'integer',
        'download_count' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version_number');
    }

    /**
     * 檢查指定同仁是否有權閱覽或下載此文件
     */
    public function canAccess(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (empty($this->restricted_roles)) {
            return true;
        }

        return in_array($user->role, $this->restricted_roles);
    }
}
