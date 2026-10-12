<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Document;
use App\Models\FormRequest as EipFormRequest;
use App\Models\MeetingRoom;
use App\Models\Poll;
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

        // 7. 檢索同仁投票與意見調查 (Polls)
        $polls = Poll::where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($p) {
                $statusText = $p->isClosed() ? '已截止' : '進行中';
                $badge = $p->is_anonymous ? '匿名投票' : '記名投票';
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'subtitle' => "狀態: {$statusText} · " . ($p->is_multiple_choice ? '複選' : '單選'),
                    'url' => route('polls.show', $p->id),
                    'badge' => $badge,
                    'type' => 'poll',
                ];
            });

        if ($polls->isNotEmpty()) {
            $results['polls'] = [
                'title' => '企業投票與調查',
                'items' => $polls,
            ];
        }

        // 8. 檢索專案與任務 (Projects)
        $projectQuery = \App\Models\Project::with(['owner']);
        if ($user->role !== 'admin') {
            $projectQuery->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', function ($mq) use ($user) {
                      $mq->where('user_id', $user->id);
                  })
                  ->orWhereHas('tasks', function ($tq) use ($user) {
                      $tq->where('assignee_id', $user->id);
                  });
            });
        }
        $projects = $projectQuery->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => "[{$p->code}] {$p->name}",
                    'subtitle' => "負責人: {$p->owner?->name} · 狀態: {$p->status}",
                    'url' => route('projects.show', $p->id),
                    'badge' => '專案',
                    'type' => 'project',
                ];
            });

        if ($projects->isNotEmpty()) {
            $results['projects'] = [
                'title' => '專案協同看板',
                'items' => $projects,
            ];
        }

        // 9. 檢索固定資產與公務設備 (Assets)
        $assets = \App\Models\Asset::where(function ($q) use ($query) {
                $q->where('asset_tag', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%")
                  ->orWhere('serial_number', 'like', "%{$query}%")
                  ->orWhere('location', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($ast) {
                return [
                    'id' => $ast->id,
                    'title' => "[{$ast->asset_tag}] {$ast->name}",
                    'subtitle' => "分類: {$ast->category_label} · 地點: " . ($ast->location ?: '未標註') . " · 狀態: {$ast->status_label}",
                    'url' => route('assets.show', $ast->id),
                    'badge' => '資產',
                    'type' => 'asset',
                ];
            });

        if ($assets->isNotEmpty()) {
            $results['assets'] = [
                'title' => '固定資產與設備',
                'items' => $assets,
            ];
        }

        // 10. 檢索工作週報 (具備權限隔離)
        $reportsQuery = \App\Models\WeeklyReport::with('user')
            ->where(function ($q) use ($query) {
                $q->where('summary', 'like', "%{$query}%")
                  ->orWhere('achievements', 'like', "%{$query}%")
                  ->orWhere('plans_next_week', 'like', "%{$query}%");
            });

        if (! $user->isAdmin() && ! $user->isHr()) {
            $reportsQuery->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->isManager() && $user->department_id) {
                    $q->orWhere('department_id', $user->department_id);
                }
            });
        }

        $reports = $reportsQuery->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'title' => "{$r->user?->name} 的週報 ({$r->year} W{$r->week_number})",
                    'subtitle' => "重點: " . \Illuminate\Support\Str::limit($r->summary, 45) . " · 狀態: {$r->status_label}",
                    'url' => route('weekly-reports.show', $r->id),
                    'badge' => '週報',
                    'type' => 'weekly_report',
                ];
            });

        if ($reports->isNotEmpty()) {
            $results['weekly_reports'] = [
                'title' => '工作週報與簡報',
                'items' => $reports,
            ];
        }

        // 11. 檢索薪資單 (Payslips - 具備嚴密身分隔離)
        $payslipsQuery = \App\Models\Payslip::query();
        if ($user->isAdmin() || $user->isHr()) {
            $payslipsQuery->with('user')->where(function ($q) use ($query) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$query}%")->orWhere('employee_no', 'like', "%{$query}%"))
                  ->orWhere('year', 'like', "%{$query}%")
                  ->orWhere('month', 'like', "%{$query}%");
            });
        } else {
            $payslipsQuery->where('user_id', $user->id)
                ->where('status', 'published')
                ->where(function ($q) use ($query) {
                    $q->where('year', 'like', "%{$query}%")
                      ->orWhere('month', 'like', "%{$query}%");
                });
        }

        $payslips = $payslipsQuery->latest('year')
            ->latest('month')
            ->limit(5)
            ->get()
            ->map(function ($p) use ($user) {
                $owner = ($user->isAdmin() || $user->isHr()) ? ($p->user?->name . ' · ') : '';
                return [
                    'id' => $p->id,
                    'title' => "{$owner}{$p->period_label} 薪資單",
                    'subtitle' => "實發金額: NT$ " . number_format($p->net_pay) . " · 狀態: {$p->status_label}",
                    'url' => route('payslips.show', $p->id),
                    'badge' => '薪資',
                    'type' => 'payslip',
                ];
            });

        if ($payslips->isNotEmpty()) {
            $results['payslips'] = [
                'title' => '薪資單據與所得明細',
                'items' => $payslips,
            ];
        }

        // 12. 檢索訂餐揪團活動 (Meal Orders)
        $mealOrders = \App\Models\MealOrder::with('creator')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('store_name', 'like', "%{$query}%");
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($mo) {
                return [
                    'id' => $mo->id,
                    'title' => "【揪團】{$mo->title} ({$mo->store_name})",
                    'subtitle' => "截止: {$mo->cutoff_time->format('m/d H:i')} · 狀態: {$mo->status_label} · 發起人: {$mo->creator->name}",
                    'url' => route('meal-orders.show', $mo->id),
                    'badge' => '揪團',
                    'type' => 'meal_order',
                ];
            });

        if ($mealOrders->isNotEmpty()) {
            $results['meal_orders'] = [
                'title' => '訂餐與下午茶揪團',
                'items' => $mealOrders,
            ];
        }

        // 13. 檢索叫修服務工單 (Tickets)
        $ticketsQuery = \App\Models\Ticket::with(['user:id,name', 'assignee:id,name']);
        if (! $user->isAdmin() && ! $user->isHr()) {
            $ticketsQuery->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('assigned_to', $user->id);
            });
        }
        $tickets = $ticketsQuery->where(function ($q) use ($query) {
                $q->where('ticket_number', 'like', "%{$query}%")
                  ->orWhere('title', 'like', "%{$query}%")
                  ->orWhere('location', 'like', "%{$query}%");
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'title' => "【工單】{$t->ticket_number} - {$t->title}",
                    'subtitle' => "分類: {$t->category_label} · 狀態: {$t->status_label} · 優先級: {$t->priority_label}",
                    'url' => route('tickets.show', $t->id),
                    'badge' => '工單',
                    'type' => 'ticket',
                ];
            });

        if ($tickets->isNotEmpty()) {
            $results['tickets'] = [
                'title' => 'IT / 總務叫修工單',
                'items' => $tickets,
            ];
        }

        // 14. 檢索訪客預約接待紀錄 (Visitor Reservations)
        $visitorsQuery = \App\Models\VisitorReservation::with(['host:id,name', 'meetingRoom:id,name']);
        if (! $user->isAdmin() && ! $user->isHr()) {
            $visitorsQuery->where('host_id', $user->id);
        }
        $visitors = $visitorsQuery->where(function ($q) use ($query) {
                $q->where('reservation_code', 'like', "%{$query}%")
                  ->orWhere('visitor_name', 'like', "%{$query}%")
                  ->orWhere('visitor_company', 'like', "%{$query}%")
                  ->orWhere('badge_number', 'like', "%{$query}%");
            })
            ->latest('visit_date')
            ->limit(5)
            ->get()
            ->map(function ($v) {
                $comp = $v->visitor_company ? " ({$v->visitor_company})" : '';
                return [
                    'id' => $v->id,
                    'title' => "【訪客】{$v->visitor_name}{$comp} · 通行碼 {$v->reservation_code}",
                    'subtitle' => "日期: {$v->visit_date->format('Y-m-d')} · 事由: {$v->purpose_label} · 狀態: {$v->status_label}",
                    'url' => route('visitor-reservations.show', $v->id),
                    'badge' => '訪客',
                    'type' => 'visitor_reservation',
                ];
            });

        if ($visitors->isNotEmpty()) {
            $results['visitor_reservations'] = [
                'title' => '企業訪客與門禁接待',
                'items' => $visitors,
            ];
        }

        // 15. 檢索公務車輛與派車單 (Vehicles & Dispatches)
        $vehicleDispatchesQuery = \App\Models\VehicleDispatch::with(['user:id,name', 'vehicle:id,plate_number,name']);
        if (! $user->isAdmin() && ! $user->isManager()) {
            $vehicleDispatchesQuery->where('user_id', $user->id);
        }
        $dispatches = $vehicleDispatchesQuery->where(function ($q) use ($query) {
                $q->where('dispatch_number', 'like', "%{$query}%")
                  ->orWhere('destination', 'like', "%{$query}%")
                  ->orWhereHas('vehicle', fn ($vq) => $vq->where('plate_number', 'like', "%{$query}%")->orWhere('name', 'like', "%{$query}%"));
            })
            ->latest('start_time')
            ->limit(5)
            ->get()
            ->map(function ($vd) {
                return [
                    'id' => $vd->id,
                    'title' => "【派車】{$vd->dispatch_number} · {$vd->vehicle?->plate_number} ({$vd->destination})",
                    'subtitle' => "用車: " . ($vd->start_time ? $vd->start_time->format('m/d H:i') : '') . " · 事由: {$vd->purpose_label} · 狀態: {$vd->status_label}",
                    'url' => route('vehicles.show', $vd->id),
                    'badge' => '派車',
                    'type' => 'vehicle_dispatch',
                ];
            });

        if ($dispatches->isNotEmpty()) {
            $results['vehicle_dispatches'] = [
                'title' => '公務車借調與出差派車',
                'items' => $dispatches,
            ];
        }

        // 16. 檢索企業內訓課程 (Training Courses)
        $trainings = \App\Models\TrainingCourse::where('status', '!=', 'draft')
            ->where(function ($q) use ($query) {
                $q->where('course_code', 'like', "%{$query}%")
                  ->orWhere('title', 'like', "%{$query}%")
                  ->orWhere('instructor', 'like', "%{$query}%")
                  ->orWhere('location', 'like', "%{$query}%");
            })
            ->latest('start_time')
            ->limit(5)
            ->get()
            ->map(function ($tc) {
                return [
                    'id' => $tc->id,
                    'title' => "【內訓】{$tc->course_code} · {$tc->title}",
                    'subtitle' => "講師: {$tc->instructor} · 開課: " . ($tc->start_time ? $tc->start_time->format('m/d H:i') : '') . " · {$tc->credits} 學分 · 狀態: {$tc->status_label}",
                    'url' => route('trainings.show', $tc->id),
                    'badge' => '內訓',
                    'type' => 'training_course',
                ];
            });

        if ($trainings->isNotEmpty()) {
            $results['training_courses'] = [
                'title' => '企業內訓與研習課程',
                'items' => $trainings,
            ];
        }

        // 17. 檢索福利商城商品 (僅上架中)
        $rewards = \App\Models\WelfareReward::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('points_cost')
            ->limit(5)
            ->get()
            ->map(function ($reward) {
                return [
                    'id' => $reward->id,
                    'title' => "【福利】{$reward->name}",
                    'subtitle' => "{$reward->category_label} · {$reward->points_cost} 點",
                    'url' => route('welfare.index'),
                    'badge' => '福利',
                    'type' => 'welfare_reward',
                ];
            });

        if ($rewards->isNotEmpty()) {
            $results['welfare_rewards'] = [
                'title' => '福利點數商城',
                'items' => $rewards,
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
                'id' => 'quick-vehicles',
                'title' => '公務車輛借調與出差派車',
                'subtitle' => '查詢在庫公務車輛、預約出差用車、領車出發與里程油料回報',
                'url' => route('vehicles.index'),
                'badge' => '派車',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-visitor-reservations',
                'title' => '訪客預約與門禁貴賓接待',
                'subtitle' => '貴賓來訪通行碼發行、前台自助報到、證號登記與接待通知',
                'url' => route('visitor-reservations.index'),
                'badge' => '訪客',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-tickets',
                'title' => 'IT / 總務叫修工單',
                'subtitle' => '快速提報硬體電腦、網路、水電與辦公環境故障叫修',
                'url' => route('tickets.index'),
                'badge' => '叫修',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-meal-orders',
                'title' => '訂餐與下午茶揪團',
                'subtitle' => '發起午餐/飲料揪團、線上點餐自訂規格與自動結算',
                'url' => route('meal-orders.index'),
                'badge' => '揪團',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-payslips',
                'title' => '個人薪資條與薪酬明細',
                'subtitle' => '安全查閱每月發放明細、實發薪資與年度扣繳',
                'url' => route('payslips.index'),
                'badge' => '薪資',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-weekly-reports',
                'title' => '工作週報與協同簡報',
                'subtitle' => '撰寫本週工作進度、查閱部門週報與主管點評',
                'url' => route('weekly-reports.index'),
                'badge' => '週報',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-assets',
                'title' => '固定資產與公務設備借調',
                'subtitle' => '查詢在庫公務設備、借調登記與個人保管名冊',
                'url' => route('assets.index'),
                'badge' => '資產',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-projects',
                'title' => '專案管理與任務協同看板',
                'subtitle' => '跨部門任務看板、流轉與工時進度',
                'url' => route('projects.index'),
                'badge' => '專案',
                'type' => 'shortcut',
            ],
            [
                'id' => 'quick-polls',
                'title' => '企業投票與意見調查',
                'subtitle' => '參與福委活動、意見徵詢與即時投票',
                'url' => route('polls.index'),
                'badge' => '投票',
                'type' => 'shortcut',
            ],
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
