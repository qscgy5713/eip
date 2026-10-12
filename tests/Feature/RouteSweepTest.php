<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** 各角色逐一造訪所有無參數的 GET 頁面,任何角色都不應該遇到 5xx。 */
class RouteSweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_get_page_returns_server_error_for_any_role(): void
    {
        $this->seed(EipDatabaseSeeder::class);

        $uris = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->map(fn ($r) => $r->uri())
            ->reject(fn ($u) => str_contains($u, '{') || str_starts_with($u, '_') || str_starts_with($u, 'api')
                || str_starts_with($u, 'storage') || in_array($u, ['up', 'logout', 'sanctum/csrf-cookie'], true))
            ->unique()->values();

        $this->assertGreaterThan(30, $uris->count());

        $failures = [];
        foreach (['admin', 'manager', 'hr', 'employee'] as $role) {
            $user = User::where('email', "$role@eip.local")->firstOrFail();
            foreach ($uris as $uri) {
                $status = $this->actingAs($user)->get('/'.$uri)->getStatusCode();
                if ($status >= 500) {
                    $failures[] = "$role /$uri => $status";
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /** 詳情頁(含單一 {id} 參數)以 seed 的前幾筆資料逐角色造訪,不應 5xx。 */
    public function test_detail_pages_do_not_return_server_error_for_any_role(): void
    {
        $this->seed(EipDatabaseSeeder::class);

        $uris = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($u) => preg_match('#\{[^/}]+\}#', $u) && substr_count($u, '{') === 1
                && ! str_contains($u, 'storage') && ! str_starts_with($u, '_') && ! str_starts_with($u, 'api'))
            ->unique()->values();

        $this->assertGreaterThan(10, $uris->count());

        $failures = [];
        foreach (['admin', 'manager', 'hr', 'employee'] as $role) {
            $user = User::where('email', "$role@eip.local")->firstOrFail();
            foreach ($uris as $uri) {
                foreach ([1, 2, 3] as $id) {
                    $url = '/'.preg_replace('#\{[^/}]+\??\}#', (string) $id, $uri);
                    $status = $this->actingAs($user)->get($url)->getStatusCode();
                    if ($status >= 500) {
                        $failures[] = "$role $url => $status";
                    }
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /** 所有寫入類路由以空資料/垃圾資料送出,應回驗證錯誤或 4xx,不可 5xx。 */
    public function test_write_routes_reject_empty_and_garbage_input_without_server_error(): void
    {
        $this->seed(EipDatabaseSeeder::class);

        $routes = collect(Route::getRoutes())
            ->filter(fn ($r) => array_intersect(['POST', 'PUT', 'PATCH'], $r->methods()))
            ->reject(fn ($r) => str_contains($r->uri(), 'logout') || str_starts_with($r->uri(), '_')
                || str_starts_with($r->uri(), 'api') || str_contains($r->uri(), 'sanctum')
                || in_array($r->uri(), ['login', 'forgot-password', 'reset-password', 'email/verification-notification'], true))
            ->values();

        $this->assertGreaterThan(40, $routes->count());

        $garbage = ['title' => str_repeat('長', 5000), 'name' => ['x'], 'amount' => 'abc', 'date' => 'not-a-date',
            'user_id' => 'zzz', 'status' => ['a' => 1], 'data' => 'str', 'email' => 'bad', 'id' => -1];

        $failures = [];
        foreach (['admin', 'employee'] as $role) {
            $user = User::where('email', "$role@eip.local")->firstOrFail();
            foreach ($routes as $route) {
                $method = collect($route->methods())->first(fn ($m) => in_array($m, ['POST', 'PUT', 'PATCH'], true));
                $url = '/'.preg_replace('#\{[^/}]+\??\}#', '1', $route->uri());
                foreach ([[], $garbage] as $payload) {
                    $status = $this->actingAs($user)->call($method, $url, $payload)->getStatusCode();
                    if ($status >= 500) {
                        $failures[] = "$role $method $url ".(empty($payload) ? '(empty)' : '(garbage)')." => $status";
                    }
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /** 刪除類路由對 seed 資料逐筆嘗試(含有關聯資料者),應成功或回 4xx,不可因外鍵等造成 5xx。 */
    public function test_delete_routes_do_not_return_server_error(): void
    {
        $this->seed(EipDatabaseSeeder::class);
        $admin = User::where('email', 'admin@eip.local')->firstOrFail();

        $routes = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('DELETE', $r->methods(), true))
            ->reject(fn ($r) => str_starts_with($r->uri(), '_') || str_starts_with($r->uri(), 'api'));

        $failures = [];
        foreach ($routes as $route) {
            foreach ([1, 2, 3] as $id) {
                $url = '/'.preg_replace('#\{[^/}]+\??\}#', (string) $id, $route->uri());
                $status = $this->actingAs($admin)->delete($url)->getStatusCode();
                if ($status >= 500) {
                    $failures[] = "DELETE $url => $status";
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
