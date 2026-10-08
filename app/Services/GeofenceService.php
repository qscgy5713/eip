<?php

namespace App\Services;

class GeofenceService
{
    /**
     * 獲取公司地理圍欄設定
     */
    public function getOfficeConfig(): array
    {
        return [
            'name' => config('eip.geofence.office_name', '台北企業總部大樓'),
            'lat' => (float) config('eip.geofence.office_lat', 25.033964),
            'lng' => (float) config('eip.geofence.office_lng', 121.564468),
            'radius' => (int) config('eip.geofence.allowed_radius', 500),
        ];
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

        if ($lat === null || $lng === null || $lat == 0 || $lng == 0) {
            return [
                'type' => 'unverified',
                'distance' => null,
                'location_desc' => '辦公室網段 (無 GPS 定位)',
                'is_in_fence' => false,
            ];
        }

        $distanceMeters = $this->calculateDistance(
            $lat,
            $lng,
            $office['lat'],
            $office['lng']
        );

        $isInFence = $distanceMeters <= $office['radius'];
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
