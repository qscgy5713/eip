<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalHubTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $manager2;
    protected User $employee;
    protected User $hrUser;
    protected Form $leaveForm;
    protected Form $expenseForm;
    protected Department $rdDept;
    protected Department $salesDept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $this->admin = User::where('role', 'admin')->first();
        $this->manager = User::where('role', 'manager')->first();
        $this->employee = User::where('role', 'employee')->first();
        $this->hrUser = User::where('role', 'hr')->first();

        // 建立第二個部門與主管，用於測試跨主管隔絕
        $this->salesDept = Department::create(['name' => '業務部', 'code' => 'SALES']);
        $this->manager2 = User::factory()->create([
            'name' => '業務主管',
            'role' => 'manager',
            'department_id' => $this->salesDept->id,
            'email' => 'sales_mgr@example.com',
        ]);

        $this->leaveForm = Form::where('code', 'LEAVE')->first();
        $this->expenseForm = Form::where('code', 'EXPENSE')->first();

        // 清理 Seeder 預先產生的示範表單申請與審批記錄，確保各測試案例資料隔離與精準計數
        ApprovalRecord::query()->delete();
        FormRequest::query()->delete();
    }

    public function test_guest_cannot_access_approval_hub(): void
    {
        $response = $this->get(route('approvals.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_manager_can_access_approval_hub_and_see_assigned_records(): void
    {
        // 員工提交請假單
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '事假 1 天',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
                'days' => 1,
                'reason' => '處理私事',
            ],
        ]);

        $response = $this->actingAs($this->manager)->get(route('approvals.index'));
        $response->assertOk();
        $response->assertInertia(fn($page) => $page
            ->component('Approvals/Index')
            ->has('pendingRecords.data', 1)
            ->where('stats.total_pending', 1)
            ->where('canViewAllCompany', false)
        );
    }

    public function test_manager_does_not_see_other_manager_records(): void
    {
        // 員工申請單據（指派給 manager）
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '研發部請假單',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
                'days' => 1,
                'reason' => '處理私事',
            ],
        ]);

        // 業務主管登入查看審批中心，應為 0 筆
        $response = $this->actingAs($this->manager2)->get(route('approvals.index'));
        $response->assertOk();
        $response->assertInertia(fn($page) => $page
            ->component('Approvals/Index')
            ->has('pendingRecords.data', 0)
            ->where('stats.total_pending', 0)
        );
    }

    public function test_delegation_allows_substitute_to_see_delegated_pending_records(): void
    {
        // 員工申請請假單（指派給 manager）
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '主管待審假單',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
                'days' => 1,
                'reason' => '處理私事',
            ],
        ]);

        // manager 設定 manager2 為代理人（目前生效）
        Delegation::create([
            'user_id' => $this->manager->id,
            'delegate_id' => $this->manager2->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'reason' => '公出差旅',
            'is_active' => true,
        ]);

        // manager2 登入查看審批中心，應能看到該筆單據且標註為代理
        $response = $this->actingAs($this->manager2)->get(route('approvals.index'));
        $response->assertOk();
        $response->assertInertia(fn($page) => $page
            ->component('Approvals/Index')
            ->has('pendingRecords.data', 1)
            ->where('pendingRecords.data.0.is_delegated', true)
            ->where('stats.delegated_count', 1)
        );
    }

    public function test_admin_can_toggle_all_company_view(): void
    {
        // 員工申請請假單
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '一般請假單',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
                'days' => 1,
                'reason' => '事由說明',
            ],
        ]);

        // Admin 預設看個人
        $responseDefault = $this->actingAs($this->admin)->get(route('approvals.index'));
        $responseDefault->assertOk();
        $responseDefault->assertInertia(fn($page) => $page
            ->component('Approvals/Index')
            ->where('canViewAllCompany', true)
            ->where('viewAllCompany', false)
        );

        // Admin 切換全公司視角 ?all_company=1
        $responseAll = $this->actingAs($this->admin)->get(route('approvals.index', ['all_company' => '1']));
        $responseAll->assertOk();
        $responseAll->assertInertia(fn($page) => $page
            ->component('Approvals/Index')
            ->where('viewAllCompany', true)
            ->has('pendingRecords.data', 1)
        );
    }

    public function test_batch_approve_action_approves_multiple_requests(): void
    {
        // 員工提交兩筆請假單 (1 天，均為主管 1 關)
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '假單 A',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
                'days' => 1,
                'reason' => '事假 A',
            ],
        ]);

        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '假單 B',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-16',
                'end_date' => '2026-10-16',
                'days' => 1,
                'reason' => '事假 B',
            ],
        ]);

        $pendingRecords = ApprovalRecord::where('approver_id', $this->manager->id)
            ->where('status', 'pending')
            ->get();
        $this->assertCount(2, $pendingRecords);

        // 主管執行一鍵批次核准
        $response = $this->actingAs($this->manager)->post(route('approvals.batchAction'), [
            'record_ids' => $pendingRecords->pluck('id')->toArray(),
            'action' => 'approved',
            'comment' => '全數准假，請交接妥當',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 驗證單據狀態均已轉為 approved
        foreach ($pendingRecords as $rec) {
            $rec->refresh();
            $this->assertEquals('approved', $rec->status);
            $this->assertEquals('全數准假，請交接妥當', $rec->comment);
            $this->assertEquals('approved', $rec->formRequest->status);
        }

        // 驗證 AuditLog 寫入
        $log = AuditLog::where('action', 'batch_approved_form_requests')->first();
        $this->assertNotNull($log);
        $this->assertEquals(2, $log->details['success_count']);
    }

    public function test_batch_reject_action_rejects_and_releases_leave_balance(): void
    {
        // 先為員工初始化特休額度 (10 天)
        LeaveBalance::create([
            'user_id' => $this->employee->id,
            'year' => 2026,
            'leave_type' => 'annual',
            'allocated_days' => 10,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        // 員工請特休 2 天
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '年假 2 天',
            'data' => [
                'leave_type' => '特別休假',
                'start_date' => '2026-10-20',
                'end_date' => '2026-10-21',
                'days' => 2,
                'reason' => '年度特休旅遊',
            ],
        ]);

        $balance = LeaveBalance::where('user_id', $this->employee->id)->where('leave_type', 'annual')->first();
        // 申請中預扣 2 天 pending_days
        $this->assertEquals(2, $balance->pending_days);
        $this->assertEquals(8, $balance->remaining_days);

        $pendingRecord = ApprovalRecord::where('approver_id', $this->manager->id)
            ->where('status', 'pending')
            ->first();

        // 主管執行批次退件駁回
        $response = $this->actingAs($this->manager)->post(route('approvals.batchAction'), [
            'record_ids' => [$pendingRecord->id],
            'action' => 'rejected',
            'comment' => '當週人力吃緊，請協調改期',
        ]);

        $response->assertRedirect();
        $pendingRecord->refresh();
        $this->assertEquals('rejected', $pendingRecord->status);
        $this->assertEquals('rejected', $pendingRecord->formRequest->status);

        // 驗證特休額度已釋放返還
        $balance->refresh();
        $this->assertEquals(0, $balance->pending_days);
        $this->assertEquals(0, $balance->used_days);
        $this->assertEquals(10, $balance->remaining_days);
    }

    public function test_batch_action_safeguards_against_unauthorized_records(): void
    {
        // 員工請假
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '研發主管單據',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
                'days' => 1,
                'reason' => '私事',
            ],
        ]);

        $pendingRecord = ApprovalRecord::where('approver_id', $this->manager->id)->first();

        // 非授權之業務主管嘗試簽核該單據
        $response = $this->actingAs($this->manager2)->post(route('approvals.batchAction'), [
            'record_ids' => [$pendingRecord->id],
            'action' => 'approved',
            'comment' => '偷渡簽核',
        ]);

        $response->assertRedirect();
        $pendingRecord->refresh();
        // 單據維持 pending，未被偷審
        $this->assertEquals('pending', $pendingRecord->status);
        $this->assertEquals('pending', $pendingRecord->formRequest->status);
    }
}
