<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 上線前自我檢查:逐項檢查正式環境設定,列出 通過 / 警告 / 失敗。
 * 有「失敗」時結束碼為 1,可放進部署腳本擋下不安全的上線。
 */
class Preflight extends Command
{
    protected $signature = 'eip:preflight';

    protected $description = '檢查正式環境設定是否安全、完整(上線前執行)';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkApp();
        $this->checkDatabase();
        $this->checkMailAndQueue();
        $this->checkAccounts();
        $this->checkOps();

        $icons = ['ok' => '<info>通過</info>', 'warn' => '<comment>警告</comment>', 'fail' => '<error>失敗</error>'];
        foreach ($this->results as [$level, $name, $hint]) {
            $this->line(sprintf('[%s] %s%s', $icons[$level], $name, $hint !== '' ? " — {$hint}" : ''));
        }

        $count = collect($this->results)->countBy(fn ($r) => $r[0]);
        $this->newLine();
        $this->line(sprintf('通過 %d、警告 %d、失敗 %d', $count['ok'] ?? 0, $count['warn'] ?? 0, $count['fail'] ?? 0));

        return ($count['fail'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function add(string $level, string $name, string $hint = ''): void
    {
        $this->results[] = [$level, $name, $hint];
    }

    private function check(bool $ok, string $name, string $hint, string $levelIfBad = 'fail'): void
    {
        $this->add($ok ? 'ok' : $levelIfBad, $name, $ok ? '' : $hint);
    }

    private function checkApp(): void
    {
        $this->check(app()->environment('production'), 'APP_ENV=production', '目前是 ' . app()->environment());
        $this->check(! config('app.debug'), 'APP_DEBUG=false', '除錯模式會把程式碼與設定顯示給使用者');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL 使用 https', '目前是 ' . config('app.url'));
        $this->check(! empty(config('app.key')), 'APP_KEY 已設定', '請執行 php artisan key:generate');
        $this->check((bool) config('session.secure'), 'SESSION_SECURE_COOKIE=true', 'Session Cookie 應只走 HTTPS');
        $this->check(config('app.timezone') === 'Asia/Taipei', '時區為 Asia/Taipei', '目前是 ' . config('app.timezone'), 'warn');
        $this->check(config('cache.default') === 'redis' && config('queue.default') === 'redis', '快取與佇列使用 Redis', 'queue/cache 目前不是 redis', 'warn');
        $this->check(! file_exists(public_path('hot')), '沒有 Vite 開發伺服器殘留 (public/hot)', '請刪除 public/hot,否則 CSP 會被略過');
        $this->check(file_exists(public_path('build/manifest.json')), '前端已建置 (public/build)', '請執行 npm run build');
        $this->check(is_link(public_path('storage')), 'storage:link 已建立', '請執行 php artisan storage:link', 'warn');
        $this->check(in_array('admin', config('eip.require_2fa_roles'), true), '管理員強制雙因素驗證', '建議設定 EIP_REQUIRE_2FA_ROLES=admin,hr', 'warn');
        $this->check((int) config('eip.password_history') > 0, '密碼歷史(禁止重用)已啟用', 'EIP_PASSWORD_HISTORY=0', 'warn');
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->add('ok', '資料庫可連線');
        } catch (\Throwable $e) {
            $this->add('fail', '資料庫可連線', $e->getMessage());

            return;
        }

        $weak = ['eip_password', 'password', 'secret', '123456', 'root', ''];
        $this->check(! in_array((string) config('database.connections.' . config('database.default') . '.password'), $weak, true), '資料庫密碼不是預設值', '請設定強密碼');

        if (config('database.redis.default.host') !== null && config('cache.default') === 'redis') {
            $this->check(! empty(config('database.redis.default.password')), 'Redis 已設定密碼', '請設定 REDIS_PASSWORD(並讓 redis 容器啟用 requirepass)', 'warn');
        }
    }

    private function checkMailAndQueue(): void
    {
        $mailer = config('mail.default');
        $this->check(! in_array($mailer, ['log', 'array'], true), '寄信不是 log/array', "MAIL_MAILER={$mailer},通知信不會真的寄出", 'warn');
        $this->check(! empty(config('mail.from.address')) && config('mail.from.address') !== 'hello@example.com', '寄件人信箱已設定', 'MAIL_FROM_ADDRESS 是預設值', 'warn');

        $queueBeat = \Illuminate\Support\Facades\Cache::get('eip:queue_heartbeat');
        $this->check($queueBeat && now()->timestamp - (int) $queueBeat < 180, 'queue worker 運行中', '沒有偵測到心跳,寄信與通知會堆積', 'warn');
        $schedBeat = \Illuminate\Support\Facades\Cache::get('eip:scheduler_heartbeat');
        $this->check($schedBeat && now()->timestamp - (int) $schedBeat < 180, 'scheduler 運行中', '沒有偵測到心跳,逾期提醒等排程不會執行', 'warn');
    }

    private function checkAccounts(): void
    {
        $demo = User::whereIn('email', ['admin@eip.local', 'manager@eip.local', 'employee@eip.local', 'hr@eip.local'])->count();
        $this->check($demo === 0, '沒有預設測試帳號 (*@eip.local)', "仍有 {$demo} 個示範帳號,請刪除或改密碼");

        $weakHash = User::query()->where('status', 'active')->limit(500)->get(['id', 'password'])
            ->filter(fn ($u) => Hash::check('password', $u->password))->count();
        $this->check($weakHash === 0, '沒有人使用密碼「password」', "{$weakHash} 個帳號仍是預設密碼");

        $this->check(User::where('role', 'admin')->where('status', 'active')->exists(), '至少有一位在職管理員', '請執行 php artisan eip:create-admin');
    }

    private function checkOps(): void
    {
        $dir = base_path('backups');
        $latest = is_dir($dir) ? collect(glob($dir . '/*.sql.gz') ?: [])->map(fn ($f) => filemtime($f))->max() : null;
        $this->check($latest && now()->timestamp - $latest < 3 * 86400, '3 天內有資料庫備份', '找不到近期備份,請設定 cron 執行 make backup', 'warn');
        $this->check(is_writable(storage_path('logs')) && is_writable(storage_path('app')), 'storage 可寫入', '請檢查 storage 目錄權限');
    }
}
