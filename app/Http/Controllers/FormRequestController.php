<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRecord;
use App\Models\Form;
use App\Models\FormRequest as EipFormRequest;
use App\Models\User;
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
        }

        return redirect()->route('forms.show', $formRequest->id)->with('success', '申請單已成功送出！');
    }

    public function show(Request $request, EipFormRequest $formRequest): Response
    {
        $user = $request->user();
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

        return redirect()->back()->with('success', '簽核狀態已更新！');
    }
}
