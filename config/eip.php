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

    /*
    |--------------------------------------------------------------------------
    | 密碼政策
    |--------------------------------------------------------------------------
    |
    | password_max_age_days:密碼最多使用幾天,超過後下次操作會被導向「更換密碼」;0 = 不啟用。
    | password_history:新密碼不可與目前及最近幾組用過的密碼相同;0 = 不檢查。
    |
    */
    'password_max_age_days' => (int) env('EIP_PASSWORD_MAX_AGE_DAYS', 0),
    'password_history' => (int) env('EIP_PASSWORD_HISTORY', 5),

    /*
    |--------------------------------------------------------------------------
    | Email 通知
    |--------------------------------------------------------------------------
    |
    | 站內通知同時以 Email 寄出(進佇列,需執行 queue worker)。可用 EIP_MAIL_NOTIFICATIONS=false
    | 全站關閉;同仁也可在「個人帳號設定」自行關閉。實際寄送請設定 MAIL_MAILER 等 SMTP 參數。
    |
    */
    'mail_notifications' => (bool) env('EIP_MAIL_NOTIFICATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | 強制雙因素驗證 (2FA) 的角色
    |--------------------------------------------------------------------------
    |
    | 以逗號分隔,例如 EIP_REQUIRE_2FA_ROLES=admin,hr 。這些角色尚未啟用 2FA 時,
    | 只能進入「個人帳號設定」完成設定,其他功能皆會被導回。預設為空(不強制)。
    |
    */
    'require_2fa_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('EIP_REQUIRE_2FA_ROLES', ''))))),
];
