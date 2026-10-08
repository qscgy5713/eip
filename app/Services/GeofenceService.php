<?php

namespace App\Services;

use App\Models\SystemSetting;

class GeofenceService
{
    /**
     * 獲取公司地理圍欄設定（優先讀取資料庫動態設定，次之讀取 config）
     */
    public function getOfficeConfig(): array
    {
        $rawLat = SystemSetting::get('geofence_office_lat');
        $rawLng = SystemSetting::get('geofence_office_lng');
        $rawRadius = SystemSetting::get('geofence_allowed_radius');

        // 若資料庫有值（若為空字串或'0'則視為未設定）
        $lat = $rawLat !== null ? ($rawLat === '' || $rawLat === '0' ? null : (float) $rawLat) : (float) config('eip.geofence.office_lat', 25.033964);
        $lng = $rawLng !== null ? ($rawLng === '' || $rawLng === '0' ? null : (float) $rawLng) : (float) config('eip.geofence.office_lng', 121.564468);
        $radius = $rawRadius !== null ? ($rawRadius === '' || $rawRadius === '0' ? null : (int) $rawRadius) : (int) config('eip.geofence.allowed_radius', 500);

        $hasLocation = !empty($lat) && !empty($lng);

        return [
            'name' => SystemSetting::get('geofence_office_name', config('eip.geofence.office_name', '台北企業總部大樓')),
            'address' => SystemSetting::get('geofence_office_address', '台北市信義區信義路五段7號'),
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
            'has_location' => $hasLocation,
        ];
    }

    /**
     * 更新公司地理圍欄設定
     */
    public function updateOfficeConfig(array $data): array
    {
        if (array_key_exists('office_name', $data)) {
            SystemSetting::set('geofence_office_name', trim($data['office_name'] ?? ''), '公司總部/辦公室名稱');
        }
        if (array_key_exists('office_address', $data)) {
            SystemSetting::set('geofence_office_address', trim($data['office_address'] ?? ''), '公司詳細地址');
        }
        if (array_key_exists('office_lat', $data)) {
            SystemSetting::set('geofence_office_lat', !empty($data['office_lat']) ? (string) $data['office_lat'] : '', '公司緯度');
        }
        if (array_key_exists('office_lng', $data)) {
            SystemSetting::set('geofence_office_lng', !empty($data['office_lng']) ? (string) $data['office_lng'] : '', '公司經度');
        }
        if (array_key_exists('allowed_radius', $data)) {
            SystemSetting::set('geofence_allowed_radius', !empty($data['allowed_radius']) ? (string) $data['allowed_radius'] : '', '允許打卡半徑（公尺）');
        }

        return $this->getOfficeConfig();
    }

    /**
     * 計算同仁 GPS 座標與公司總部的距離，並判定打卡型態
     *
     * @param float|null $lat
     * @param float|null $lng
     * @return array
     */
    public function evaluateLocation(?float $lat, ?float $lng): array
    {
        $office = $this->getOfficeConfig();

        // 若公司未設定公司位置：無需設定距離，全數判定為遠端打卡，無需強制事由
        if (!$office['has_location']) {
            return [
                'type' => 'remote',
                'distance' => null,
                'location_desc' => '遠端辦公 (公司未設定固定位置)',
                'is_in_fence' => true,
                'requires_reason' => false,
            ];
        }

        if ($lat === null || $lng === null || $lat == 0 || $lng == 0) {
            return [
                'type' => 'unverified',
                'distance' => null,
                'location_desc' => '辦公室網段 (無 GPS 定位)',
                'is_in_fence' => false,
                'requires_reason' => false,
            ];
        }

        $distanceMeters = $this->calculateDistance(
            $lat,
            $lng,
            $office['lat'],
            $office['lng']
        );

        $isInFence = !empty($office['radius']) && $distanceMeters <= $office['radius'];
        $type = $isInFence ? 'office' : 'remote';

        $distanceText = $this->formatDistance($distanceMeters);
        $desc = $isInFence
            ? "{$office['name']} 圍欄內 ({$distanceText})"
            : "外勤/遠端 (距總部 {$distanceText})";

        return [
            'type' => $type,
            'distance' => $distanceMeters,
            'location_desc' => $desc,
            'is_in_fence' => $isInFence,
            'requires_reason' => !$isInFence,
        ];
    }

    /**
     * 使用 Haversine 大圓距離公式計算兩組經緯度之間的公尺距離
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000; // 地球半徑 (公尺)

        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }

    /**
     * 友善格式化距離顯示 (公尺 / 公里)
     */
    public function formatDistance(int $meters): string
    {
        if ($meters < 1000) {
            return "{$meters}m";
        }

        $km = round($meters / 1000, 1);
        return "{$km}km";
    }
}
