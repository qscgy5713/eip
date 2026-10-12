<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
    }

    private function user(string $role): User
    {
        return User::where('email', "$role@eip.local")->firstOrFail();
    }

    public function test_workday_count_skips_weekends_holidays_and_includes_make_up_workdays(): void
    {
        $svc = app(WorkdayService::class);

        // 2026-10-05(一)~10-11(日):純週間 5 天
        $this->assertSame(5, $svc->countWorkdays(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-11')));
        // 單日:週六 → 0;週一 → 1
        $this->assertSame(0, $svc->countWorkdays(Carbon::parse('2026-10-10'), Carbon::parse('2026-10-10')));
        $this->assertSame(1, $svc->countWorkdays(Carbon::parse('2026-10-12'), Carbon::parse('2026-10-12')));

        Holiday::create(['date' => '2026-10-07', 'name' => '測試放假', 'type' => 'holiday']);   // 週三放假
        Holiday::create(['date' => '2026-10-10', 'name' => '測試補班', 'type' => 'workday']);   // 週六補班
        $this->assertSame(5, $svc->countWorkdays(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-11')), '4 天 + 1 天補班');
        $this->assertSame(1, $svc->countWorkdays(Carbon::parse('2026-10-10'), Carbon::parse('2026-10-10')));
        $this->assertSame(0, $svc->countWorkdays(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07')));
    }

    public function test_holiday_on_weekend_does_not_double_subtract(): void
    {
        Holiday::create(['date' => '2026-10-10', 'name' => '國慶日', 'type' => 'holiday']); // 週六
        $this->assertSame(5, app(WorkdayService::class)->countWorkdays(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-11')));
    }

    public function test_reversed_and_overlong_ranges_are_safe(): void
    {
        $svc = app(WorkdayService::class);
        $this->assertSame(0, $svc->countWorkdays(Carbon::parse('2026-10-10'), Carbon::parse('2026-10-01')));
        $this->assertLessThanOrEqual(262, $svc->countWorkdays(Carbon::parse('2026-01-01'), Carbon::parse('2036-01-01')));
    }

    public function test_workdays_endpoint(): void
    {
        $emp = $this->user('employee');
        $this->actingAs($emp)->getJson(route('holidays.workdays', ['start' => '2026-10-05', 'end' => '2026-10-09']))
            ->assertOk()->assertJson(['days' => 5]);
        $this->actingAs($emp)->getJson(route('holidays.workdays', ['start' => 'abc', 'end' => '2026-10-09']))->assertStatus(422);
        $this->actingAs($emp)->getJson(route('holidays.workdays'))->assertStatus(422);
    }

    public function test_everyone_can_view_but_only_hr_and_admin_can_manage(): void
    {
        foreach (['employee', 'manager', 'hr', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get(route('holidays.index'))->assertOk()
                ->assertInertia(fn ($p) => $p->where('canManage', in_array($role, ['hr', 'admin'], true)));
        }

        foreach (['employee', 'manager'] as $role) {
            $u = $this->user($role);
            $this->actingAs($u)->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'x', 'type' => 'holiday'])->assertForbidden();
            $this->actingAs($u)->post(route('holidays.seed-fixed'), ['year' => 2026])->assertForbidden();
        }
        $h = Holiday::create(['date' => '2026-12-26', 'name' => 'y', 'type' => 'holiday']);
        $this->actingAs($this->user('employee'))->delete(route('holidays.destroy', $h))->assertForbidden();
        $this->assertDatabaseHas('holidays', ['id' => $h->id]);
    }

    public function test_hr_can_add_and_delete_with_validation_and_audit(): void
    {
        $hr = $this->user('hr');

        $this->actingAs($hr)->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => '行憲紀念日', 'type' => 'holiday'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('holidays', ['date' => '2026-12-25', 'name' => '行憲紀念日']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create_holiday']);

        $this->actingAs($hr)->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => '重複', 'type' => 'holiday'])->assertSessionHasErrors('date');
        $this->actingAs($hr)->post(route('holidays.store'), ['date' => 'bad', 'name' => '', 'type' => 'party'])->assertSessionHasErrors(['date', 'name', 'type']);

        $id = Holiday::where('date', '2026-12-25')->value('id');
        $this->actingAs($hr)->delete(route('holidays.destroy', $id))->assertSessionHas('success');
        $this->assertDatabaseMissing('holidays', ['id' => $id]);
        $this->actingAs($hr)->delete('/holidays/abc')->assertNotFound();
    }

    public function test_seed_fixed_holidays_is_idempotent_and_keeps_existing(): void
    {
        $hr = $this->user('hr');
        Holiday::create(['date' => '2027-05-01', 'name' => '自訂名稱', 'type' => 'holiday']);

        $this->actingAs($hr)->post(route('holidays.seed-fixed'), ['year' => 2027])->assertSessionHas('success');
        $this->actingAs($hr)->post(route('holidays.seed-fixed'), ['year' => 2027]);

        $this->assertSame(5, Holiday::whereYear('date', 2027)->count());
        $this->assertSame('自訂名稱', Holiday::where('date', '2027-05-01')->value('name'));
        $this->actingAs($hr)->post(route('holidays.seed-fixed'), ['year' => 1800])->assertSessionHasErrors('year');
    }

    public function test_calendar_receives_holidays_in_range(): void
    {
        Holiday::create(['date' => '2026-10-10', 'name' => '國慶日', 'type' => 'holiday']);
        Holiday::create(['date' => '2027-03-01', 'name' => '範圍外', 'type' => 'holiday']);

        $this->actingAs($this->user('employee'))->get(route('calendar.index', ['month' => '2026-10']))
            ->assertInertia(fn ($p) => $p->has('holidays', 1)->where('holidays.0.name', '國慶日'));
    }

    public function test_index_handles_garbage_year(): void
    {
        $this->actingAs($this->user('hr'))->get(route('holidays.index', ['year' => '99999']))->assertOk()
            ->assertInertia(fn ($p) => $p->where('year', (int) now()->year));
    }
}
