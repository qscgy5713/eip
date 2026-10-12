<?php

namespace App\Console\Commands;

use App\Models\ApprovalRecord;
use App\Notifications\EipSystemNotification;
use Illuminate\Console\Command;

/**
 * 每日提醒:簽核關卡停留超過指定天數的主管,彙整成一則通知(站內 + Email)。
 * 一人一則、同一人一天最多一則,停權/離職的簽核人不提醒。
 */
class RemindPendingApprovals extends Command
{
    protected $signature = 'eip:remind-pending-approvals {--days=2 : 停留幾天以上才提醒}';

    protected $description = '提醒簽核人處理停留過久的待簽核單據';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $records = ApprovalRecord::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->whereHas('formRequest', fn ($q) => $q->where('status', 'pending'))
            ->whereHas('approver', fn ($q) => $q->where('status', 'active'))
            ->with(['approver', 'formRequest:id,title,user_id', 'formRequest.user:id,name'])
            ->orderBy('created_at')
            ->get();

        $reminded = 0;
        foreach ($records->groupBy('approver_id') as $approverRecords) {
            $approver = $approverRecords->first()->approver;

            $alreadyToday = $approver->notifications()
                ->where('data', 'like', '%"type":"approval_reminder"%')
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();
            if ($alreadyToday) {
                continue;
            }

            $count = $approverRecords->count();
            $oldest = $approverRecords->first();
            $list = $approverRecords->take(5)->map(function ($r) {
                $who = $r->formRequest->user?->name ?? '同仁';

                return "・{$who}「{$r->formRequest->title}」(已等待 ".max(1, (int) $r->created_at->diffInDays(now())).' 天)';
            })->implode("\n");
            $more = $count > 5 ? "\n…另有 ".($count - 5).' 張' : '';

            $approver->notify(new EipSystemNotification(
                title: "【簽核提醒】您有 {$count} 張單據等待處理超過 {$days} 天",
                message: "以下單據仍在等您簽核:\n{$list}{$more}",
                type: 'approval_reminder',
                actionUrl: route('approvals.index'),
                senderName: '系統提醒',
                extra: ['count' => $count, 'oldest_form_request_id' => $oldest->form_request_id]
            ));
            $reminded++;
        }

        $this->info("已提醒 {$reminded} 位簽核人(共 {$records->count()} 張逾期單據)。");

        return self::SUCCESS;
    }
}
