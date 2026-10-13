<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/** 系統健康檢查(系統健康頁與 eip:health-alert 共用) */
class SystemHealth
{
    /** worker / scheduler 超過這個秒數沒有心跳就視為停擺 */
    public const STALE_SECONDS = 180;

    public function database(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'detail' => config('database.default')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => '無法連線'];
        }
    }

    /** @return array{ok: bool, last_seen: ?string, seconds_ago: ?int} */
    public function heartbeat(string $key): array
    {
        $ts = Cache::get($key);
        if (! $ts) {
            return ['ok' => false, 'last_seen' => null, 'seconds_ago' => null];
        }
        $ago = max(0, now()->timestamp - (int) $ts);

        return ['ok' => $ago <= self::STALE_SECONDS, 'last_seen' => date('Y-m-d H:i:s', (int) $ts), 'seconds_ago' => $ago];
    }

    public function pendingCount(): ?int
    {
        try {
            return Queue::size();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{found: bool, file: ?string, at: ?string, hours_ago: ?int} */
    public function latestBackup(): array
    {
        $files = glob(base_path('backups/eip_db_*.sql.gz')) ?: [];
        if (! $files) {
            return ['found' => false, 'file' => null, 'at' => null, 'hours_ago' => null];
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $mtime = filemtime($files[0]);

        return [
            'found' => true,
            'file' => basename($files[0]),
            'at' => date('Y-m-d H:i:s', $mtime),
            'hours_ago' => (int) floor((time() - $mtime) / 3600),
        ];
    }

    /** @return array{free_gb: float, total_gb: float, used_percent: int} */
    public function disk(): array
    {
        $free = (float) @disk_free_space(base_path());
        $total = (float) @disk_total_space(base_path());

        return [
            'free_gb' => round($free / 1073741824, 1),
            'total_gb' => round($total / 1073741824, 1),
            'used_percent' => $total > 0 ? (int) round((1 - $free / $total) * 100) : 0,
        ];
    }
}
