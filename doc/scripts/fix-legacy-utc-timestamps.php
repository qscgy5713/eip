<?php
/*
 * 一次性資料修正:2026-10-12 時區由 UTC 改為 Asia/Taipei 之前寫入的「系統產生時間」,
 * 實際儲存的是 UTC,畫面上會比實際早 8 小時。本腳本把這些時間 +8 小時。
 *
 * 切換點:2026-10-12 06:47 (UTC 儲存值)。小於此值者為舊資料(審計日誌 #101 06:45 → #102 14:48 為證)。
 * 不處理(原本就是台北本地時間的 seed 寫死值):出勤打卡、會議室預約、培訓課程、派車起迄。
 * 另外處理兩筆「相對 seed 當下時間(UTC)」的未來時間:投票截止、訂餐截止。
 *
 * 【已於 2026-10-12 執行過,請勿重複執行,否則會多加 8 小時】
 * 只能執行一次!執行前請先備份:
 *   docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' > eip_backup.sql
 * 執行:
 *   docker compose cp doc/scripts/fix-legacy-utc-timestamps.php app:/tmp/fix.php
 *   docker compose exec -T app php artisan tinker --execute="require '/tmp/fix.php';"
 */

use Illuminate\Support\Facades\DB;

$cut = '2026-10-12 06:47:00';
$literal = [
    'attendances' => ['clock_in_at', 'clock_out_at'],
    'room_bookings' => ['start_time', 'end_time'],
    'training_courses' => ['start_time', 'end_time'],
    'vehicle_dispatches' => ['start_time', 'end_time'],
    'polls' => ['ends_at'],
    'meal_orders' => ['cutoff_time'],
];

$cols = DB::select("select table_name, column_name from information_schema.columns where table_schema = current_schema() and data_type like 'timestamp%'");
$report = [];

DB::transaction(function () use ($cols, $cut, $literal, &$report) {
    foreach ($cols as $c) {
        $t = $c->table_name;
        $k = $c->column_name;
        if ($t === 'migrations' || (isset($literal[$t]) && in_array($k, $literal[$t], true))) {
            continue;
        }
        $n = DB::update("update \"$t\" set \"$k\" = \"$k\" + interval '8 hours' where \"$k\" is not null and \"$k\" < ?", [$cut]);
        if ($n) {
            $report[] = "$t.$k:$n";
        }
    }

    $report[] = 'polls.ends_at:'.DB::update("update polls set ends_at = ends_at + interval '8 hours' where ends_at::time between '05:00' and '06:00'");
    $report[] = 'meal_orders.cutoff_time:'.DB::update("update meal_orders set cutoff_time = cutoff_time + interval '8 hours' where cutoff_time::time between '10:00' and '11:00'");
});

echo implode("\n", $report)."\n";
