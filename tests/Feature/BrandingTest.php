<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_default_branding_is_shared_with_every_page(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertInertia(fn (Assert $p) => $p
                ->where('branding.name', Branding::DEFAULT_NAME)
                ->where('branding.logo_url', null)
                ->where('branding.favicon_url', null));
    }

    public function test_only_admin_can_open_or_update_branding(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)->get('/branding')->assertForbidden();
        $this->actingAs($employee)->post('/branding', ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->admin())->get('/branding')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Branding/Edit'));
    }

    public function test_admin_can_change_name_logo_and_favicon_then_reset(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/branding', [
            'name' => '星河科技',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'favicon' => UploadedFile::fake()->image('fav.png', 64, 64),
        ])->assertRedirect('/branding');

        $this->assertSame('星河科技', Branding::name());
        $this->assertNotNull(Branding::url('logo'));
        $this->assertNotNull(Branding::url('favicon'));

        // 檔案可公開讀取(登入頁也會用到)
        auth()->logout();
        $this->get('/branding/logo')->assertOk();
        $this->get('/branding/favicon')->assertOk();
        $this->get('/login')->assertOk()->assertSee('星河科技', false)
            ->assertSee('property="og:image" content="'.Branding::url('logo').'"', false);

        $this->actingAs($admin)->post('/branding', [
            'name' => '星河科技',
            'remove_logo' => 1,
            'remove_favicon' => 1,
        ])->assertRedirect('/branding');

        $this->assertNull(Branding::url('logo'));
        $this->assertNull(Branding::url('favicon'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'update_branding']);
    }

    public function test_rejects_non_image_and_svg_uploads(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/branding', [
            'name' => 'X',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            'favicon' => UploadedFile::fake()->create('fav.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['logo', 'favicon']);

        $this->assertNull(SystemSetting::find('brand_logo_path'));
    }

    public function test_unknown_asset_kind_is_404(): void
    {
        $this->get('/branding/other')->assertNotFound();
        $this->get('/branding/logo')->assertNotFound();
    }
}
