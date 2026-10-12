<?php

namespace Tests\Feature;

use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\TrainingCourse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCourseTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $secondUser;
    protected User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();

        $dept = Department::create(['name' => '資訊研發部', 'code' => 'IT']);

        $this->user = User::factory()->create([
            'name' => '王小明',
            'department_id' => $dept->id,
            'role' => 'employee',
        ]);

        $this->secondUser = User::factory()->create([
            'name' => '陳大偉',
            'department_id' => $dept->id,
            'role' => 'employee',
        ]);

        $this->hrUser = User::factory()->create([
            'name' => '林人資',
            'department_id' => $dept->id,
            'role' => 'hr',
        ]);
    }

    public function test_user_can_view_trainings_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('trainings.index'));
        $response->assertStatus(200);
    }

    public function test_hr_can_create_training_course(): void
    {
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0);
        $endTime = Carbon::now()->addDays(5)->setHour(17)->setMinute(0);

        $response = $this->actingAs($this->hrUser)->post(route('trainings.store'), [
            'title' => '生成式 AI 企業實務落地工作坊',
            'description' => '深入解析 LLM 應用架構與 Prompt Engineering 實戰。',
            'instructor' => '李架構師',
            'instructor_title' => '資深 AI 研發顧問',
            'category' => 'technical',
            'location' => '總部 3F 階梯國際會議廳',
            'start_time' => $startTime->toDateTimeString(),
            'end_time' => $endTime->toDateTimeString(),
            'max_participants' => 20,
            'credits' => 3,
            'hours' => 3.0,
            'status' => 'upcoming',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('training_courses', [
            'title' => '生成式 AI 企業實務落地工作坊',
            'instructor' => '李架構師',
            'credits' => 3,
            'creator_id' => $this->hrUser->id,
        ]);
    }

    public function test_regular_employee_cannot_create_course(): void
    {
        $startTime = Carbon::now()->addDays(5)->setHour(14)->setMinute(0);
        $endTime = Carbon::now()->addDays(5)->setHour(17)->setMinute(0);

        $response = $this->actingAs($this->user)->post(route('trainings.store'), [
            'title' => '非法人資開課測試',
            'instructor' => '測試講師',
            'category' => 'general',
            'location' => '會議室 A',
            'start_time' => $startTime->toDateTimeString(),
            'end_time' => $endTime->toDateTimeString(),
            'max_participants' => 10,
            'credits' => 1,
            'hours' => 1.0,
            'status' => 'upcoming',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_register_for_upcoming_course(): void
    {
        $course = TrainingCourse::create([
            'course_code' => 'TC-202610-0001',
            'title' => '現代前端架構演進',
            'instructor' => '黃資深工程師',
            'category' => 'technical',
            'location' => '線上會議',
            'start_time' => Carbon::now()->addDays(3)->setHour(10)->setMinute(0),
            'end_time' => Carbon::now()->addDays(3)->setHour(12)->setMinute(0),
            'max_participants' => 10,
            'credits' => 2,
            'hours' => 2.0,
            'status' => 'upcoming',
            'creator_id' => $this->hrUser->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('trainings.register', $course->id));
        $response->assertRedirect();

        $this->assertDatabaseHas('course_registrations', [
            'training_course_id' => $course->id,
            'user_id' => $this->user->id,
            'status' => 'registered',
        ]);
    }

    public function test_registration_puts_user_on_waitlist_when_full_and_auto_promotes_on_cancellation(): void
    {
        // 建立限額 1 人的課程
        $course = TrainingCourse::create([
            'course_code' => 'TC-202610-0002',
            'title' => '高階管理卓越領導力',
            'instructor' => '戴特約顧問',
            'category' => 'management',
            'location' => '總部貴賓廳',
            'start_time' => Carbon::now()->addDays(4)->setHour(9)->setMinute(0),
            'end_time' => Carbon::now()->addDays(4)->setHour(17)->setMinute(0),
            'max_participants' => 1,
            'credits' => 4,
            'hours' => 7.0,
            'status' => 'upcoming',
            'creator_id' => $this->hrUser->id,
        ]);

        // 第 1 位學員報名 -> 正取 (registered)
        $this->actingAs($this->user)->post(route('trainings.register', $course->id));
        $this->assertDatabaseHas('course_registrations', [
            'training_course_id' => $course->id,
            'user_id' => $this->user->id,
            'status' => 'registered',
        ]);

        // 第 2 位學員報名 -> 候補 (waitlist)
        $this->actingAs($this->secondUser)->post(route('trainings.register', $course->id));
        $this->assertDatabaseHas('course_registrations', [
            'training_course_id' => $course->id,
            'user_id' => $this->secondUser->id,
            'status' => 'waitlist',
        ]);

        // 第 1 位學員取消報名 -> 候補第 1 位自動遞補為正取！
        $this->actingAs($this->user)->post(route('trainings.cancel', $course->id));
        $this->assertEquals('cancelled', CourseRegistration::where('user_id', $this->user->id)->first()->status);
        $this->assertEquals('registered', CourseRegistration::where('user_id', $this->secondUser->id)->first()->status);
    }

    public function test_hr_can_check_in_participant_and_awards_credits(): void
    {
        $course = TrainingCourse::create([
            'course_code' => 'TC-202610-0003',
            'title' => '資訊安全與個人資料保護規範實務',
            'instructor' => '資安長',
            'category' => 'compliance',
            'location' => '階梯教室',
            'start_time' => Carbon::now()->addDays(2)->setHour(14)->setMinute(0),
            'end_time' => Carbon::now()->addDays(2)->setHour(16)->setMinute(0),
            'max_participants' => 50,
            'credits' => 2,
            'hours' => 2.0,
            'status' => 'upcoming',
            'creator_id' => $this->hrUser->id,
        ]);

        $reg = CourseRegistration::create([
            'training_course_id' => $course->id,
            'user_id' => $this->user->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $this->assertEquals(0, $this->user->fresh()->earned_credits);

        // HR 簽到完訓
        $response = $this->actingAs($this->hrUser)->post(route('trainings.check-in', [$course->id, $reg->id]));
        $response->assertRedirect();

        $this->assertEquals('attended', $reg->fresh()->status);
        $this->assertNotNull($reg->fresh()->attended_at);

        // 檢查使用者學分與時數累積
        $this->assertEquals(2, $this->user->fresh()->earned_credits);
        $this->assertEquals(2.0, $this->user->fresh()->earned_hours);
    }

    public function test_attended_participant_can_submit_rating_and_feedback(): void
    {
        $course = TrainingCourse::create([
            'course_code' => 'TC-202610-0004',
            'title' => '商業溝通與簡報說服力',
            'instructor' => '公關總監',
            'category' => 'general',
            'location' => '大講堂',
            'start_time' => Carbon::now()->subDay()->setHour(14)->setMinute(0),
            'end_time' => Carbon::now()->subDay()->setHour(17)->setMinute(0),
            'max_participants' => 30,
            'credits' => 3,
            'hours' => 3.0,
            'status' => 'completed',
            'creator_id' => $this->hrUser->id,
        ]);

        CourseRegistration::create([
            'training_course_id' => $course->id,
            'user_id' => $this->user->id,
            'status' => 'attended',
            'registered_at' => now()->subDays(2),
            'attended_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->post(route('trainings.feedback', $course->id), [
            'rating' => 5,
            'feedback' => '講師講解清晰生動，案例與演練非常實用！',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('course_registrations', [
            'training_course_id' => $course->id,
            'user_id' => $this->user->id,
            'rating' => 5,
            'feedback' => '講師講解清晰生動，案例與演練非常實用！',
        ]);
    }

    public function test_hr_can_update_course_status(): void
    {
        $course = TrainingCourse::create([
            'course_code' => 'TC-202610-0005',
            'title' => '敏捷專案管理 Scrum 實戰',
            'instructor' => '敏捷教練',
            'category' => 'management',
            'location' => '總部 2F',
            'start_time' => Carbon::now()->addDays(7)->setHour(9)->setMinute(0),
            'end_time' => Carbon::now()->addDays(7)->setHour(18)->setMinute(0),
            'max_participants' => 15,
            'credits' => 4,
            'hours' => 8.0,
            'status' => 'upcoming',
            'creator_id' => $this->hrUser->id,
        ]);

        $response = $this->actingAs($this->hrUser)->patch(route('trainings.status', $course->id), [
            'status' => 'in_progress',
        ]);

        $response->assertRedirect();
        $this->assertEquals('in_progress', $course->fresh()->status);
    }

    public function test_manager_can_export_trainings_csv(): void
    {
        $response = $this->actingAs($this->hrUser)->get(route('trainings.export'));
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
    }

    public function test_global_search_can_find_training_course(): void
    {
        TrainingCourse::create([
            'course_code' => 'TC-202610-9999',
            'title' => '雲端微服務架構實務',
            'instructor' => '林架構大師',
            'category' => 'technical',
            'location' => '線上',
            'start_time' => Carbon::now()->addDays(10)->setHour(14)->setMinute(0),
            'end_time' => Carbon::now()->addDays(10)->setHour(17)->setMinute(0),
            'max_participants' => 30,
            'credits' => 3,
            'hours' => 3.0,
            'status' => 'upcoming',
            'creator_id' => $this->hrUser->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('global-search', ['q' => '雲端微服務']));
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'badge' => '內訓',
        ]);
    }
}
