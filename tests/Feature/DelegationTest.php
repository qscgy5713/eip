<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\Delegation;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    public function test_user_can_view_delegations_index(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();

        $response = $this->actingAs($manager)->get('/delegations');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Delegations/Index')
            ->has('myDelegations')
            ->has('delegatedToMe')
            ->has('availableDelegates')
        );
    }

    public function test_user_can_create_delegation(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();
        $employee = User::where('email', 'employee@eip.local')->first();

        $response = $this->actingAs($manager)->post('/delegations', [
            'delegate_id' => $employee->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'reason' => '主管年度休假出國',
        ]);

        $response->assertRedirect('/delegations');
        $this->assertDatabaseHas('delegations', [
            'user_id' => $manager->id,
            'delegate_id' => $employee->id,
            'reason' => '主管年度休假出國',
            'is_active' => true,
        ]);
    }

    public function test_user_cannot_delegate_to_self(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();

        $response = $this->actingAs($manager)->post('/delegations', [
            'delegate_id' => $manager->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertSessionHasErrors(['delegate_id']);
    }

    public function test_user_can_toggle_and_delete_delegation(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();
        $employee = User::where('email', 'employee@eip.local')->first();

        $delegation = Delegation::create([
            'user_id' => $manager->id,
            'delegate_id' => $employee->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ]);

        // 切換停用
        $response = $this->actingAs($manager)->patch("/delegations/{$delegation->id}/toggle");
        $response->assertRedirect('/delegations');
        $this->assertFalse($delegation->fresh()->is_active);

        // 刪除
        $deleteResponse = $this->actingAs($manager)->delete("/delegations/{$delegation->id}");
        $deleteResponse->assertRedirect('/delegations');
        $this->assertDatabaseMissing('delegations', [
            'id' => $delegation->id,
        ]);
    }

    public function test_delegate_can_view_and_approve_delegated_form_request(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();
        $delegate = User::where('email', 'hr@eip.local')->first();
        $employee = User::where('email', 'employee@eip.local')->first();

        // 1. 主管指派 HR 為生效中代理人
        Delegation::create([
            'user_id' => $manager->id,
            'delegate_id' => $delegate->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'reason' => '出差公出代理',
            'is_active' => true,
        ]);

        // 2. 建立一筆待主管審批的申請單
        $form = Form::where('code', 'LEAVE')->first();
        $formRequest = FormRequest::create([
            'form_id' => $form->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-TEST-DELEGATE-1',
            'title' => '代理人簽核測試單',
            'data' => ['days' => 2],
            'status' => 'pending',
        ]);

        $record = ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'pending',
        ]);

        // 3. 代理人調閱單據頁面 (IDOR 保護應通過)
        $viewResponse = $this->actingAs($delegate)->get("/forms/requests/{$formRequest->id}");
        $viewResponse->assertStatus(200);

        // 4. 代理人執行代簽核准
        $actionResponse = $this->actingAs($delegate)->post("/forms/requests/{$formRequest->id}/action", [
            'status' => 'approved',
            'comment' => '代理主管核准通過',
        ]);

        $actionResponse->assertRedirect();

        // 驗證單據與紀錄狀態，且 delegated_from_id 被正確註記為原主管
        $this->assertEquals('approved', $formRequest->fresh()->status);
        $freshRecord = $record->fresh();
        $this->assertEquals('approved', $freshRecord->status);
        $this->assertEquals($manager->id, $freshRecord->delegated_from_id);
    }

    public function test_expired_delegate_cannot_approve_form_request(): void
    {
        $manager = User::where('email', 'manager@eip.local')->first();
        $delegate = User::where('email', 'hr@eip.local')->first();
        $employee = User::where('email', 'employee@eip.local')->first();

        // 代理設定已於昨日到期
        Delegation::create([
            'user_id' => $manager->id,
            'delegate_id' => $delegate->id,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $form = Form::where('code', 'LEAVE')->first();
        $formRequest = FormRequest::create([
            'form_id' => $form->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-TEST-EXPIRED-1',
            'title' => '過期代理測試單',
            'data' => ['days' => 1],
            'status' => 'pending',
        ]);

        ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'pending',
        ]);

        // 代理已過期，無法調閱 (403 Forbidden)
        $viewResponse = $this->actingAs($delegate)->get("/forms/requests/{$formRequest->id}");
        $viewResponse->assertStatus(403);
    }
}
