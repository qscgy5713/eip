<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_url_renders_inertia_error_page_with_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/this-page-does-not-exist')
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404));
    }

    public function test_json_requests_still_receive_json_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/this-page-does-not-exist')
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }
}
