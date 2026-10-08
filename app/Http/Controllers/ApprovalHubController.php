<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRecord;
use App\Models\AuditLog;
use App\Models\Form;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalHubController extends Controller
{
    public function __construct(
        protected WorkflowService $workflowService
    ) {}

    /**
     * 主管審批中心看板
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 取得當前同仁受託代理之主管 ID 清單
        $delegatorIds = $user->delegatedToMe()->currentlyActive()->pluck('user_id');
        $validApproverIds = collect([$user->id])->merge($delegatorIds)->unique();

        $viewAllCompany = $user->isAdmin() && $request->boolean('all_company', false);

        // 統計指標 (專屬個人與代理待辦)
        $baseStatQuery = ApprovalRecord::where('status', 'pending');
        if (!$viewAllCompany) {
            $baseStatQuery->whereIn('approver_id', $validApproverIds);
        }

        $totalPending = (clone $baseStatQuery)->count();
        $todayNew = (clone $baseStatQuery)->whereDate('created_at', today())->count();
        $delegatedCount = (clone $baseStatQuery)->where('approver_id', '!=', $user->id)->count();
        $leaveCount = (clone $baseStatQuery)
            ->whereHas('formRequest.form', fn($q) => $q->where('code', 'LEAVE'))
            ->count();

        // 待審單據列表查詢
        $query = ApprovalRecord::with([
            'formRequest.user.department',
            'formRequest.form',
            'approver.department',
        ])
            ->where('status', 'pending');

        if (!$viewAllCompany) {
            $query->whereIn('approver_id', $validApproverIds);
        }

        // 表單種類過濾
        if ($request->filled('form_id')) {
            $query->whereHas('formRequest', fn($q) => $q->where('form_id', $request->input('form_id')));
        }

        // 代理單據過濾
        if ($request->filled('filter_type')) {
            $filterType = $request->input('filter_type');
            if ($filterType === 'delegated') {
                $query->where('approver_id', '!=', $user->id);
            } elseif ($filterType === 'direct') {
                $query->where('approver_id', $user->id);
            }
        }

        // 關鍵字搜尋 (申請人姓名、單號、主旨)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('formRequest', function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('request_no', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'ilike', "%{$search}%")->orWhere('employee_no', 'ilike', "%{$search}%"));
            });
        }

        $records = $query->latest()->paginate(15)->withQueryString();

        // 標註每筆單據是否為職務代理代簽
        $records->getCollection()->transform(function ($record) use ($user) {
            $record->is_delegated = $record->approver_id !== $user->id;
            return $record;
        });

        $forms = Form::where('is_active', true)->select('id', 'name', 'code')->get();

        return Inertia::render('Approvals/Index', [
            'pendingRecords' => $records,
            'stats' => [
                'total_pending' => $totalPending,
                'today_new' => $todayNew,
                'delegated_count' => $delegatedCount,
                'leave_count' => $leaveCount,
            ],
            'forms' => $forms,
            'viewAllCompany' => $viewAllCompany,
            'canViewAllCompany' => $user->isAdmin(),
            'filters' => [
                'form_id' => $request->input('form_id', ''),
                'filter_type' => $request->input('filter_type', ''),
                'search' => $request->input('search', ''),
                'all_company' => $viewAllCompany,
            ],
        ]);
    }

    /**
     * 一鍵批次簽核動作（批次核准或批次駁回）
     */
    public function batchAction(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'record_ids' => 'required|array|min:1|max:100',
            'record_ids.*' => 'required|integer|exists:approval_records,id',
            'action' => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:500',
        ]);

        $delegatorIds = $user->delegatedToMe()->currentlyActive()->pluck('user_id');
        $validApproverIds = collect([$user->id])->merge($delegatorIds)->unique();

        $records = ApprovalRecord::with('formRequest.form')
            ->whereIn('id', $validated['record_ids'])
            ->where('status', 'pending')
            ->get();

        $successCount = 0;
        $failCount = 0;
        $action = $validated['action'];
        $comment = $validated['comment'] ?? ($action === 'approved' ? '批次核准通過' : '批次退件駁回');

        foreach ($records as $record) {
            // 權限檢查：僅限管理員、本人負責之關卡或生效中代理人有權簽核
            if (!$user->isAdmin() && !$validApproverIds->contains($record->approver_id)) {
                $failCount++;
                continue;
            }

            // 防呆檢查：確認單據當前 step 與 record step 一致（防範越級搶審）
            if ($record->formRequest->current_step !== $record->step) {
                $failCount++;
                continue;
            }

            try {
                $this->workflowService->processAction(
                    $record->formRequest,
                    $record,
                    $user,
                    $action,
                    $comment
                );
                $successCount++;
            } catch (\Throwable $e) {
                $failCount++;
            }
        }

        $actionText = $action === 'approved' ? '核准' : '駁回';

        // 記錄批次審批審計日誌
        AuditLog::log(
            action: "batch_{$action}_form_requests",
            description: "同仁 {$user->name} 執行了批次{$actionText}簽核（成功 {$successCount} 筆，失敗 {$failCount} 筆，意見：{$comment}）",
            details: [
                'action' => $action,
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'target_record_ids' => $validated['record_ids'],
                'comment' => $comment,
            ]
        );

        $msg = "批次{$actionText}完成！共成功處理 {$successCount} 筆單據。";
        if ($failCount > 0) {
            $msg .= "（另有 {$failCount} 筆因權限或關卡狀態異動略過）";
        }

        return redirect()->back()->with('success', $msg);
    }
}
