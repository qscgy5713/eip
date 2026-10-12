<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** 國定假日 / 補班日設定(人資、系統管理員維護;所有登入同仁可查詢工作日數) */
class HolidayController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isHr(), 403, '僅人資或系統管理員可維護假日日曆。');
    }

    public function index(Request $request): Response
    {
        $year = (int) $request->input('year', now()->year);
        $year = ($year >= 2000 && $year <= 2100) ? $year : (int) now()->year;

        return Inertia::render('Holidays/Index', [
            'year' => $year,
            'holidays' => Holiday::whereYear('date', $year)->orderBy('date')->get(['id', 'date', 'name', 'type']),
            'canManage' => $request->user()->isAdmin() || $request->user()->isHr(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in([Holiday::TYPE_HOLIDAY, Holiday::TYPE_WORKDAY])],
        ], [
            'date.unique' => '這一天已經登錄過了。',
        ], ['date' => '日期', 'name' => '名稱', 'type' => '類型']);

        $holiday = Holiday::create($validated);
        AuditLog::log(action: 'create_holiday', description: "{$request->user()->name} 新增了假日設定 {$holiday->date->toDateString()} {$holiday->name}", auditable: $holiday);

        return back()->with('success', '已新增。');
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->authorizeManage($request);

        AuditLog::log(action: 'delete_holiday', description: "{$request->user()->name} 刪除了假日設定 {$holiday->date->toDateString()} {$holiday->name}", auditable: $holiday);
        $holiday->delete();

        return back()->with('success', '已刪除。');
    }

    /** 載入某年度日期固定的國定假日(已存在的日期不覆蓋) */
    public function seedFixed(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $year = (int) $request->validate(['year' => ['required', 'integer', 'between:2000,2100']])['year'];

        $added = 0;
        foreach (Holiday::FIXED_DATE_HOLIDAYS as $md => $name) {
            $created = Holiday::firstOrCreate(['date' => "{$year}-{$md}"], ['name' => $name, 'type' => Holiday::TYPE_HOLIDAY]);
            $added += $created->wasRecentlyCreated ? 1 : 0;
        }
        AuditLog::log(action: 'seed_fixed_holidays', description: "{$request->user()->name} 載入了 {$year} 年固定日期國定假日(新增 {$added} 筆)");

        return back()->with('success', "已載入 {$year} 年固定日期假日(新增 {$added} 筆)。春節、清明、端午、中秋與補假、補班日請依人事行政總處公告自行新增。");
    }

    /** 查詢區間的工作日數,供請假單自動帶入天數 */
    public function workdays(Request $request, WorkdayService $workdays): JsonResponse
    {
        $v = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json(['days' => $workdays->countWorkdays(Carbon::parse($v['start']), Carbon::parse($v['end']))]);
    }
}
