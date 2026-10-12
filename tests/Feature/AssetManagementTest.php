<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $employee1;
    protected User $employee2;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => '資訊管理部',
            'code' => 'IT',
        ]);

        $this->admin = User::factory()->create([
            'name' => '系統管理員',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->manager = User::factory()->create([
            'name' => 'IT 主管',
            'email' => 'manager@example.com',
            'role' => 'manager',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->employee1 = User::factory()->create([
            'name' => '工程師小王',
            'email' => 'emp1@example.com',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        $this->employee2 = User::factory()->create([
            'name' => '設計師小美',
            'email' => 'emp2@example.com',
            'role' => 'employee',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);
    }

    public function test_authenticated_users_can_view_assets_index(): void
    {
        Asset::create([
            'asset_tag' => 'AST-2026-0001',
            'name' => 'MacBook Pro 16吋 M3 Max',
            'category' => 'it_hardware',
            'status' => 'available',
            'department_id' => $this->department->id,
        ]);

        $response = $this->actingAs($this->employee1)->get(route('assets.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Assets/Index')
            ->has('assets.data', 1)
            ->has('stats')
        );
    }

    public function test_manager_and_admin_can_create_new_asset_with_auto_asset_tag(): void
    {
        $response = $this->actingAs($this->admin)->post(route('assets.store'), [
            'name' => 'Dell UltraSharp 32吋 4K 螢幕',
            'category' => 'it_hardware',
            'serial_number' => 'SN-DELL-998877',
            'specification' => 'U3223QE 4K IPS Black',
            'location' => 'A棟4樓研發部實驗室',
            'department_id' => $this->department->id,
            'purchase_date' => '2026-03-15',
            'purchase_cost' => 28900,
            'notes' => '採購專案測試設備',
        ]);

        $response->assertRedirect();
        
        $asset = Asset::where('serial_number', 'SN-DELL-998877')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('AST-2026-0001', $asset->asset_tag);
        $this->assertEquals('Dell UltraSharp 32吋 4K 螢幕', $asset->name);
        $this->assertEquals('available', $asset->status);

        // 驗證審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'asset_create',
            'auditable_type' => Asset::class,
            'auditable_id' => $asset->id,
        ]);
    }

    public function test_general_employee_cannot_create_asset(): void
    {
        $response = $this->actingAs($this->employee1)->post(route('assets.store'), [
            'name' => '未經授權登記設備',
            'category' => 'office_equipment',
        ]);

        $response->assertStatus(403);
    }

    public function test_employee_can_borrow_available_asset_for_themselves(): void
    {
        $asset = Asset::create([
            'asset_tag' => 'AST-2026-0002',
            'name' => 'Sony A7M4 4K 單眼攝影機套組',
            'category' => 'av_media',
            'status' => 'available',
            'location' => '公務器材防潮箱',
        ]);

        $expectedReturn = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->actingAs($this->employee1)->post(route('assets.borrow', $asset->id), [
            'borrower_id' => null, // 留空即指派給自己
            'expected_return_date' => $expectedReturn,
            'purpose' => '品牌年會活動拍攝錄影與社群素材取材',
        ]);

        $response->assertRedirect();
        $asset->refresh();

        $this->assertEquals('in_use', $asset->status);
        $this->assertEquals($this->employee1->id, $asset->current_holder_id);

        $loan = AssetLoan::where('asset_id', $asset->id)->latest()->first();
        $this->assertNotNull($loan);
        $this->assertEquals($this->employee1->id, $loan->user_id);
        $this->assertEquals($this->employee1->id, $loan->handled_by);
        $this->assertEquals('borrowed', $loan->status);
        $this->assertEquals('品牌年會活動拍攝錄影與社群素材取材', $loan->purpose);
    }

    public function test_manager_can_borrow_asset_on_behalf_of_other_employee_and_sends_notification(): void
    {
        Notification::fake();

        $asset = Asset::create([
            'asset_tag' => 'AST-2026-0003',
            'name' => '公務車 Toyota RAV4 (ABC-8888)',
            'category' => 'vehicles',
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->manager)->post(route('assets.borrow', $asset->id), [
            'borrower_id' => $this->employee2->id,
            'expected_return_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'purpose' => '前往南部客戶廠區進行全日系統上線測試部署',
        ]);

        $response->assertRedirect();
        $asset->refresh();

        $this->assertEquals('in_use', $asset->status);
        $this->assertEquals($this->employee2->id, $asset->current_holder_id);

        $loan = AssetLoan::where('asset_id', $asset->id)->latest()->first();
        $this->assertEquals($this->employee2->id, $loan->user_id);
        $this->assertEquals($this->manager->id, $loan->handled_by);

        // 驗證代借發送通知給 employee2
        Notification::assertSentTo(
            $this->employee2,
            EipSystemNotification::class,
            function ($notification) use ($asset) {
                return str_contains($notification->title, '公務資產借調通知')
                    && str_contains($notification->message, $asset->name);
            }
        );
    }

    public function test_cannot_borrow_asset_that_is_not_available(): void
    {
        $asset = Asset::create([
            'asset_tag' => 'AST-2026-0004',
            'name' => '伺服器檢測儀',
            'category' => 'it_hardware',
            'status' => 'in_use',
            'current_holder_id' => $this->employee1->id,
        ]);

        $response = $this->actingAs($this->employee2)->post(route('assets.borrow', $asset->id), [
            'purpose' => '嘗試重複借調',
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertEquals(0, AssetLoan::where('user_id', $this->employee2->id)->count());
    }

    public function test_cannot_delete_asset_currently_in_use(): void
    {
        $asset = Asset::create([
            'asset_tag' => 'AST-2026-0005',
            'name' => '企業高階投影機',
            'category' => 'av_media',
            'status' => 'in_use',
            'current_holder_id' => $this->employee1->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('assets.destroy', $asset->id));
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_holder_or_manager_can_return_asset_with_acceptance_notes(): void
    {
        $asset = Asset::create([
            'asset_tag' => 'AST-2026-0006',
            'name' => '微軟 HoloLens 2 開發套件',
            'category' => 'it_hardware',
            'status' => 'in_use',
            'current_holder_id' => $this->employee1->id,
        ]);

        $loan = AssetLoan::create([
            'asset_id' => $asset->id,
            'user_id' => $this->employee1->id,
            'handled_by' => $this->employee1->id,
            'borrowed_at' => Carbon::now()->subDays(2),
            'expected_return_date' => Carbon::tomorrow(),
            'status' => 'borrowed',
            'purpose' => 'AR 專案測試',
        ]);

        $response = $this->actingAs($this->employee1)->post(route('assets.return', $asset->id), [
            'return_notes' => '外觀完整，原廠線材與充電頭均已收納歸位。',
        ]);

        $response->assertRedirect();
        $asset->refresh();
        $loan->refresh();

        $this->assertEquals('available', $asset->status);
        $this->assertNull($asset->current_holder_id);
        $this->assertEquals('returned', $loan->status);
        $this->assertNotNull($loan->returned_at);
        $this->assertEquals('外觀完整，原廠線材與充電頭均已收納歸位。', $loan->return_notes);

        // 驗證審計日誌
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->employee1->id,
            'action' => 'asset_return',
            'auditable_type' => Asset::class,
            'auditable_id' => $asset->id,
        ]);
    }

    public function test_overdue_check_logic_is_accurate(): void
    {
        $asset = Asset::create([
            'asset_tag' => 'AST-2026-0007',
            'name' => '便攜式會議音訊麥克風',
            'category' => 'av_media',
            'status' => 'in_use',
            'current_holder_id' => $this->employee1->id,
        ]);

        // 昨天到期 -> 逾期
        $loanPast = AssetLoan::create([
            'asset_id' => $asset->id,
            'user_id' => $this->employee1->id,
            'handled_by' => $this->employee1->id,
            'borrowed_at' => Carbon::now()->subDays(5),
            'expected_return_date' => Carbon::yesterday(),
            'status' => 'borrowed',
        ]);

        $this->assertTrue($loanPast->isOverdue());

        // 今天到期 -> 不算逾期 (endOfDay 寬限至今天 23:59:59)
        $loanToday = AssetLoan::create([
            'asset_id' => $asset->id,
            'user_id' => $this->employee1->id,
            'handled_by' => $this->employee1->id,
            'borrowed_at' => Carbon::now()->subDays(1),
            'expected_return_date' => Carbon::today(),
            'status' => 'borrowed',
        ]);

        $this->assertFalse($loanToday->isOverdue());

        // 已歸還的紀錄不可判定為逾期
        $loanPast->update(['status' => 'returned', 'returned_at' => Carbon::now()]);
        $this->assertFalse($loanPast->isOverdue());
    }

    public function test_dashboard_displays_held_assets_and_counts(): void
    {
        $asset = Asset::create([
            'asset_tag' => 'AST-2026-0008',
            'name' => 'ThinkPad X1 Carbon Gen 12',
            'category' => 'it_hardware',
            'status' => 'in_use',
            'current_holder_id' => $this->employee1->id,
        ]);

        AssetLoan::create([
            'asset_id' => $asset->id,
            'user_id' => $this->employee1->id,
            'handled_by' => $this->employee1->id,
            'borrowed_at' => Carbon::now(),
            'expected_return_date' => Carbon::now()->addDays(7),
            'status' => 'borrowed',
            'purpose' => '差旅外派使用',
        ]);

        $response = $this->actingAs($this->employee1)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.myHeldAssetsCount', 1)
            ->has('myAssets', 1)
            ->where('myAssets.0.asset_tag', 'AST-2026-0008')
        );
    }

    public function test_global_search_returns_asset_results(): void
    {
        Asset::create([
            'asset_tag' => 'AST-2026-0009',
            'name' => 'Wacom Cintiq Pro 27 繪圖螢幕',
            'category' => 'it_hardware',
            'status' => 'available',
            'serial_number' => 'WC-888999',
            'location' => '設計部多媒體工作坊',
        ]);

        $response = $this->actingAs($this->employee1)->get(route('global-search', ['q' => 'Wacom']));
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => '[AST-2026-0009] Wacom Cintiq Pro 27 繪圖螢幕',
        ]);
    }
}
