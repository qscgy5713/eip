<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRecord;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormRequest as EipFormRequest;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormRequestController extends Controller
{
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

        // 待我審核的單據
        $pendingApprovals = ApprovalRecord::with(['formRequest.user', 'formRequest.form'])
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        return Inertia::render('Forms/Index', [
            'availableForms' => $availableForms,
            'myRequests' => $myRequests,
            'pendingApprovals' => $pendingApprovals,
            'canManageForms' => $user->isAdmin() || $user->isManager(),
        ]);
    }

    public function create(Form $form): Response
    {
        return Inertia::render('Forms/Create', [
            'form' => $form,
        ]);
    }

    public function store(Request $request, Form $form): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'data' => 'required|array',
        ]);

        $requestNo = 'REQ-' . date('Ymd') . '-' . str_pad((string) (EipFormRequest::count() + 1), 4, '0', STR_PAD_LEFT);

        $formRequest = EipFormRequest::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'request_no' => $requestNo,
            'title' => $validated['title'],
            'data' => $validated['data'],
            'status' => 'pending',
            'current_step' => 1,
        ]);

        // 指派審核主管 (同部門主管或系統管理員)
        $approver = User::where('department_id', $user->department_id)
            ->whereIn('role', ['manager', 'admin'])
            ->where('id', '!=', $user->id)
            ->first() ?? User::where('role', 'admin')->first();

        if ($approver) {
            ApprovalRecord::create([
                'form_request_id' => $formRequest->id,
                'step' => 1,
                'approver_id' => $approver->id,
                'status' => 'pending',
            ]);

            // 發送待簽核通知給主管
            $approver->notify(new EipSystemNotification(
                title: "【待簽核單據】{$form->name}",
                message: "同仁 {$user->name} 提交了「{$formRequest->title}」，請至簽核中心進行審批。",
                type: 'form_approval',
                actionUrl: route('forms.show', $formRequest->id),
                senderName: $user->name,
                extra: ['form_request_id' => $formRequest->id]
            ));
        }

        // 記錄審計日誌
        AuditLog::log(
            action: 'submit_form_request',
            description: "同仁 {$user->name} 發起了「{$form->name}」申請單（{$formRequest->title}）",
            auditable: $formRequest,
            details: ['form_id' => $form->id, 'form_code' => $form->code, 'title' => $formRequest->title]
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

        $formRequest->load(['form', 'user.department', 'approvalRecords.approver']);

        $canApprove = $user->role === 'admin'
            ? $formRequest->approvalRecords->where('status', 'pending')->isNotEmpty()
            : $formRequest->approvalRecords
                ->where('approver_id', $user->id)
                ->where('status', 'pending')
                ->isNotEmpty();

        return Inertia::render('Forms/Show', [
            'formRequest' => $formRequest,
            'canApprove' => $canApprove,
        ]);
    }

    public function action(Request $request, EipFormRequest $formRequest): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:500',
        ]);

        $recordQuery = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('status', 'pending');

        if ($user->role !== 'admin') {
            $recordQuery->where('approver_id', $user->id);
        }

        $record = $recordQuery->firstOrFail();

        $record->update([
            'status' => $validated['status'],
            'comment' => $validated['comment'] ?? null,
            'actioned_at' => now(),
        ]);

        // 更新申請單總體狀態
        $formRequest->update([
            'status' => $validated['status'],
        ]);

        $statusText = $validated['status'] === 'approved' ? '核准通過' : '退件駁回';

        // 記錄審計日誌
        AuditLog::log(
            action: $validated['status'] === 'approved' ? 'approve_form_request' : 'reject_form_request',
            description: "主管 {$user->name} {$statusText}了申請單「{$formRequest->title}」",
            auditable: $formRequest,
            details: ['status' => $validated['status'], 'comment' => $validated['comment'] ?? null]
        );

        // 通知原申請同仁
        $formRequest->user?->notify(new EipSystemNotification(
            title: "【簽核結果】您的申請單「{$formRequest->title}」已{$statusText}",
            message: "主管 {$user->name} 已完成審核（狀態：{$statusText}）。" . (!empty($validated['comment']) ? " 意見：{$validated['comment']}" : ''),
            type: 'form_approval',
            actionUrl: route('forms.show', $formRequest->id),
            senderName: $user->name,
            extra: ['status' => $validated['status']]
        ));

        return redirect()->back()->with('success', '簽核狀態已更新！');
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
        ]);

        $form = Form::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'] ?? null,
            'fields_schema' => $validated['fields_schema'],
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
}
