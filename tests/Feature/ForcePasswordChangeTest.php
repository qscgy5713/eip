<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    private function user(string $role = 'employee'): User
    {
        return User::where('email', "$role@eip.local")->firstOrFail();
    }

    public function test_admin_created_user_must_change_password(): void
    {
        $this->actingAs($this->user('admin'))->post(route('org-management.users.store'), [
            'name' => '新同仁', 'email' => 'new@eip.local', 'role' => 'employee', 'password' => 'Init1234',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'new@eip.local')->first()->must_change_password);
    }

    public function test_admin_reset_password_forces_change(): void
    {
        $employee = $this->user();
        $this->actingAs($this->user('hr'))->post(route('org-management.users.reset-password', $employee), [
            'password' => 'Reset1234', 'password_confirmation' => 'Reset1234',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($employee->fresh()->must_change_password);
    }

    public function test_flagged_user_is_redirected_to_change_page_everywhere(): void
    {
        $employee = $this->user();
        $employee->forceFill(['must_change_password' => true])->save();

        foreach (['/dashboard', '/forms', '/payslips', '/announcements'] as $url) {
            $this->actingAs($employee)->get($url)->assertRedirect(route('password.force'));
        }
        $this->actingAs($employee)->post('/forms/create/1', [])->assertRedirect(route('password.force'));

        $this->actingAs($employee)->get(route('password.force'))->assertOk();
    }

    public function test_changing_password_clears_flag_and_unlocks_system(): void
    {
        $employee = $this->user();
        $employee->forceFill(['must_change_password' => true])->save();

        $this->actingAs($employee)->post(route('password.force.update'), [
            'current_password' => 'password', 'password' => 'Brandnew123', 'password_confirmation' => 'Brandnew123',
        ])->assertRedirect(route('dashboard'));

        $employee->refresh();
        $this->assertFalse($employee->must_change_password);
        $this->assertTrue(Hash::check('Brandnew123', $employee->password));
        $this->assertNotNull($employee->password_changed_at);
        $this->actingAs($employee)->get('/dashboard')->assertOk();
        $this->actingAs($employee)->get(route('password.force'))->assertRedirect(route('dashboard'));
    }

    public function test_new_password_must_differ_and_be_strong(): void
    {
        $employee = $this->user();
        $employee->forceFill(['must_change_password' => true])->save();

        $this->actingAs($employee)->post(route('password.force.update'), [
            'current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
        $this->actingAs($employee)->post(route('password.force.update'), [
            'current_password' => 'password', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh',
        ])->assertSessionHasErrors('password');
        $this->assertTrue($employee->fresh()->must_change_password);
    }

    public function test_flagged_user_can_still_log_out(): void
    {
        $employee = $this->user();
        $employee->forceFill(['must_change_password' => true])->save();

        $this->actingAs($employee)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
