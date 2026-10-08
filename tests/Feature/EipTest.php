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

    public function test_manager_can_create_custom_form_template(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();

        $response = $this->actingAs($manager)->post('/forms/templates', [
            'name' => '資訊硬體採購單',
            'code' => 'IT_BUY',
            'description' => '部門筆電與伺服器採購申請',
            'fields_schema' => [
                [
                    'key' => 'item_name',
                    'label' => '品項名稱',
                    'type' => 'text',
                ],
                [
                    'key' => 'estimated_cost',
                    'label' => '預估金額',
                    'type' => 'number',
                ],
                [
                    'key' => 'urgency',
                    'label' => '緊急程度',
                    'type' => 'select',
                    'options' => ['一般', '緊急', '特急件'],
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('forms', [
            'name' => '資訊硬體採購單',
            'code' => 'IT_BUY',
            'is_active' => true,
        ]);
    }

    public function test_regular_employee_cannot_create_form_template(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();

        $response = $this->actingAs($employee)->post('/forms/templates', [
            'name' => '同仁私設表單',
            'code' => 'HACK_FORM',
            'description' => '無權限建立',
            'fields_schema' => [
                ['key' => 'reason', 'label' => '事由', 'type' => 'text'],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('forms', [
            'code' => 'HACK_FORM',
        ]);
    }

    public function test_admin_can_delete_or_deactivate_form_template(): void
    {
        $admin = User::where('email', 'admin@eip.local')->first();

        // 1. 測試刪除尚無單據的範本 -> 直接刪除
        $emptyForm = Form::create([
            'name' => '暫存測試單',
            'code' => 'TEMP_TEST',
            'fields_schema' => [['key' => 'note', 'label' => '備註', 'type' => 'text']],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/forms/templates/{$emptyForm->id}");
        $response->assertRedirect();
        $this->assertDatabaseMissing('forms', ['id' => $emptyForm->id]);

        // 2. 測試停用已有單據的範本 -> 軟性停用 is_active = false
        $leaveForm = Form::where('code', 'LEAVE')->first();
        if ($leaveForm) {
            $response = $this->actingAs($admin)->delete("/forms/templates/{$leaveForm->id}");
            $response->assertRedirect();
            $this->assertDatabaseHas('forms', [
                'id' => $leaveForm->id,
                'is_active' => false,
            ]);
        }
    }
}
