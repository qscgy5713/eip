<?php

namespace Tests\Feature;

use App\Models\FormRequest;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDataValidationTest extends TestCase
{
    use RefreshDatabase;

    private function expenseFormId(): int
    {
        return \App\Models\Form::where('code', 'EXPENSE')->value('id');
    }

    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        $this->employeeUser = User::where('email', 'employee@eip.local')->first();
    }

    private function employee(): User
    {
        return $this->employeeUser;
    }

    private function submit(array $data)
    {
        return $this->actingAs($this->employee())
            ->post(route('forms.store', $this->expenseFormId()), ['title' => '報銷', 'data' => $data]);
    }

    public function test_negative_or_zero_amount_is_rejected(): void
    {
        $before = FormRequest::count();
        foreach ([-100, 0] as $amount) {
            $this->submit(['expense_type' => '辦公耗材', 'amount' => $amount])
                ->assertSessionHasErrors('data.amount');
        }
        $this->assertSame($before, FormRequest::count());
    }

    public function test_select_value_must_be_one_of_the_options(): void
    {
        $this->submit(['expense_type' => '不存在的項目', 'amount' => 100])
            ->assertSessionHasErrors('data.expense_type');
    }

    public function test_valid_expense_is_accepted(): void
    {
        $before = FormRequest::count();
        $this->submit(['expense_type' => '辦公耗材', 'amount' => 1280.5, 'invoice_no' => 'AB-12345678'])
            ->assertSessionHasNoErrors();
        $this->assertSame($before + 1, FormRequest::count());
    }

    public function test_leave_end_date_cannot_precede_start_date(): void
    {
        $leaveId = \App\Models\Form::where('code', 'LEAVE')->value('id');
        $this->actingAs($this->employee())->post(route('forms.store', $leaveId), [
            'title' => '請假',
            'data' => ['leave_type' => '事假', 'start_date' => '2026-11-05', 'end_date' => '2026-11-01', 'days' => 1],
        ])->assertSessionHasErrors('data.end_date');
    }

    public function test_reject_requires_a_comment(): void
    {
        $this->submit(['expense_type' => '辦公耗材', 'amount' => 100])->assertSessionHasNoErrors();
        $formRequest = FormRequest::where('user_id', $this->employee()->id)->latest('id')->first();
        $manager = User::where('email', 'manager@eip.local')->first();

        $this->actingAs($manager)->post(route('forms.action', $formRequest->id), ['status' => 'rejected'])
            ->assertSessionHasErrors('comment');
        $this->assertSame('pending', $formRequest->fresh()->status);

        $this->actingAs($manager)->post(route('forms.action', $formRequest->id), ['status' => 'rejected', 'comment' => '預算不足'])
            ->assertSessionHasNoErrors();
        $this->assertSame('rejected', $formRequest->fresh()->status);
    }
}
