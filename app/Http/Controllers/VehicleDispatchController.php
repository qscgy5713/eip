<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDispatch;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleDispatchController extends Controller
{
    /**
     * 車隊管理與公務派車總表
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. 公務車隊列表與當前車況
        $vehicles = Vehicle::withCount([
            'dispatches as active_dispatches_count' => function ($q) {
                $q->whereIn('status', ['approved', 'active']);
            }
        ])->orderBy('name')->get();

        // 2. 派車紀錄查詢
        $query = VehicleDispatch::with(['user.department', 'vehicle', 'approver:id,name']);

        $scope = $request->input('scope', ($user->isAdmin() || $user->isManager()) ? 'all' : 'mine');
        if ($scope === 'mine') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('dispatch_number', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vehicle', fn ($vq) => $vq->where('plate_number', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }

        // 營運指標卡
        $metrics = [
            'total_vehicles' => $vehicles->count(),
            'available_vehicles' => $vehicles->where('status', 'available')->count(),
            'in_use_vehicles' => $vehicles->where('status', 'in_use')->count(),
            'pending_dispatches' => VehicleDispatch::where('status', 'pending')->count(),
        ];

        $dispatches = $query->orderBy('start_time', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
            'dispatches' => $dispatches,
            'metrics' => $metrics,
            'filters' => [
                'status' => $request->input('status', ''),
                'vehicle_id' => $request->input('vehicle_id', ''),
                'search' => $request->input('search', ''),
                'scope' => $scope,
            ],
            'canManageAll' => $user->isAdmin() || $user->isManager(),
        ]);
    }

    /**
     * 發起公務車派車申請表單
     */
    public function create(): Response
    {
        $availableVehicles = Vehicle::where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get();

        return Inertia::render('Vehicles/Create', [
            'vehicles' => $availableVehicles,
        ]);
    }

    /**
     * 儲存派車借調申請
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'start_time' => ['required', 'date', 'after_or_equal:now - 30 minutes'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'destination' => ['required', 'string', 'max:150'],
            'purpose' => ['required', 'string', 'in:client_visit,airport_pickup,vendor_meeting,official_errand,other'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);

        // 乘載人數檢核
        if ($validated['passenger_count'] > $vehicle->capacity) {
            return back()->withErrors(['passenger_count' => "該車輛最多僅可乘載 {$vehicle->capacity} 人，請更換大型車輛！"]);
        }

        // 防時段重疊衝突檢核
        if (! $vehicle->isAvailableBetween($validated['start_time'], $validated['end_time'])) {
            return back()->withErrors(['vehicle_id' => "該公務車於所選時段內已被借調或出勤中，請選擇其他車輛或調整時段！"]);
        }

        $user = $request->user();
        // 管理者與主管申請可直接自動核准，一般同仁進入 pending 審批流
        $isAutoApproved = $user->isAdmin() || $user->isManager();

        $dispatch = VehicleDispatch::create([
            'dispatch_number' => VehicleDispatch::generateNumber(),
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'approved_by' => $isAutoApproved ? $user->id : null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'destination' => $validated['destination'],
            'purpose' => $validated['purpose'],
            'passenger_count' => $validated['passenger_count'],
            'status' => $isAutoApproved ? 'approved' : 'pending',
            'start_mileage' => $vehicle->current_mileage,
        ]);

        $msg = $isAutoApproved
            ? "派車單申請成功並已直接核准！單號：{$dispatch->dispatch_number}"
            : "派車單已提報，待總務主管審批！單號：{$dispatch->dispatch_number}";

        return redirect()->route('vehicles.show', $dispatch)->with('success', $msg);
    }

    /**
     * 檢視派車單詳情與行程控制台
     */
    public function show(Request $request, VehicleDispatch $vehicleDispatch): Response
    {
        $vehicleDispatch->load(['user.department', 'vehicle', 'approver:id,name']);

        return Inertia::render('Vehicles/Show', [
            'dispatch' => $vehicleDispatch,
            'canManage' => $vehicleDispatch->canManage($request->user()),
            'isApprover' => $request->user()->isAdmin() || $request->user()->isManager(),
        ]);
    }

    /**
     * 總務 / 主管核准派車單
     */
    public function approve(Request $request, VehicleDispatch $vehicleDispatch): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, '僅主管或管理人員可核准派車單');
        }

        if ($vehicleDispatch->status !== 'pending') {
            return back()->withErrors(['error' => '此派車單非待審批狀態']);
        }

        // 二度確認時段無被插單衝突
        if (! $vehicleDispatch->vehicle->isAvailableBetween($vehicleDispatch->start_time, $vehicleDispatch->end_time, $vehicleDispatch->id)) {
            return back()->withErrors(['error' => '該時段已與其他核准派車衝突，無法核准！']);
        }

        $vehicleDispatch->update([
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);

        // 通知借車同仁
        $vehicleDispatch->user->notify(new EipSystemNotification(
            title: '【派車單已核准】公務車借調通知',
            message: "您申請的公務車 {$vehicleDispatch->vehicle->plate_number} ({$vehicleDispatch->vehicle->name}) 出差派車單已由 {$user->name} 核准通過！",
            type: 'vehicle',
            actionUrl: route('vehicles.show', $vehicleDispatch),
            senderName: $user->name
        ));

        return back()->with('success', '派車單已順利核准！');
    }

    /**
     * 總務 / 主管駁回派車單
     */
    public function reject(Request $request, VehicleDispatch $vehicleDispatch): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, '僅主管或管理人員可駁回派車單');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $vehicleDispatch->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        // 通知借車同仁
        $vehicleDispatch->user->notify(new EipSystemNotification(
            title: '【派車單已駁回】公務車借調退回',
            message: "您申請的派車單 {$vehicleDispatch->dispatch_number} 未獲核准，事由：{$validated['rejection_reason']}",
            type: 'vehicle',
            actionUrl: route('vehicles.show', $vehicleDispatch),
            senderName: $user->name
        ));

        return back()->with('success', '已駁回該派車單。');
    }

    /**
     * 領車出發 (Start Trip)
     */
    public function startTrip(Request $request, VehicleDispatch $vehicleDispatch): RedirectResponse
    {
        if (! $vehicleDispatch->canManage($request->user())) {
            abort(403, '您沒有權限啟動此派車單');
        }

        $validated = $request->validate([
            'start_mileage' => ['nullable', 'integer', 'min:0'],
        ]);

        $vehicle = $vehicleDispatch->vehicle;
        $startMileage = $validated['start_mileage'] ?? $vehicle->current_mileage;

        $vehicleDispatch->update([
            'status' => 'active',
            'actual_start_at' => now(),
            'start_mileage' => $startMileage,
        ]);

        $vehicle->update([
            'status' => 'in_use',
        ]);

        return back()->with('success', "公務車已成功出車領用！祝您出差行程平安順心！");
    }

    /**
     * 還車檢核與里程油料回報 (Return Trip)
     */
    public function returnTrip(Request $request, VehicleDispatch $vehicleDispatch): RedirectResponse
    {
        if (! $vehicleDispatch->canManage($request->user())) {
            abort(403, '您沒有權限辦理此派車還車');
        }

        $validated = $request->validate([
            'end_mileage' => ['required', 'integer', "min:{$vehicleDispatch->start_mileage}"],
            'return_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $vehicleDispatch->update([
            'status' => 'completed',
            'actual_return_at' => now(),
            'end_mileage' => $validated['end_mileage'],
            'return_notes' => $validated['return_notes'] ?? null,
        ]);

        // 同步更新車輛之累積里程與在庫狀態
        $vehicle = $vehicleDispatch->vehicle;
        $vehicle->update([
            'current_mileage' => $validated['end_mileage'],
            'status' => 'available',
        ]);

        return back()->with('success', "公務車已完成還車手續！累計行駛 {$vehicleDispatch->mileage_traveled} km。");
    }

    /**
     * 取消派車預約
     */
    public function cancel(Request $request, VehicleDispatch $vehicleDispatch): RedirectResponse
    {
        if (! $vehicleDispatch->canManage($request->user())) {
            abort(403, '您沒有權限取消此派車單');
        }

        if (! in_array($vehicleDispatch->status, ['pending', 'approved'])) {
            return back()->withErrors(['error' => '已出勤或已結束之派車無法取消']);
        }

        $vehicleDispatch->update([
            'status' => 'cancelled',
        ]);

        return back()->with('success', '派車預約已取消。');
    }

    /**
     * 派車里程與出差公務紀錄 CSV 匯出
     */
    public function exportLog(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, '僅限系統管理員或主管可匯出派車紀錄。');
        }

        $fileName = 'vehicle_dispatches_' . date('Ymd_His') . '.csv';

        $query = VehicleDispatch::with(['user.department', 'vehicle', 'approver:id,name']);

        if ($request->filled('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('end_time', '<=', $request->end_date);
        }

        $records = $query->orderBy('start_time', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($records) {
            $output = fopen('php://output', 'w');

            // UTF-8 BOM
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // 標題列
            fputcsv($output, [
                '派車單號',
                '借調員工',
                '所屬部門',
                '車牌號碼',
                '公務車款',
                '出發時間',
                '預計歸還',
                '目的地',
                '用車事由',
                '乘載人數',
                '出發里程(km)',
                '歸還里程(km)',
                '行駛里程(km)',
                '狀態',
                '審核人員',
                '車況/油料回報',
            ]);

            foreach ($records as $item) {
                $sanitize = function (?string $val) {
                    if ($val === null) return '';
                    if (str_starts_with($val, '=') || str_starts_with($val, '+') || str_starts_with($val, '-') || str_starts_with($val, '@')) {
                        return "'" . $val;
                    }
                    return $val;
                };

                fputcsv($output, [
                    $sanitize($item->dispatch_number),
                    $item->user?->name ?? '未知',
                    $item->user?->department?->name ?? '',
                    $sanitize($item->vehicle?->plate_number),
                    $sanitize($item->vehicle?->name),
                    $item->start_time ? $item->start_time->format('Y-m-d H:i') : '',
                    $item->end_time ? $item->end_time->format('Y-m-d H:i') : '',
                    $sanitize($item->destination),
                    $item->purpose_label,
                    $item->passenger_count,
                    $item->start_mileage ?? '',
                    $item->end_mileage ?? '',
                    $item->mileage_traveled ?? '',
                    $item->status_label,
                    $item->approver?->name ?? '系統自動核准',
                    $sanitize($item->return_notes),
                ]);
            }

            fclose($output);
        }, 200, $headers);
    }
}
