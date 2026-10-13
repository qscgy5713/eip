<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 上傳檔案不得讓使用者放進可被網頁伺服器直接執行/渲染的檔案(RCE / 儲存型 XSS) */
class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        $this->employee = User::where('email', 'employee@eip.local')->firstOrFail();
    }

    private function ticketPayload(UploadedFile $file): array
    {
        return ['category' => 'other', 'priority' => 'low', 'title' => 'x', 'description' => 'y', 'attachments' => [$file]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dangerous')]
    public function test_ticket_rejects_executable_or_renderable_uploads(string $name, string $content): void
    {
        $file = UploadedFile::fake()->createWithContent($name, $content);

        $this->actingAs($this->employee)->post(route('tickets.store'), $this->ticketPayload($file));

        $this->assertSame(0, Ticket::count(), "{$name} 不應該被接受");
        $this->assertSame([], Storage::disk('public')->allFiles(), "{$name} 不應該被存進 public disk");
    }

    public static function dangerous(): array
    {
        return [
            'php' => ['shell.php', '<?php echo 1;'],
            'phtml' => ['shell.phtml', '<?php echo 1;'],
            'php disguised as png' => ['shell.png.php', '<?php echo 1;'],
            'html' => ['x.html', '<script>alert(1)</script>'],
            'svg' => ['x.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
            'htaccess' => ['.htaccess', 'AddType application/x-httpd-php .png'],
        ];
    }

    public function test_ticket_accepts_normal_attachments_and_stores_them_privately(): void
    {
        $file = UploadedFile::fake()->image('screenshot.png');

        $this->actingAs($this->employee)->post(route('tickets.store'), $this->ticketPayload($file))->assertSessionHasNoErrors();

        $this->assertSame(1, Ticket::count());
        $this->assertSame([], Storage::disk('public')->allFiles(), '工單附件不應該放在可直接下載的 public disk');
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_stored_ticket_attachment_is_not_downloadable_by_unrelated_user(): void
    {
        $this->actingAs($this->employee)->post(route('tickets.store'), $this->ticketPayload(UploadedFile::fake()->image('a.png')));
        $ticket = Ticket::firstOrFail();
        $att = $ticket->attachments[0];

        $other = User::where('email', 'manager@eip.local')->firstOrFail();
        $this->actingAs($other)->get(route('tickets.attachment', [$ticket, 0]))->assertForbidden();
        $this->actingAs($this->employee)->get(route('tickets.attachment', [$ticket, 0]))->assertOk();
        $this->assertNotEmpty($att['path']);
    }

    public function test_internal_reply_attachment_is_only_for_staff(): void
    {
        $this->actingAs($this->employee)->post(route('tickets.store'), $this->ticketPayload(UploadedFile::fake()->image('a.png')));
        $ticket = Ticket::firstOrFail();
        $hr = User::where('email', 'hr@eip.local')->firstOrFail();

        $this->actingAs($hr)->post(route('tickets.reply', $ticket), [
            'message' => '內部', 'is_internal' => true, 'attachments' => [UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $reply = $ticket->replies()->firstOrFail();

        $this->actingAs($this->employee)->get(route('tickets.reply-attachment', [$ticket, $reply, 0]))->assertForbidden();
        $this->actingAs($hr)->get(route('tickets.reply-attachment', [$ticket, $reply, 0]))->assertOk();
    }

    public function test_attachment_route_rejects_reply_of_another_ticket_and_bad_index(): void
    {
        $this->actingAs($this->employee)->post(route('tickets.store'), $this->ticketPayload(UploadedFile::fake()->image('a.png')));
        $this->actingAs($this->employee)->post(route('tickets.store'), $this->ticketPayload(UploadedFile::fake()->image('b.png')));
        [$t1, $t2] = Ticket::orderBy('id')->get()->all();
        $hr = User::where('email', 'hr@eip.local')->firstOrFail();
        $this->actingAs($hr)->post(route('tickets.reply', $t2), ['message' => 'x', 'attachments' => [UploadedFile::fake()->create('r.pdf', 5, 'application/pdf')]]);
        $reply = $t2->replies()->firstOrFail();

        $this->actingAs($hr)->get(route('tickets.reply-attachment', [$t1, $reply, 0]))->assertNotFound();
        $this->actingAs($this->employee)->get(route('tickets.attachment', [$t1, 5]))->assertNotFound();
        $this->get('/tickets/' . $t1->id . '/attachments/abc')->assertNotFound();
    }

    public function test_download_is_forced_attachment_with_nosniff(): void
    {
        $this->actingAs($this->employee)->post(route('tickets.store'), $this->ticketPayload(UploadedFile::fake()->image('a.png')));
        $res = $this->actingAs($this->employee)->get(route('tickets.attachment', [Ticket::firstOrFail(), 0]));

        $res->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
    }
}
