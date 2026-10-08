<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Document;
use App\Models\Form;
use App\Models\FormRequest as EipFormRequest;
use App\Models\MeetingRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected User $otherEmployee;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '資訊工程部',
            'code' => 'IT',
        ]);

        $this->admin = User::factory()->create([
            'name' => '系統管理員',
            'email' => 'admin@eip.test',
            'role' => 'admin',
            'status' => 'active',
            'department_id' => $this->department->id,
            'phone' => '8888',
        ]);

        $this->employee = User::factory()->create([
            'name' => '張小明',
            'email' => 'ming@eip.test',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
            'phone' => '1001',
        ]);

        $this->otherEmployee = User::factory()->create([
            'name' => '李大華',
            'email' => 'hua@eip.test',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
            'phone' => '1002',
        ]);
    }

    public function test_empty_query_returns_quick_shortcuts(): void
    {
        $response = $this->actingAs($this->employee)
            ->getJson(route('global-search'));

        $response->assertOk();
        $response->assertJsonStructure([
            'query',
            'results' => [
                'shortcuts' => [
                    '*' => ['id', 'title', 'subtitle', 'url', 'badge', 'type'],
                ],
            ],
        ]);

        $shortcuts = $response->json('results.shortcuts');
        $this->assertNotEmpty($shortcuts);
        $this->assertTrue(collect($shortcuts)->contains('id', 'quick-form'));
    }

    public function test_search_employees_by_name_or_extension(): void
    {
        $response = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => '張小明']));

        $response->assertOk();
        $items = $response->json('results.employees.items');
        $this->assertCount(1, $items);
        $this->assertEquals('張小明', $items[0]['title']);

        // 搜尋分機
        $responseExt = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => '1002']));
        $responseExt->assertOk();
        $itemsExt = $responseExt->json('results.employees.items');
        $this->assertCount(1, $itemsExt);
        $this->assertEquals('李大華', $itemsExt[0]['title']);
    }

    public function test_search_announcements_excludes_draft_and_future_posts(): void
    {
        // 1. 已發布正式公告
        Announcement::create([
            'title' => '2026 年終尾牙宴會通知',
            'content' => '歡迎同仁攜眷參加',
            'category' => '行政公告',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'author_id' => $this->admin->id,
        ]);

        // 2. 未發布草稿公告
        Announcement::create([
            'title' => '2026 機密內部改組通知草稿',
            'content' => '尚未定案，嚴禁外洩',
            'category' => '組織架構',
            'status' => 'draft',
            'published_at' => null,
            'author_id' => $this->admin->id,
        ]);

        // 搜尋已發布公告
        $response = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => '尾牙']));
        $response->assertOk();
        $items = $response->json('results.announcements.items');
        $this->assertCount(1, $items);
        $this->assertStringContainsString('尾牙', $items[0]['title']);

        // 搜尋草稿公告，一般同仁不得檢索出草稿
        $responseDraft = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => '機密內部改組']));
        $responseDraft->assertOk();
        $this->assertEmpty($responseDraft->json('results.announcements.items') ?? []);
    }

    public function test_search_form_requests_respects_idor_privacy(): void
    {
        $form = Form::create([
            'name' => '特別請假單',
            'code' => 'CUSTOM_LEAVE',
            'fields_schema' => [],
            'is_active' => true,
        ]);

        // 小明的請假單
        $mingReq = EipFormRequest::create([
            'form_id' => $form->id,
            'user_id' => $this->employee->id,
            'request_no' => 'REQ-MING-001',
            'title' => '小明特別請假申請單',
            'data' => ['reason' => '家庭私人旅遊'],
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => 1,
        ]);

        // 大華的請假單
        $huaReq = EipFormRequest::create([
            'form_id' => $form->id,
            'user_id' => $this->otherEmployee->id,
            'request_no' => 'REQ-HUA-002',
            'title' => '大華個人重大病假申請單',
            'data' => ['reason' => '私人就醫診療'],
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => 1,
        ]);

        // 小明搜尋單據，只能搜到自己的單號，搜不到大華的私密單據
        $resMingOwn = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => 'REQ-MING-001']));
        $resMingOwn->assertOk();
        $this->assertCount(1, $resMingOwn->json('results.forms.items'));

        $resMingOther = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => 'REQ-HUA-002']));
        $resMingOther->assertOk();
        $this->assertEmpty($resMingOther->json('results.forms.items') ?? []);

        // 管理員搜尋，可跨同仁檢索單據
        $resAdmin = $this->actingAs($this->admin)
            ->getJson(route('global-search', ['q' => 'REQ-HUA-002']));
        $resAdmin->assertOk();
        $this->assertCount(1, $resAdmin->json('results.forms.items'));
    }

    public function test_search_documents_respects_restricted_roles(): void
    {
        // 公開規章文件
        Document::create([
            'title' => '員工工作守則與考勤辦法規章',
            'category' => '人事規章',
            'uploader_id' => $this->admin->id,
            'current_version' => 1,
            'restricted_roles' => null,
        ]);

        // 機密薪資制度文件 (限 admin, hr)
        Document::create([
            'title' => '高階主管薪酬激勵計劃機密文件',
            'category' => '薪酬制度',
            'uploader_id' => $this->admin->id,
            'current_version' => 1,
            'restricted_roles' => ['admin', 'hr'],
        ]);

        // 一般同仁搜尋公開文件可查到
        $resPublic = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => '工作守則']));
        $resPublic->assertOk();
        $this->assertCount(1, $resPublic->json('results.documents.items'));

        // 一般同仁搜尋機密文件查不到 (密件隔離)
        $resConfidential = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => '薪酬激勵']));
        $resConfidential->assertOk();
        $this->assertEmpty($resConfidential->json('results.documents.items') ?? []);

        // 管理員搜尋機密文件可查到
        $resAdmin = $this->actingAs($this->admin)
            ->getJson(route('global-search', ['q' => '薪酬激勵']));
        $resAdmin->assertOk();
        $this->assertCount(1, $resAdmin->json('results.documents.items'));
    }

    public function test_search_meeting_rooms(): void
    {
        MeetingRoom::create([
            'name' => '101 晶圓大會議室',
            'location' => 'A棟 1 樓',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->employee)
            ->getJson(route('global-search', ['q' => '晶圓']));

        $response->assertOk();
        $items = $response->json('results.meeting_rooms.items');
        $this->assertCount(1, $items);
        $this->assertEquals('101 晶圓大會議室', $items[0]['title']);
    }
}
