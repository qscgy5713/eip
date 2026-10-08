<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use App\Services\WebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->query('category');
        $status = $request->query('status', 'published');

        $query = Announcement::with('author')
            ->withExists(['reads as is_read' => fn($q) => $q->where('user_id', $user->id)]);

        // 僅具備管理身分者可查看草稿公告
        if ($user->isAdmin() || $user->isHr()) {
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }
        } else {
            $query->where('status', 'published');
        }

        $query->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->latest();

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        $announcements = $query->paginate(10);

        return Inertia::render('Announcements/Index', [
            'announcements' => $announcements,
            'category' => $category ?? 'all',
            'status' => $status,
            'canManage' => $user->isAdmin() || $user->isHr() || $user->isManager(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isHr() && !$user->isManager()) {
            abort(403, '您沒有發布企業公告的權限。');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string|in:company,activity,administrative,general',
            'priority' => 'required|string|in:normal,high,urgent',
            'is_pinned' => 'nullable|boolean',
            'status' => 'required|string|in:published,draft',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,zip',
        ]);

        $attachmentsData = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file && $file->isValid()) {
                    // 存入 local 私有儲存磁碟，避免草稿附件遭 Nginx 直接外洩 (SEC-01)
                    $path = $file->store('private_announcement_attachments', 'local');
                    $attachmentsData[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'mime' => $file->getClientMimeType(),
                    ];
                }
            }
        }

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
            'status' => $validated['status'],
            'attachments' => $attachmentsData,
            'author_id' => $user->id,
            'department_id' => $user->department_id,
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        AuditLog::log(
            action: 'create_announcement',
            description: "同仁 {$user->name} 發布了企業公告「{$announcement->title}」" . (count($attachmentsData) > 0 ? "（檢附 " . count($attachmentsData) . " 個附件）" : ''),
            auditable: $announcement,
            details: [
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'category' => $announcement->category,
                'priority' => $announcement->priority,
                'status' => $announcement->status,
                'attachments_count' => count($attachmentsData),
            ]
        );

        if ($announcement->status === 'published') {
            WebhookService::dispatch(
                'announcement.published',
                [
                    'announcement_id' => $announcement->id,
                    'title' => $announcement->title,
                    'category' => $announcement->category,
                    'priority' => $announcement->priority,
                    'author' => $user->name,
                ],
                "【企業公告】{$user->name} 發布了「{$announcement->title}」"
            );

            // 若為置頂或緊急公告，發送全員站內通知
            if ($announcement->is_pinned || in_array($announcement->priority, ['urgent', 'high'])) {
                $activeUsers = User::where('status', 'active')->where('id', '!=', $user->id)->get();
                foreach ($activeUsers as $targetUser) {
                    $targetUser->notify(new EipSystemNotification(
                        title: "【重要公告】{$announcement->title}",
                        message: "公司發布了重要公告，請同仁撥冗查閱！",
                        type: 'announcement',
                        actionUrl: route('announcements.show', $announcement->id),
                        senderName: $user->name,
                        extra: ['announcement_id' => $announcement->id]
                    ));
                }
            }
        }

        return redirect()->route('announcements.index')
            ->with('success', '企業公告發布成功！');
    }

    public function show(Request $request, Announcement $announcement): Response
    {
        $user = $request->user();

        // 僅管理員或作者可預覽未發布或草稿公告
        if ($announcement->status !== 'published' && !$user->isAdmin() && $announcement->author_id !== $user->id) {
            abort(404, '此公告目前未公開或已被下架。');
        }

        // 自動登記已讀狀態
        AnnouncementRead::firstOrCreate([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
        ], [
            'read_at' => now(),
        ]);

        $announcement->load(['author', 'reads.user']);
        $announcement->reads_count = $announcement->reads()->count();

        return Inertia::render('Announcements/Show', [
            'announcement' => $announcement,
            'canManage' => $user->isAdmin() || $user->isHr() || $announcement->author_id === $user->id,
            'readers' => $announcement->reads->map(fn($r) => [
                'name' => $r->user?->name ?? '離職同仁',
                'read_at' => $r->read_at?->format('Y-m-d H:i') ?? '',
            ]),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isHr() && $announcement->author_id !== $user->id) {
            abort(403, '您沒有編輯此公告的權限。');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string|in:company,activity,administrative,general',
            'priority' => 'required|string|in:normal,high,urgent',
            'is_pinned' => 'nullable|boolean',
            'status' => 'required|string|in:published,draft',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv,zip',
        ]);

        $existingAttachments = $announcement->attachments ?? [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file && $file->isValid()) {
                    // 改用 local 私有儲存磁碟 (SEC-01)
                    $path = $file->store('private_announcement_attachments', 'local');
                    $existingAttachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'mime' => $file->getClientMimeType(),
                    ];
                }
            }
        }

        $wasDraft = $announcement->status === 'draft';
        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
            'status' => $validated['status'],
            'attachments' => $existingAttachments,
            'published_at' => ($wasDraft && $validated['status'] === 'published') ? now() : $announcement->published_at,
        ]);

        AuditLog::log(
            action: 'update_announcement',
            description: "同仁 {$user->name} 更新了企業公告「{$announcement->title}」",
            auditable: $announcement,
            details: [
                'announcement_id' => $announcement->id,
                'status' => $announcement->status,
                'attachments_count' => count($existingAttachments),
            ]
        );

        return redirect()->route('announcements.show', $announcement->id)
            ->with('success', '公告更新成功！');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isHr() && $announcement->author_id !== $user->id) {
            abort(403, '您沒有刪除此公告的權限。');
        }

        $title = $announcement->title;
        $id = $announcement->id;

        // 清理附件實體檔案 (local 與 public 雙磁碟相容)
        if (!empty($announcement->attachments)) {
            foreach ($announcement->attachments as $att) {
                if (isset($att['path'])) {
                    if (Storage::disk('local')->exists($att['path'])) {
                        Storage::disk('local')->delete($att['path']);
                    }
                    if (Storage::disk('public')->exists($att['path'])) {
                        Storage::disk('public')->delete($att['path']);
                    }
                }
            }
        }

        $announcement->delete();

        AuditLog::log(
            action: 'delete_announcement',
            description: "同仁 {$user->name} 刪除了企業公告「{$title}」(#{$id})",
            details: [
                'announcement_id' => $id,
                'title' => $title,
            ]
        );

        return redirect()->route('announcements.index')
            ->with('success', "公告「{$title}」已成功刪除。");
    }

    /**
     * 安全下載公告官方附件檔案
     */
    public function downloadAttachment(Request $request, Announcement $announcement, int $index)
    {
        $user = $request->user();

        // 僅限公開公告，或管理員/作者可下載草稿附件
        if ($announcement->status !== 'published' && !$user->isAdmin() && $announcement->author_id !== $user->id) {
            abort(404, '此公告目前未公開，無法下載附件。');
        }

        $attachments = $announcement->attachments ?? [];
        if (!isset($attachments[$index])) {
            abort(404, '找不到指定的公告附件。');
        }

        $attachment = $attachments[$index];
        $disk = Storage::disk('local');
        $filePath = $attachment['path'] ?? '';

        // 優先從 local 私有磁碟讀取，若不存在則相容歷史 public 磁碟檔案 (SEC-01)
        if (!$disk->exists($filePath)) {
            $disk = Storage::disk('public');
            if (!$disk->exists($filePath)) {
                abort(404, '附件檔案實體不存在或已損毀。');
            }
        }

        AuditLog::log(
            action: 'download_announcement_attachment',
            description: "同仁 {$user->name} 下載了公告「{$announcement->title}」之附件「{$attachment['name']}」",
            auditable: $announcement,
            details: [
                'announcement_id' => $announcement->id,
                'attachment_name' => $attachment['name'],
                'file_size' => $attachment['size'],
            ]
        );

        return $disk->download($filePath, $attachment['name']);
    }
}

