<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnnouncementReadTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Announcement $ann;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        $this->ann = Announcement::where('status', 'published')->firstOrFail();
        AnnouncementRead::where('announcement_id', $this->ann->id)->delete();
        Cache::forget("announcement:{$this->ann->id}:reminded_at");
    }

    private function u(string $role): User
    {
        return User::where('email', "$role@eip.local")->firstOrFail();
    }

    private function reminders(User $u)
    {
        return $u->notifications()->where('data', 'like', '%"reminder":true%')->get();
    }

    public function test_regular_employee_cannot_see_reader_or_unread_lists(): void
    {
        AnnouncementRead::create(['announcement_id' => $this->ann->id, 'user_id' => $this->u('manager')->id, 'read_at' => now()]);

        $this->actingAs($this->u('employee'))->get(route('announcements.show', $this->ann))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('canManage', false)->where('readers', [])->where('unread', []));
    }

    public function test_hr_sees_unread_list_excluding_readers_and_inactive(): void
    {
        $inactive = User::factory()->create(['status' => 'resigned', 'name' => '已離職者']);
        AnnouncementRead::create(['announcement_id' => $this->ann->id, 'user_id' => $this->u('manager')->id, 'read_at' => now()]);

        $this->actingAs($this->u('hr'))->get(route('announcements.show', $this->ann))
            ->assertInertia(function ($p) use ($inactive) {
                $p->where('canManage', true);
                $names = collect($p->toArray()['props']['unread'])->pluck('name');
                $readers = collect($p->toArray()['props']['readers'])->pluck('name');
                $this->assertNotContains($this->u('manager')->name, $names->all());
                $this->assertNotContains($inactive->name, $names->all());
                $this->assertContains($this->u('employee')->name, $names->all());
                $this->assertContains($this->u('manager')->name, $readers->all());
                $this->assertContains($this->u('hr')->name, $readers->all(), '開啟頁面的人自己會被登記為已讀');
            });
    }

    public function test_viewing_registers_read_and_removes_from_unread(): void
    {
        $this->actingAs($this->u('employee'))->get(route('announcements.show', $this->ann))->assertOk();

        $this->assertDatabaseHas('announcement_reads', ['announcement_id' => $this->ann->id, 'user_id' => $this->u('employee')->id]);
    }

    public function test_remind_notifies_only_unread_active_users_and_not_the_sender(): void
    {
        $hr = $this->u('hr');
        AnnouncementRead::create(['announcement_id' => $this->ann->id, 'user_id' => $this->u('manager')->id, 'read_at' => now()]);
        $resigned = User::factory()->create(['status' => 'resigned']);

        $this->actingAs($hr)->post(route('announcements.remind', $this->ann))->assertSessionHas('success');

        $this->assertCount(1, $this->reminders($this->u('employee')));
        $this->assertCount(0, $this->reminders($this->u('manager')), '已讀者不催');
        $this->assertCount(0, $this->reminders($hr), '不通知自己');
        $this->assertCount(0, $this->reminders($resigned), '離職者不催');
        $this->assertDatabaseHas('audit_logs', ['action' => 'remind_announcement_unread']);
    }

    public function test_remind_is_rate_limited_to_once_per_hour(): void
    {
        $hr = $this->u('hr');
        $this->actingAs($hr)->post(route('announcements.remind', $this->ann))->assertSessionHas('success');
        $this->actingAs($hr)->post(route('announcements.remind', $this->ann))->assertSessionHas('error');

        $this->assertCount(1, $this->reminders($this->u('employee')));
    }

    public function test_only_managers_can_remind(): void
    {
        $this->actingAs($this->u('employee'))->post(route('announcements.remind', $this->ann))->assertForbidden();
        $this->actingAs($this->u('manager'))->post(route('announcements.remind', $this->ann))->assertForbidden();
        $this->assertCount(0, $this->reminders($this->u('hr')));
    }

    public function test_author_can_remind_own_announcement(): void
    {
        $author = $this->u('manager');
        $this->ann->update(['author_id' => $author->id]);

        $this->actingAs($author)->post(route('announcements.remind', $this->ann))->assertSessionHas('success');
    }

    public function test_draft_cannot_be_reminded(): void
    {
        $this->ann->update(['status' => 'draft']);

        $this->actingAs($this->u('admin'))->post(route('announcements.remind', $this->ann))->assertSessionHas('error');
        $this->assertCount(0, $this->reminders($this->u('employee')));
    }

    public function test_non_numeric_id_is_404(): void
    {
        $this->actingAs($this->u('admin'))->post('/announcements/abc/remind')->assertNotFound();
    }
}
