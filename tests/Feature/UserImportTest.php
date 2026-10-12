<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\EipDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    private string $deptCode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EipDatabaseSeeder::class);
        $this->deptCode = Department::whereNotNull('code')->firstOrFail()->code;
    }

    private function user(string $role): User
    {
        return User::where('email', "$role@eip.local")->firstOrFail();
    }

    private function csv(array $rows, string $encoding = 'UTF-8', bool $bom = true): UploadedFile
    {
        $header = ['姓名', 'Email', '員工編號', '部門代碼', '職稱', '角色', '電話', '初始密碼'];
        $out = '';
        foreach (array_merge([$header], $rows) as $row) {
            $out .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row))."\r\n";
        }
        if ($encoding === 'BIG5') {
            $out = mb_convert_encoding($out, 'BIG5', 'UTF-8');
        } elseif ($bom) {
            $out = "\xEF\xBB\xBF".$out;
        }

        return UploadedFile::fake()->createWithContent('import.csv', $out);
    }

    private function import(string $mode, UploadedFile $file, string $role = 'hr')
    {
        return $this->actingAs($this->user($role))->post(route('org-management.import.store'), ['file' => $file, 'mode' => $mode]);
    }

    public function test_only_admin_and_hr_can_use_import(): void
    {
        foreach (['employee', 'manager'] as $role) {
            $this->actingAs($this->user($role))->get(route('org-management.import'))->assertForbidden();
            $this->actingAs($this->user($role))->get(route('org-management.import.template'))->assertForbidden();
            $this->import('commit', $this->csv([['甲', 'a@example.com', '', '', '', '', '', '']]), $role)->assertForbidden();
        }
        $this->assertDatabaseMissing('users', ['email' => 'a@example.com']);
        $this->actingAs($this->user('hr'))->get(route('org-management.import'))->assertOk();
        $this->get(route('org-management.import.template'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_preview_validates_without_writing(): void
    {
        $count = User::count();
        $this->import('preview', $this->csv([
            ['王小明', 'wang@example.com', 'N001', $this->deptCode, '工程師', '一般員工', '', ''],
        ]))->assertSessionHas('import_result', fn ($r) => $r['total'] === 1 && $r['valid'] === 1 && $r['invalid'] === 0 && ! $r['committed']);

        $this->assertSame($count, User::count());
    }

    public function test_commit_creates_users_with_forced_password_change_and_balances(): void
    {
        $this->import('commit', $this->csv([
            ['王小明', 'wang@example.com', 'N001', $this->deptCode, '工程師', '一般員工', '0912', 'Init12345'],
            ['李主管', 'lee@example.com', 'N002', '', '經理', '部門主管', '', ''],
        ]))->assertSessionHas('import_result', function ($r) {
            return $r['committed'] && count($r['generated']) === 1 && $r['generated'][0]['email'] === 'lee@example.com';
        });

        $wang = User::where('email', 'wang@example.com')->firstOrFail();
        $this->assertTrue($wang->must_change_password);
        $this->assertSame('active', $wang->status);
        $this->assertSame('employee', $wang->role);
        $this->assertNotNull($wang->department_id);
        $this->assertTrue(Hash::check('Init12345', $wang->password));
        $this->assertGreaterThan(0, $wang->leaveBalances()->count());
        $this->assertSame('manager', User::where('email', 'lee@example.com')->value('role'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'import_users_csv']);
    }

    public function test_generated_password_is_strong_and_works(): void
    {
        $response = $this->import('commit', $this->csv([['自動密碼', 'auto@example.com', '', '', '', '', '', '']]));
        $pw = session('import_result')['generated'][0]['password'];

        $this->assertMatchesRegularExpression('/[A-Za-z]/', $pw);
        $this->assertMatchesRegularExpression('/\d/', $pw);
        $this->assertGreaterThanOrEqual(8, strlen($pw));
        $this->assertTrue(Hash::check($pw, User::where('email', 'auto@example.com')->value('password')));
    }

    public function test_any_invalid_row_blocks_the_whole_commit(): void
    {
        $count = User::count();
        $this->import('commit', $this->csv([
            ['好的', 'good@example.com', '', '', '', '', '', ''],
            ['壞的', 'not-an-email', '', '', '', '', '', ''],
        ]))->assertSessionHas('import_result', fn ($r) => ! $r['committed'] && $r['invalid'] === 1 && $r['valid'] === 1);

        $this->assertSame($count, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'good@example.com']);
    }

    public function test_row_level_errors_are_reported(): void
    {
        $this->import('preview', $this->csv([
            ['', 'e1@example.com', '', '', '', '', '', ''],                                  // 缺姓名
            ['重複', 'employee@eip.local', '', '', '', '', '', ''],                           // Email 已存在
            ['甲', 'dup@example.com', '', 'NOPE', '', '', '', ''],                            // 部門不存在
            ['乙', 'dup@example.com', '', '', '', '', '', ''],                                // 檔內重複
            ['丙', 'role@example.com', '', '', '', '外星人', '', ''],                          // 角色無法辨識
            ['丁', 'weak@example.com', '', '', '', '', '', 'abc'],                            // 弱密碼
            ['戊', 'noadmin@example.com', '', '', '', '系統管理員', '', ''],                    // hr 不可匯入 admin
        ]));

        $r = session('import_result');
        $this->assertSame(7, $r['invalid']);
        $msg = fn ($i) => implode('|', $r['rows'][$i]['errors']);
        $this->assertStringContainsString('姓名', $msg(0));
        $this->assertStringContainsString('Email', $msg(1));
        $this->assertStringContainsString('NOPE', $msg(2));
        $this->assertStringContainsString('重複', $msg(3));
        $this->assertStringContainsString('外星人', $msg(4));
        $this->assertStringContainsString('初始密碼', $msg(5));
        $this->assertStringContainsString('僅系統管理員', $msg(6));
    }

    public function test_admin_can_import_admin_role(): void
    {
        $this->import('commit', $this->csv([['新管理員', 'newadmin@example.com', '', '', '', 'admin', '', 'Admin12345']]), 'admin');
        $this->assertSame('admin', User::where('email', 'newadmin@example.com')->value('role'));
    }

    public function test_big5_encoded_file_is_converted(): void
    {
        $this->import('preview', $this->csv([['陳大文', 'big5@example.com', '', '', '', '', '', '']], 'BIG5'));
        $this->assertSame('陳大文', session('import_result')['rows'][0]['name']);
    }

    public function test_missing_required_header_or_empty_file_is_rejected(): void
    {
        $bad = UploadedFile::fake()->createWithContent('x.csv', "foo,bar\n1,2\n");
        $this->import('preview', $bad)->assertSessionHasErrors('file');

        $empty = UploadedFile::fake()->createWithContent('x.csv', "姓名,Email\n");
        $this->import('preview', $empty)->assertSessionHasErrors('file');
    }

    public function test_too_many_rows_and_wrong_type_are_rejected(): void
    {
        $rows = [];
        for ($i = 0; $i < 501; $i++) {
            $rows[] = ["人$i", "u$i@example.com", '', '', '', '', '', ''];
        }
        $this->import('preview', $this->csv($rows))->assertSessionHasErrors('file');

        $this->actingAs($this->user('hr'))->post(route('org-management.import.store'), [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'mode' => 'preview',
        ])->assertSessionHasErrors('file');
    }

    public function test_csv_injection_cell_is_kept_as_plain_text(): void
    {
        $this->import('commit', $this->csv([['=HYPERLINK("http://evil")', 'inj@example.com', '', '', '', '', '', '']]));
        // 以純文字儲存;匯出名冊時既有的 Formula Injection 防護會處理
        $this->assertSame('=HYPERLINK("http://evil")', User::where('email', 'inj@example.com')->value('name'));
    }
}
