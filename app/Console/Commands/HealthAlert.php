<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SystemHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * 監控告警:發現背景服務停擺、磁碟將滿、備份過期、有失敗任務時,直接寄信給系統管理員。
 *
 * 刻意「不經過佇列」直接寄(佇列壞掉時才需要警報),並且不依賴 Laravel scheduler(它也可能是壞掉的那個):
 * 請用主機的 cron 定時執行,例如每 5 分鐘:
 *   *\/5 * * * * docker exec eip-app php artisan eip:health-alert
 *
 * 同一組問題 6 小時內只通知一次;問題全部消失時會寄一封「已恢復」。
 */
class HealthAlert extends Command
{
    protected $signature = 'eip:health-alert {--dry-run : 只列出問題,不寄信}';

    protected $description = '檢查系統健康,有問題時寄信通知系統管理員';

    private const STATE_KEY = 'eip:health_alert_state';

    private const REPEAT_HOURS = 6;

    public function handle(SystemHealth $health): int
    {
        $problems = $this->problems($health);

        foreach ($problems as $p) {
            $this->line("- {$p}");
        }
        if (! $problems) {
            $this->info('一切正常');
        }
        if ($this->option('dry-run')) {
            return $problems ? self::FAILURE : self::SUCCESS;
        }

        $state = Cache::get(self::STATE_KEY);
        $signature = md5(implode('|', $problems));

        if ($problems) {
            $repeat = ! $state || $state['signature'] !== $signature || now()->timestamp - $state['sent_at'] >= self::REPEAT_HOURS * 3600;
            if ($repeat && $this->notify('【EIP 警報】系統異常 ' . count($problems) . ' 項', "發現以下問題:\n\n- " . implode("\n- ", $problems) . "\n\n請到「系統管理 → 系統健康」查看詳情。")) {
                Cache::forever(self::STATE_KEY, ['signature' => $signature, 'sent_at' => now()->timestamp]);
            }
        } elseif ($state) {
            if ($this->notify('【EIP 恢復】系統已恢復正常', '先前的異常已全部排除。')) {
                Cache::forget(self::STATE_KEY);
            }
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, string> */
    private function problems(SystemHealth $health): array
    {
        $out = [];

        if (! $health->database()['ok']) {
            $out[] = '資料庫無法連線';
        }
        $queue = $health->heartbeat('eip:queue_heartbeat');
        if (! $queue['ok']) {
            $out[] = 'queue worker 沒有心跳(通知與信件不會寄出)' . ($queue['last_seen'] ? ",最後一次 {$queue['last_seen']}" : '');
        }
        $sched = $health->heartbeat('eip:scheduler_heartbeat');
        if (! $sched['ok']) {
            $out[] = 'scheduler 沒有心跳(逾期提醒等排程不會執行)' . ($sched['last_seen'] ? ",最後一次 {$sched['last_seen']}" : '');
        }

        $failed = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        if ($failed > 0) {
            $out[] = "有 {$failed} 筆失敗任務(可能是寄信失敗)";
        }

        $disk = $health->disk();
        if ($disk['used_percent'] >= 85) {
            $out[] = "磁碟已使用 {$disk['used_percent']}%(剩 {$disk['free_gb']} GB)";
        }

        $backup = $health->latestBackup();
        if ($backup['found'] && $backup['hours_ago'] > 48) {
            $out[] = "最近一次備份是 {$backup['hours_ago']} 小時前";
        }

        return $out;
    }

    private function notify(string $subject, string $body): bool
    {
        $emails = User::where('role', 'admin')->where('status', 'active')->pluck('email')->all();
        if (! $emails) {
            $this->warn('沒有在職管理員可通知');

            return false;
        }

        try {
            Mail::raw($body . "\n\n— " . config('app.name') . ' (' . config('app.url') . ')', fn ($m) => $m->to($emails)->subject($subject));
        } catch (\Throwable $e) {
            $this->error('寄信失敗:' . $e->getMessage());

            return false;
        }

        return true;
    }
}
