<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\PollVoter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PollController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = $request->query('status', 'active'); // 'active', 'closed', 'all', 'my'

        $query = Poll::with(['creator:id,name,employee_no'])
            ->withCount(['options', 'voters']);

        if ($status === 'active') {
            $query->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
                });
        } elseif ($status === 'closed') {
            $query->where(function ($q) {
                $q->where('status', 'closed')
                  ->orWhere('ends_at', '<=', now());
            });
        } elseif ($status === 'my') {
            $query->where('creator_id', $user->id);
        }

        $polls = $query->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        // 標記目前使用者在各個投票的參與狀態
        $votedPollIds = PollVoter::where('user_id', $user->id)
            ->whereIn('poll_id', $polls->pluck('id'))
            ->pluck('poll_id')
            ->toArray();

        $polls->through(function ($poll) use ($votedPollIds) {
            $poll->has_voted = in_array($poll->id, $votedPollIds);
            $poll->is_closed = $poll->isClosed();
            return $poll;
        });

        return Inertia::render('Polls/Index', [
            'polls' => $polls,
            'filters' => [
                'status' => $status,
            ],
            'can_create' => in_array($user->role, ['admin', 'manager', 'hr']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'manager', 'hr'])) {
            abort(403, '僅有管理員、主管或人事同仁可建立投票活動。');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_multiple_choice' => ['boolean'],
            'is_anonymous' => ['boolean'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'options' => ['required', 'array', 'min:2', 'max:20'],
            'options.*' => ['required', 'string', 'max:255'],
        ], [
            'title.required' => '請輸入投票主題。',
            'options.min' => '投票至少需提供 2 個選項。',
            'options.*.required' => '選項內容不可為空。',
            'ends_at.after' => '截止時間必須為未來時間。',
        ]);

        $poll = DB::transaction(function () use ($validated, $user) {
            $poll = Poll::create([
                'creator_id' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'is_multiple_choice' => $validated['is_multiple_choice'] ?? false,
                'is_anonymous' => $validated['is_anonymous'] ?? false,
                'ends_at' => $validated['ends_at'] ?? null,
                'status' => 'active',
            ]);

            foreach ($validated['options'] as $index => $optionText) {
                if (trim($optionText) !== '') {
                    $poll->options()->create([
                        'option_text' => trim($optionText),
                        'sort_order' => $index,
                    ]);
                }
            }

            return $poll;
        });

        AuditLog::log(
            action: 'create',
            description: "發起企業投票活動「{$poll->title}」",
            auditable: $poll,
            details: [
                'title' => $poll->title,
                'is_anonymous' => $poll->is_anonymous,
                'is_multiple_choice' => $poll->is_multiple_choice,
            ],
        );

        return redirect()->route('polls.show', $poll)
            ->with('success', '投票活動建立成功！');
    }

    public function show(Request $request, Poll $poll): Response
    {
        $user = $request->user();
        $isClosed = $poll->isClosed();
        $hasVoted = $poll->hasVoted($user);
        $canManage = $user->isAdmin() || $poll->creator_id === $user->id;

        // 載入選項與票數
        $poll->load(['creator:id,name,employee_no,job_title']);
        
        $optionsQuery = $poll->options()->withCount('votes');

        // 若非匿名且具管理權限，可查看投票人明細
        if (!$poll->is_anonymous && $canManage) {
            $optionsQuery->with(['votes.user:id,name,employee_no']);
        }

        $options = $optionsQuery->get();
        $totalVotes = $poll->votes()->count();
        $votersCount = $poll->voters()->count();

        // 各選項百分比計算
        $optionsWithStats = $options->map(function ($option) use ($totalVotes) {
            $pct = $totalVotes > 0 ? round(($option->votes_count / $totalVotes) * 100, 1) : 0;
            return [
                'id' => $option->id,
                'option_text' => $option->option_text,
                'votes_count' => $option->votes_count,
                'percentage' => $pct,
                'voters' => $option->relationLoaded('votes')
                    ? $option->votes->filter(fn($v) => $v->user !== null)->map(fn($v) => [
                        'id' => $v->user->id,
                        'name' => $v->user->name,
                        'employee_no' => $v->user->employee_no,
                    ])->values()
                    : [],
            ];
        });

        // 若不是匿名且使用者已投過票，取得使用者投了哪些選項
        $myVotedOptionIds = [];
        if ($hasVoted && !$poll->is_anonymous) {
            $myVotedOptionIds = PollVote::where('poll_id', $poll->id)
                ->where('user_id', $user->id)
                ->pluck('poll_option_id')
                ->toArray();
        }

        return Inertia::render('Polls/Show', [
            'poll' => [
                'id' => $poll->id,
                'title' => $poll->title,
                'description' => $poll->description,
                'is_multiple_choice' => $poll->is_multiple_choice,
                'is_anonymous' => $poll->is_anonymous,
                'ends_at' => $poll->ends_at?->format('Y-m-d H:i:s'),
                'status' => $poll->status,
                'is_closed' => $isClosed,
                'created_at' => $poll->created_at->format('Y-m-d H:i'),
                'creator' => $poll->creator,
            ],
            'options' => $optionsWithStats,
            'has_voted' => $hasVoted,
            'my_voted_option_ids' => $myVotedOptionIds,
            'total_votes' => $totalVotes,
            'voters_count' => $votersCount,
            'can_manage' => $canManage,
        ]);
    }

    public function vote(Request $request, Poll $poll): RedirectResponse
    {
        $user = $request->user();

        if ($poll->isClosed()) {
            return back()->withErrors(['vote' => '此投票活動已截止或關閉，無法再進行投票。']);
        }

        if ($poll->hasVoted($user)) {
            return back()->withErrors(['vote' => '您已經參與過此投票，每位同仁僅限投票一次。']);
        }

        $validated = $request->validate([
            'option_ids' => ['required', 'array', 'min:1'],
            'option_ids.*' => ['required', 'integer'],
        ], [
            'option_ids.required' => '請至少選擇一個選項。',
            'option_ids.min' => '請至少選擇一個選項。',
        ]);

        if (!$poll->is_multiple_choice && count($validated['option_ids']) > 1) {
            return back()->withErrors(['vote' => '此投票為單選題，僅可選擇一個選項。']);
        }

        // 校驗選項是否全屬於該投票
        $validOptionIds = $poll->options()->pluck('id')->toArray();
        foreach ($validated['option_ids'] as $optId) {
            if (!in_array($optId, $validOptionIds)) {
                return back()->withErrors(['vote' => '無效的投票選項。']);
            }
        }

        DB::transaction(function () use ($poll, $user, $validated) {
            // 寫入投票者紀錄防止重複投票
            PollVoter::create([
                'poll_id' => $poll->id,
                'user_id' => $user->id,
            ]);

            // 寫入得票選項（匿名投票 user_id 記錄 null，徹底保護隱私防回查）
            foreach ($validated['option_ids'] as $optId) {
                PollVote::create([
                    'poll_id' => $poll->id,
                    'poll_option_id' => $optId,
                    'user_id' => $poll->is_anonymous ? null : $user->id,
                ]);
            }
        });

        AuditLog::log(
            action: 'vote',
            description: "參與企業投票活動「{$poll->title}」",
            auditable: $poll,
            details: [
                'is_anonymous' => $poll->is_anonymous,
                'selected_options_count' => count($validated['option_ids']),
            ],
        );

        return back()->with('success', '投票成功！感謝您的寶貴意見。');
    }

    public function close(Request $request, Poll $poll): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && $poll->creator_id !== $user->id) {
            abort(403, '僅有建立者或管理員可提早關閉此投票。');
        }

        $poll->update(['status' => 'closed']);

        AuditLog::log(
            action: 'close_poll',
            description: "提早結束企業投票活動「{$poll->title}」",
            auditable: $poll,
            details: ['status' => 'closed'],
        );

        return back()->with('success', '已提早結束此投票活動。');
    }

    public function destroy(Request $request, Poll $poll): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && $poll->creator_id !== $user->id) {
            abort(403, '僅有建立者或管理員可刪除此投票。');
        }

        $pollTitle = $poll->title;
        $pollId = $poll->id;

        $poll->delete();

        AuditLog::log(
            action: 'delete_poll',
            description: "刪除企業投票活動「{$pollTitle}」",
            auditable: null,
            details: ['id' => $pollId, 'title' => $pollTitle],
        );

        return redirect()->route('polls.index')
            ->with('success', '投票活動已成功刪除。');
    }
}
