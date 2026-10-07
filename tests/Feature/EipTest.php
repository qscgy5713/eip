<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }
    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::where('email', 'admin@eip.local')->first() ?? User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_user_can_view_announcements_and_mark_as_read(): void
    {
        $user = User::where('email', 'employee@eip.local')->first() ?? User::factory()->create();
        $announcement = Announcement::first();

        $response = $this->actingAs($user)->get('/announcements');
        $response->assertStatus(200);

        if ($announcement) {
            $showResponse = $this->actingAs($user)->get("/announcements/{$announcement->id}");
            $showResponse->assertStatus(200);
            $this->assertTrue($announcement->fresh()->isReadBy($user));
        }
    }

    public function test_user_can_submit_form_request(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();
        $form = Form::where('code', 'LEAVE')->first();

        if ($employee && $form) {
            $response = $this->actingAs($employee)->post("/forms/create/{$form->id}", [
                'title' => '測試特休申請',
                'data' => [
                    'leave_type' => '特休假',
                    'start_date' => '2026-10-15',
                    'end_date' => '2026-10-15',
                    'days' => 1,
                    'reason' => '個人事由',
                ],
            ]);

            $response->assertRedirect();
            $this->assertDatabaseHas('form_requests', [
                'title' => '測試特休申請',
                'user_id' => $employee->id,
                'status' => 'pending',
            ]);
        }
    }

    public function test_manager_can_approve_form_request(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();
        $formRequest = FormRequest::where('status', 'pending')->first();

        if ($manager && $formRequest) {
            // 確保審批人包含此經理
            $formRequest->approvalRecords()->firstOrCreate([
                'step' => 1,
                'approver_id' => $manager->id,
                'status' => 'pending',
            ]);

            $response = $this->actingAs($manager)->post("/forms/requests/{$formRequest->id}/action", [
                'status' => 'approved',
                'comment' => '准假，祝假期愉快！',
            ]);

            $response->assertRedirect();
            $this->assertEquals('approved', $formRequest->fresh()->status);
        }
    }

    public function test_user_can_access_directory(): void
    {
        $user = User::where('email', 'employee@eip.local')->first() ?? User::factory()->create();

        $response = $this->actingAs($user)->get('/directory');
        $response->assertStatus(200);
    }
}
