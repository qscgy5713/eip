<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AssetController extends Controller
{
    /**
     * 資產總覽清單與篩選
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $category = $request->query('category');
        $search = $request->query('search');
        $myAssetsOnly = $request->boolean('my_assets');

        $query = Asset::with(['currentHolder:id,name,email,job_title', 'department:id,name', 'activeLoan.user:id,name']);

        if ($myAssetsOnly) {
            $query->where('current_holder_id', $user->id);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $assets = $query->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => Asset::count(),
            'available' => Asset::where('status', 'available')->count(),
            'in_use' => Asset::where('status', 'in_use')->count(),
            'maintenance' => Asset::where('status', 'maintenance')->count(),
            'my_held_count' => Asset::where('current_holder_id', $user->id)->count(),
        ];

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $users = User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'job_title']);

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'stats' => $stats,
            'departments' => $departments,
            'users' => $users,
            'filters' => [
                'status' => $status ?? 'all',
                'category' => $category ?? 'all',
                'search' => $search ?? '',
                'my_assets' => $myAssetsOnly,
            ],
            'canManage' => $user->isManager() || $user->isAdmin(),
        ]);
    }

    /**
     * 登錄新資產 (限主管或管理員)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isManager() && ! $user->isAdmin()) {
            abort(403, '僅主管或系統管理員可登錄公司資產');
        }

        if (empty($request->asset_tag)) {
            $year = date('Y');
            $prefix = "AST-{$year}-";
            $latest = Asset::where('asset_tag', 'like', "{$prefix}%")->orderByDesc('id')->first();
            $nextSeq = 1;
            if ($latest && preg_match('/AST-\d{4}-(\d+)/', $latest->asset_tag, $matches)) {
                $nextSeq = intval($matches[1]) + 1;
            }
            $request->merge(['asset_tag' => $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT)]);
        }

        $validated = $request->validate([
            'asset_tag' => ['required', 'string', 'max:50', 'unique:assets,asset_tag'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:it_hardware,office_equipment,av_media,vehicles,peripherals,other'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'specification' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:available,in_use,maintenance,retired'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($validated['status'])) {
            $validated['status'] = 'available';
        }

        $asset = Asset::create($validated);

        AuditLog::log(
            'asset_create',
            "登錄新資產 [{$asset->asset_tag}] {$asset->name}",
            $asset,
            ['asset_id' => $asset->id, 'asset_tag' => $asset->asset_tag]
        );

        return back()->with('success', "資產 [{$asset->asset_tag}] 登錄成功！");
    }

    /**
     * 資產詳細資料與借調履歷
     */
    public function show(Asset $asset)
    {
        $asset->load([
            'currentHolder:id,name,email,job_title',
            'department:id,name',
            'activeLoan.user:id,name,email',
            'loans' => function ($q) {
                $q->with(['user:id,name,email', 'handler:id,name'])
                  ->orderByDesc('id');
            },
        ]);

        $users = User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'job_title']);
        $departments = Department::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Assets/Show', [
            'asset' => $asset,
            'users' => $users,
            'departments' => $departments,
        ]);
    }

    /**
     * 更新資產資訊
     */
    public function update(Request $request, Asset $asset)
    {
        $user = $request->user();

        if (! $user->isManager() && ! $user->isAdmin()) {
            abort(403, '僅主管或系統管理員可編輯資產設定');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:it_hardware,office_equipment,av_media,vehicles,peripherals,other'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'specification' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:available,in_use,maintenance,retired'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $asset->update($validated);

        AuditLog::log(
            'asset_update',
            "更新資產 [{$asset->asset_tag}] {$asset->name}",
            $asset,
            ['asset_id' => $asset->id]
        );

        return back()->with('success', '資產資訊已更新！');
    }

    /**
     * 刪除資產
     */
    public function destroy(Asset $asset, Request $request)
    {
        $user = $request->user();

        if (! $user->isManager() && ! $user->isAdmin()) {
            abort(403, '僅主管或系統管理員可刪除資產');
        }

        if ($asset->status === 'in_use' || $asset->current_holder_id) {
            return back()->withErrors(['error' => '此資產目前由同仁借調使用中，無法刪除！請先完成歸還驗收。']);
        }

        $tag = $asset->asset_tag;
        $name = $asset->name;

        AuditLog::log(
            'asset_delete',
            "刪除資產 [{$tag}] {$name}",
            $asset,
            ['asset_tag' => $tag, 'name' => $name]
        );

        $asset->delete();

        return redirect()->route('assets.index')->with('success', "資產 [{$tag}] 已順利刪除");
    }

    /**
     * 借出 / 領用資產
     */
    public function borrow(Request $request, Asset $asset)
    {
        $user = $request->user();

        if ($asset->status !== 'available') {
            return back()->withErrors(['error' => '此資產目前非在庫可借狀態，無法借出！']);
        }

        $validated = $request->validate([
            'borrower_id' => ['nullable', 'exists:users,id'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:today'],
            'purpose' => ['required', 'string', 'max:500'],
        ]);

        // 若管理員代為登記，指定 borrower_id；否則由登入使用者本人領用
        $borrowerId = ($user->isAdmin() || $user->isManager()) && ! empty($validated['borrower_id'])
            ? $validated['borrower_id']
            : $user->id;

        $borrower = User::findOrFail($borrowerId);

        DB::transaction(function () use ($asset, $borrower, $validated, $user) {
            $asset->update([
                'status' => 'in_use',
                'current_holder_id' => $borrower->id,
            ]);

            AssetLoan::create([
                'asset_id' => $asset->id,
                'user_id' => $borrower->id,
                'borrowed_at' => now(),
                'expected_return_date' => $validated['expected_return_date'] ?? null,
                'status' => 'borrowed',
                'purpose' => $validated['purpose'],
                'handled_by' => $user->id,
            ]);
        });

        // 發送通知給借用人 (若為他人代登記)
        if ($borrower->id !== $user->id) {
            $borrower->notify(new EipSystemNotification(
                title: '公務資產借調通知',
                message: "{$user->name} 已為您登記借用公務資產 [{$asset->asset_tag}] {$asset->name}",
                type: 'asset',
                actionUrl: route('assets.show', $asset->id),
                senderName: $user->name,
                extra: ['asset_id' => $asset->id]
            ));
        }

        AuditLog::log(
            'asset_borrow',
            "借出資產 [{$asset->asset_tag}] {$asset->name} 給 {$borrower->name}",
            $asset,
            ['asset_id' => $asset->id, 'borrower_id' => $borrower->id]
        );

        return back()->with('success', "資產 [{$asset->asset_tag}] 借出登記完成！");
    }

    /**
     * 歸還資產驗收
     */
    public function returnAsset(Request $request, Asset $asset)
    {
        $user = $request->user();

        // 僅限當前保管人、主管或管理員
        if ($asset->current_holder_id !== $user->id && ! $user->isManager() && ! $user->isAdmin()) {
            abort(403, '僅資產保管人、主管或系統管理員可執行歸還驗收');
        }

        $validated = $request->validate([
            'return_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $holderName = $asset->currentHolder?->name ?? '同仁';

        DB::transaction(function () use ($asset, $validated) {
            // 更新借用紀錄
            $loan = AssetLoan::where('asset_id', $asset->id)
                ->where('status', 'borrowed')
                ->latest('id')
                ->first();

            if ($loan) {
                $loan->update([
                    'status' => 'returned',
                    'returned_at' => now(),
                    'return_notes' => $validated['return_notes'] ?? null,
                ]);
            }

            // 資產轉回在庫
            $asset->update([
                'status' => 'available',
                'current_holder_id' => null,
            ]);
        });

        AuditLog::log(
            'asset_return',
            "驗收歸還資產 [{$asset->asset_tag}] {$asset->name} (原保管人: {$holderName})",
            $asset,
            ['asset_id' => $asset->id]
        );

        return back()->with('success', "資產 [{$asset->asset_tag}] 已成功驗收歸還在庫！");
    }
}
