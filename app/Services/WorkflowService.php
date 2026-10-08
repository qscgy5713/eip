<?php

namespace App\Services;

use App\Models\ApprovalRecord;
use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Form;
use App\Models\FormRequest;
use App\Models\User;
use App\Notifications\EipSystemNotification;

class WorkflowService
{
    /**
     * 依據表單定義、申請同仁與表單填寫資料，動態判定簽核關卡鏈 (Approval Pipeline)
     *
     * @param Form $form
     * @param User $applicant
     * @param array $data
     * @return array 關卡鏈定義陣列
     */
    public function determineWorkflow(Form $form, User $applicant, array $data): array
    {
        // 1. 若表單自訂了 workflow_config 且格式合法，優先套用自訂規則
        if (!empty($form->workflow_config) && is_array($form->workflow_config)) {
            $customSteps = $this->evaluateCustomWorkflow($form->workflow_config, $applicant, $data);
            if (!empty($customSteps)) {
                return $customSteps;
            }
        }

        // 2. 系統內建預設工作流與企業條件分支判定 (Conditional Branching)
        $steps = [];

        // 預設第 1 關：直屬主管初審 (同部門 Manager，若無則為 Admin)
        $manager = User::where('department_id', $applicant->department_id)
            ->whereIn('role', ['manager', 'admin'])
            ->where('id', '!=', $applicant->id)
            ->first() ?? User::where('role', 'admin')->where('id', '!=', $applicant->id)->first()
            ?? User::where('role', 'admin')->first();

        $steps[] = [
            'step' => 1,
            'title' => '直屬主管初審',
            'role' => 'manager',
            'approver_id' => $manager?->id,
            'approver_name' => $manager?->name ?? '主管',
            'condition_desc' => '直屬部門主管審查',
        ];

        // 條件分支 A：休假申請單 (LEAVE)
        if ($form->code === 'LEAVE') {
            $days = floatval($data['days'] ?? 0);
            if ($days > 3) {
                // 長天期休假（大於 3 天），追加第 2 關：人資行政部複核
                $hrApprover = User::where('role', 'hr')->where('id', '!=', $applicant->id)->first()
                    ?? User::where('role', 'admin')->first();

                $steps[] = [
                    'step' => 2,
                    'title' => '人資主管複核',
                    'role' => 'hr',
                    'approver_id' => $hrApprover?->id,
                    'approver_name' => $hrApprover?->name ?? '人資主管',
                    'condition_desc' => "請假天數達 {$days} 天（超過 3 天門檻需人資複核）",
                ];
            }
        }

        // 條件分支 B：費用報銷申請單 (EXPENSE)
        if ($form->code === 'EXPENSE') {
            $amount = floatval($data['amount'] ?? 0);
            if ($amount >= 10000 && $amount < 50000) {
                // 金額超過 10,000 元但未滿 50,000 元，追加第 2 關：財務複核
                $financeApprover = User::where('role', 'admin')->where('id', '!=', $applicant->id)->first()
                    ?? User::where('role', 'admin')->first();

                $steps[] = [
                    'step' => 2,
                    'title' => '財務主管複核',
                    'role' => 'finance',
                    'approver_id' => $financeApprover?->id,
                    'approver_name' => $financeApprover?->name ?? '財務長 / 管理員',
                    'condition_desc' => "報銷金額達 NT$ " . number_format($amount) . "（達 NT$ 10,000 門檻需財務複核）",
                ];
            } elseif ($amount >= 50000) {
                // 金額達 50,000 元以上，三級簽核：財務複核 + 執行長/總經理決行
                $financeApprover = User::where('role', 'admin')->where('id', '!=', $applicant->id)->first()
                    ?? User::where('role', 'admin')->first();

                $steps[] = [
                    'step' => 2,
                    'title' => '財務主管複核',
                    'role' => 'finance',
                    'approver_id' => $financeApprover?->id,
                    'approver_name' => $financeApprover?->name ?? '財務長 / 管理員',
                    'condition_desc' => "報銷金額達 NT$ " . number_format($amount) . "（達 NT$ 10,000 門檻需財務複核）",
                ];

                $ceoApprover = User::where('role', 'admin')->first();
                $steps[] = [
                    'step' => 3,
                    'title' => '執行長/總經理決行',
                    'role' => 'admin',
                    'approver_id' => $ceoApprover?->id,
                    'approver_name' => $ceoApprover?->name ?? '總經理室',
                    'condition_desc' => "高額報支 NT$ " . number_format($amount) . "（達 NT$ 50,000 門檻需總經理決行）",
                ];
            }
        }

        // 條件分支 C：加班申請單 (OVERTIME)
        if ($form->code === 'OVERTIME') {
            $hours = floatval($data['hours'] ?? 0);
            if ($hours >= 8) {
                // 加班時數達 8 小時（全日），追加第 2 關：人資工時稽核
                $hrApprover = User::where('role', 'hr')->where('id', '!=', $applicant->id)->first()
                    ?? User::where('role', 'admin')->first();

                $steps[] = [
                    'step' => 2,
                    'title' => '人資工時稽核',
                    'role' => 'hr',
                    'approver_id' => $hrApprover?->id,
                    'approver_name' => $hrApprover?->name ?? '人資主管',
                    'condition_desc' => "加班時數達 {$hours} 小時（超過 8 小時需人資出勤稽核）",
                ];
            }
        }

        // 重新依序重編 step 編號以確保連續性
        return array_values(array_map(function ($item, $index) {
            $item['step'] = $index + 1;
            return $item;
        }, $steps, array_keys($steps)));
    }

    /**
     * 解析自訂工作流配置
     */
    protected function evaluateCustomWorkflow(array $workflowConfig, User $applicant, array $data): array
    {
        $evaluatedSteps = [];
        $currentStepIndex = 1;

        foreach ($workflowConfig as $configItem) {
            // 檢查條件是否成立
            if (isset($configItem['condition']) && is_array($configItem['condition'])) {
                $cond = $configItem['condition'];
                $field = $cond['field'] ?? null;
                $operator = $cond['operator'] ?? '==';
                $expected = $cond['value'] ?? null;
                $actual = $data[$field] ?? null;

                $isMatch = false;
                switch ($operator) {
                    case '>':
                        $isMatch = floatval($actual) > floatval($expected);
                        break;
                    case '>=':
                        $isMatch = floatval($actual) >= floatval($expected);
                        break;
                    case '<':
                        $isMatch = floatval($actual) < floatval($expected);
                        break;
                    case '<=':
                        $isMatch = floatval($actual) <= floatval($expected);
                        break;
                    case '!=':
                        $isMatch = $actual != $expected;
                        break;
                    case '==':
                    default:
                        $isMatch = $actual == $expected;
                        break;
                }

                if (!$isMatch) {
                    continue; // 條件未達成，跳過此關卡
                }
            }

            // 指派該關卡的審批人
            $role = $configItem['role'] ?? 'manager';
            $approver = null;

            if (!empty($configItem['user_id'])) {
                $approver = User::find($configItem['user_id']);
            } elseif ($role === 'manager') {
                $approver = User::where('department_id', $applicant->department_id)
                    ->whereIn('role', ['manager', 'admin'])
                    ->where('id', '!=', $applicant->id)
                    ->first() ?? User::where('role', 'admin')->first();
            } elseif ($role === 'hr') {
                $approver = User::where('role', 'hr')->where('id', '!=', $applicant->id)->first()
                    ?? User::where('role', 'admin')->first();
            } else {
                $approver = User::where('role', 'admin')->first();
            }

            $evaluatedSteps[] = [
                'step' => $currentStepIndex++,
                'title' => $configItem['title'] ?? ("關卡 " . $currentStepIndex),
                'role' => $role,
                'approver_id' => $approver?->id,
                'approver_name' => $approver?->name ?? '指定審核人',
                'condition_desc' => $configItem['description'] ?? '流程關卡',
            ];
        }

        return $evaluatedSteps;
    }

    /**
     * 初始化表單簽核流程（記錄關卡快照並啟用第 1 關）
     */
    public function startWorkflow(FormRequest $formRequest, array $workflowChain): void
    {
        $totalSteps = count($workflowChain);
        $formRequest->update([
            'total_steps' => $totalSteps,
            'workflow_snapshot' => $workflowChain,
            'current_step' => 1,
            'status' => 'pending',
        ]);

        if (empty($workflowChain)) {
            return;
        }

        $firstStep = $workflowChain[0];
        $approverId = $firstStep['approver_id'];

        if ($approverId) {
            $record = ApprovalRecord::create([
                'form_request_id' => $formRequest->id,
                'step' => 1,
                'step_title' => $firstStep['title'] ?? '第一關審查',
                'approver_id' => $approverId,
                'status' => 'pending',
            ]);

            $this->notifyApproverAndDelegates($record, $formRequest, $firstStep['title']);
        }
    }

    /**
     * 執行簽核動作（核准流轉或駁回結案）
     */
    public function processAction(
        FormRequest $formRequest,
        ApprovalRecord $record,
        User $actionUser,
        string $status,
        ?string $comment,
        ?string $signature = null
    ): array {
        $delegatedFromId = null;
        if ($record->approver_id !== $actionUser->id) {
            $delegatedFromId = $record->approver_id;
        }

        $record->update([
            'status' => $status,
            'comment' => $comment,
            'signature' => $signature,
            'delegated_from_id' => $delegatedFromId,
            'actioned_at' => now(),
        ]);

        $statusText = $status === 'approved' ? '核准通過' : '退件駁回';
        $delegator = $delegatedFromId ? User::find($delegatedFromId) : null;
        $signRoleText = $delegator ? "代理人 {$actionUser->name}（原主管：{$delegator->name}）" : "審核人 {$actionUser->name}";
        $stepTitle = $record->step_title ?? "關卡 {$record->step}";

        // 0. 特殊分支：若該記錄為協同「會辦加簽」記錄，加簽簽署不影響主關卡推進或終止
        if ($record->is_add_sign) {
            $addSignedBy = $record->addSignedBy;
            $msg = $status === 'approved' ? '會辦簽署通過' : '會辦意見保留';

            AuditLog::log(
                action: 'complete_add_sign',
                description: "加簽協辦人 {$actionUser->name} 完成了對單據「{$formRequest->title}」之會辦加簽（{$msg}，意見：{$comment}）",
                auditable: $formRequest,
                details: [
                    'action_user_id' => $actionUser->id,
                    'status' => $status,
                    'comment' => $comment,
                    'original_approver_id' => $record->add_signed_by_id,
                ]
            );

            // 通知發起加簽之主審主管
            $addSignedBy?->notify(new EipSystemNotification(
                title: "【加簽完成回流】單據「{$formRequest->title}」已完成會辦",
                message: "同仁 {$actionUser->name} 已簽署加簽意見：" . ($comment ?: '無特殊備註') . "，請您接續進行審查決行。",
                type: 'form_approval',
                actionUrl: route('forms.show', $formRequest->id),
                senderName: $actionUser->name,
                extra: ['form_request_id' => $formRequest->id, 'step' => $record->step]
            ));

            return [
                'status' => $status,
                'is_completed' => false,
                'message' => '您已順利完成會辦加簽意見簽署！',
            ];
        }

        // 1. 若退回修改 (Revision Required / Send Back)
        if ($status === 'revision_required') {
            $record->update([
                'status' => 'returned',
                'comment' => $comment ?: '主管要求退回補件修改',
                'delegated_from_id' => $delegatedFromId,
                'actioned_at' => now(),
            ]);

            $formRequest->update(['status' => 'revision_required']);

            AuditLog::log(
                action: 'request_form_revision',
                description: "{$signRoleText} 於「{$stepTitle}」退回了申請單「{$formRequest->title}」要求修改（指示：{$comment}）",
                auditable: $formRequest,
                details: [
                    'step' => $record->step,
                    'step_title' => $stepTitle,
                    'status' => 'revision_required',
                    'comment' => $comment,
                    'delegated_from_id' => $delegatedFromId,
                ]
            );

            $formRequest->user?->notify(new EipSystemNotification(
                title: "【退回修改通知】您的申請單「{$formRequest->title}」已被審核主管退回修改",
                message: "{$signRoleText} 在「{$stepTitle}」將您的申請單退回修改。" . (!empty($comment) ? " 指示：{$comment}" : '') . " 請儘速修正或補齊證明後重新提交審查。",
                type: 'form_revision',
                actionUrl: route('forms.show', $formRequest->id),
                senderName: $actionUser->name,
                extra: ['status' => 'revision_required', 'step' => $record->step]
            ));

            WebhookService::dispatch(
                'form.revision_required',
                [
                    'form_request_id' => $formRequest->id,
                    'title' => $formRequest->title,
                    'status' => 'revision_required',
                    'step' => $record->step,
                    'step_title' => $stepTitle,
                    'approver' => $actionUser->name,
                    'comment' => $comment,
                ],
                "【退回修改】{$signRoleText} 在「{$stepTitle}」退回了「{$formRequest->title}」要求修改補件"
            );

            return [
                'status' => 'revision_required',
                'is_completed' => false,
                'message' => '已將申請單退回申請人修改補件！',
            ];
        }

        // 2. 若駁回：立即終止流程
        if ($status === 'rejected') {
            $formRequest->update(['status' => 'rejected']);

            // 若為休假單，解除凍結扣留額度 (Pending 釋放)
            app(\App\Services\LeaveBalanceService::class)->releaseBalance($formRequest, approved: false);

            AuditLog::log(
                action: 'reject_form_request',
                description: "{$signRoleText} 於「{$stepTitle}」駁回了申請單「{$formRequest->title}」",
                auditable: $formRequest,
                details: [
                    'step' => $record->step,
                    'step_title' => $stepTitle,
                    'status' => 'rejected',
                    'comment' => $comment,
                    'delegated_from_id' => $delegatedFromId,
                ]
            );

            $formRequest->user?->notify(new EipSystemNotification(
                title: "【簽核結果】您的申請單「{$formRequest->title}」已被駁回",
                message: "{$signRoleText} 在「{$stepTitle}」將您的申請單駁回。" . (!empty($comment) ? " 意見：{$comment}" : ''),
                type: 'form_approval',
                actionUrl: route('forms.show', $formRequest->id),
                senderName: $actionUser->name,
                extra: ['status' => 'rejected', 'step' => $record->step]
            ));

            WebhookService::dispatch(
                'form.rejected',
                [
                    'form_request_id' => $formRequest->id,
                    'title' => $formRequest->title,
                    'status' => 'rejected',
                    'step' => $record->step,
                    'step_title' => $stepTitle,
                    'approver' => $actionUser->name,
                    'comment' => $comment,
                ],
                "【簽核駁回】{$signRoleText} 在「{$stepTitle}」駁回了「{$formRequest->title}」"
            );

            return [
                'status' => 'rejected',
                'is_completed' => true,
                'message' => '申請單已被駁回，簽核流程終止。',
            ];
        }

        // 2. 若核准：判斷是否還有後續關卡
        $currentStep = $record->step;
        $totalSteps = $formRequest->total_steps ?: 1;

        if ($currentStep < $totalSteps) {
            // 尚有下一關，推進流程流轉 (Pipeline Advance)
            $nextStepNumber = $currentStep + 1;
            $workflowSnapshot = $formRequest->workflow_snapshot ?? [];
            $nextStepData = $workflowSnapshot[$currentStep] ?? null; // 0-indexed: index = currentStep

            $nextApproverId = $nextStepData['approver_id'] ?? null;
            if (!$nextApproverId) {
                // 防呆：若無指定下一關審核人，指派系統管理員
                $nextApproverId = User::where('role', 'admin')->value('id');
            }

            $nextStepTitle = $nextStepData['title'] ?? "第 {$nextStepNumber} 關審核";

            // 建立下一關的 ApprovalRecord
            $nextRecord = ApprovalRecord::create([
                'form_request_id' => $formRequest->id,
                'step' => $nextStepNumber,
                'step_title' => $nextStepTitle,
                'approver_id' => $nextApproverId,
                'status' => 'pending',
            ]);

            $formRequest->update([
                'current_step' => $nextStepNumber,
                'status' => 'pending', // 保持 pending 狀態繼續流轉
            ]);

            AuditLog::log(
                action: 'advance_form_request',
                description: "{$signRoleText} 核准了「{$stepTitle}」，流程流轉至下一關卡「{$nextStepTitle}」",
                auditable: $formRequest,
                details: [
                    'step' => $currentStep,
                    'next_step' => $nextStepNumber,
                    'next_step_title' => $nextStepTitle,
                    'comment' => $comment,
                ]
            );

            // 通知下一關審批人及代理人
            $this->notifyApproverAndDelegates($nextRecord, $formRequest, $nextStepTitle);

            // 通知申請人進度更新
            $formRequest->user?->notify(new EipSystemNotification(
                title: "【簽核進度】您的申請單「{$formRequest->title}」已通過「{$stepTitle}」",
                message: "{$signRoleText} 已核准（關卡 {$currentStep}/{$totalSteps}），目前已流轉至「{$nextStepTitle}」審核中。",
                type: 'form_approval',
                actionUrl: route('forms.show', $formRequest->id),
                senderName: $actionUser->name,
                extra: ['current_step' => $nextStepNumber, 'total_steps' => $totalSteps]
            ));

            WebhookService::dispatch(
                'form.step_approved',
                [
                    'form_request_id' => $formRequest->id,
                    'title' => $formRequest->title,
                    'step' => $currentStep,
                    'step_title' => $stepTitle,
                    'next_step' => $nextStepNumber,
                    'next_step_title' => $nextStepTitle,
                    'approver' => $actionUser->name,
                ],
                "【簽核進度】{$signRoleText} 核准「{$formRequest->title}」的 {$stepTitle}，進入 {$nextStepTitle}"
            );

            return [
                'status' => 'advanced',
                'is_completed' => false,
                'message' => "已核准「{$stepTitle}」，單據已流轉至「{$nextStepTitle}」！",
            ];
        }

        // 3. 所有關卡已全數核准，流程正式結案 (Completed)
        $formRequest->update([
            'status' => 'approved',
        ]);

        // 若為休假單，將凍結扣留額度正式結轉為已使用額度 (Pending ➜ Used)
        app(\App\Services\LeaveBalanceService::class)->releaseBalance($formRequest, approved: true);

        // 若為忘刷/補打卡單，自動同步修復考勤打卡紀錄 (Attendance Regularization)
        $this->syncAttendanceAmendment($formRequest);

        // 若為加班單且選擇換取補休，自動將加班時數折算入補休額度 (Overtime Compensatory Credit)
        app(\App\Services\LeaveBalanceService::class)->creditCompensatoryLeave($formRequest);

        AuditLog::log(
            action: 'approve_form_request',
            description: "{$signRoleText} 完成了「{$stepTitle}」最終審定，單據「{$formRequest->title}」正式結案核准",
            auditable: $formRequest,
            details: [
                'step' => $currentStep,
                'total_steps' => $totalSteps,
                'status' => 'approved',
                'comment' => $comment,
            ]
        );

        $formRequest->user?->notify(new EipSystemNotification(
            title: "【簽核完成】您的申請單「{$formRequest->title}」已全數通過核准！",
            message: "恭喜！您的申請單已通過所有簽核關卡（共 {$totalSteps} 關），目前已正式生效結案。" . (!empty($comment) ? " 意見：{$comment}" : ''),
            type: 'form_approval',
            actionUrl: route('forms.show', $formRequest->id),
            senderName: $actionUser->name,
            extra: ['status' => 'approved', 'total_steps' => $totalSteps]
        ));

        WebhookService::dispatch(
            'form.approved',
            [
                'form_request_id' => $formRequest->id,
                'title' => $formRequest->title,
                'status' => 'approved',
                'total_steps' => $totalSteps,
                'approver' => $actionUser->name,
                'comment' => $comment,
            ],
            "【簽核完成】「{$formRequest->title}」已通過全數關卡審核並正式生效！"
        );

        return [
            'status' => 'approved',
            'is_completed' => true,
            'message' => '所有關卡審核完成，申請單已正式核准結案！',
        ];
    }

    /**
     * 發送待審通知給審批人及其代理人
     */
    protected function notifyApproverAndDelegates(ApprovalRecord $record, FormRequest $formRequest, string $stepTitle): void
    {
        $approver = $record->approver;
        if (!$approver) {
            return;
        }

        $applicantName = $formRequest->user?->name ?? '同仁';
        $formName = $formRequest->form?->name ?? '表單';

        $approver->notify(new EipSystemNotification(
            title: "【待簽核單據】{$formName}（{$stepTitle}）",
            message: "同仁 {$applicantName} 的「{$formRequest->title}」流轉至「{$stepTitle}」，請進行審批。",
            type: 'form_approval',
            actionUrl: route('forms.show', $formRequest->id),
            senderName: $applicantName,
            extra: ['form_request_id' => $formRequest->id, 'step' => $record->step]
        ));

        // 檢查代理人
        $activeDelegations = Delegation::where('user_id', $approver->id)
            ->currentlyActive()
            ->with('delegate')
            ->get();

        foreach ($activeDelegations as $delegation) {
            $delegation->delegate?->notify(new EipSystemNotification(
                title: "【代理待審核】{$formName}（{$stepTitle} - 主管：{$approver->name}）",
                message: "同仁 {$applicantName} 的「{$formRequest->title}」進入「{$stepTitle}」，您為主管 {$approver->name} 之代理人，可進行代審。",
                type: 'form_approval',
                actionUrl: route('forms.show', $formRequest->id),
                senderName: $applicantName,
                extra: ['form_request_id' => $formRequest->id, 'delegated_from' => $approver->name, 'step' => $record->step]
            ));
        }
    }

    /**
     * 主管協同轉簽 (Transfer Approval)
     * 將當前審核關卡權責轉派給指定主管決行
     */
    public function transferApproval(
        FormRequest $formRequest,
        ApprovalRecord $record,
        User $operator,
        User $targetApprover,
        string $reason
    ): array {
        $isValidApprover = $operator->isAdmin()
            || $record->approver_id === $operator->id
            || $operator->canActAsDelegateFor($record->approver_id);

        if (!$isValidApprover) {
            abort(403, '您沒有權限轉簽此單據。');
        }

        if ($targetApprover->id === $operator->id) {
            abort(422, '不可將單據轉簽給自己。');
        }

        $stepTitle = $record->step_title ?: "關卡 {$record->step}";

        // 更新原主管記錄為 transferred
        $record->update([
            'status' => 'transferred',
            'comment' => "【轉簽派審】轉由主管 {$targetApprover->name} 決行。事由：{$reason}",
            'transferred_to_id' => $targetApprover->id,
            'actioned_at' => now(),
        ]);

        // 建立受派新主管的待審記錄
        $newRecord = ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => $record->step,
            'step_title' => "{$stepTitle} (由 {$operator->name} 轉簽)",
            'approver_id' => $targetApprover->id,
            'transferred_from_id' => $operator->id,
            'status' => 'pending',
        ]);

        $newRecord->load('approver');
        $this->notifyApproverAndDelegates($newRecord, $formRequest, $newRecord->step_title);

        AuditLog::log(
            action: 'transfer_form_approval',
            description: "審核人 {$operator->name} 將單據「{$formRequest->title}」之「{$stepTitle}」轉簽給主管 {$targetApprover->name}（事由：{$reason}）",
            auditable: $formRequest,
            details: [
                'from_user_id' => $operator->id,
                'target_approver_id' => $targetApprover->id,
                'step' => $record->step,
                'reason' => $reason,
            ]
        );

        WebhookService::dispatch(
            'form.transferred',
            [
                'form_request_id' => $formRequest->id,
                'title' => $formRequest->title,
                'step' => $record->step,
                'from_user' => $operator->name,
                'target_user' => $targetApprover->name,
                'reason' => $reason,
            ],
            "【簽核轉簽】「{$formRequest->title}」已由 {$operator->name} 轉簽給主管 {$targetApprover->name}"
        );

        return [
            'status' => 'transferred',
            'message' => "單據已成功轉簽給主管「{$targetApprover->name}」！",
        ];
    }

    /**
     * 主管協同加簽 (Add-Sign)
     * 邀請其他同仁或專業主管會辦簽署意見
     */
    public function addSignApproval(
        FormRequest $formRequest,
        ApprovalRecord $record,
        User $operator,
        User $targetAddSigner,
        string $reason
    ): array {
        $isValidApprover = $operator->isAdmin()
            || $record->approver_id === $operator->id
            || $operator->canActAsDelegateFor($record->approver_id);

        if (!$isValidApprover) {
            abort(403, '您沒有權限發起加簽。');
        }

        if ($targetAddSigner->id === $operator->id) {
            abort(422, '不可對自己發起會辦加簽。');
        }

        $existingPendingAddSign = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('step', $record->step)
            ->where('is_add_sign', true)
            ->where('approver_id', $targetAddSigner->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPendingAddSign) {
            abort(422, "同仁「{$targetAddSigner->name}」已在會辦加簽名單中，尚待簽署。");
        }

        $stepTitle = "【會辦加簽】邀請 {$targetAddSigner->name} 會審";

        $addSignRecord = ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => $record->step,
            'step_title' => $stepTitle,
            'approver_id' => $targetAddSigner->id,
            'add_signed_by_id' => $operator->id,
            'is_add_sign' => true,
            'status' => 'pending',
            'comment' => "【加簽邀請事由】{$reason}",
        ]);

        $addSignRecord->load('approver');
        $this->notifyApproverAndDelegates($addSignRecord, $formRequest, $stepTitle);

        AuditLog::log(
            action: 'add_sign_form_request',
            description: "審核人 {$operator->name} 於單據「{$formRequest->title}」發起會辦加簽給 {$targetAddSigner->name}（事由：{$reason}）",
            auditable: $formRequest,
            details: [
                'operator_id' => $operator->id,
                'target_add_signer_id' => $targetAddSigner->id,
                'step' => $record->step,
                'reason' => $reason,
            ]
        );

        return [
            'status' => 'add_signed',
            'message' => "已成功發起會辦加簽給「{$targetAddSigner->name}」！",
        ];
    }

    /**
     * 補打卡申請單核准結案時，自動同步修正或建立當日考勤紀錄 (Attendance Regularization)
     */
    public function syncAttendanceAmendment(FormRequest $formRequest): void
    {
        // 需為忘刷/補打卡單
        if ($formRequest->form?->code !== 'CLOCK_ADJUST') {
            return;
        }

        $formData = $formRequest->data ?? [];
        $adjustDate = $formData['adjust_date'] ?? null;
        if (!$adjustDate) {
            return;
        }

        $adjustType = $formData['adjust_type'] ?? '';
        $actualTimeStr = trim($formData['actual_time'] ?? '');
        $reason = $formData['reason'] ?? '';
        $user = $formRequest->user;
        if (!$user) {
            return;
        }

        // 查找或建立當日考勤紀錄 (使用 whereDate 相容多種資料庫日期儲存格式)
        $attendance = \App\Models\Attendance::where('user_id', $user->id)
            ->whereDate('date', $adjustDate)
            ->first();

        if (!$attendance) {
            $attendance = new \App\Models\Attendance([
                'user_id' => $user->id,
                'date' => $adjustDate,
            ]);
        }

        $noteMsg = "[補打卡核准結案] 單號 #{$formRequest->id}" . ($reason ? " (事由: {$reason})" : "");
        $attendance->note = $attendance->note ? "{$attendance->note}；{$noteMsg}" : $noteMsg;

        // 解析時間：例如 "09:00"、"18:30"、"09:00 - 18:00" 或 "09:00~18:00"
        $times = preg_split('/[\s\-\~～至到,]+/', $actualTimeStr);
        $time1 = !empty($times[0]) ? $times[0] : null;
        $time2 = !empty($times[1]) ? $times[1] : null;

        $parseDateTime = function ($date, $time, $defaultTime) {
            $t = $time ?: $defaultTime;
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t)) {
                return \Carbon\Carbon::parse("{$date} {$t}");
            }
            try {
                return \Carbon\Carbon::parse("{$date} {$t}");
            } catch (\Exception $e) {
                return \Carbon\Carbon::parse("{$date} {$defaultTime}");
            }
        };

        $isClockInOnly = str_contains($adjustType, '上班') && !str_contains($adjustType, '全日');
        $isClockOutOnly = str_contains($adjustType, '下班') && !str_contains($adjustType, '全日');

        if ($isClockInOnly) {
            $attendance->clock_in_at = $parseDateTime($adjustDate, $time1, '09:00:00');
            $attendance->clock_in_type = $attendance->clock_in_type ?: 'office';
        } elseif ($isClockOutOnly) {
            $attendance->clock_out_at = $parseDateTime($adjustDate, $time1, '18:00:00');
            $attendance->clock_out_type = $attendance->clock_out_type ?: 'office';
        } else {
            // 全日未打卡補登
            $attendance->clock_in_at = $parseDateTime($adjustDate, $time1, '09:00:00');
            $attendance->clock_in_type = $attendance->clock_in_type ?: 'office';
            $attendance->clock_out_at = $parseDateTime($adjustDate, $time2 ?: '18:00:00', '18:00:00');
            $attendance->clock_out_type = $attendance->clock_out_type ?: 'office';
        }

        // 計算工時與狀態判定
        $attendance->calculateWorkHours();
        // 主管核准補卡後，若上下班打卡時間齊備，出勤狀態校正為正常出勤 (normal)
        if ($attendance->clock_in_at && $attendance->clock_out_at) {
            $attendance->status = 'normal';
        } elseif (!$attendance->status) {
            $attendance->status = 'normal';
        }

        $attendance->save();

        AuditLog::log(
            action: 'attendance_amendment_synced',
            description: "補打卡單「{$formRequest->title}」核准結案，系統自動同步修正同仁 {$user->name} 於 {$adjustDate} 之考勤紀錄",
            auditable: $attendance,
            details: [
                'form_request_id' => $formRequest->id,
                'adjust_date' => $adjustDate,
                'adjust_type' => $adjustType,
                'clock_in_at' => $attendance->clock_in_at?->toDateTimeString(),
                'clock_out_at' => $attendance->clock_out_at?->toDateTimeString(),
                'status' => $attendance->status,
                'work_hours' => $attendance->work_hours,
            ]
        );
    }

    /**
     * 申請人主動撤回申請單 (Withdraw Form Request)
     */
    public function withdrawFormRequest(FormRequest $formRequest, User $operator, ?string $reason = null): array
    {
        if ($formRequest->status !== 'pending') {
            abort(422, '該單據已非審批中狀態，無法進行撤回。');
        }

        // 僅限申請人本人或管理者可撤回
        if ($formRequest->user_id !== $operator->id && !$operator->isAdmin()) {
            abort(403, '您沒有權限撤回此份申請單據。');
        }

        $formRequest->update(['status' => 'withdrawn']);

        // 若為休假單，立即釋放扣留凍結之額度 (Pending => Released)
        if ($formRequest->form?->code === 'LEAVE') {
            app(LeaveBalanceService::class)->releaseBalance($formRequest, approved: false);
        }

        // 作廢當前進行中的待審批記錄 (包含主審與加簽)
        $pendingRecords = ApprovalRecord::where('form_request_id', $formRequest->id)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingRecords as $record) {
            $record->update([
                'status' => 'withdrawn',
                'comment' => "【申請人撤回】" . ($reason ? "事由：{$reason}" : '申請人主動撤回作廢'),
                'actioned_at' => now(),
            ]);

            // 通知原本負責審核的主管或受派人
            $record->approver?->notify(new EipSystemNotification(
                title: "【簽核撤回】同仁已撤回單據「{$formRequest->title}」",
                message: "同仁 {$operator->name} 已主動撤回申請單「{$formRequest->title}」" . ($reason ? "，事由：{$reason}" : '') . "，該單據已作廢，無需再進行審批。",
                type: 'form_withdrawn',
                actionUrl: route('forms.show', $formRequest->id),
                senderName: $operator->name,
                extra: ['status' => 'withdrawn', 'form_request_id' => $formRequest->id]
            ));
        }

        AuditLog::log(
            action: 'withdraw_form_request',
            description: "同仁 {$operator->name} 主動撤回了單據「{$formRequest->title}」" . ($reason ? "（事由：{$reason}）" : ''),
            auditable: $formRequest,
            details: [
                'form_request_id' => $formRequest->id,
                'request_no' => $formRequest->request_no,
                'operator_id' => $operator->id,
                'reason' => $reason,
                'previous_status' => 'pending',
                'new_status' => 'withdrawn',
            ]
        );

        WebhookService::dispatch(
            'form.withdrawn',
            [
                'form_request_id' => $formRequest->id,
                'request_no' => $formRequest->request_no,
                'title' => $formRequest->title,
                'operator' => $operator->name,
                'reason' => $reason,
            ],
            "【簽核撤回】同仁 {$operator->name} 已主動撤回「{$formRequest->title}」"
        );

        return [
            'status' => 'withdrawn',
            'message' => '申請單已成功撤回並作廢。',
        ];
    }

    /**
     * 申請人修改內容並重新提交審查 (Resubmit Form Request)
     */
    public function resubmitFormRequest(
        FormRequest $formRequest,
        User $applicant,
        array $updatedData,
        ?array $newAttachments = null,
        ?string $resubmitNote = null
    ): array {
        if ($formRequest->status !== 'revision_required') {
            throw new \InvalidArgumentException('僅限處於退回修改狀態之申請單允許重新提交。');
        }

        if ($formRequest->user_id !== $applicant->id) {
            throw new \InvalidArgumentException('您沒有權限重新提交此份申請單。');
        }

        // 處理休假天數變更 (若為休假單且天數有調整)
        if ($formRequest->form?->code === 'LEAVE' || ($formRequest->data['leave_type'] ?? null)) {
            $oldDays = floatval($formRequest->data['days'] ?? 0);
            $newDays = floatval($updatedData['days'] ?? $oldDays);
            $rawLeaveType = $updatedData['leave_type'] ?? ($formRequest->data['leave_type'] ?? 'annual');
            $leaveType = \App\Models\LeaveBalance::normalizeType($rawLeaveType);

            if ($newDays !== $oldDays) {
                // 天數有變更，調校 pending_days
                $balance = \App\Models\LeaveBalance::where('user_id', $applicant->id)
                    ->where('leave_type', $leaveType)
                    ->where('year', now()->year)
                    ->first();

                if ($balance) {
                    $diff = $newDays - $oldDays;
                    if ($diff > 0 && ($balance->remaining_days < $diff)) {
                        throw new \InvalidArgumentException("可用額度不足，無法增加請假天數至 {$newDays} 天。");
                    }
                    $balance->increment('pending_days', $diff);
                }
            }
        }

        // 合併附件
        $attachments = $formRequest->attachments ?? [];
        if (!empty($newAttachments)) {
            $attachments = array_merge($attachments, $newAttachments);
        }

        // 記錄歷次版本修訂快照與差異對比
        $revisionHistory = $formRequest->revision_history ?? [];
        $versionNumber = count($revisionHistory) + 1;
        $revisionHistory[] = [
            'version' => $versionNumber,
            'resubmitted_at' => now()->toIso8601String(),
            'resubmitted_by' => [
                'id' => $applicant->id,
                'name' => $applicant->name,
            ],
            'resubmit_note' => $resubmitNote,
            'previous_data' => $formRequest->data ?? [],
            'new_data' => $updatedData,
            'new_attachments' => $newAttachments ?? [],
        ];

        // 更新單據資料與狀態
        $formRequest->update([
            'data' => $updatedData,
            'attachments' => $attachments,
            'status' => 'pending',
            'revision_history' => $revisionHistory,
        ]);

        // 取得當前關卡資訊
        $currentStep = $formRequest->current_step ?: 1;
        $workflowSnapshot = $formRequest->workflow_snapshot ?? [];
        $currentStepData = $workflowSnapshot[$currentStep - 1] ?? null;
        $targetApproverId = $currentStepData['approver_id'] ?? null;

        // 若無明確 approver_id，嘗試從前一筆退回記錄找回原審核主管
        if (!$targetApproverId) {
            $lastReturnedRecord = ApprovalRecord::where('form_request_id', $formRequest->id)
                ->where('step', $currentStep)
                ->where('status', 'returned')
                ->latest()
                ->first();
            $targetApproverId = $lastReturnedRecord?->approver_id ?? User::where('role', 'admin')->value('id');
        }

        $stepTitle = $currentStepData['title'] ?? "第 {$currentStep} 關審核";

        // 為當前關卡建立新的待審批記錄
        $newRecord = ApprovalRecord::create([
            'form_request_id' => $formRequest->id,
            'step' => $currentStep,
            'step_title' => $stepTitle,
            'approver_id' => $targetApproverId,
            'status' => 'pending',
            'comment' => $resubmitNote ? "【同仁補件重新送審】說明：{$resubmitNote}" : "【同仁補件重新送審】已更新內容",
        ]);

        // 通知審核主管
        $this->notifyApproverAndDelegates($newRecord, $formRequest, $stepTitle);

        AuditLog::log(
            action: 'resubmit_form_request',
            description: "同仁 {$applicant->name} 修改並重新提交了申請單「{$formRequest->title}」" . ($resubmitNote ? "（說明：{$resubmitNote}）" : ''),
            auditable: $formRequest,
            details: [
                'form_request_id' => $formRequest->id,
                'request_no' => $formRequest->request_no,
                'applicant_id' => $applicant->id,
                'current_step' => $currentStep,
                'resubmit_note' => $resubmitNote,
            ]
        );

        WebhookService::dispatch(
            'form.resubmitted',
            [
                'form_request_id' => $formRequest->id,
                'request_no' => $formRequest->request_no,
                'title' => $formRequest->title,
                'applicant' => $applicant->name,
                'step' => $currentStep,
                'resubmit_note' => $resubmitNote,
            ],
            "【補件重新送審】同仁 {$applicant->name} 已修改並重新提交「{$formRequest->title}」"
        );

        return [
            'status' => 'pending',
            'message' => '申請單已成功修改並重新提交主管審查！',
        ];
    }
}
