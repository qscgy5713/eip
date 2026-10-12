<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function ownRequest(User $applicant): array
    {
        $form = Form::where('code', 'LEAVE')->first();
        $request = FormRequest::create([
            'form_id' => $form->id,
            'user_id' => $applicant->id,
            'request_no' => 'REQ-SELF-1',
            'title' => '自審測試單',
            'data' => ['days' => 1],
            'status' => 'pending',
        ]);
        $record = ApprovalRecord::create([
            'form_request_id' => $request->id,
            'step' => 1,
            'approver_id' => $applicant->id,
            'status' => 'pending',
        ]);

        return [$request, $record];
    }

    public function test_admin_cannot_approve_own_request_when_other_approvers_exist(): void
    {
        $this->seed(EipDatabaseSeeder::class);
        $admin = User::where('email', 'admin@eip.local')->first();
        [$request, $record] = $this->ownRequest($admin);

        $this->actingAs($admin)->post("/forms/requests/{$request->id}/action", ['status' => 'approved'])
            ->assertForbidden();

        $this->assertSame('pending', $record->fresh()->status);
    }

    public function test_own_requests_are_hidden_from_approval_hub(): void
    {
        $this->seed(EipDatabaseSeeder::class);
        $admin = User::where('email', 'admin@eip.local')->first();
        $before = null;
        $this->actingAs($admin)->get('/approvals')
            ->assertInertia(function ($p) use (&$before) {
                $before = $p->toArray()['props']['stats']['total_pending'];

                return $p;
            });

        $this->ownRequest($admin);

        $this->actingAs($admin)->get('/approvals')
            ->assertInertia(fn ($p) => $p->where('stats.total_pending', $before));
    }

    public function test_sole_approver_may_still_approve_to_avoid_deadlock(): void
    {
        $this->seed(EipDatabaseSeeder::class);
        // 只留下一位管理員:把其餘主管/管理員停用
        $admin = User::where('email', 'admin@eip.local')->first();
        User::whereIn('role', ['admin', 'manager'])->where('id', '!=', $admin->id)->update(['status' => 'inactive']);
        [$request, $record] = $this->ownRequest($admin);

        $this->actingAs($admin)->post("/forms/requests/{$request->id}/action", ['status' => 'approved'])
            ->assertRedirect();

        $this->assertSame('approved', $record->fresh()->status);
    }
}
