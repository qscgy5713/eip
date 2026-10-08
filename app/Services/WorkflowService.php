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
        ?string $comment
    ): array {
        $delegatedFromId = null;
        if ($record->approver_id !== $actionUser->id) {
            $delegatedFromId = $record->approver_id;
        }

        $record->update([
            'status' => $status,
            'comment' => $comment,
            'delegated_from_id' => $delegatedFromId,
            'actioned_at' => now(),
        ]);

        $statusText = $status === 'approved' ? '核准通過' : '退件駁回';
        $delegator = $delegatedFromId ? User::find($delegatedFromId) : null;
        $signRoleText = $delegator ? "代理人 {$actionUser->name}（原主管：{$delegator->name}）" : "審核人 {$actionUser->name}";
        $stepTitle = $record->step_title ?? "關卡 {$record->step}";

        // 1. 若駁回：立即終止流程
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
}
