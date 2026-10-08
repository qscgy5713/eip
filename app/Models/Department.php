<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'parent_id',
        'leader_id',
        'sort_order',
        'is_active',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id')->orderBy('sort_order');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * 遞迴取得所有子孫部門 ID 清單 (防範階層循環依賴)
     */
    public function getAllDescendantIds(array &$visited = []): array
    {
        if (in_array($this->id, $visited, true)) {
            return [];
        }
        $visited[] = $this->id;

        $descendants = [];
        $children = $this->children()->get();

        foreach ($children as $child) {
            $descendants[] = $child->id;
            $descendants = array_merge($descendants, $child->getAllDescendantIds($visited));
        }

        return array_values(array_unique($descendants));
    }

    /**
     * 檢查指定部門是否為此部門之子孫部門
     */
    public function isDescendantOf(Department|int $parent): bool
    {
        $parentId = $parent instanceof Department ? $parent->id : $parent;
        $current = $this->parent;
        $visited = [];

        while ($current) {
            if ($current->id === $parentId) {
                return true;
            }
            if (in_array($current->id, $visited, true)) {
                break;
            }
            $visited[] = $current->id;
            $current = $current->parent;
        }

        return false;
    }

    /**
     * 計算包含此部門及所有轄下子孫部門之總在職人數 (全體子樹編制)
     */
    public function getTotalHeadcount(): int
    {
        $allDeptIds = array_merge([$this->id], $this->getAllDescendantIds());
        return User::whereIn('department_id', $allDeptIds)
            ->where('status', 'active')
            ->count();
    }
}
