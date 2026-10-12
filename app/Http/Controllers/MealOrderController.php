<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MealOrder;
use App\Models\MealOrderItem;
use App\Models\User;
use App\Notifications\EipSystemNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MealOrderController extends Controller
{
    /**
     * 揪團列表首頁
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->input('tab', 'open'); // 'open', 'my_created', 'my_joined', 'history'
        $search = trim($request->input('search', ''));

        $query = MealOrder::with(['creator', 'items.user']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('store_name', 'like', "%{$search}%");
            });
        }

        if ($tab === 'open') {
            $query->where('status', 'open')
                  ->where('cutoff_time', '>', Carbon::now());
        } elseif ($tab === 'my_created') {
            $query->where('creator_id', $user->id);
        } elseif ($tab === 'my_joined') {
            $query->whereHas('items', fn ($iq) => $iq->where('user_id', $user->id));
        } elseif ($tab === 'history') {
            $query->whereIn('status', ['closed', 'completed', 'cancelled']);
        }

        $orders = $query->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        // 統計指標 (鍵名需與 MealOrders/Index.vue 讀取的一致)
        $openOrders = MealOrder::where('status', 'open')
            ->where('cutoff_time', '>', Carbon::now())
            ->count();

        $todayOrders = MealOrder::whereDate('created_at', Carbon::today())->count();

        $myJoinedCount = MealOrderItem::where('user_id', $user->id)
            ->distinct('meal_order_id')
            ->count('meal_order_id');

        // 待繳金額:每個尚未結清的訂單,同仁品項小計扣除公司補助 (最低為 0) 後加總
        $myPendingPayable = MealOrderItem::with('order:id,subsidy_per_person')
            ->where('user_id', $user->id)
            ->where('payment_status', 'unpaid')
            ->whereHas('order', fn ($oq) => $oq->whereIn('status', ['open', 'closed']))
            ->get()
            ->groupBy('meal_order_id')
            ->sum(function ($items) {
                $subsidy = (float) $items->first()->order->subsidy_per_person;

                return max(0, (float) $items->sum('subtotal') - $subsidy);
            });

        return Inertia::render('MealOrders/Index', [
            'orders' => $orders,
            'tab' => $tab,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
            ],
            'stats' => [
                'openOrders' => $openOrders,
                'todayOrders' => $todayOrders,
                'myJoinedCount' => $myJoinedCount,
                'myPendingPayable' => round($myPendingPayable, 0),
            ],
        ]);
    }

    /**
     * 發起新揪團活動
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($request->has('restaurant_name') && ! $request->filled('store_name')) {
            $request->merge(['store_name' => $request->input('restaurant_name')]);
        }
        if ($request->has('restaurant_phone') && ! $request->filled('store_phone')) {
            $request->merge(['store_phone' => $request->input('restaurant_phone')]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'store_name' => ['required', 'string', 'max:150'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'menu_url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cutoff_time' => ['required', 'date', 'after:now'],
            'subsidy_per_person' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['creator_id'] = $user->id;
        $validated['status'] = 'open';

        $order = MealOrder::create($validated);

        AuditLog::log(
            'meal_order_create',
            "發起揪團訂餐活動「{$order->title}」({$order->store_name})",
            $order,
            ['meal_order_id' => $order->id, 'store_name' => $order->store_name]
        );

        return redirect()->route('meal-orders.show', $order->id)
            ->with('success', "成功發起「{$order->title}」揪團活動！");
    }

    /**
     * 揪團活動詳情與點餐看板
     */
    public function show(MealOrder $mealOrder, Request $request)
    {
        $user = $request->user();
        $mealOrder->load(['creator', 'items.user.department']);

        // 自動彙總品項（依品項名稱與規格加總數量，方便行政向店家打電話下訂）
        $summary = [];
        foreach ($mealOrder->items as $item) {
            $key = $item->item_name . '|' . ($item->specification ?: '標準');
            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'item_name' => $item->item_name,
                    'specification' => $item->specification ?: '標準',
                    'options' => $item->specification ?: '標準',
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => 0,
                    'total_quantity' => 0,
                    'total_amount' => 0,
                    'subtotal' => 0, // 前端彙總表讀取的鍵名,與 total_amount 同值
                    'users' => [],
                ];
            }
            $summary[$key]['quantity'] += $item->quantity;
            $summary[$key]['total_quantity'] += $item->quantity;
            $summary[$key]['total_amount'] += (float) $item->subtotal;
            $summary[$key]['subtotal'] += (float) $item->subtotal;
            $summary[$key]['users'][] = $item->user->name . ($item->quantity > 1 ? " x{$item->quantity}" : '');
        }

        // 同仁結算清冊 (依同仁分組計算每人總額、公司補助、應付自費)
        $settlements = [];
        $subsidy = (float) $mealOrder->subsidy_per_person;
        foreach ($mealOrder->items as $item) {
            $uid = $item->user_id;
            if (! isset($settlements[$uid])) {
                $settlements[$uid] = [
                    'user' => $item->user,
                    'user_id' => $uid,
                    'user_name' => $item->user->name,
                    'items_count' => 0,
                    'total_spend' => 0,
                    'total_amount' => 0,
                    'subsidy' => $subsidy,
                    'net_payable' => 0,
                    'all_paid' => true,
                    'has_unpaid' => false,
                    'items' => [],
                ];
            }
            $settlements[$uid]['items_count'] += $item->quantity;
            $settlements[$uid]['total_spend'] += (float) $item->subtotal;
            $settlements[$uid]['total_amount'] += (float) $item->subtotal;
            $settlements[$uid]['items'][] = $item;
            if ($item->payment_status !== 'paid') {
                $settlements[$uid]['all_paid'] = false;
                $settlements[$uid]['has_unpaid'] = true;
            }
        }

        foreach ($settlements as $uid => &$s) {
            $s['net_payable'] = max(0, $s['total_spend'] - $subsidy);
            $s['is_fully_paid'] = $s['all_paid'];
        }
        unset($s);

        // 我個人的點餐清單
        $myItems = $mealOrder->items->where('user_id', $user->id)->values();

        return Inertia::render('MealOrders/Show', [
            'order' => $mealOrder,
            'summaryItems' => array_values($summary),
            'userSettlements' => array_values($settlements),
            'myItems' => $myItems,
            'canManage' => $mealOrder->canManage($user),
        ]);
    }

    /**
     * 同仁送出點餐品項
     */
    public function addItem(Request $request, MealOrder $mealOrder)
    {
        $user = $request->user();

        if ($mealOrder->status !== 'open' || $mealOrder->is_expired) {
            return back()->with('error', '該揪團已截止點餐，無法再新增品項。')
                         ->withErrors(['error' => '該揪團已截止點餐，無法再新增品項。']);
        }

        if ($request->has('options') && ! $request->filled('specification')) {
            $request->merge(['specification' => $request->input('options')]);
        }

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:100'],
            'specification' => ['nullable', 'string', 'max:100'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['meal_order_id'] = $mealOrder->id;
        $validated['user_id'] = $user->id;
        $validated['payment_status'] = 'unpaid';

        MealOrderItem::create($validated);

        return back()->with('success', "已將「{$validated['item_name']}」加入您的點餐清單！");
    }

    /**
     * 刪除點餐品項
     */
    public function removeItem(MealOrder $mealOrder, MealOrderItem $item, Request $request)
    {
        $user = $request->user();
        $order = $item->order;

        if (! $order->canManage($user) && $item->user_id !== $user->id) {
            abort(403, '您沒有權限刪除此品項');
        }

        if ($order->status !== 'open' && ! $order->canManage($user)) {
            return back()->with('error', '該揪團已結單，同仁無法自行移除品項。')
                         ->withErrors(['error' => '該揪團已結單，同仁無法自行移除品項。']);
        }

        $item->delete();

        return back()->with('success', '已從清單中移除該品項。');
    }

    /**
     * 提前手動結單
     */
    public function close(MealOrder $mealOrder, Request $request)
    {
        $user = $request->user();
        if (! $mealOrder->canManage($user)) {
            abort(403, '僅發起人或管理者可執行結單');
        }

        $mealOrder->update(['status' => 'closed']);

        // 通知所有參與者已結單
        $participants = User::whereIn('id', $mealOrder->items()->pluck('user_id'))->get();
        foreach ($participants as $p) {
            if ($p->id !== $user->id) {
                $p->notify(new EipSystemNotification(
                    title: '訂餐揪團已結單',
                    message: "「{$mealOrder->title}」已正式結單！請進入活動確認應付自費金額並付款。",
                    type: 'meal_order',
                    actionUrl: route('meal-orders.show', $mealOrder->id),
                    senderName: $user->name,
                    extra: ['meal_order_id' => $mealOrder->id]
                ));
            }
        }

        AuditLog::log('meal_order_close', "結單「{$mealOrder->title}」", $mealOrder);

        return back()->with('success', '揪團活動已結單，已停止受理新點餐！');
    }

    /**
     * 標記餐點已送達
     */
    public function complete(MealOrder $mealOrder, Request $request)
    {
        $user = $request->user();
        if (! $mealOrder->canManage($user)) {
            abort(403, '僅發起人或管理者可標記完成');
        }

        $mealOrder->update(['status' => 'completed']);

        // 通知所有參與者餐點已送達
        $participants = User::whereIn('id', $mealOrder->items()->pluck('user_id'))->get();
        foreach ($participants as $p) {
            $p->notify(new EipSystemNotification(
                title: '餐點外送已送達！',
                message: "您在「{$mealOrder->title}」訂購的餐點已送達公司，請前往指定地點領取享用！",
                type: 'meal_order',
                actionUrl: route('meal-orders.show', $mealOrder->id),
                senderName: $user->name,
                extra: ['meal_order_id' => $mealOrder->id]
            ));
        }

        AuditLog::log('meal_order_complete', "餐點已送達並完成「{$mealOrder->title}」", $mealOrder);

        return back()->with('success', '已標記餐點送達並推播通知給所有訂餐同仁！');
    }

    /**
     * 取消揪團活動
     */
    public function cancel(MealOrder $mealOrder, Request $request)
    {
        $user = $request->user();
        if (! $mealOrder->canManage($user)) {
            abort(403, '僅發起人或管理者可取消活動');
        }

        $mealOrder->update(['status' => 'cancelled']);

        AuditLog::log('meal_order_cancel', "取消揪團活動「{$mealOrder->title}」", $mealOrder);

        return back()->with('success', '已取消該揪團活動。');
    }

    /**
     * 切換付款狀態 (paid / unpaid)
     */
    public function togglePayment(MealOrder $mealOrder, MealOrderItem $item, Request $request)
    {
        $user = $request->user();
        $order = $item->order;

        if (! $order->canManage($user)) {
            abort(403, '僅發起人或管理者可標記付款狀態');
        }

        $newStatus = $item->payment_status === 'paid' ? 'unpaid' : 'paid';
        $item->update([
            'payment_status' => $newStatus,
            'paid_at' => $newStatus === 'paid' ? now() : null,
        ]);

        return back()->with('success', "已將品項付款狀態標記為「{$item->payment_status_label}」！");
    }

    /**
     * 一鍵催繳未付款同仁
     */
    public function notifyParticipants(MealOrder $mealOrder, Request $request)
    {
        $user = $request->user();
        if (! $mealOrder->canManage($user)) {
            abort(403, '僅發起人或管理者可發送催繳提醒');
        }

        $unpaidUserIds = $mealOrder->items()
            ->where('payment_status', 'unpaid')
            ->distinct()
            ->pluck('user_id');

        $unpaidUsers = User::whereIn('id', $unpaidUserIds)->get();
        foreach ($unpaidUsers as $target) {
            if ($target->id !== $user->id) {
                $target->notify(new EipSystemNotification(
                    title: '餐費付款提醒',
                    message: "提醒您：在「{$mealOrder->title}」中有尚未結清的餐點款項，請撥空支付給主揪同仁，謝謝！",
                    type: 'meal_order',
                    actionUrl: route('meal-orders.show', $mealOrder->id),
                    senderName: $user->name,
                    extra: ['meal_order_id' => $mealOrder->id]
                ));
            }
        }

        return back()->with('success', "已發送餐費催繳提醒通知至 " . $unpaidUsers->count() . " 位未付款同仁！");
    }
}
