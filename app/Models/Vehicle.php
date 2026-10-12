<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate_number',
        'name',
        'vehicle_type',
        'capacity',
        'fuel_type',
        'current_mileage',
        'last_maintenance_mileage',
        'status',
        'parking_location',
        'notes',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'current_mileage' => 'integer',
        'last_maintenance_mileage' => 'integer',
    ];

    protected $appends = [
        'vehicle_type_label',
        'fuel_type_label',
        'status_label',
        'status_color',
        'needs_maintenance',
    ];

    /**
     * 派車借調紀錄關聯
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(VehicleDispatch::class);
    }

    /**
     * 車型中文標籤
     */
    public function getVehicleTypeLabelAttribute(): string
    {
        return match ($this->vehicle_type) {
            'suv' => '運動休旅車 (SUV)',
            'van' => '商務多人座 (VAN)',
            'truck' => '公務貨車 (Truck)',
            default => '舒適房車 (Sedan)',
        };
    }

    /**
     * 能源類型中文標籤
     */
    public function getFuelTypeLabelAttribute(): string
    {
        return match ($this->fuel_type) {
            'hybrid' => '油電複合動力',
            'electric' => '純電電動車 (EV)',
            'diesel' => '柴油引擎',
            default => '汽油車 (95/98)',
        };
    }

    /**
     * 狀態中文標籤
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'available' => '在庫可預約',
            'in_use' => '派車出勤中',
            'maintenance' => '保養檢修中',
            default => '停用封存',
        };
    }

    /**
     * 狀態色彩徽章
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'available' => 'emerald',
            'in_use' => 'blue',
            'maintenance' => 'amber',
            default => 'gray',
        };
    }

    /**
     * 判斷是否達定期保養里程標準 (每 5,000 km)
     */
    public function getNeedsMaintenanceAttribute(): bool
    {
        return ($this->current_mileage - $this->last_maintenance_mileage) >= 5000;
    }

    /**
     * 檢查車輛在指定時段是否無重疊借用且處於可用狀態
     */
    public function isAvailableBetween($startTime, $endTime, $excludeDispatchId = null): bool
    {
        if ($this->status === 'maintenance' || $this->status === 'inactive') {
            return false;
        }

        $query = $this->dispatches()
            ->whereIn('status', ['pending', 'approved', 'active'])
            ->where(function ($q) use ($startTime, $endTime) {
                // 重疊檢查：借用開始前於結束前，且借用結束後於開始後
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            });

        if ($excludeDispatchId) {
            $query->where('id', '!=', $excludeDispatchId);
        }

        return ! $query->exists();
    }
}
