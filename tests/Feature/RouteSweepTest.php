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
}
