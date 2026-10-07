<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\ApprovalRecord;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            'description' => '事假、病假、特休、公假等各式差假申請',
            'fields_schema' => [
                ['key' => 'leave_type', 'label' => '假別', 'type' => 'select', 'options' => ['特休假', '事假', '病假', '公假', '婚喪假']],
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

        // 5. 示範申請單據與審批歷程
        $request1 = FormRequest::create([
            'form_id' => $leaveForm->id,
            'user_id' => $employee->id,
            'request_no' => 'REQ-' . date('Ymd') . '-0001',
            'title' => '陳同仁 - 特休申請 (1天)',
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
            'form_request_id' => $request1->id,
            'step' => 1,
            'approver_id' => $manager->id,
            'status' => 'pending',
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
    }
}
