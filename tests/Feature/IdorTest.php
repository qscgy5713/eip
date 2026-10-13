<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\FormRequest;
use App\Models\Payslip;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 橫向越權(IDOR):一般同仁不能碰別人的單據、薪資、通知與私人設定 */
class IdorTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        // 沒有主管/部門關係的普通同仁
        $this->attacker = User::factory()->create(['role' => 'employee', 'email' => 'attacker@corp.com', 'status' => 'active', 'must_change_password' => false]);
    }

    private function victimForm(): FormRequest
    {
        return FormRequest::where('user_id', '!=', $this->attacker->id)->firstOrFail();
    }

    public function test_cannot_view_or_print_someone_elses_form_request(): void
    {
        $f = $this->victimForm();

        $this->actingAs($this->attacker)->get(route('forms.show', $f))->assertForbidden();
        $this->actingAs($this->attacker)->get(route('forms.print', $f))->assertForbidden();
        $this->actingAs($this->attacker)->get(route('forms.attachments.download', [$f, 0]))->assertStatus(403);
    }

    public function test_cannot_act_on_someone_elses_form_request(): void
    {
        $f = $this->victimForm();
        $before = $f->only(['status', 'current_step']);

        foreach (['forms.action' => ['action' => 'approve', 'comment' => 'x'], 'forms.withdraw' => [], 'forms.transfer' => ['user_id' => $this->attacker->id], 'forms.add-sign' => ['user_id' => $this->attacker->id]] as $name => $data) {
            $res = $this->actingAs($this->attacker)->post(route($name, $f), $data);
            $this->assertContains($res->getStatusCode(), [302, 403, 404, 422], $name);
        }

        $this->assertSame($before, $f->fresh()->only(['status', 'current_step']), '別人的單據狀態不應被改變');
        $this->assertDatabaseMissing('approval_records', ['form_request_id' => $f->id, 'approver_id' => $this->attacker->id, 'status' => 'approved']);
    }

    public function test_cannot_read_or_modify_someone_elses_payslip(): void
    {
        $p = Payslip::create(['user_id' => User::where('email', 'manager@eip.local')->first()->id, 'period' => '2026-09', 'base_salary' => 60000, 'gross_pay' => 60000, 'net_pay' => 55000, 'status' => 'published']);
        $this->assertNotSame($p->user_id, $this->attacker->id);

        $this->actingAs($this->attacker)->get(route('payslips.show', $p))->assertStatus(403);
        $this->actingAs($this->attacker)->put(route('payslips.update', $p), ['base_salary' => 1])->assertStatus(403);
        $this->actingAs($this->attacker)->delete(route('payslips.destroy', $p))->assertStatus(403);
        $this->assertDatabaseHas('payslips', ['id' => $p->id]);
    }

    public function test_cannot_touch_someone_elses_notifications(): void
    {
        $victim = User::where('email', 'manager@eip.local')->first();
        $victim->notify(new \App\Notifications\EipSystemNotification(title: '機密', message: 'x', type: 'info', actionUrl: '/', senderName: 's'));
        $n = $victim->notifications()->firstOrFail();

        $this->actingAs($this->attacker)->post(route('notifications.read', $n->id));
        $this->actingAs($this->attacker)->delete(route('notifications.destroy', $n->id));

        $this->assertNull($n->fresh()->read_at);
        $this->assertDatabaseHas('notifications', ['id' => $n->id]);
    }

    public function test_cannot_change_someone_elses_leave_balance(): void
    {
        $victim = User::where('email', 'manager@eip.local')->first();

        $this->actingAs($this->attacker)->put(route('leave-balances.update', $victim), ['annual_total' => 999])->assertStatus(403);
    }

    public function test_cannot_download_document_version_that_belongs_to_another_document(): void
    {
        $docs = Document::with('versions')->get()->filter(fn ($d) => $d->versions->isNotEmpty());
        if ($docs->count() < 2) {
            $this->markTestSkipped('種子資料沒有兩份以上有版本的文件');
        }
        [$mine, $other] = [$docs->first(), $docs->last()];
        $foreignVersion = $other->versions->first();

        foreach (['documents.download', 'documents.preview'] as $name) {
            $res = $this->actingAs(User::where('email', 'admin@eip.local')->first())->get(route($name, [$mine, $foreignVersion]));
            $this->assertSame(404, $res->getStatusCode(), "{$name}: 版本不屬於該文件必須 404");
        }
    }

    public function test_user_ids_in_urls_do_not_expose_other_profiles(): void
    {
        $victim = User::where('email', 'hr@eip.local')->first();

        foreach (["/org-management/users/{$victim->id}", "/leave-balances/{$victim->id}"] as $url) {
            $res = $this->actingAs($this->attacker)->get($url);
            $this->assertContains($res->getStatusCode(), [403, 404, 405], $url);
        }
    }
}
