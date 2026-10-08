<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\ApprovalRecord;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class EipDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 部門
        $hq = Department::create(['name' => '總經理室', 'code' => 'HQ', 'sort_order' => 1]);
        $rd = Department::create(['name' => '研發工程部', 'code' => 'RD', 'sort_order' => 2]);
        $hr = Department::create(['name' => '人資行政部', 'code' => 'HR', 'sort_order' => 3]);
        $mkt = Department::create(['name' => '行銷業務部', 'code' => 'MKT', 'sort_order' => 4]);

        // 2. 使用者 (密碼皆為 password)
        $admin = User::create([
            'name' => '系統管理員 (王總裁)',
            'email' => 'admin@eip.local',
            'password' => Hash::make('password'),
            'department_id' => $hq->id,
            'employee_no' => 'EMP-001',
            'job_title' => '執行長 / 管理員',
            'role' => 'admin',
            'phone' => '0912-000-001',
        ]);

        $manager = User::create([
            'name' => '張經理 (研發主管)',
            'email' => 'manager@eip.local',
            'password' => Hash::make('password'),
            'department_id' => $rd->id,
            'employee_no' => 'EMP-002',
            'job_title' => '研發部經理',
            'role' => 'manager',
            'phone' => '0912-000-002',
        ]);

        $employee = User::create([
            'name' => '陳同仁 (前端工程師)',
            'email' => 'employee@eip.local',
            'password' => Hash::make('password'),
            'department_id' => $rd->id,
            'employee_no' => 'EMP-003',
            'job_title' => '全端工程師',
            'role' => 'employee',
            'phone' => '0912-000-003',
        ]);

        $hrUser = User::create([
            'name' => '林專員 (人資行政)',
            'email' => 'hr@eip.local',
            'password' => Hash::make('password'),
            'department_id' => $hr->id,
            'employee_no' => 'EMP-004',
            'job_title' => '人資專員',
            'role' => 'hr',
            'phone' => '0912-000-004',
        ]);

        // 3. 企業公告
        Announcement::create([
            'title' => '【重大公告】EIP 企業資訊入口網全新改版上線！',
            'content' => '歡迎各位同仁使用新版 EIP 系統。本系統支援組織架構瀏覽、公司最新公告推播與線上表單快速簽核，各部門如有操作疑問歡迎聯繫人資行政部。',
            'category' => 'company',
            'priority' => 'urgent',
            'is_pinned' => true,
            'status' => 'published',
            'author_id' => $admin->id,
            'published_at' => now(),
        ]);

        Announcement::create([
            'title' => '【行政通知】下週五下午全員季會與季度慶生茶會',
            'content' => '時間：下週五 15:30 - 17:30。地點：大會議室。請全體同仁準時出席，遠端同仁請透過視訊會議連結參加。',
            'category' => 'activity',
            'priority' => 'high',
            'is_pinned' => false,
            'status' => 'published',
            'author_id' => $hrUser->id,
            'published_at' => now()->subDay(),
        ]);

        Announcement::create([
            'title' => '【研發通知】週四凌晨核心資料庫排程備份升級公告',
            'content' => '研發部預計於週四 02:00 - 04:00 進行主機維護，期間內部網路部分系統可能短暫中斷。',
            'category' => 'general',
            'priority' => 'normal',
            'is_pinned' => false,
            'status' => 'published',
            'author_id' => $manager->id,
            'published_at' => now()->subDays(2),
        ]);

        // 4. 表單範本
        $leaveForm = Form::create([
            'name' => '休假申請單',
            'code' => 'LEAVE',
            'description' => '事假、病假、特休、公假、補休等各式差假申請',
            'fields_schema' => [
                ['key' => 'leave_type', 'label' => '假別', 'type' => 'select', 'options' => ['特休假', '事假', '病假', '公假', '補休', '婚喪假']],
                ['key' => 'start_date', 'label' => '開始日期', 'type' => 'date'],
                ['key' => 'end_date', 'label' => '結束日期', 'type' => 'date'],
                ['key' => 'days', 'label' => '請假天數', 'type' => 'number'],
                ['key' => 'reason', 'label' => '請假事由', 'type' => 'textarea'],
            ],
        ]);

        $expenseForm = Form::create([
            'name' => '費用報銷申請單',
            'code' => 'EXPENSE',
            'description' => '出差交通費、業務餐敘、辦公耗材代墊費用報支',
            'fields_schema' => [
                ['key' => 'expense_type', 'label' => '支出項目', 'type' => 'select', 'options' => ['出差交通費', '客戶交際費', '辦公耗材', '軟體授權', '其他雜支']],
                ['key' => 'amount', 'label' => '報銷金額 (TWD)', 'type' => 'number'],
                ['key' => 'invoice_no', 'label' => '發票/統編號碼', 'type' => 'text'],
                ['key' => 'description', 'label' => '用途說明', 'type' => 'textarea'],
            ],
        ]);

        $overtimeForm = Form::create([
            'name' => '加班申請單',
            'code' => 'OVERTIME',
            'description' => '平日延長工時或國定休假日專案支援加班申請',
            'fields_schema' => [
                ['key' => 'overtime_type', 'label' => '加班類型', 'type' => 'select', 'options' => ['平日延長工時', '週末假日加班', '國定假日專案支援']],
                ['key' => 'overtime_date', 'label' => '加班日期', 'type' => 'date'],
                ['key' => 'hours', 'label' => '加班時數 (小時)', 'type' => 'number'],
                ['key' => 'compensation', 'label' => '補償方式', 'type' => 'select', 'options' => ['換取補休時數', '核發加班費']],
                ['key' => 'reason', 'label' => '專案事由與工作內容', 'type' => 'textarea'],
            ],
        ]);

        $clockAdjustForm = Form::create([
            'name' => '忘刷/補打卡申請單',
            'code' => 'CLOCK_ADJUST',
            'description' => '公出外勤、感應異常或突發狀況忘記上下班打卡之補登申請',
            'fields_schema' => [
                ['key' => 'adjust_date', 'label' => '忘刷日期', 'type' => 'date'],
                ['key' => 'adjust_type', 'label' => '補刷卡別', 'type' => 'select', 'options' => ['上班卡補刷', '下班卡補刷', '全日未打卡補登']],
                ['key' => 'actual_time', 'label' => '實際出勤時間', 'type' => 'text'],
                ['key' => 'reason', 'label' => '未依規定打卡原因說明', 'type' => 'textarea'],
            ],
        ]);

        $purchaseForm = Form::create([
            'name' => '資訊設備與資產採購單',
            'code' => 'PURCHASE',
            'description' => '開發用筆電、外接螢幕、測試設備與雲端伺服器擴容請購',
            'fields_schema' => [
                ['key' => 'item_category', 'label' => '資產類別', 'type' => 'select', 'options' => ['電腦主機/筆電', '周邊顯示器/硬體', '雲端主機擴展', '軟體商業授權']],
                ['key' => 'item_name', 'label' => '請購品項與型號', 'type' => 'text'],
                ['key' => 'estimated_cost', 'label' => '預算金額 (TWD)', 'type' => 'number'],
                ['key' => 'urgency', 'label' => '急迫程度', 'type' => 'select', 'options' => ['普通 (一週內)', '緊急 (專案阻礙)', '年度編列預算']],
                ['key' => 'purpose', 'label' => '請購需求與效益評估', 'type' => 'textarea'],
            ],
        ]);

        $tripForm = Form::create([
            'name' => '公出與外勤洽公單',
            'code' => 'TRIP',
            'description' => '上班時間外出拜訪客戶、跨廠區技術交流或外勤公事申請',
            'fields_schema' => [
                ['key' => 'trip_date', 'label' => '公出日期', 'type' => 'date'],
                ['key' => 'client_name', 'label' => '拜訪對象 / 客戶機構', 'type' => 'text'],
                ['key' => 'destination', 'label' => '目的地地點', 'type' => 'text'],
                ['key' => 'transportation', 'label' => '交通工具', 'type' => 'select', 'options' => ['大眾捷運/公車', '高鐵/台鐵', '公司公務車', '自行駕車']],
                ['key' => 'agenda', 'label' => '洽談主旨與任務預計產出', 'type' => 'textarea'],
            ],
        ]);

        // 5. 示範申請單據與審批歷程 (多筆豐富資料)
        // 單據 1: 陳同仁 - 特休申請 (待審核)
        $req1 = FormRequest::create([
            'form_id' => $leaveForm->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-' . date('Ymd') . '-0001',
            'title' => '陳同仁 - 特休請假申請 (1天)',
            'data' => [
                'leave_type' => '特休假',
                'start_date' => date('Y-m-d', strtotime('+3 days')),
                'end_date' => date('Y-m-d', strtotime('+3 days')),
                'days' => 1,
                'reason' => '家庭私人事務處理',
            ],
            'status' => 'pending',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req1->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'pending',
        ]);

        // 單據 2: 陳同仁 - 週末系統部署加班申請 (待審核)
        $req2 = FormRequest::create([
            'form_id' => $overtimeForm->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-' . date('Ymd') . '-0002',
            'title' => '陳同仁 - 週末版本上線加班申請 (4小時)',
            'data' => [
                'overtime_type' => '週末假日加班',
                'overtime_date' => date('Y-m-d', strtotime('+5 days')),
                'hours' => 4,
                'compensation' => '換取補休時數',
                'reason' => 'EIP 企業入口網核心模組全面上線發布與驗收監控。',
            ],
            'status' => 'pending',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req2->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'pending',
        ]);

        // 單據 3: 陳同仁 - 4K 專業螢幕請購 (待審核)
        $req3 = FormRequest::create([
            'form_id' => $purchaseForm->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-' . date('Ymd') . '-0003',
            'title' => '陳同仁 - 前端多工開發用 27 吋 4K 顯示器請購',
            'data' => [
                'item_category' => '周邊顯示器/硬體',
                'item_name' => 'Dell UltraSharp 27 4K USB-C Hub 顯示器 (U2723QE)',
                'estimated_cost' => 18500,
                'urgency' => '普通 (一週內)',
                'purpose' => '多重視窗切換與響應式前台跨端校對，可大幅提升編程開發效率。',
            ],
            'status' => 'pending',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req3->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'pending',
        ]);

        // 單據 4: 陳同仁 - 出差高鐵交通費報支 (已核准)
        $req4 = FormRequest::create([
            'form_id' => $expenseForm->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-' . date('Ymd', strtotime('-3 days')) . '-0004',
            'title' => '陳同仁 - 新竹台積電廠區技術對齊高鐵交通費報銷',
            'data' => [
                'expense_type' => '出差交通費',
                'amount' => 2980,
                'invoice_no' => 'THSR-88992211',
                'description' => '台北至新竹往返商務車廂票券，客戶現場系統連線測試。',
            ],
            'status' => 'approved',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req4->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'approved',
            'comment' => '單據核對無誤，同意核撥報支款項。',
        ]);

        // 單據 5: 陳同仁 - 昨日捷運訊號不良補打卡 (已核准)
        $req5 = FormRequest::create([
            'form_id' => $clockAdjustForm->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-' . date('Ymd', strtotime('-1 day')) . '-0005',
            'title' => '陳同仁 - 昨日上班卡補登 (08:58)',
            'data' => [
                'adjust_date' => date('Y-m-d', strtotime('-1 day')),
                'adjust_type' => '上班卡補刷',
                'actual_time' => '08:58',
                'reason' => '大樓閘門感應器維護，改由臨櫃簽到，特此補刷。',
            ],
            'status' => 'approved',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req5->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'approved',
            'comment' => '行政部已查核紙本簽名紀錄相符，准予補登。',
        ]);

        // 單據 6: 張經理 - 雲端伺服器年度升級採購單 (待王總裁審批)
        $req6 = FormRequest::create([
            'form_id' => $purchaseForm->id,
            'user_id' => $manager->id,
            'request_no' => 'REQ-' . date('Ymd') . '-0006',
            'title' => '張經理 - 核心 Kubernetes 叢集與資料庫擴容採購',
            'data' => [
                'item_category' => '雲端主機擴展',
                'item_name' => 'AWS EKS Production Cluster 64GB 節點擴充方案 (年度約)',
                'estimated_cost' => 68000,
                'urgency' => '普通 (一週內)',
                'purpose' => '因應用戶量大幅成長，提升資料庫在高併發情境下的查詢效能與穩定度。',
            ],
            'status' => 'pending',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req6->id,
            'step' => 1,
            'approver_id' => $admin->id,
            'status' => 'pending',
        ]);

        // 單據 7: 張經理 - 客戶機構技術諮詢公出單 (已核准)
        $req7 = FormRequest::create([
            'form_id' => $tripForm->id,
            'user_id' => $manager->id,
            'request_no' => 'REQ-' . date('Ymd', strtotime('-2 days')) . '-0007',
            'title' => '張經理 - 國泰世華金控總部架構諮詢公出',
            'data' => [
                'trip_date' => date('Y-m-d', strtotime('-2 days')),
                'client_name' => '國泰世華商業銀行 資訊處',
                'destination' => '台北市信義區松仁路 7 號',
                'transportation' => '大眾捷運/公車',
                'agenda' => '商討企業級 SSO 與 EIP 權限架構相容性規範。',
            ],
            'status' => 'approved',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req7->id,
            'step' => 1,
            'approver_id' => $admin->id,
            'status' => 'approved',
            'comment' => '同意公出。請隨時回報洽商進度。',
        ]);

        // 單據 8: 陳同仁 - 未檢附發票之耗材代墊 (已駁回示範)
        $req8 = FormRequest::create([
            'form_id' => $expenseForm->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-' . date('Ymd', strtotime('-4 days')) . '-0008',
            'title' => '陳同仁 - 文具與白板筆代墊款項 (NT$ 1,200)',
            'data' => [
                'expense_type' => '辦公耗材',
                'amount' => 1200,
                'invoice_no' => '未檢附',
                'description' => '緊急採買討論用玻璃白板專用筆與磁鐵。',
            ],
            'status' => 'rejected',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req8->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'rejected',
            'comment' => '依據財務規定，未檢附統一發票統編憑證無法報支，請向店家重新索取發票後再行送件。',
        ]);

        // 單據 9: 林專員 (HR) - 季度茶會點心採購 (已核准)
        $req9 = FormRequest::create([
            'form_id' => $purchaseForm->id,
            'user_id' => $hrUser->id,
            'request_no' => 'REQ-' . date('Ymd', strtotime('-1 day')) . '-0009',
            'title' => '林專員 - Q4 全員大會暨慶生茶會餐飲點心採購',
            'data' => [
                'item_category' => '周邊顯示器/硬體',
                'item_name' => '知名烘焙坊精選茶點與新鮮水果盒 (共 50 人份)',
                'estimated_cost' => 6500,
                'urgency' => '普通 (一週內)',
                'purpose' => '提升同仁向心力與跨部門情感交流，已列入年度福利預算。',
            ],
            'status' => 'approved',
            'current_step' => 1,
        ]);
        ApprovalRecord::create([
            'form_request_id' => $req9->id,
            'step' => 1,
            'approver_id' => $admin->id,
            'status' => 'approved',
            'comment' => '准予動支福委會編列預算。',
        ]);

        // 6. 示範考勤打卡紀錄
        $users = [$admin, $manager, $employee, $hrUser];
        for ($i = 5; $i >= 1; $i--) {
            $date = now()->subDays($i)->toDateString();
            foreach ($users as $u) {
                Attendance::create([
                    'user_id' => $u->id,
                    'date' => $date,
                    'clock_in_at' => Carbon::parse("{$date} 08:55:00"),
                    'clock_in_ip' => '192.168.1.100',
                    'clock_in_location' => '台北總部辦公室',
                    'clock_out_at' => Carbon::parse("{$date} 18:10:00"),
                    'clock_out_ip' => '192.168.1.100',
                    'clock_out_location' => '台北總部辦公室',
                    'status' => 'normal',
                    'work_hours' => 8.5,
                ]);
            }
        }

        // 今日打卡示範 (陳同仁已打上班卡)
        Attendance::create([
            'user_id' => $employee->id,
            'date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(8, 50),
            'clock_in_ip' => '192.168.1.102',
            'clock_in_location' => '台北總部辦公室',
            'status' => 'normal',
            'work_hours' => 0,
        ]);

        // 今日打卡示範 (張經理已結算)
        Attendance::create([
            'user_id' => $manager->id,
            'date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 10),
            'clock_in_ip' => '192.168.1.101',
            'clock_in_location' => '台北總部辦公室',
            'clock_out_at' => now()->setTime(18, 15),
            'clock_out_ip' => '192.168.1.101',
            'clock_out_location' => '台北總部辦公室',
            'status' => 'normal',
            'work_hours' => 8.2,
        ]);

        // 7. 會議室資料
        $room1 = MeetingRoom::create([
            'name' => '101 創想會議室',
            'location' => '台北總部 A棟 1F',
            'capacity' => 8,
            'equipment' => ['投影機', '視訊會議設備', '傳統白板'],
            'is_active' => true,
            'description' => '適合敏捷小組討論與日常站立會議。',
        ]);

        $room2 = MeetingRoom::create([
            'name' => '201 研討與發表室',
            'location' => '台北總部 A棟 2F',
            'capacity' => 20,
            'equipment' => ['投影機', '視訊會議設備', '電子白板', '獨立音響'],
            'is_active' => true,
            'description' => '大型多功能會議室，配備舞台投影與音響。',
        ]);

        $room3 = MeetingRoom::create([
            'name' => 'VIP 戰略決策室',
            'location' => '台北總部 B棟 6F',
            'capacity' => 12,
            'equipment' => ['視訊會議設備', '會議電話', '茶水設備'],
            'is_active' => true,
            'description' => '主管決策會議專用，隔音效果佳。',
        ]);

        // 8. 示範會議預約
        RoomBooking::create([
            'meeting_room_id' => $room1->id,
            'user_id' => $manager->id,
            'title' => '2026 Q4 技術架構評審',
            'description' => '討論後端架構升級與 Docker Compose 部署方案',
            'start_time' => now()->setTime(14, 0),
            'end_time' => now()->setTime(15, 30),
            'attendees_count' => 6,
            'status' => 'confirmed',
        ]);

        RoomBooking::create([
            'meeting_room_id' => $room1->id,
            'user_id' => $employee->id,
            'title' => '前端元件庫重構對齊會',
            'description' => '對齊 Vue 3 + Tailwind CSS 設計規範',
            'start_time' => now()->setTime(16, 0),
            'end_time' => now()->setTime(17, 0),
            'attendees_count' => 4,
            'status' => 'confirmed',
        ]);

        RoomBooking::create([
            'meeting_room_id' => $room2->id,
            'user_id' => $hrUser->id,
            'title' => '新進同仁職前培訓',
            'description' => '企業文化與內部 EIP 系統操作指南教學',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 30),
            'attendees_count' => 12,
            'status' => 'confirmed',
        ]);

        // 9. 企業文件庫範例
        Storage::disk('local')->put('documents/demo_leave_policy_v1.pdf', '%PDF-1.4 示範員工請假及考勤規章內容');
        Storage::disk('local')->put('documents/demo_leave_policy_v2.pdf', '%PDF-1.4 示範員工請假及考勤規章最新修正版 (v2.0)');
        Storage::disk('local')->put('documents/demo_expense_template.xlsx', '示範出差旅費報支單範本檔案內容');
        Storage::disk('local')->put('documents/demo_security_spec.pdf', '%PDF-1.4 示範資安防護規格書機密內容');

        // 文件 1: 員工請假及出勤管理辦法 (具雙版本歷程)
        $doc1 = Document::create([
            'title' => '2026 年度員工請假及出勤管理辦法',
            'category' => 'policy',
            'description' => '涵蓋特休計算標準、事病假請假規則、遲到早退工時扣抵規範。',
            'department_id' => $hr->id,
            'uploader_id' => $hrUser->id,
            'current_version' => 2,
            'download_count' => 18,
            'restricted_roles' => null, // 全員公開
        ]);

        DocumentVersion::create([
            'document_id' => $doc1->id,
            'uploader_id' => $hrUser->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => 'documents/demo_leave_policy_v1.pdf',
            'file_name' => '2026_員工出勤管理要點_初版.pdf',
            'file_size' => 1024 * 350, // 350 KB
            'mime_type' => 'application/pdf',
            'changelog' => '年初人事新規初版發布',
        ]);

        DocumentVersion::create([
            'document_id' => $doc1->id,
            'uploader_id' => $hrUser->id,
            'version_number' => 2,
            'version_label' => 'v2.0',
            'file_path' => 'documents/demo_leave_policy_v2.pdf',
            'file_name' => '2026_員工出勤管理要點_修訂版.pdf',
            'file_size' => 1024 * 420, // 420 KB
            'mime_type' => 'application/pdf',
            'changelog' => '配合勞基法修訂第 4 條特休提前結算辦法',
        ]);

        // 文件 2: 差旅報銷標準 Excel 範本
        $doc2 = Document::create([
            'title' => '國內外出差旅費報銷標準範本',
            'category' => 'template',
            'description' => '含高鐵、住宿、膳雜費每日上限試算公式，填畢後請檢附單據送簽核。',
            'department_id' => null, // 全公司共用
            'uploader_id' => $admin->id,
            'current_version' => 1,
            'download_count' => 45,
            'restricted_roles' => null,
        ]);

        DocumentVersion::create([
            'document_id' => $doc2->id,
            'uploader_id' => $admin->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => 'documents/demo_expense_template.xlsx',
            'file_name' => 'EIP_出差報銷申請單_v1.0.xlsx',
            'file_size' => 1024 * 128, // 128 KB
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'changelog' => '全公司適用初版 Excel 報支範本',
        ]);

        // 文件 3: 企業雲端架構資安合規手冊 (主管機密限定)
        $doc3 = Document::create([
            'title' => '雲端核心架構資安稽核與金鑰管理規範',
            'category' => 'tech',
            'description' => '機密等級：僅部門主管與系統管理員具備閱覽授權。含正式環境存取金鑰作業程序。',
            'department_id' => $rd->id,
            'uploader_id' => $manager->id,
            'current_version' => 1,
            'download_count' => 5,
            'restricted_roles' => ['admin', 'manager'], // 機密限定
        ]);

        DocumentVersion::create([
            'document_id' => $doc3->id,
            'uploader_id' => $manager->id,
            'version_number' => 1,
            'version_label' => 'v1.0',
            'file_path' => 'documents/demo_security_spec.pdf',
            'file_name' => 'EIP_資安規範與金鑰管理規約_CONFIDENTIAL.pdf',
            'file_size' => 1024 * 1024 * 2, // 2 MB
            'mime_type' => 'application/pdf',
            'changelog' => '研發主管制訂初版機密規範',
        ]);
    }
}
