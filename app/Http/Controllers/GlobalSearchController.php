<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Document;
use App\Models\FormRequest as EipFormRequest;
use App\Models\MeetingRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * 全站快捷搜尋 API (支援跨模組模糊檢索與權限嚴格過濾)
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = trim((string) $request->input('q', ''));

        // 1. 若無搜尋關鍵字，回傳推薦之快捷功能導航
        if ($query === '') {
            return response()->json([
                'query' => '',
                'results' => [
                    'shortcuts' => $this->getQuickShortcuts($user),
                ],
            ]);
        }

        $results = [];

        // 2. 檢索同仁通訊錄 (限在職同仁)
        $employees = User::with('department')
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('employee_no', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('job_title', 'like', "%{$query}%")
                    ->orWhereHas('department', function ($dq) use ($query) {
                        $dq->where('name', 'like', "%{$query}%");
                    });
            })
            ->limit(5)
            ->get()
            ->map(function ($u) {
                $deptName = $u->department?->name ?? '未分派部門';
                $job = $u->job_title ? " · {$u->job_title}" : '';
                return [
                    'id' => $u->id,
                    'title' => $u->name,
                    'subtitle' => "{$deptName}{$job} (分機/電話: " . ($u->phone ?: '無') . ")",
                    'url' => route('directory.index', ['search' => $u->name]),
                    'badge' => $u->role,
                    'type' => 'employee',
                ];
            });

        if ($employees->isNotEmpty()) {
            $results['employees'] = [
                'title' => '同仁通訊錄',
                'items' => $employees,
            ];
        }

        // 3. 檢索表單簽核單據 (嚴格 IDOR 權限隔離)
        $formQuery = EipFormRequest::with(['form', 'user']);

        if (!$user->isAdmin() && !$user->isHr()) {
            if ($user->isManager()) {
                // 主管可查：本人單據、其轄下部屬單據、或本人受派待審單據
                $subordinateIds = User::where('department_id', $user->department_id)->pluck('id');
                $formQuery->where(function ($q) use ($user, $subordinateIds) {
                    $q->where('user_id', $user->id)
                        ->orWhereIn('user_id', $subordinateIds)
                        ->orWhereHas('approvalRecords', function ($aq) use ($user) {
                            $aq->where('approver_id', $user->id);
                        });
                });
            } else {
                // 一般同仁：僅可查本人申請之單據
                $formQuery->where('user_id', $user->id);
            }
        }

        $formRequests = $formQuery->where(function ($q) use ($query) {
            $q->where('request_no', 'like', "%{$query}%")
                ->orWhere('title', 'like', "%{$query}%")
                ->orWhereHas('form', function ($fq) use ($query) {
                    $fq->where('name', 'like', "%{$query}%");
                });
        })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => "【{$item->request_no}】{$item->title}",
                    'subtitle' => "申請人: {$item->user->name} · 狀態: {$this->formatFormStatus($item->status)}",
                    'url' => route('forms.show', $item->id),
                    'badge' => $item->status,
                    'type' => 'form',
                ];
            });

        if ($formRequests->isNotEmpty()) {
            $results['forms'] = [
                'title' => '公文單據',
                'items' => $formRequests,
            ];
        }

        // 4. 檢索企業公告 (排除草稿與未來預約排程)
        $announcements = Announcement::where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%");
            })
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(function ($a) {
                $pubDate = $a->published_at ? $a->published_at->format('Y-m-d') : '即時';
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'subtitle' => "分類: {$a->category} · 發布: {$pubDate}",
                    'url' => route('announcements.show', $a->id),
                    'badge' => $a->is_pinned ? '置頂' : null,
                    'type' => 'announcement',
                ];
            });

        if ($announcements->isNotEmpty()) {
            $results['announcements'] = [
                'title' => '企業公告',
                'items' => $announcements,
            ];
        }

        // 5. 檢索會議空間 (Meeting Rooms)
        $meetingRooms = MeetingRoom::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('location', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get()
            ->map(function ($mr) {
                return [
                    'id' => $mr->id,
                    'title' => $mr->name,
                    'subtitle' => "容納人數: {$mr->capacity} 人 · 地點: {$mr->location}",
                    'url' => route('meeting-rooms.index'),
                    'badge' => "{$mr->capacity}人",
                    'type' => 'meeting_room',
                ];
            });

        if ($meetingRooms->isNotEmpty()) {
            $results['meeting_rooms'] = [
                'title' => '會議室空間',
                'items' => $meetingRooms,
            ];
        }

        // 6. 檢索知識文件 (嚴格過濾密件角色白名單)
        $documents = Document::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('category', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
        })
            ->latest()
            ->limit(10)
            ->get()
            ->filter(function ($doc) use ($user) {
                // 若為密件，檢驗當前使用者是否擁有存取角色白名單
                return $doc->canAccess($user);
            })
            ->take(5)
            ->values()
            ->map(function ($d) {
                return [
                    'id' => $d->id,
                    'title' => $d->title,
                    'subtitle' => "分類: {$d->category} · 版本: v{$d->current_version}",
                    'url' => route('documents.preview', $d->id),
                    'badge' => !empty($d->restricted_roles) ? '密件' : null,
                    'type' => 'document',
                ];
            });

        if ($documents->isNotEmpty()) {
            $results['documents'] = [
                'title' => '知識文件庫',
                'items' => $documents,
            ];
        }

        return response()->json([
            'query' => $query,
            'results' => $results,
        ]);
    }

    /**
     * 快捷指令捷徑
     */
    private function getQuickShortcuts(User $user): array
    {
        $shortcuts = [
            [
                'id' => 'quick-form',
                'title' => '發起電子表單申請',
                'subtitle' => '請假、加班、補打卡、差旅與自訂審批',
                'url' => route('forms.index'),
                'badge' => '簽核',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-clock',
                'title' => '考勤打卡與出勤紀錄',
                'subtitle' => 'GPS 定位簽到、簽退與個人月報',
                'url' => route('attendance.index'),
                'badge' => '考勤',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-booking',
                'title' => '預約會議室空間',
                'subtitle' => '即時查詢可用會議室、邀請與會同仁',
                'url' => route('meeting-rooms.index'),
                'badge' => '設施',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-directory',
                'title' => '企業同仁通訊名冊',
                'subtitle' => '瀏覽組織架構圖與全體在職同仁聯絡資訊',
                'url' => route('directory.index'),
                'badge' => '通訊錄',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-leaves',
                'title' => '休假與補休額度查詢',
                'subtitle' => '查閱特休、病事假剩餘天數與對帳明細',
                'url' => route('leave-balances.index'),
                'badge' => '額度',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-docs',
                'title' => '企業文件知識庫',
                'subtitle' => '規章辦法、ISO標準文件與檔案線上預覽',
                'url' => route('documents.index'),
                'badge' => '文庫',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-calendar',
                'title' => '企業全景綜合行事曆',
                'subtitle' => '整合會議室預約、同仁休假排程與重要公告',
                'url' => route('calendar.index'),
                'badge' => '日曆',
                'type' => 'shortcut',
            ],
        ];

        if ($user->isAdmin() || $user->isManager()) {
            $shortcuts[] = [
                'id' => 'quick-approvals',
                'title' => '主管待審中心 (Approvals Hub)',
                'subtitle' => '一鍵批次審批、會辦加簽與轉簽派審',
                'url' => route('approvals.index'),
                'badge' => '主管',
                'type' => 'shortcut',
            ];
        }

        if ($user->isAdmin() || $user->isHr()) {
            $shortcuts[] = [
                'id' => 'quick-org',
                'title' => '組織架構與人員管理後台',
                'subtitle' => '拖曳部門階層、指派主管與編制匯出',
                'url' => route('org-management.index'),
                'badge' => '人事',
                'type' => 'shortcut',
            ];
        }

        return $shortcuts;
    }

    /**
     * 格式化單據狀態字串
     */
    private function formatFormStatus(string $status): string
    {
        return match ($status) {
            'pending' => '審核中',
            'approved' => '已核准',
            'rejected' => '已駁回',
            'withdrawn' => '已撤回',
            'revision_required' => '退回修改',
            default => $status,
        };
    }
}
