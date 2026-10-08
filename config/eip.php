<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EIP 公司總部地理座標與圍欄設定 (Geofence Settings)
    |--------------------------------------------------------------------------
    |
    | 用於考勤打卡時比對同仁之 GPS 座標距離，判斷為辦公室內勤或外勤遠端。
    |
    */
    'geofence' => [
        'office_name' => env('EIP_OFFICE_NAME', '台北企業總部大樓'),
        'office_lat' => (float) env('EIP_OFFICE_LAT', 25.033964),
        'office_lng' => (float) env('EIP_OFFICE_LNG', 121.564468),
        'allowed_radius' => (int) env('EIP_OFFICE_RADIUS', 500), // 允許半徑（公尺）
    ],
];
