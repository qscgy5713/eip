<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'user_id',
        'request_no',
        'title',
        'data',
        'attachments',
        'status',
        'current_step',
        'total_steps',
        'workflow_snapshot',
    ];

    protected $casts = [
        'data' => 'array',
        'attachments' => 'array',
        'workflow_snapshot' => 'array',
        'current_step' => 'integer',
        'total_steps' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvalRecords(): HasMany
    {
        return $this->hasMany(ApprovalRecord::class)->orderBy('step');
    }

    /**
     * 檢查使用者是否有權檢閱此單據 (防範 IDOR 越權存取)
     */
    public function canAccess(User $user): bool
    {
        // 1. 系統管理員具備全局調閱權限
        if ($user->isAdmin()) {
            return true;
        }

        // 2. 申請同仁本人
        if ($this->user_id === $user->id) {
            return true;
        }

        // 3. 審核記錄中包含此使用者 (如已審或待審主管)
        if ($this->approvalRecords()->where('approver_id', $user->id)->exists()) {
            return true;
        }

        // 4. 多級工作流快照中的預定審核主管 (包含後續關卡主管)
        if (!empty($this->workflow_snapshot) && is_array($this->workflow_snapshot)) {
            $workflowApproverIds = array_column($this->workflow_snapshot, 'approver_id');
            if (in_array($user->id, $workflowApproverIds)) {
                return true;
            }
        }

        // 5. 該同仁所屬部門主管
        if ($user->isManager() && $this->user && $this->user->department_id === $user->department_id) {
            return true;
        }

        // 6. 待審核人目前生效中的職務代理人
        $pendingApproverIds = $this->approvalRecords()
            ->where('status', 'pending')
            ->pluck('approver_id');

        if ($user->delegatedToMe()->whereIn('user_id', $pendingApproverIds)->currentlyActive()->exists()) {
            return true;
        }

        return false;
    }
}
