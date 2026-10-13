<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 全路由資安掃描:
 *  1. 未登入者不能打到任何需要登入的路由(GET/POST/PUT/PATCH/DELETE 都一樣)
 *  2. 一般員工不能打到管理員/人資專用路由
 * 新增路由時如果忘了加授權,這裡會抓到。
 */
class SecuritySweepTest extends TestCase
{
    use RefreshDatabase;

    /** 不需要登入的公開路由(白名單,新增要審慎) */
    private const PUBLIC = [
        'login', 'password.request', 'password.email', 'password.reset', 'password.store',
        'pwa.manifest', 'branding.asset', 'two-factor.challenge', 'two-factor.challenge.store',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    /** @return array<int, array{0: string, 1: string, 2: ?string}> [method, uri, name] */
    private function routes(): array
    {
        $out = [];
        foreach (Route::getRoutes() as $route) {
            /** @var LaravelRoute $route */
            $uri = $route->uri();
            if (str_starts_with($uri, '_') || str_starts_with($uri, 'up') || str_starts_with($uri, 'storage/') || $uri === 'sanctum/csrf-cookie') {
                continue;
            }
            foreach ($route->methods() as $m) {
                if ($m === 'HEAD') {
                    continue;
                }
                // 路由參數用「1」代入(模型不存在時,授權/認證中介層仍會先於 404 運作)
                $filled = preg_replace('/\{[^}]+\}/', '1', $uri);
                $out[] = [$m, '/' . ltrim($filled, '/'), $route->getName()];
            }
        }

        return $out;
    }

    private function isPublic(string $uri, ?string $name): bool
    {
        if ($name && (in_array($name, self::PUBLIC, true) || str_starts_with($name, 'password.') || str_starts_with($name, 'two-factor.challenge'))) {
            return true;
        }

        return in_array($uri, ['/', '/login', '/manifest.webmanifest', '/forgot-password', '/two-factor-challenge'], true)
            || str_starts_with($uri, '/reset-password');
    }

    public function test_guests_cannot_reach_any_authenticated_route(): void
    {
        $leaks = [];
        foreach ($this->routes() as [$method, $uri, $name]) {
            if ($this->isPublic($uri, $name)) {
                continue;
            }
            $res = $this->call($method, $uri);
            // 未登入應該被導去登入頁(302)或 401/419(CSRF);200/403/404/500 都代表沒被認證擋下來
            if (! in_array($res->getStatusCode(), [302, 401, 419], true) || ($res->getStatusCode() === 302 && ! str_contains((string) $res->headers->get('Location'), 'login'))) {
                $leaks[] = "{$method} {$uri} ({$name}) => {$res->getStatusCode()}";
            }
        }

        $this->assertSame([], $leaks, "未登入者可到達的路由:\n" . implode("\n", $leaks));
    }

    public function test_no_route_returns_server_error_for_employee(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();
        $errors = [];
        foreach ($this->routes() as [$method, $uri, $name]) {
            if ($method !== 'GET' || $uri === '/logout') {
                continue;
            }
            // 同仁搜尋用 PostgreSQL 的 ilike,測試用的 SQLite 不支援,已知限制
            $res = $this->actingAs($employee)->call($method, $uri);
            if ($res->getStatusCode() >= 500 && ! str_contains($uri, 'search')) {
                $errors[] = "{$uri} ({$name}) => {$res->getStatusCode()}";
            }
        }

        $this->assertSame([], $errors, "回 5xx 的路由(可能是未處理的輸入或資訊洩漏):\n" . implode("\n", $errors));
    }

    public function test_employee_is_denied_on_admin_and_hr_only_areas(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();
        $adminOnly = [
            ['GET', '/system-health'], ['POST', '/system-health/retry-all'], ['GET', '/audit-logs'], ['GET', '/webhooks'],
            ['GET', '/org-management'], ['POST', '/org-management/users'], ['GET', '/attendance/settings'], ['POST', '/attendance/settings'],
            ['GET', '/branding'], ['POST', '/holidays'], ['POST', '/holidays/seed-fixed'], ['DELETE', '/holidays/1'],
        ];
        $notDenied = [];
        foreach ($adminOnly as [$method, $uri]) {
            $res = $this->actingAs($employee)->call($method, $uri);
            if (! in_array($res->getStatusCode(), [403, 404], true)) {
                $notDenied[] = "{$method} {$uri} => {$res->getStatusCode()}";
            }
        }

        $this->assertSame([], $notDenied, "員工不該進得去的管理路由:\n" . implode("\n", $notDenied));
    }
}
