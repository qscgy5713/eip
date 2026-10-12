<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_tag',
        'name',
        'category',
        'serial_number',
        'specification',
        'location',
        'status',
        'current_holder_id',
        'department_id',
        'purchase_date',
        'purchase_cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_holder_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(AssetLoan::class)->latest('id');
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(AssetLoan::class)
            ->where('status', 'borrowed')
            ->latestOfMany();
    }

    /**
     * 檢查資產是否可供借用
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * 取得分類繁中名稱
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'it_hardware' => 'IT 資訊硬體',
            'office_equipment' => '辦公 OA 設備',
            'av_media' => '視聽攝影器材',
            'vehicles' => '公務車輛',
            'peripherals' => '週邊配件耗材',
            default => '其他資產',
        };
    }

    /**
     * 取得狀態繁中名稱
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => '在庫可借',
            'in_use' => '使用中',
            'maintenance' => '維護保養中',
            'retired' => '已報廢除役',
            default => $this->status,
        };
    }
}
