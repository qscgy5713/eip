<?php

namespace App\Http\Controllers;

use App\Models\CourseRegistration;
use App\Models\TrainingCourse;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingCourseController extends Controller
{
    /**
     * 企業內訓課程總表與學習學分檔案
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = TrainingCourse::with(['creator:id,name', 'registrations.user.department']);

        // 搜尋過濾
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('course_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('instructor', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // 頁籤過濾：all(全體課程) 或 mine(我的報名與學分歷程)
        $tab = $request->input('tab', 'all');
        if ($tab === 'mine') {
            $query->whereHas('registrations', function ($rq) use ($user) {
                $rq->where('user_id', $user->id);
            });
        }

        $courses = $query->orderBy('start_time', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(9)
            ->withQueryString();

        // 附加當前使用者的報名狀態至各課程中
        $myRegistrations = CourseRegistration::where('user_id', $user->id)
            ->pluck('status', 'training_course_id');

        $courses->getCollection()->transform(function ($course) use ($myRegistrations) {
            $course->my_registration_status = $myRegistrations[$course->id] ?? null;
            return $course;
        });

        // 營運指標數據
        $metrics = [
            'upcoming_courses' => TrainingCourse::where('status', 'upcoming')->count(),
            'total_participants' => CourseRegistration::whereIn('status', ['registered', 'attended'])->count(),
            'my_credits' => $user->earned_credits,
            'my_hours' => $user->earned_hours,
        ];

        return Inertia::render('Trainings/Index', [
            'courses' => $courses,
            'metrics' => $metrics,
            'filters' => [
                'category' => $request->input('category', ''),
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
                'tab' => $tab,
            ],
            'canManageAll' => $user->isAdmin() || $user->hasRole('hr'),
        ]);
    }

    /**
     * 開設新內訓課程表單
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->hasRole('hr')) {
            abort(403, '僅人資或系統管理人員可開辦企業內訓課程');
        }

        return Inertia::render('Trainings/Create');
    }

    /**
     * 儲存新內訓課程
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->hasRole('hr')) {
            abort(403, '僅人資或系統管理人員可開辦企業內訓課程');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructor' => ['required', 'string', 'max:100'],
            'instructor_title' => ['nullable', 'string', 'max:150'],
            'category' => ['required', 'string', 'in:technical,management,compliance,general'],
            'location' => ['required', 'string', 'max:150'],
            'start_time' => ['required', 'date', 'after:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'max_participants' => ['required', 'integer', 'min:0', 'max:500'],
            'credits' => ['required', 'integer', 'min:1', 'max:20'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'status' => ['required', 'string', 'in:draft,upcoming'],
        ]);

        $course = TrainingCourse::create([
            'course_code' => TrainingCourse::generateCourseCode(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'instructor' => $validated['instructor'],
            'instructor_title' => $validated['instructor_title'] ?? null,
            'category' => $validated['category'],
            'location' => $validated['location'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'max_participants' => $validated['max_participants'],
            'credits' => $validated['credits'],
            'hours' => $validated['hours'],
            'status' => $validated['status'],
            'creator_id' => $user->id,
        ]);

        return redirect()->route('trainings.show', $course)->with('success', "內訓課程已成功建立！代碼：{$course->course_code}");
    }

    /**
     * 檢視課程詳情、學員名單與簽到控制台
     */
    public function show(Request $request, TrainingCourse $trainingCourse): Response
    {
        $trainingCourse->load([
            'creator:id,name',
            'registrations' => function ($q) {
                $q->with('user.department')->orderBy('registered_at', 'asc');
            },
        ]);

        $user = $request->user();
        $myRegistration = CourseRegistration::where('training_course_id', $trainingCourse->id)
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('Trainings/Show', [
            'course' => $trainingCourse,
            'myRegistration' => $myRegistration,
            'canManage' => $trainingCourse->canManage($user),
        ]);
    }

    /**
     * 同仁線上報名內訓課程 (額滿自動遞補機制)
     */
    public function register(Request $request, TrainingCourse $trainingCourse): RedirectResponse
    {
        $user = $request->user();

        if ($trainingCourse->status !== 'upcoming') {
            return back()->withErrors(['error' => '此課程目前未開放報名或已結束']);
        }

        // 檢查是否已有報名紀錄
        $existing = CourseRegistration::where('training_course_id', $trainingCourse->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'cancelled') {
                // 重新報名
                $isFull = $trainingCourse->is_full;
                $newStatus = $isFull ? 'waitlist' : 'registered';
                $existing->update([
                    'status' => $newStatus,
                    'registered_at' => now(),
                ]);

                $msg = $isFull
                    ? '課程正取已額滿，已為您登記至【候補名單】！有名額時將自動依序遞補。'
                    : '已成功重新報名內訓課程！請準時出席。';
                return back()->with('success', $msg);
            }

            return back()->withErrors(['error' => '您已經報名過此內訓課程，無需重複報名！']);
        }

        // 依額滿狀態決定正取或候補
        $isFull = $trainingCourse->is_full;
        $status = $isFull ? 'waitlist' : 'registered';

        CourseRegistration::create([
            'training_course_id' => $trainingCourse->id,
            'user_id' => $user->id,
            'status' => $status,
            'registered_at' => now(),
        ]);

        // 發送通知給學員
        $noticeTitle = $isFull ? '【內訓候補成功】' : '【內訓報名成功】';
        $noticeMsg = $isFull
            ? "您已加入《{$trainingCourse->title}》候補名單，若有名額釋出系統將主動通知您。"
            : "您已成功報名《{$trainingCourse->title}》，上課時間：{$trainingCourse->start_time->format('Y-m-d H:i')}，地點：{$trainingCourse->location}。";

        $user->notify(new EipSystemNotification(
            title: $noticeTitle . $trainingCourse->title,
            message: $noticeMsg,
            type: 'training',
            actionUrl: route('trainings.show', $trainingCourse),
            senderName: '人資訓練中心'
        ));

        $msg = $isFull
            ? '課程正取名額已額滿，已為您登記於【候補名單】！'
            : '報名成功！期待您的蒞臨與學習成長！';

        return back()->with('success', $msg);
    }

    /**
     * 取消報名 (自動促發候補名單遞補)
     */
    public function cancelRegistration(Request $request, TrainingCourse $trainingCourse): RedirectResponse
    {
        $user = $request->user();

        $registration = CourseRegistration::where('training_course_id', $trainingCourse->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $registration || $registration->status === 'cancelled') {
            return back()->withErrors(['error' => '您未報名此課程或已取消']);
        }

        $wasRegistered = $registration->status === 'registered';

        $registration->update([
            'status' => 'cancelled',
        ]);

        // 若取消者原本為正取，自動遞補候補名單第一位
        if ($wasRegistered) {
            $nextCandidate = CourseRegistration::where('training_course_id', $trainingCourse->id)
                ->where('status', 'waitlist')
                ->orderBy('registered_at', 'asc')
                ->first();

            if ($nextCandidate) {
                $nextCandidate->update(['status' => 'registered']);

                // 發送恭喜遞補通知
                $nextCandidate->user->notify(new EipSystemNotification(
                    title: '【候補遞補成功】內訓正取通知',
                    message: "恭喜！您在內訓課程《{$trainingCourse->title}》的候補已成功遞補為【正取學員】！請準時出席！",
                    type: 'training',
                    actionUrl: route('trainings.show', $trainingCourse),
                    senderName: '人資訓練中心'
                ));
            }
        }

        return back()->with('success', '已成功取消報名。名額已釋出供同仁遞補。');
    }

    /**
     * 講師 / HR 點名簽到並核發研習學分
     */
    public function checkIn(Request $request, TrainingCourse $trainingCourse, CourseRegistration $registration): RedirectResponse
    {
        if (! $trainingCourse->canManage($request->user())) {
            abort(403, '僅課程主辦人或管理員可進行簽到點名');
        }

        $registration->update([
            'status' => 'attended',
            'attended_at' => now(),
        ]);

        // 發送完訓與學分入帳通知
        $registration->user->notify(new EipSystemNotification(
            title: '【完訓合格】研習學分入帳通知',
            message: "恭喜完成《{$trainingCourse->title}》培訓！已核發 {$trainingCourse->credits} 學分與 {$trainingCourse->hours} 小時研習認證！",
            type: 'training',
            actionUrl: route('trainings.show', $trainingCourse),
            senderName: '人資訓練中心'
        ));

        return back()->with('success', "已完成學員 {$registration->user->name} 簽到完訓並核發 {$trainingCourse->credits} 學分！");
    }

    /**
     * 學員填寫完訓滿意度與課後心得
     */
    public function submitFeedback(Request $request, TrainingCourse $trainingCourse): RedirectResponse
    {
        $user = $request->user();

        $registration = CourseRegistration::where('training_course_id', $trainingCourse->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $registration->update([
            'rating' => $validated['rating'],
            'feedback' => $validated['feedback'] ?? null,
        ]);

        return back()->with('success', '感謝您的課後回饋，您的寶貴意見將作為下期課程之精進依據！');
    }

    /**
     * 變更課程狀態 (授課中 / 結訓 / 停辦)
     */
    public function updateStatus(Request $request, TrainingCourse $trainingCourse): RedirectResponse
    {
        if (! $trainingCourse->canManage($request->user())) {
            abort(403, '無權限更動課程狀態');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:upcoming,in_progress,completed,cancelled'],
        ]);

        $trainingCourse->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', "課程狀態已更新為【{$trainingCourse->status_label}】。");
    }

    /**
     * ESG 人力資本培訓時數總表 CSV 匯出
     */
    public function exportLog(Request $request): StreamedResponse
    {
        $fileName = 'training_credits_report_' . date('Ymd_His') . '.csv';

        $query = CourseRegistration::with(['course', 'user.department'])
            ->where('status', 'attended');

        if ($request->filled('category')) {
            $query->whereHas('course', fn ($cq) => $cq->where('category', $request->category));
        }

        $records = $query->orderBy('attended_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                '課程代碼',
                '課程名稱',
                '領域類別',
                '講師',
                '學員姓名',
                '同仁編號',
                '所屬部門',
                '完訓時數 (hrs)',
                '認證學分 (credits)',
                '完訓核發時間',
                '課後滿意度 (1-5星)',
            ]);

            foreach ($records as $reg) {
                $sanitize = function ($value) {
                    if ($value === null) {
                        return '';
                    }
                    $str = (string) $value;
                    if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"])) {
                        return "'" . $str;
                    }
                    return $str;
                };

                fputcsv($handle, [
                    $sanitize($reg->course->course_code),
                    $sanitize($reg->course->title),
                    $sanitize($reg->course->category_label),
                    $sanitize($reg->course->instructor),
                    $sanitize($reg->user->name),
                    $sanitize($reg->user->employee_id ?? 'N/A'),
                    $sanitize($reg->user->department->name ?? '總部'),
                    $sanitize($reg->course->hours),
                    $sanitize($reg->course->credits),
                    $sanitize($reg->attended_at ? $reg->attended_at->format('Y-m-d H:i:s') : '—'),
                    $sanitize($reg->rating ? $reg->rating . ' 星' : '未評分'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
