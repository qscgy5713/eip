<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** 系統健康狀態與失敗任務(含寄信失敗)管理,僅系統管理員 */
class SystemHealthController extends Controller
{
    /** worker / scheduler 超過這個秒數沒有心跳就視為停擺 */
    private const STALE_SECONDS = 180;

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, '僅系統管理員可查看系統健康狀態。');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $failed = collect(app('queue.failer')->all())->take(50)->map(function ($f) {
            $payload = json_decode($f->payload, true) ?: [];

            return [
                'id' => $f->id,
                'job' => $payload['displayName'] ?? '(未知任務)',
                'queue' => $f->queue,
                'error' => Str::limit(Str::before((string) $f->exception, "\n"), 300),
                'failed_at' => $f->failed_at,
            ];
        })->values();

        return Inertia::render('SystemHealth/Index', [
            'checks' => [
                'database' => $this->checkDatabase(),
                'queue' => $this->heartbeat('eip:queue_heartbeat'),
                'scheduler' => $this->heartbeat('eip:scheduler_heartbeat'),
                'backup' => $this->latestBackup(),
                'disk' => $this->disk(),
            ],
            'queue' => [
                'connection' => config('queue.default'),
                'pending' => $this->pendingCount(),
                'failed_total' => DB::table(config('queue.failed.table', 'failed_jobs'))->count(),
            ],
            'mail' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.'.config('mail.default').'.host'),
                'from' => config('mail.from.address'),
                'notifications_enabled' => (bool) config('eip.mail_notifications'),
            ],
            'failedJobs' => $failed,
        ]);
    }

    public function retry(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Str::isUuid($id), 404);

        if (! app('queue.failer')->find($id)) {
            return back()->with('error', '找不到這筆失敗任務(可能已經重送或刪除)。');
        }
        try {
            Artisan::call('queue:retry', ['id' => [$id]]);
        } catch (\Throwable $e) {
            return back()->with('error', '這筆任務的內容已損毀,無法重送,請刪除它。');
        }
        AuditLog::log(action: 'retry_failed_job', description: "{$request->user()->name} 重送了失敗任務 {$id}");

        return back()->with('success', '已重新排入佇列。');
    }

    public function retryAll(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $count = count(app('queue.failer')->all());
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
        } catch (\Throwable $e) {
            return back()->with('error', '有任務內容已損毀,重送中斷。請逐筆重送,並刪除損毀的任務。');
        }
        AuditLog::log(action: 'retry_all_failed_jobs', description: "{$request->user()->name} 重送了全部失敗任務({$count} 筆)");

        return back()->with('success', "已重新排入 {$count} 筆失敗任務。");
    }

    public function forget(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Str::isUuid($id), 404);

        app('queue.failer')->forget($id);
        AuditLog::log(action: 'delete_failed_job', description: "{$request->user()->name} 刪除了失敗任務 {$id}");

        return back()->with('success', '已刪除。');
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'detail' => config('database.default')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => '無法連線'];
        }
    }

    /** @return array{ok: bool, last_seen: ?string, seconds_ago: ?int} */
    private function heartbeat(string $key): array
    {
        $ts = Cache::get($key);
        if (! $ts) {
            return ['ok' => false, 'last_seen' => null, 'seconds_ago' => null];
        }
        $ago = max(0, now()->timestamp - (int) $ts);

        return ['ok' => $ago <= self::STALE_SECONDS, 'last_seen' => date('Y-m-d H:i:s', (int) $ts), 'seconds_ago' => $ago];
    }

    private function pendingCount(): ?int
    {
        try {
            return Queue::size();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{found: bool, file: ?string, at: ?string, hours_ago: ?int} */
    private function latestBackup(): array
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
    private function disk(): array
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
