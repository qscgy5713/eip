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

    public function test_non_numeric_ids_return_404_instead_of_500(): void
    {
        $user = User::factory()->create();

        foreach (['/assets/create', '/polls/create', '/tickets/abc', '/meal-orders/new'] as $url) {
            $this->actingAs($user)->get($url)->assertStatus(404);
        }
    }
}
