<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    public function test_user_cannot_view_other_users_form_request_idor_protection(): void
    {
        // 建立另一名其他部門的同仁
        $stranger = User::factory()->create([
            'role' => 'employee',
            'department_id' => null,
        ]);

        $employee = User::where('email', 'employee@eip.local')->first();
        $formRequest = FormRequest::where('user_id', $employee->id)->first();

        // 陌生同仁嘗試直接以 URL 讀取陳同仁的申請單
        $response = $this->actingAs($stranger)->get("/forms/requests/{$formRequest->id}");
        $response->assertStatus(403);
    }

    public function test_owner_can_view_own_form_request(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();
        $formRequest = FormRequest::where('user_id', $employee->id)->first();

        $response = $this->actingAs($employee)->get("/forms/requests/{$formRequest->id}");
        $response->assertStatus(200);
    }

    public function test_manager_and_admin_can_view_subordinate_form_request(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();
        $admin = User::where('email', 'admin@eip.local')->first();
        $employee = User::where('email', 'employee@eip.local')->first();
        $formRequest = FormRequest::where('user_id', $employee->id)->first();

        // 部門主管可閱覽
        $responseManager = $this->actingAs($manager)->get("/forms/requests/{$formRequest->id}");
        $responseManager->assertStatus(200);

        // 系統管理員全局可閱覽
        $responseAdmin = $this->actingAs($admin)->get("/forms/requests/{$formRequest->id}");
        $responseAdmin->assertStatus(200);
    }

    public function test_regular_user_cannot_view_unpublished_draft_announcement(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();
        $admin = User::where('email', 'admin@eip.local')->first();

        $draft = Announcement::create([
            'title' => '未公開機密草稿公告',
            'content' => '這是未正式發布的內部草案。',
            'category' => 'hr',
            'priority' => 'urgent',
            'status' => 'draft',
            'author_id' => $admin->id,
        ]);

        // 一般同仁存取草稿應回傳 404
        $response = $this->actingAs($employee)->get("/announcements/{$draft->id}");
        $response->assertStatus(404);

        // 管理員存取草稿應可順利預覽
        $adminResponse = $this->actingAs($admin)->get("/announcements/{$draft->id}");
        $adminResponse->assertStatus(200);
    }

    public function test_regular_user_cannot_modify_meeting_rooms(): void
    {
        $employee = User::where('email', 'employee@eip.local')->first();
        $room = MeetingRoom::first();

        // 嘗試新增會議室
        $createResponse = $this->actingAs($employee)->post('/meeting-rooms', [
            'name' => '同仁私設會議室',
            'location' => '頂樓陽台',
            'capacity' => 10,
        ]);
        $createResponse->assertStatus(403);

        // 嘗試修改會議室
        $updateResponse = $this->actingAs($employee)->put("/meeting-rooms/{$room->id}", [
            'name' => '竄改會議室名稱',
            'location' => 'B1',
            'capacity' => 20,
        ]);
        $updateResponse->assertStatus(403);
    }

    public function test_user_cannot_cancel_others_booking(): void
    {
        $stranger = User::factory()->create(['role' => 'employee']);
        $employee = User::where('email', 'employee@eip.local')->first();
        $booking = RoomBooking::where('user_id', $employee->id)->where('status', 'confirmed')->first();

        if ($booking) {
            $response = $this->actingAs($stranger)->post("/meeting-rooms/bookings/{$booking->id}/cancel");
            $response->assertStatus(403);
        }
    }
}
