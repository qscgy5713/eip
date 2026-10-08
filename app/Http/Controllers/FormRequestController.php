<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRecord;
use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Form;
use App\Models\FormRequest as EipFormRequest;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use App\Services\WebhookService;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FormRequestController extends Controller
{
    public function __construct(
        protected WorkflowService $workflowService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // 可申請的表單範本
        $availableForms = Form::where('is_active', true)->get();

        // 我發起的申請單
        $myRequests = EipFormRequest::with('form')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        // 待我審核的單據 (包含我代理之主管單據)
        $delegatorIds = $user->delegatedToMe()->currentlyActive()->pluck('user_id');
        $approverIds = collect([$user->id])->merge($delegatorIds)->unique();

        $pendingApprovals = ApprovalRecord::with(['formRequest.user', 'formRequest.form', 'approver'])
            ->whereIn('approver_id', $approverIds)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function ($record) use ($user) {
                $record->is_delegated = $record->approver_id !== $user->id;
                return $record;
            });

        return Inertia::render('Forms/Index', [
            'availableForms' => $availableForms,
            'myRequests' => $myRequests,
            'pendingApprovals' => $pendingApprovals,
            'canManageForms' => $user->isAdmin() || $user->isManager(),
        ]);
    }

    public function create(Request $request, Form $form): Response
    {
        $leaveBalances = null;
        if ($form->code === 'LEAVE') {
            $leaveBalances = app(\App\Services\LeaveBalanceService::class)->getUserBalances($request->user());
        }

        $prefillParam = $request->query('prefill');
        $prefillData = is_array($prefillParam) ? $prefillParam : [];

        // 支援從既有單據 (例如已撤回單據) 複製內容重新申請
        if ($request->has('copy_from')) {
            $sourceRequest = EipFormRequest::find($request->query('copy_from'));
            if ($sourceRequest && $sourceRequest->canAccess($request->user())) {
                $prefillData = array_merge($sourceRequest->data ?? [], $prefillData);
            }
        }

        if ($request->has('date') && !isset($prefillData['adjust_date'])) {
            $prefillData['adjust_date'] = (string) $request->query('date');
        }
        if ($request->has('adjust_type') && !isset($prefillData['adjust_type'])) {
            $prefillData['adjust_type'] = (string) $request->query('adjust_type');
        }

        return Inertia::render('Forms/Create', [
            'form' => $form,
            'leaveBalances' => $leaveBalances,
            'prefillData' => $prefillData,
        ]);
    }

    public function store(Request $request, Form $form): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'data' => 'required|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,zip',
        ]);

        // 若為休假申請單，校驗假別可用額度是否充足
        if ($form->code === 'LEAVE') {
            $leaveService = app(\App\Services\LeaveBalanceService::class);
            $check = $leaveService->checkAvailability(
                $user,
                $validated['data']['leave_type'] ?? '',
                (float) ($validated['data']['days'] ?? 0)
            );

            if (!$check['allowed']) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['data.days' => $check['message']]);
            }
        }

        $requestNo = 'REQ-' . date('Ymd') . '-' . str_pad((string) (EipFormRequest::count() + 1), 4, '0', STR_PAD_LEFT);

        // 處理證明文件附件上傳
        $attachmentsData = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file && $file->isValid()) {
                    $originalName = $file->getClientOriginalName();
                    // 改用 local 私有儲存磁碟，杜絕 Nginx 靜態存取繞過授權 (SEC-01)
                    $storedPath = $file->store('private_form_attachments', 'local');
                    $attachmentsData[] = [
                        'name' => $originalName,
                        'path' => $storedPath,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getClientMimeType(),
                        'uploaded_at' => now()->toIso8601String(),
                    ];
                }
            }
        }

        $formRequest = EipFormRequest::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'request_no' => $requestNo,
            'title' => $validated['title'],
            'data' => $validated['data'],
            'attachments' => $attachmentsData,
            'status' => 'pending',
            'current_step' => 1,
        ]);

        // 若為休假申請單，凍結扣留額度 (pending)
        if ($form->code === 'LEAVE') {
            app(\App\Services\LeaveBalanceService::class)->holdBalance($formRequest);
        }

        // 動態計算並啟動多層級與條件簽核工作流
        $workflowSteps = $this->workflowService->determineWorkflow($form, $user, $validated['data']);
        $this->workflowService->startWorkflow($formRequest, $workflowSteps);

        // 記錄審計日誌
        AuditLog::log(
            action: 'submit_form_request',
            description: "同仁 {$user->name} 發起了「{$form->name}」申請單（{$formRequest->title}），共配置 " . count($workflowSteps) . " 關審批",
            auditable: $formRequest,
            details: [
                'form_id' => $form->id,
                'form_code' => $form->code,
                'title' => $formRequest->title,
                'total_steps' => count($workflowSteps),
                'workflow' => $workflowSteps,
            ]
        );

        // 觸發外部生態 Webhook 事件
        WebhookService::dispatch(
            'form.submitted',
            [
                'form_request_id' => $formRequest->id,
                'form_name' => $form->name,
                'title' => $formRequest->title,
                'applicant' => $user->name,
                'department' => $user->department?->name ?? '公司同仁',
            ],
            "【簽核申請】{$user->name} 提交了「{$form->name}」（{$formRequest->title}）"
        );

        return redirect()->route('forms.show', $formRequest->id)->with('success', '申請單已成功送出！');
    }

    public function show(Request $request, EipFormRequest $formRequest): Response
    {
        $user = $request->user();

        // 防範 IDOR 水平越權：僅限申請人、審核人、部門主管或系統管理員查閱
        if (!$formRequest->canAccess($user)) {
            abort(403, '您沒有權限檢閱此份申請單據。');
        }

        $formRequest->load([
            'form',
            'user.department',
            'approvalRecords.approver.department',
            'approvalRecords.delegatedFrom',
            'approvalRecords.transferredTo',
            'approvalRecords.transferredFrom',
            'approvalRecords.addSignedBy',
        ]);

        $delegatorIds = $user->delegatedToMe()->currentlyActive()->pluck('user_id');
        $validApproverIds = collect([$user->id])->merge($delegatorIds);

        // 優先尋找屬於當前登入者（或其代理主管）的待簽核記錄（無論是加簽或主審）
        $myPendingRecord = $formRequest->approvalRecords
            ->where('status', 'pending')
            ->first(function ($r) use ($validApproverIds) {
                return $validApproverIds->contains($r->approver_id);
            });

        // 若無個人指定項目，但具備管理者權限，則取得當前主流程待審記錄
        $currentPendingRecord = $myPendingRecord ?: $formRequest->approvalRecords
            ->where('status', 'pending')
            ->where('is_add_sign', false)
            ->first();

        // 僅限當前關卡之負責人、有效代理人或系統管理員具備審批權限 (防範後續關卡搶先審核)
        $canApprove = false;
        if ($currentPendingRecord) {
            if ($user->role === 'admin') {
                $canApprove = true;
            } elseif ($validApproverIds->contains($currentPendingRecord->approver_id)) {
                $canApprove = true;
            }
        }

        $activeUsers = User::where('status', 'active')
            ->where('id', '!=', $user->id)
            ->select('id', 'name', 'job_title', 'department_id', 'role')
            ->orderBy('name')
            ->get();

        return Inertia::render('Forms/Show', [
            'formRequest' => $formRequest,
            'currentPendingRecord' => $currentPendingRecord,
            'canApprove' => $canApprove,
            'activeUsers' => $activeUsers,
        ]);
    }

    /**
     * 產製公文單據正式列印與 PDF 存證視圖
     */
    public function print(Request $request, EipFormRequest $formRequest): Response
    {
        $user = $request->user();

        // 嚴格 IDOR 檢查：僅限申請人、審核主管、代理人或系統管理員查閱
        if (!$formRequest->canAccess($user)) {
            abort(403, '您沒有權限調閱或列印此份申請單據。');
        }

        $formRequest->load([
            'form',
            'user.department',
            'approvalRecords.approver.department',
            'approvalRecords.delegatedFrom'
        ]);

        // 記錄機密單據列印稽核日誌
        AuditLog::log(
            action: 'print_form_request',
            description: "同仁 {$user->name} 調閱並產製了單據「{$formRequest->request_no}」之正式存證列印文件",
            auditable: $formRequest,
            details: [
                'form_request_id' => $formRequest->id,
                'request_no' => $formRequest->request_no,
                'status' => $formRequest->status,
                'total_steps' => $formRequest->total_steps,
            ]
        );

        return Inertia::render('Forms/Print', [
            'formRequest' => $formRequest,
            'printedBy' => [
                'name' => $user->name,
                'employee_no' => $user->employee_no ?? 'N/A',
                'department' => $user->department?->name ?? '公司同仁',
                'printed_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * 安全下載單據證明附件
     */
    public function downloadAttachment(Request $request, EipFormRequest $formRequest, int $index)
    {
        $user = $request->user();

        // 嚴格 IDOR 檢查：僅限申請人、審核主管、代理人或系統管理員存取
        if (!$formRequest->canAccess($user)) {
            abort(403, '您沒有權限下載此份申請單據之附件。');
        }

        $attachments = $formRequest->attachments ?? [];
        if (!isset($attachments[$index])) {
            abort(404, '找不到指定的證明附件檔案。');
        }

        $attachment = $attachments[$index];
        $disk = Storage::disk('local');
        $filePath = $attachment['path'] ?? '';

        // 優先從 local 私有磁碟讀取，若不存在則相容歷史 public 檔案 (SEC-01)
        if (!$disk->exists($filePath)) {
            $disk = Storage::disk('public');
            if (!$disk->exists($filePath)) {
                abort(404, '附件檔案實體不存在或已損毀。');
            }
        }

        // 記錄附件下載審計日誌
        AuditLog::log(
            action: 'download_form_attachment',
            description: "同仁 {$user->name} 下載了單據「{$formRequest->request_no}」之附件「{$attachment['name']}」",
            auditable: $formRequest,
            details: [
                'form_request_id' => $formRequest->id,
                'attachment_name' => $attachment['name'],
                'file_size' => $attachment['size'],
            ]
        );

        return $disk->download($filePath, $attachment['name']);
    }

    public function action(Request $request, EipFormRequest $formRequest): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,revision_required',
            'comment' => $request->input('status') === 'revision_required' ? 'required|string|max:500' : 'nullable|string|max:500',
            'signature' => 'nullable|string',
        ], [
            'comment.required' => '退回修改時必須填寫退回原因與修改指示。',
        ]);

        $delegatorIds = $user->delegatedToMe()->currentlyActive()->pluck('user_id');
        $validApproverIds = collect([$user->id])->merge($delegatorIds);

        // 優先尋找明確指派給當前操作者（或其代理主管）的 pending 記錄（加簽或主管審核）
        $record = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('status', 'pending')
            ->whereIn('approver_id', $validApproverIds)
            ->first();

        // 若無個人指派記錄，但操作者為系統管理員，則允許代審當前主流程待審記錄
        if (!$record && $user->role === 'admin') {
            $record = ApprovalRecord::where('form_request_id', $formRequest->id)
                ->where('status', 'pending')
                ->where('is_add_sign', false)
                ->first();
        }

        if (!$record) {
            abort(403, '您目前沒有此單據的待簽核權限。');
        }

        // 委由多層級簽核引擎處理流轉或結案
        $result = $this->workflowService->processAction(
            $formRequest,
            $record,
            $user,
            $validated['status'],
            $validated['comment'] ?? null,
            $validated['signature'] ?? null
        );

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * 主管協同轉簽 (Transfer)
     */
    public function transfer(Request $request, EipFormRequest $formRequest): RedirectResponse
    {
        $user = $request->user();

        $recordQuery = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('status', 'pending');

        if ($user->role !== 'admin') {
            $delegatorIds = $user->delegatedToMe()->currentlyActive()->pluck('user_id');
            $validApproverIds = collect([$user->id])->merge($delegatorIds);
            $recordQuery->whereIn('approver_id', $validApproverIds);
        }

        $record = $recordQuery->first();
        if (!$record) {
            abort(403, '您沒有權限轉簽此單據。');
        }

        $validated = $request->validate([
            'target_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$user->id]),
            ],
            'reason' => 'required|string|max:500',
        ], [
            'target_user_id.not_in' => '不可將單據轉簽給自己。',
        ]);

        $targetUser = User::findOrFail($validated['target_user_id']);

        $result = $this->workflowService->transferApproval(
            $formRequest,
            $record,
            $user,
            $targetUser,
            $validated['reason']
        );

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * 主管協同會辦加簽 (Add-Sign)
     */
    public function addSign(Request $request, EipFormRequest $formRequest): RedirectResponse
    {
        $user = $request->user();

        $recordQuery = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('status', 'pending');

        if ($user->role !== 'admin') {
            $delegatorIds = $user->delegatedToMe()->currentlyActive()->pluck('user_id');
            $validApproverIds = collect([$user->id])->merge($delegatorIds);
            $recordQuery->whereIn('approver_id', $validApproverIds);
        }

        $record = $recordQuery->first();
        if (!$record) {
            abort(403, '您沒有權限發起加簽。');
        }

        $validated = $request->validate([
            'target_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$user->id]),
            ],
            'reason' => 'required|string|max:500',
        ], [
            'target_user_id.not_in' => '不可對自己發起會辦加簽。',
        ]);

        // 防呆：確認是否已在此關卡存在尚未簽署的加簽
        $existingPendingAddSign = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('step', $record->step)
            ->where('is_add_sign', true)
            ->where('approver_id', $validated['target_user_id'])
            ->where('status', 'pending')
            ->exists();

        if ($existingPendingAddSign) {
            throw ValidationException::withMessages([
                'target_user_id' => '該同仁已在會辦加簽名單中，尚待簽署。',
            ]);
        }

        $targetUser = User::findOrFail($validated['target_user_id']);

        $result = $this->workflowService->addSignApproval(
            $formRequest,
            $record,
            $user,
            $targetUser,
            $validated['reason']
        );

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * 自訂建立新表單種類 (管理員與主管)
     */
    public function storeTemplate(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isManager()) {
            abort(403, '僅主管與系統管理員具備自訂表單範本權限。');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', 'unique:forms,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'fields_schema' => ['required', 'array', 'min:1'],
            'fields_schema.*.key' => ['required', 'string', 'max:50'],
            'fields_schema.*.label' => ['required', 'string', 'max:100'],
            'fields_schema.*.type' => ['required', 'string', 'in:text,textarea,number,date,select'],
            'fields_schema.*.options' => ['nullable', 'array'],
            'workflow_config' => ['nullable', 'array'],
        ]);

        $form = Form::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'] ?? null,
            'fields_schema' => $validated['fields_schema'],
            'workflow_config' => $validated['workflow_config'] ?? null,
            'is_active' => true,
        ]);

        AuditLog::log(
            action: 'create_form_template',
            description: "建立新表單種類範本「{$form->name}」({$form->code})",
            auditable: $form,
            details: ['code' => $form->code, 'fields_count' => count($validated['fields_schema'])]
        );

        return back()->with('success', "已成功建立新表單範本「{$form->name}」！全體同仁現已可發起申請。");
    }

    /**
     * 刪除或下架自訂表單範本
     */
    public function destroyTemplate(Request $request, Form $form): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isManager()) {
            abort(403, '您沒有權限管理此表單。');
        }

        // 若表單已有申請單，執行軟性下架停用；若無申請單則可直接刪除
        if ($form->requests()->exists()) {
            $form->update(['is_active' => false]);
            $msg = "表單「{$form->name}」已下架停用（既有申請單歷程完整保留）。";
            AuditLog::log(
                action: 'deactivate_form_template',
                description: "下架停用表單種類範本「{$form->name}」({$form->code})",
                auditable: $form
            );
        } else {
            $name = $form->name;
            $code = $form->code;
            $form->delete();
            $msg = "已成功刪除表單「{$name}」。";
            AuditLog::log(
                action: 'delete_form_template',
                description: "徹底刪除表單種類範本「{$name}」({$code})"
            );
        }

        return back()->with('success', $msg);
    }

    /**
     * 申請人主動撤回表單申請單據
     */
    public function withdraw(Request $request, EipFormRequest $formRequest): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $workflowService = app(\App\Services\WorkflowService::class);
        $result = $workflowService->withdrawFormRequest($formRequest, $user, $validated['reason'] ?? null);

        return redirect()->route('forms.show', $formRequest->id)
            ->with('success', $result['message']);
    }

    /**
     * 申請人修改內容並重新提交審查 (Resubmit Form Request)
     */
    public function resubmit(Request $request, EipFormRequest $formRequest): RedirectResponse
    {
        $user = $request->user();

        if ($formRequest->user_id !== $user->id) {
            abort(403, '您沒有權限重新提交此份申請單據。');
        }

        if ($formRequest->status !== 'revision_required') {
            return back()->with('error', '僅限處於退回修改狀態之單據允許重新提交。');
        }

        $validated = $request->validate([
            'data' => 'required|array',
            'resubmit_note' => 'nullable|string|max:500',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip',
        ]);

        // 處理補充上傳之附件檔案 (改用 local 私有儲存磁碟 SEC-01)
        $newAttachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('private_form_attachments', 'local');
                $newAttachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }
        }

        try {
            $result = $this->workflowService->resubmitFormRequest(
                $formRequest,
                $user,
                $validated['data'],
                $newAttachments,
                $validated['resubmit_note'] ?? null
            );

            return redirect()->route('forms.show', $formRequest->id)
                ->with('success', $result['message']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
