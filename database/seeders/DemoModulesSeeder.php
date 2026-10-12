<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\CourseRegistration;
use App\Models\MealOrder;
use App\Models\MealOrderItem;
use App\Models\Payslip;
use App\Models\PointWallet;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\RecognitionPost;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TrainingCourse;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDispatch;
use App\Models\VisitorReservation;
use App\Models\WeeklyReport;
use App\Models\WelfareRedemption;
use App\Models\WelfareReward;
use Illuminate\Database\Seeder;

/**
 * 示範資料：讓新模組 (工單/派車/訂餐/內訓/訪客/專案/資產/週報/投票/薪資/感謝幣/福利) 的列表與詳情頁有內容可檢視。
 * 可重複執行 (以唯一代號 firstOrCreate)。僅供開發環境使用：php artisan db:seed --class=DemoModulesSeeder
 */
class DemoModulesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@eip.local')->first();
        $manager = User::where('email', 'manager@eip.local')->first() ?? $admin;
        $employee = User::where('email', 'employee@eip.local')->first() ?? $admin;
        $hr = User::where('email', 'hr@eip.local')->first() ?? $admin;

        if (! $admin) {
            $this->command?->warn('找不到 admin@eip.local，請先執行 EipDatabaseSeeder。');

            return;
        }

        $this->tickets($admin, $employee, $manager);
        $this->vehicles($admin, $employee, $manager);
        $this->mealOrders($admin, $employee, $manager);
        $this->trainings($admin, $employee, $hr);
        $this->visitors($employee, $manager);
        $this->projects($admin, $employee, $manager);
        $this->assets($admin, $employee);
        $this->weeklyReports($employee, $manager);
        $this->polls($admin, $employee);
        $this->payslips($employee, $hr);
        $this->recognition($admin, $employee, $manager, $hr);
    }

    private function tickets(User $admin, User $employee, User $manager): void
    {
        $open = Ticket::firstOrCreate(['ticket_number' => 'TK-DEMO-0001'], [
            'user_id' => $employee->id, 'category' => 'it_hardware', 'priority' => 'high', 'status' => 'open',
            'title' => '會議室投影機無法開機', 'description' => '301 會議室投影機電源燈不亮，下午客戶簡報需要使用，請協助檢修。',
            'location' => '總部 3F 301 會議室',
        ]);

        $progress = Ticket::firstOrCreate(['ticket_number' => 'TK-DEMO-0002'], [
            'user_id' => $employee->id, 'assigned_to' => $admin->id, 'category' => 'it_network', 'priority' => 'normal',
            'status' => 'in_progress', 'title' => '研發部 Wi-Fi 訊號不穩', 'description' => '研發部座位區每天下午都會斷線，影響視訊會議。',
            'location' => '總部 5F 研發部',
        ]);
        TicketReply::firstOrCreate(['ticket_id' => $progress->id, 'user_id' => $admin->id, 'message' => '已收到，明天上午會到現場量測訊號強度。']);
        TicketReply::firstOrCreate(['ticket_id' => $progress->id, 'user_id' => $admin->id, 'message' => '疑似 AP 韌體問題，先排程更新。', 'is_internal' => true]);

        Ticket::firstOrCreate(['ticket_number' => 'TK-DEMO-0003'], [
            'user_id' => $manager->id, 'assigned_to' => $admin->id, 'category' => 'facility', 'priority' => 'low',
            'status' => 'resolved', 'title' => '茶水間日光燈閃爍', 'description' => '茶水間燈管持續閃爍。',
            'location' => '總部 4F 茶水間', 'resolved_at' => now()->subHours(3),
        ]);
    }

    private function vehicles(User $admin, User $employee, User $manager): void
    {
        $camry = Vehicle::firstOrCreate(['plate_number' => 'DEMO-1001'], [
            'name' => 'Toyota Camry 2.5 Hybrid', 'vehicle_type' => 'sedan', 'capacity' => 5, 'fuel_type' => 'hybrid',
            'current_mileage' => 48200, 'last_maintenance_mileage' => 45000, 'status' => 'available', 'parking_location' => '地下 B2-15',
        ]);
        $van = Vehicle::firstOrCreate(['plate_number' => 'DEMO-2002'], [
            'name' => 'Toyota Alphard 商務車', 'vehicle_type' => 'van', 'capacity' => 7, 'fuel_type' => 'gasoline',
            'current_mileage' => 31800, 'last_maintenance_mileage' => 25000, 'status' => 'available', 'parking_location' => '地下 B2-08',
            'notes' => '機場接送與貴賓用車，請保持車內整潔。',
        ]);

        VehicleDispatch::firstOrCreate(['dispatch_number' => 'VD-DEMO-0001'], [
            'user_id' => $employee->id, 'vehicle_id' => $camry->id, 'start_time' => now()->addDay()->setTime(9, 0),
            'end_time' => now()->addDay()->setTime(17, 0), 'destination' => '新竹科學園區 客戶端', 'purpose' => 'client_visit',
            'passenger_count' => 3, 'status' => 'pending',
        ]);
        VehicleDispatch::firstOrCreate(['dispatch_number' => 'VD-DEMO-0002'], [
            'user_id' => $manager->id, 'vehicle_id' => $van->id, 'approved_by' => $admin->id,
            'start_time' => now()->addDays(3)->setTime(7, 30), 'end_time' => now()->addDays(3)->setTime(11, 0),
            'destination' => '桃園國際機場 第二航廈', 'purpose' => 'airport_pickup', 'passenger_count' => 2, 'status' => 'approved',
        ]);
    }

    private function mealOrders(User $admin, User $employee, User $manager): void
    {
        $order = MealOrder::firstOrCreate(['title' => '週三下午茶：迷客夏 (示範)'], [
            'creator_id' => $manager->id, 'store_name' => '迷客夏 信義店', 'store_phone' => '02-2345-6789',
            'description' => '公司每人補助 50 元，數量不限，請在截止前登記。', 'cutoff_time' => now()->addHours(5),
            'subsidy_per_person' => 50, 'status' => 'open',
        ]);

        foreach ([[$employee, '大甲芋頭鮮奶', '大杯 / 微糖微冰', 65], [$admin, '玉露青茶', '中杯 / 無糖去冰', 40], [$manager, '珍珠紅茶拿鐵', '大杯 / 半糖少冰', 70]] as [$u, $name, $spec, $price]) {
            MealOrderItem::firstOrCreate(['meal_order_id' => $order->id, 'user_id' => $u->id, 'item_name' => $name], [
                'specification' => $spec, 'unit_price' => $price, 'quantity' => 1, 'subtotal' => $price,
            ]);
        }
    }

    private function trainings(User $admin, User $employee, User $hr): void
    {
        $course = TrainingCourse::firstOrCreate(['course_code' => 'TC-DEMO-0001'], [
            'title' => '資訊安全與個資保護實務 (示範)', 'description' => "涵蓋社交工程辨識、密碼管理與個資法遵循。\n課後附隨堂測驗。",
            'instructor' => '李資安', 'instructor_title' => '資安長', 'category' => 'compliance',
            'location' => '總部 3F 階梯教室', 'start_time' => now()->addDays(5)->setTime(14, 0), 'end_time' => now()->addDays(5)->setTime(17, 0),
            'max_participants' => 2, 'credits' => 3, 'hours' => 3, 'status' => 'upcoming', 'creator_id' => $hr->id,
        ]);

        CourseRegistration::firstOrCreate(['training_course_id' => $course->id, 'user_id' => $employee->id], ['status' => 'registered', 'registered_at' => now()]);
        CourseRegistration::firstOrCreate(['training_course_id' => $course->id, 'user_id' => $admin->id], ['status' => 'registered', 'registered_at' => now()]);
        CourseRegistration::firstOrCreate(['training_course_id' => $course->id, 'user_id' => $hr->id], ['status' => 'waitlist', 'registered_at' => now()]);
    }

    private function visitors(User $employee, User $manager): void
    {
        VisitorReservation::firstOrCreate(['reservation_code' => 'VR-DEMO-0001'], [
            'host_id' => $employee->id, 'visitor_name' => '陳雅婷', 'visitor_company' => '宏達科技股份有限公司',
            'visitor_email' => 'ya.ting@example.com', 'visitor_phone' => '0912-345-678', 'visit_date' => now()->toDateString(),
            'expected_arrival_time' => '14:00', 'purpose' => 'business', 'visitor_count' => 2, 'status' => 'expected',
            'notes' => '請於大廳櫃檯領取訪客證。',
        ]);
        VisitorReservation::firstOrCreate(['reservation_code' => 'VR-DEMO-0002'], [
            'host_id' => $manager->id, 'visitor_name' => '林志豪', 'visitor_company' => '遠景稽核事務所',
            'visit_date' => now()->toDateString(), 'expected_arrival_time' => '10:30', 'purpose' => 'audit', 'visitor_count' => 1,
            'status' => 'checked_in', 'actual_check_in_at' => now()->subHour(), 'badge_number' => 'V-018',
        ]);
    }

    private function projects(User $admin, User $employee, User $manager): void
    {
        $project = Project::firstOrCreate(['code' => 'PRJ-DEMO-01'], [
            'name' => 'EIP 行動版體驗優化 (示範)', 'description' => '盤點手機版版面問題並分批改善。', 'owner_id' => $manager->id,
            'status' => 'active', 'priority' => 'high', 'start_date' => now()->subWeeks(1)->toDateString(), 'due_date' => now()->addWeeks(3)->toDateString(),
        ]);
        foreach ([[$manager, 'lead'], [$employee, 'member'], [$admin, 'member']] as [$u, $role]) {
            ProjectMember::firstOrCreate(['project_id' => $project->id, 'user_id' => $u->id], ['role' => $role]);
        }

        $tasks = [
            ['盤點各頁面手機版溢出問題', 'done', 'high', $employee, 8],
            ['導覽列改為分組下拉', 'review', 'high', $employee, 6],
            ['深色模式樣式補齊', 'in_progress', 'medium', $admin, 10],
            ['撰寫視覺驗收清單', 'todo', 'low', $manager, 3],
        ];
        foreach ($tasks as $i => [$title, $status, $priority, $assignee, $hours]) {
            Task::firstOrCreate(['project_id' => $project->id, 'title' => $title], [
                'status' => $status, 'priority' => $priority, 'assignee_id' => $assignee->id, 'creator_id' => $manager->id,
                'due_date' => now()->addDays($i + 2)->toDateString(), 'estimated_hours' => $hours, 'sort_order' => $i,
                'completed_at' => $status === 'done' ? now()->subDay() : null,
            ]);
        }
    }

    private function assets(User $admin, User $employee): void
    {
        $laptop = Asset::firstOrCreate(['asset_tag' => 'AST-DEMO-0001'], [
            'name' => 'MacBook Pro 14" (示範)', 'category' => 'it_hardware', 'serial_number' => 'C02XXDEMO001',
            'specification' => 'M3 Pro / 36GB / 1TB', 'location' => '總部 5F 資訊室', 'status' => 'in_use',
            'current_holder_id' => $employee->id, 'purchase_date' => now()->subMonths(8)->toDateString(), 'purchase_cost' => 78900,
        ]);
        AssetLoan::firstOrCreate(['asset_id' => $laptop->id, 'user_id' => $employee->id, 'status' => 'borrowed'], [
            'borrowed_at' => now()->subDays(10), 'expected_return_date' => now()->addDays(20)->toDateString(),
            'purpose' => '外勤簡報與客戶展示', 'handled_by' => $admin->id,
        ]);

        Asset::firstOrCreate(['asset_tag' => 'AST-DEMO-0002'], [
            'name' => 'Sony A7 IV 攝影機 (示範)', 'category' => 'av_media', 'serial_number' => 'SNY-DEMO-7788',
            'specification' => '含 24-70mm 鏡頭、雙電池', 'location' => '總部 2F 總務處', 'status' => 'available',
            'purchase_date' => now()->subYear()->toDateString(), 'purchase_cost' => 69800,
        ]);
    }

    private function weeklyReports(User $employee, User $manager): void
    {
        $start = now()->startOfWeek();
        WeeklyReport::firstOrCreate(['user_id' => $employee->id, 'year' => (int) $start->format('o'), 'week_number' => (int) $start->format('W')], [
            'department_id' => $employee->department_id, 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->endOfWeek()->toDateString(),
            'summary' => '完成手機版面檢查與導覽列重構。', 'achievements' => "1. 修正 29 個頁面左右留白\n2. 導覽列改為分組下拉\n3. 補齊深色模式樣式",
            'plans_next_week' => "1. 驗收詳情頁\n2. 撰寫驗收清單", 'challenges_or_support' => '需要測試帳號與示範資料。',
            'status' => 'submitted', 'submitted_at' => now(),
        ]);
    }

    private function polls(User $admin, User $employee): void
    {
        $poll = Poll::firstOrCreate(['title' => '年度尾牙地點票選 (示範)'], [
            'creator_id' => $admin->id, 'description' => '請選出你偏好的尾牙場地，得票最高者將列為首選。',
            'is_multiple_choice' => false, 'is_anonymous' => true, 'ends_at' => now()->addDays(7), 'status' => 'active',
        ]);
        foreach (['海鮮餐廳 (信義區)', '日式燒肉 (大安區)', '河濱戶外烤肉'] as $i => $text) {
            PollOption::firstOrCreate(['poll_id' => $poll->id, 'option_text' => $text], ['sort_order' => $i]);
        }
    }

    private function payslips(User $employee, User $hr): void
    {
        $base = 52000; $allow = 3000; $perf = 5000; $ot = 1800; $other = 0;
        $labor = 1100; $health = 780; $pension = 0; $tax = 1500; $otherD = 0;
        $earn = $base + $allow + $perf + $ot + $other;
        $ded = $labor + $health + $pension + $tax + $otherD;

        $last = now()->subMonth();
        Payslip::firstOrCreate(['user_id' => $employee->id, 'year' => (int) $last->format('Y'), 'month' => (int) $last->format('n')], [
            'base_salary' => $base, 'allowance' => $allow, 'performance_bonus' => $perf, 'overtime_pay' => $ot, 'other_earnings' => $other,
            'labor_insurance' => $labor, 'health_insurance' => $health, 'pension_contribution' => $pension, 'tax_withholding' => $tax, 'other_deductions' => $otherD,
            'total_earnings' => $earn, 'total_deductions' => $ded, 'net_pay' => $earn - $ded,
            'status' => 'published', 'published_at' => now()->subDays(5), 'notes' => '含九月專案獎金。',
        ]);
    }

    private function recognition(User $admin, User $employee, User $manager, User $hr): void
    {
        foreach ([[$manager, $employee, 20, 'teamwork', '謝謝你連假期間處理線上事故，讓客戶順利度過上線日！', false],
                  [$employee, $admin, 15, 'helpfulness', '感謝協助處理 VPN 權限，兩分鐘就搞定。', false],
                  [$hr, $employee, 10, 'innovation', '提出的導覽列分組方案讓大家找功能快很多。', true]] as [$from, $to, $pts, $value, $msg, $anon]) {
            RecognitionPost::firstOrCreate(['sender_id' => $from->id, 'recipient_id' => $to->id, 'message' => $msg], [
                'points' => $pts, 'core_value' => $value, 'is_anonymous' => $anon,
            ]);
        }

        $wallet = PointWallet::getOrCreateForUser($employee);
        if ($wallet->reward_points === 0) {
            $wallet->update(['reward_points' => 130]);
        }

        $coffee = WelfareReward::firstOrCreate(['name' => '星巴克中杯飲品券 (示範)'], [
            'description' => '全台門市適用，兌換後由人資發放電子票券。', 'points_cost' => 50, 'stock' => 20, 'category' => 'beverage', 'is_active' => true,
        ]);
        WelfareReward::firstOrCreate(['name' => '半日榮譽休假券 (示範)'], [
            'description' => '可折抵半天特休，需主管核准排程。', 'points_cost' => 200, 'stock' => 5, 'category' => 'leave_credit', 'is_active' => true,
        ]);
        WelfareReward::firstOrCreate(['name' => '電影票兩張 (示範)'], [
            'description' => '威秀影城全台適用。', 'points_cost' => 120, 'stock' => 0, 'category' => 'leisure', 'is_active' => true,
        ]);

        WelfareRedemption::firstOrCreate(['redemption_code' => 'WR-DEMO-0001'], [
            'user_id' => $employee->id, 'welfare_reward_id' => $coffee->id, 'points_spent' => 50, 'status' => 'pending',
        ]);
    }
}
