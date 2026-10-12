<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password1', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['short1', 'abcdefghij', '1234567890'] as $weak) {
            $this->actingAs($user)->from('/profile')->put('/password', [
                'current_password' => 'password',
                'password' => $weak,
                'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }
    }
}
