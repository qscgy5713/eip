<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TicketController extends Controller
{
    /**
     * 服務工單中心首頁
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isStaff = $user->isAdmin() || $user->isHr();

        $tab = $request->input('tab', 'my'); // my, assigned, all, pending
        $search = $request->input('search');
        $category = $request->input('category');
        $priority = $request->input('priority');
        $status = $request->input('status');

        $query = Ticket::with(['user:id,name,email,department_id', 'user.department:id,name', 'assignee:id,name']);

        // 依 Tab 劃分視圖
        if ($tab === 'my') {
            $query->where('user_id', $user->id);
        } elseif ($tab === 'assigned') {
            $query->where('assigned_to', $user->id);
        } elseif ($tab === 'pending') {
            if (! $isStaff) {
                $query->where('user_id', $user->id);
            }
            $query->whereIn('status', ['open', 'in_progress', 'pending_user']);
        } elseif ($tab === 'all') {
            if (! $isStaff) {
                $query->where('user_id', $user->id);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $tickets = $query->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // 統計指標
        $openCount = Ticket::where('status', 'open')->count();
        $inProgressCount = Ticket::where('status', 'in_progress')->count();
        $myPendingCount = Ticket::where('user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress', 'pending_user'])
            ->count();
        $resolvedCount = Ticket::where('status', 'resolved')->count();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'tab' => $tab,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
            ],
            'stats' => [
                'openCount' => $openCount,
                'inProgressCount' => $inProgressCount,
                'myPendingCount' => $myPendingCount,
                'resolvedCount' => $resolvedCount,
            ],
            'isStaff' => $isStaff,
        ]);
    }

    /**
     * 建立工單頁面
     */
    public function create()
    {
        return Inertia::render('Tickets/Create');
    }

    /**
     * 儲存新叫修工單
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:it_hardware,it_software,it_network,facility,stationery,other'],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:100'],
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip'], // 10MB;依檔案內容判斷型別,不收 html/svg/php 等可被瀏覽器或伺服器執行的檔案
        ]);

        $storedAttachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('private_ticket_attachments', 'local');
                $storedAttachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                ];
            }
        }

        $ticket = Ticket::create([
            'ticket_number' => Ticket::generateTicketNumber(),
            'user_id' => $user->id,
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'title' => $validated['title'],
            'description' => $validated['description'],
            'location' => $validated['location'] ?? null,
            'attachments' => $storedAttachments,
        ]);

        AuditLog::log(
            'ticket_create',
            "提報服務叫修工單「{$ticket->ticket_number} - {$ticket->title}」",
            $ticket,
            ['ticket_number' => $ticket->ticket_number, 'priority' => $ticket->priority]
        );

        // 通知 IT/總務管理人員
        $staffMembers = User::where('role', 'admin')->get();
        foreach ($staffMembers as $staff) {
            if ($staff->id !== $user->id) {
                $staff->notify(new EipSystemNotification(
                    title: "【新工單受理】{$ticket->ticket_number}",
                    message: "同仁 {$user->name} 提報了「{$ticket->title}」叫修工單，請儘速指派工程師處理。",
                    type: 'ticket',
                    actionUrl: route('tickets.show', $ticket->id),
                    senderName: $user->name,
                    extra: ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number]
                ));
            }
        }

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', "工單 {$ticket->ticket_number} 已成功建立並送出，將儘速為您安排處理！");
    }

    /**
     * 檢視工單詳情與溝通履歷
     */
    public function show(Ticket $ticket, Request $request)
    {
        $user = $request->user();

        if (! $ticket->canView($user)) {
            abort(403, '您沒有權限檢視此工單');
        }

        $ticket->load(['user.department', 'assignee']);

        // 載入回覆對話歷程（一般同仁不可見 is_internal 內部備註）
        $canManage = $ticket->canManage($user);
        $repliesQuery = $ticket->replies()->with('user:id,name,role')->orderBy('created_at');
        if (! $canManage) {
            $repliesQuery->where('is_internal', false);
        }
        $replies = $repliesQuery->get();

        // 可指派之 IT/總務人員
        $staffList = $canManage ? User::whereIn('role', ['admin', 'manager', 'hr'])->select('id', 'name', 'role')->get() : [];

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'replies' => $replies,
            'canManage' => $canManage,
            'staffList' => $staffList,
        ]);
    }

    /** 下載工單附件(需有權檢視該工單;內部備註的附件只有處理人員能下載) */
    public function attachment(Request $request, Ticket $ticket, int $index)
    {
        return $this->sendAttachment($request, $ticket, null, $index);
    }

    public function replyAttachment(Request $request, Ticket $ticket, TicketReply $reply, int $index)
    {
        return $this->sendAttachment($request, $ticket, $reply, $index);
    }

    private function sendAttachment(Request $request, Ticket $ticket, ?TicketReply $reply, int $index)
    {
        $user = $request->user();
        abort_unless($ticket->canView($user), 403, '您沒有權限檢視此工單');

        if ($reply) {
            abort_unless($reply->ticket_id === $ticket->id, 404);
            abort_if($reply->is_internal && ! $ticket->canManage($user), 403, '您沒有權限檢視此附件');
            $list = $reply->attachments ?? [];
        } else {
            $list = $ticket->attachments ?? [];
        }

        $att = $list[$index] ?? null;
        abort_unless($att && ! empty($att['path']), 404);

        // 舊資料(改版前)放在 public disk,新資料放在私有 local disk
        $disk = Storage::disk('local')->exists($att['path']) ? Storage::disk('local') : Storage::disk('public');
        abort_unless($disk->exists($att['path']), 404);

        // 一律以下載方式輸出並禁止瀏覽器猜型別,避免上傳的內容在本站網域被渲染執行
        return $disk->download($att['path'], $att['name'] ?? basename($att['path']), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }

    /**
     * 指派/轉派工單負責工程師
     */
    public function assign(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        if (! $ticket->canManage($user)) {
            abort(403, '僅管理員或指派工程師可調度工單');
        }

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignee = User::findOrFail($validated['assigned_to']);
        $oldAssignee = $ticket->assignee?->name;

        $ticket->update([
            'assigned_to' => $assignee->id,
            'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
        ]);

        AuditLog::log(
            'ticket_assign',
            "指派工單「{$ticket->ticket_number}」給工程師 {$assignee->name}",
            $ticket,
            ['assigned_to' => $assignee->id]
        );

        // 通知被指派工程師
        if ($assignee->id !== $user->id) {
            $assignee->notify(new EipSystemNotification(
                title: "【工單指派】{$ticket->ticket_number}",
                message: "您已被指派處理「{$ticket->title}」服務叫修工單，請儘速接洽處理。",
                type: 'ticket',
                actionUrl: route('tickets.show', $ticket->id),
                senderName: $user->name,
                extra: ['ticket_id' => $ticket->id]
            ));
        }

        return back()->with('success', "已將工單成功指派給 {$assignee->name} 處理！");
    }

    /**
     * 更新處理狀態 (in_progress, pending_user, resolved)
     */
    public function updateStatus(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        if (! $ticket->canManage($user)) {
            abort(403, '僅管理員或指派工程師可更新狀態');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:in_progress,pending_user,resolved,closed'],
        ]);

        $newStatus = $validated['status'];
        $data = ['status' => $newStatus];

        if ($newStatus === 'resolved' && ! $ticket->resolved_at) {
            $data['resolved_at'] = now();
        }

        if ($newStatus === 'closed' && ! $ticket->closed_at) {
            $data['closed_at'] = now();
        }

        $ticket->update($data);

        AuditLog::log('ticket_status_change', "更新工單「{$ticket->ticket_number}」狀態為 {$ticket->status_label}", $ticket);

        // 完修時通知提單同仁驗收
        if ($newStatus === 'resolved' && $ticket->user_id !== $user->id) {
            $ticket->user->notify(new EipSystemNotification(
                title: "【工單已完修】{$ticket->ticket_number}",
                message: "您提報的「{$ticket->title}」已完成修復！請前往確認並進行結案評分。",
                type: 'ticket',
                actionUrl: route('tickets.show', $ticket->id),
                senderName: $user->name,
                extra: ['ticket_id' => $ticket->id]
            ));
        }

        return back()->with('success', "已將工單狀態更新為「{$ticket->status_label}」！");
    }

    /**
     * 新增溝通與處理歷程回覆
     */
    public function reply(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        if (! $ticket->canView($user)) {
            abort(403, '您沒有權限回覆此工單');
        }

        $validated = $request->validate([
            'message' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);

        $isInternal = $request->boolean('is_internal') && $ticket->canManage($user);

        $storedAttachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('private_ticket_attachments', 'local');
                $storedAttachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                ];
            }
        }

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_internal' => $isInternal,
            'attachments' => $storedAttachments,
        ]);

        // 若提單同仁回覆且狀態為 pending_user，自動轉為 in_progress
        if ($user->id === $ticket->user_id && $ticket->status === 'pending_user') {
            $ticket->update(['status' => 'in_progress']);
        }

        // 通知對方（非內部備註時）
        if (! $isInternal) {
            $recipient = ($user->id === $ticket->user_id) ? $ticket->assignee : $ticket->user;
            if ($recipient && $recipient->id !== $user->id) {
                $recipient->notify(new EipSystemNotification(
                    title: "【工單有新回覆】{$ticket->ticket_number}",
                    message: "{$user->name} 在工單「{$ticket->title}」留下了新回覆訊息。",
                    type: 'ticket',
                    actionUrl: route('tickets.show', $ticket->id),
                    senderName: $user->name,
                    extra: ['ticket_id' => $ticket->id]
                ));
            }
        }

        return back()->with('success', '回覆訊息已送出！');
    }

    /**
     * 提單同仁驗收滿意度評分與結案
     */
    public function rate(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        if ($ticket->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, '僅提單同仁可進行結案評分');
        }

        $validated = $request->validate([
            'satisfaction_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'satisfaction_feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->update([
            'satisfaction_rating' => $validated['satisfaction_rating'],
            'satisfaction_feedback' => $validated['satisfaction_feedback'] ?? null,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        AuditLog::log(
            'ticket_rate',
            "工單「{$ticket->ticket_number}」評分 {$ticket->satisfaction_rating} 星並結案",
            $ticket
        );

        return back()->with('success', '感謝您的滿意度回饋，工單已正式結案！');
    }

    /**
     * 刪除工單 (限管理員)
     */
    public function destroy(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            abort(403, '僅管理員可刪除工單');
        }

        $number = $ticket->ticket_number;
        $ticket->delete();

        AuditLog::log('ticket_delete', "刪除工單「{$number}」", null);

        return redirect()->route('tickets.index')
            ->with('success', "工單 {$number} 已被刪除。");
    }
}
