<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultilevelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $employee;
    protected User $hrUser;
    protected Form $leaveForm;
    protected Form $expenseForm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $this->admin = User::where('role', 'admin')->first();
        $this->manager = User::where('role', 'manager')->first();
        $this->employee = User::where('role', 'employee')->first();
        $this->hrUser = User::where('role', 'hr')->first();

        $this->leaveForm = Form::where('code', 'LEAVE')->first();
        $this->expenseForm = Form::where('code', 'EXPENSE')->first();
    }

    public function test_short_leave_request_uses_single_stage_workflow(): void
    {
        // 請假 2 天 (<= 3 天)，僅需主管 1 關審核
        $response = $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '私人事假 2 天',
            'data' => [
                'leave_type' => '事假',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-11',
                'days' => 2,
                'reason' => '家中有事處理',
            ],
        ]);

        $response->assertRedirect();
        $formRequest = FormRequest::latest('id')->first();
        $this->assertEquals(1, $formRequest->total_steps);
        $this->assertEquals(1, $formRequest->current_step);
        $this->assertEquals('pending', $formRequest->status);

        // 主管核准
        $actionResponse = $this->actingAs($this->manager)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '准假',
        ]);

        $actionResponse->assertRedirect();
        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);
        $this->assertEquals(1, $formRequest->approvalRecords()->count());
    }

    public function test_long_leave_request_triggers_two_stage_workflow(): void
    {
        // 請假 5 天 (> 3 天)，自動觸發二級審核 (直屬主管 -> 人資主管)
        $response = $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '出國旅遊特休 5 天',
            'data' => [
                'leave_type' => '特休假',
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-20',
                'days' => 5,
                'reason' => '年度特休旅遊',
            ],
        ]);

        $response->assertRedirect();
        $formRequest = FormRequest::latest('id')->first();
        $this->assertEquals(2, $formRequest->total_steps);
        $this->assertEquals(1, $formRequest->current_step);
        $this->assertEquals('pending', $formRequest->status);

        // 關卡 1：直屬主管初審
        $this->actingAs($this->manager)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '主管初審同意，轉交人資主管',
        ]);

        $formRequest->refresh();
        // 應流轉至第 2 關，且單據狀態依然為 pending (未結案)
        $this->assertEquals(2, $formRequest->current_step);
        $this->assertEquals('pending', $formRequest->status);
        $this->assertEquals(2, $formRequest->approvalRecords()->count());

        $secondRecord = $formRequest->approvalRecords()->where('step', 2)->first();
        $this->assertEquals('pending', $secondRecord->status);
        $this->assertEquals($this->hrUser->id, $secondRecord->approver_id);
        $this->assertEquals('人資主管複核', $secondRecord->step_title);

        // 關卡 2：人資主管複核
        $this->actingAs($this->hrUser)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '人資核對假勤無誤，准予核銷',
        ]);

        $formRequest->refresh();
        // 所有關卡皆完成，單據正式結案核准
        $this->assertEquals('approved', $formRequest->status);
        $this->assertEquals(2, $formRequest->approvalRecords()->where('status', 'approved')->count());
    }

    public function test_high_amount_expense_triggers_multi_stage_workflow_and_prevents_out_of_order_approval(): void
    {
        // 報銷 15,000 元 (>= 10,000 元)，二級審核 (主管 -> 財務)
        $this->actingAs($this->employee)->post(route('forms.store', $this->expenseForm->id), [
            'title' => '伺服器硬體擴充採購報銷',
            'data' => [
                'expense_type' => '辦公耗材',
                'amount' => 15000,
                'invoice_no' => 'AB-12345678',
                'description' => '更換記憶體條',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();
        $this->assertEquals(2, $formRequest->total_steps);
        $this->assertEquals(1, $formRequest->current_step);

        // 當處於第 1 關時，第 1 關主管核准
        $this->actingAs($this->manager)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '工程部核准採購',
        ]);

        $formRequest->refresh();
        $this->assertEquals(2, $formRequest->current_step);

        // 第 2 關由財務長/管理員核准
        $this->actingAs($this->admin)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '發票金額符合規範，同意撥款',
        ]);

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);
    }

    public function test_rejection_at_any_stage_terminates_workflow(): void
    {
        // 長假 5 天，但在第 1 關主管即駁回
        $this->actingAs($this->employee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '專案上線期間請長假',
            'data' => [
                'leave_type' => '事假',
                'days' => 5,
                'reason' => '想出遊',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();
        $this->assertEquals(2, $formRequest->total_steps);

        // 主管直接駁回
        $this->actingAs($this->manager)->post(route('forms.action', $formRequest->id), [
            'status' => 'rejected',
            'comment' => '專案衝刺期間人力不足，請協調延後',
        ]);

        $formRequest->refresh();
        $this->assertEquals('rejected', $formRequest->status);
        // 後續關卡不應被建立
        $this->assertEquals(1, $formRequest->approvalRecords()->count());
        $this->assertEquals('rejected', $formRequest->approvalRecords()->first()->status);
    }

    public function test_custom_form_with_three_stage_workflow(): void
    {
        // 建立自訂三級表單
        $this->actingAs($this->admin)->post(route('forms.templates.store'), [
            'name' => '固定資產報廢申請單',
            'code' => 'ASSET_SCRAP',
            'description' => '電腦設備報廢折舊評估',
            'fields_schema' => [
                ['key' => 'asset_id', 'label' => '資產編號', 'type' => 'text'],
                ['key' => 'scrap_reason', 'label' => '報廢原因', 'type' => 'textarea'],
            ],
            'workflow_config' => [
                ['step' => 1, 'title' => '部門主管初審', 'role' => 'manager', 'description' => '主管確認資產現況'],
                ['step' => 2, 'title' => '總務行政查核', 'role' => 'hr', 'description' => '行政盤點殘值'],
                ['step' => 3, 'title' => '執行長核決', 'role' => 'admin', 'description' => '管理決行'],
            ],
        ]);

        $scrapForm = Form::where('code', 'ASSET_SCRAP')->first();
        $this->assertNotNull($scrapForm);
        $this->assertCount(3, $scrapForm->workflow_config);

        // 同仁發起申請
        $this->actingAs($this->employee)->post(route('forms.store', $scrapForm->id), [
            'title' => '舊型筆電毀損報廢',
            'data' => [
                'asset_id' => 'PC-2020-009',
                'scrap_reason' => '主機板受潮故障無法維修',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();
        $this->assertEquals(3, $formRequest->total_steps);
        $this->assertEquals(1, $formRequest->current_step);

        // 第 1 關 主管核准
        $this->actingAs($this->manager)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '確認無法修復',
        ]);

        $formRequest->refresh();
        $this->assertEquals(2, $formRequest->current_step);
        $this->assertEquals('pending', $formRequest->status);

        // 第 2 關 人資/行政核准
        $this->actingAs($this->hrUser)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '殘值歸零，已自盤點冊銷案',
        ]);

        $formRequest->refresh();
        $this->assertEquals(3, $formRequest->current_step);
        $this->assertEquals('pending', $formRequest->status);

        // 第 3 關 執行長核決
        $this->actingAs($this->admin)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '准予除帳',
        ]);

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);
        $this->assertEquals(3, $formRequest->approvalRecords()->where('status', 'approved')->count());
    }

    public function test_delegation_works_in_multilevel_workflow(): void
    {
        // 主管設定陳同仁為代理人
        Delegation::create([
            'user_id' => $this->manager->id,
            'delegate_id' => $this->employee->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
            'reason' => '主管出差公幹',
        ]);

        // 另一位同仁或系統同仁送出申請
        $otherEmployee = User::factory()->create([
            'name' => '林研發',
            'role' => 'employee',
            'department_id' => $this->manager->department_id,
        ]);

        $this->actingAs($otherEmployee)->post(route('forms.store', $this->leaveForm->id), [
            'title' => '同仁婚假 2 天',
            'data' => [
                'leave_type' => '婚喪假',
                'days' => 2,
                'reason' => '結婚登記',
            ],
        ]);

        $formRequest = FormRequest::latest('id')->first();

        // 代理人（陳同仁）進行代審
        $this->actingAs($this->employee)->post(route('forms.action', $formRequest->id), [
            'status' => 'approved',
            'comment' => '代主管核准祝新婚愉快',
        ]);

        $formRequest->refresh();
        $this->assertEquals('approved', $formRequest->status);

        $record = $formRequest->approvalRecords()->first();
        $this->assertEquals($this->manager->id, $record->delegated_from_id);
    }
}
