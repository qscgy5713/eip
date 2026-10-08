<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FormPrintTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $approver;
    protected User $otherUser;
    protected FormRequest $formRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EipDatabaseSeeder::class);

        $department = Department::first();

        $this->approver = User::factory()->create([
            'role' => 'manager',
            'department_id' => $department->id,
        ]);

        $this->owner = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
        ]);

        $otherDept = Department::skip(1)->first() ?? Department::create(['name' => '業務二部', 'code' => 'SALES2']);
        $this->otherUser = User::factory()->create([
            'role' => 'employee',
            'department_id' => $otherDept->id,
        ]);

        $form = Form::first();

        $this->formRequest = FormRequest::create([
            'form_id' => $form->id,
            'user_id' => $this->owner->id,
            'request_no' => 'REQ-PRINT-TEST-001',
            'title' => '列印功能驗證申請單',
            'status' => 'approved',
            'current_step' => 1,
            'data' => [
                'reason' => '存證列印測試',
                'amount' => 5000,
            ],
        ]);
    }

    public function test_owner_can_access_print_view(): void
    {
        $response = $this->actingAs($this->owner)->get(route('forms.print', $this->formRequest->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Forms/Print')
            ->where('formRequest.id', $this->formRequest->id)
            ->where('formRequest.request_no', 'REQ-PRINT-TEST-001')
            ->has('printedBy')
        );
    }

    public function test_approver_can_access_print_view(): void
    {
        $response = $this->actingAs($this->approver)->get(route('forms.print', $this->formRequest->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Forms/Print')
            ->where('formRequest.id', $this->formRequest->id)
        );
    }

    public function test_unauthorized_user_cannot_access_print_view(): void
    {
        // 他人嘗試存取他人單據列印，應被阻擋 (403 IDOR 防護)
        $response = $this->actingAs($this->otherUser)->get(route('forms.print', $this->formRequest->id));

        $response->assertStatus(403);
    }

    public function test_print_action_generates_audit_log(): void
    {
        $this->actingAs($this->owner)->get(route('forms.print', $this->formRequest->id));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'print_form_request',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_active_delegate_can_access_print_view(): void
    {
        $delegate = User::factory()->create([
            'role' => 'employee',
            'department_id' => $this->approver->department_id,
        ]);

        // 主管指派 delegate 為代理人
        Delegation::create([
            'user_id' => $this->approver->id,
            'delegate_id' => $delegate->id,
            'start_date' => now()->subDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'is_active' => true,
        ]);

        // 建立待審核主管的審核紀錄
        $this->formRequest->approvalRecords()->create([
            'approver_id' => $this->approver->id,
            'status' => 'pending',
            'step' => 1,
        ]);

        $response = $this->actingAs($delegate)->get(route('forms.print', $this->formRequest->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Forms/Print')
            ->where('formRequest.id', $this->formRequest->id)
        );
    }
}
