<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 補打卡核准後回寫考勤的邊界情況(以種子表單的實際選項為準)。 */
class AttendanceAmendmentEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private User $emp;
    private User $mgr;
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        FormRequest::query()->delete();
        $this->emp = User::where('email', 'employee@eip.local')->first();
        $this->mgr = User::where('email', 'manager@eip.local')->first();
        $this->form = Form::where('code', 'CLOCK_ADJUST')->first();
    }

    private function submitAndApprove(array $data, string $finalStatus = 'approved'): FormRequest
    {
        $this->actingAs($this->emp)->post(route('forms.store', $this->form->id), [
            'title' => '補打卡測試', 'data' => $data + ['reason' => '忘記打卡'],
        ])->assertSessionHasNoErrors();
        $req = FormRequest::where('user_id', $this->emp->id)->latest('id')->first();

        $this->actingAs($this->mgr)->post(route('forms.action', $req->id), ['status' => $finalStatus, 'comment' => '處理']);

        return $req->fresh();
    }

    private function att(string $date): ?Attendance
    {
        return Attendance::where('user_id', $this->emp->id)->whereDate('date', $date)->first();
    }

    public function test_seed_form_options_are_handled(): void
    {
        $options = collect($this->form->fields_schema)->firstWhere('key', 'adjust_type')['options'];
        $this->assertNotEmpty($options);

        foreach ($options as $i => $option) {
            $date = Carbon::parse('2026-09-01')->addDays($i)->toDateString();
            $this->submitAndApprove(['adjust_date' => $date, 'adjust_type' => $option, 'actual_time' => '09:00 - 18:00']);

            $a = $this->att($date);
            $this->assertNotNull($a, "選項「{$option}」核准後沒有建立考勤紀錄");
            $this->assertTrue($a->clock_in_at !== null || $a->clock_out_at !== null, "選項「{$option}」沒有寫入任何打卡時間");
        }
    }

    public function test_clock_in_only_keeps_existing_clock_out(): void
    {
        $date = '2026-09-10';
        Attendance::create(['user_id' => $this->emp->id, 'date' => $date, 'clock_out_at' => "$date 18:30:00", 'status' => 'incomplete']);

        $this->submitAndApprove(['adjust_date' => $date, 'adjust_type' => '上班卡補刷', 'actual_time' => '08:55']);

        $a = $this->att($date);
        $this->assertSame('08:55', $a->clock_in_at->format('H:i'));
        $this->assertSame('18:30', $a->clock_out_at->format('H:i'), '原本的下班時間不可被覆蓋');
        $this->assertSame('normal', $a->status);
    }

    public function test_rejected_request_does_not_touch_attendance(): void
    {
        $date = '2026-09-11';
        $this->submitAndApprove(['adjust_date' => $date, 'adjust_type' => '全日未打卡補登', 'actual_time' => '09:00 - 18:00'], 'rejected');

        $this->assertNull($this->att($date));
    }

    public function test_pending_request_does_not_touch_attendance(): void
    {
        $date = '2026-09-12';
        $this->actingAs($this->emp)->post(route('forms.store', $this->form->id), [
            'title' => '尚未核准', 'data' => ['adjust_date' => $date, 'adjust_type' => '全日未打卡補登', 'actual_time' => '09:00 - 18:00', 'reason' => 'x'],
        ]);

        $this->assertNull($this->att($date));
    }

    public function test_garbled_time_falls_back_to_defaults_instead_of_failing(): void
    {
        $date = '2026-09-14';
        $req = $this->submitAndApprove(['adjust_date' => $date, 'adjust_type' => '全日未打卡補登', 'actual_time' => '大約早上吧']);

        $this->assertSame('approved', $req->status);
        $a = $this->att($date);
        $this->assertNotNull($a);
        $this->assertSame('09:00', $a->clock_in_at->format('H:i'));
        $this->assertSame('18:00', $a->clock_out_at->format('H:i'));
    }

    public function test_attendance_page_shows_amended_record(): void
    {
        $date = '2026-09-15';
        $this->submitAndApprove(['adjust_date' => $date, 'adjust_type' => '全日未打卡補登', 'actual_time' => '09:00 - 18:00']);

        $this->actingAs($this->emp)->get(route('attendance.index', ['month' => '2026-09']))->assertOk();
        $this->assertStringContainsString('補打卡核准結案', $this->att($date)->note);
    }
}
