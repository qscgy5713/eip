<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->query('category');

        $query = Announcement::with('author')
            ->withExists(['reads as is_read' => fn($q) => $q->where('user_id', $user->id)])
            ->where('status', 'published')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        $announcements = $query->paginate(10);

        return Inertia::render('Announcements/Index', [
            'announcements' => $announcements,
            'category' => $category ?? 'all',
        ]);
    }

    public function show(Request $request, Announcement $announcement): Response
    {
        $user = $request->user();

        // 僅管理員可預覽未發布或草稿公告
        if ($announcement->status !== 'published' && !$user->isAdmin()) {
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
            'readers' => $announcement->reads->map(fn($r) => [
                'name' => $r->user?->name ?? '離職同仁',
                'read_at' => $r->read_at?->format('Y-m-d H:i') ?? '',
            ]),
        ]);
    }
}
