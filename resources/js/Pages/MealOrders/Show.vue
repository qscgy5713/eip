<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    order: Object,
    summaryItems: Array,
    userSettlements: Array,
    myItems: Array,
    canManage: Boolean,
});

// 新增點餐品項表單
const addItemForm = useForm({
    item_name: '',
    unit_price: '',
    quantity: 1,
    options: '',
    notes: '',
});

const submitAddItem = () => {
    addItemForm.post(route('meal-orders.items.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            addItemForm.reset();
            addItemForm.quantity = 1;
        },
    });
};

const removeItem = async (itemId) => {
    if (await confirmDialog('確定要取消此點餐品項嗎？')) {
        router.delete(route('meal-orders.items.destroy', [props.order.id, itemId]), {
            preserveScroll: true,
        });
    }
};

const togglePayment = (itemId) => {
    router.patch(route('meal-orders.items.toggle-payment', [props.order.id, itemId]), {}, {
        preserveScroll: true,
    });
};

const closeOrder = async () => {
    if (await confirmDialog('確定要結單嗎？結單後同仁將無法再新增或修改點餐。')) {
        router.post(route('meal-orders.close', props.order.id), {}, { preserveScroll: true });
    }
};

const completeOrder = async () => {
    if (await confirmDialog('確定餐點已送達並完成訂單發送嗎？')) {
        router.post(route('meal-orders.complete', props.order.id), {}, { preserveScroll: true });
    }
};

const cancelOrder = async () => {
    if (await confirmDialog('確定要取消此揪團活動嗎？')) {
        router.post(route('meal-orders.cancel', props.order.id), {}, { preserveScroll: true });
    }
};

const notifyParticipants = () => {
    router.post(route('meal-orders.notify', props.order.id), {}, { preserveScroll: true });
};

const activeTab = ref('summary'); // 'summary' | 'all' | 'settlement' | 'my'

const progressPercentage = computed(() => {
    if (!props.order.minimum_order_amount) return 100;
    return Math.min(100, Math.round((props.order.total_amount / props.order.minimum_order_amount) * 100));
});

const isOrderingAllowed = computed(() => {
    return props.order.status === 'open' && !props.order.is_expired;
});

const getStatusBadge = (order) => {
    if (order.status === 'open') {
        if (order.is_expired) {
            return { label: '已過截止時間', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800' };
        }
        return { label: '進行中開團', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800 animate-pulse' };
    }
    if (order.status === 'closed') {
        return { label: '已結單處理中', class: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800' };
    }
    if (order.status === 'completed') {
        return { label: '已送達發送', class: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700' };
    }
    return { label: '已取消', class: 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300 border-red-200 dark:border-red-800' };
};
</script>

<template>
    <Head :title="`${order.title} - 訂餐詳情`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('meal-orders.index')" class="p-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-500 transition dark:text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100">
                                {{ order.title }}
                            </h2>
                            <span :class="['px-2.5 py-0.5 text-xs font-bold rounded-lg border', getStatusBadge(order).class]">
                                {{ getStatusBadge(order).label }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-3">
                            <span>店家：<strong class="text-indigo-600 dark:text-indigo-400">{{ order.restaurant_name }}</strong></span>
                            <span v-if="order.restaurant_phone">電話：{{ order.restaurant_phone }}</span>
                            <span>發起人：{{ order.creator?.name }}</span>
                        </p>
                    </div>
                </div>

                <!-- 開團者/主管管理按鈕組 -->
                <div v-if="canManage" class="flex flex-wrap items-center gap-2 shrink-0">
                    <button
                        v-if="order.status === 'open'"
                        @click="closeOrder"
                        class="px-3.5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition"
                    >
                        結單 (停止點餐)
                    </button>
                    <button
                        v-if="['open', 'closed'].includes(order.status)"
                        @click="completeOrder"
                        class="px-3.5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs transition"
                    >
                        餐點已送達 (發送通知)
                    </button>
                    <button
                        @click="notifyParticipants"
                        class="px-3.5 py-2 text-xs font-bold text-indigo-700 bg-indigo-100 hover:bg-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 rounded-xl transition"
                    >
                         一鍵催繳自費
                    </button>
                    <button
                        v-if="order.status !== 'cancelled'"
                        @click="cancelOrder"
                        class="px-3 py-2 text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-400 rounded-xl transition"
                    >
                        取消開團
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6 px-4">
                <!-- 提示/說明橫幅與門檻統計 -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 shadow-xs">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-xs text-gray-400 font-medium">截止時間與狀態</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 mt-1 flex items-center gap-1.5">
                                <span v-if="order.cutoff_time">
                                    {{ new Date(order.cutoff_time).toLocaleString('zh-TW', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                </span>
                                <span v-else>無特定截止時間</span>
                                <span v-if="order.is_expired && order.status === 'open'" class="text-red-600 font-bold text-xs">
                                    (已逾期截止)
                                </span>
                            </p>
                            <p v-if="order.description" class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                說明：{{ order.description }}
                            </p>
                            <div v-if="order.menu_url" class="mt-2">
                                <a :href="order.menu_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    <span>開啟官方線上菜單</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs text-gray-400 font-medium">起送/免運門檻進度</p>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">
                                    ${{ Number(order.total_amount).toLocaleString() }}
                                </span>
                                <span v-if="order.minimum_order_amount" class="text-xs text-gray-400">
                                    / 目標 ${{ Number(order.minimum_order_amount).toLocaleString() }}
                                </span>
                            </div>
                            <div v-if="order.minimum_order_amount" class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 mt-2 overflow-hidden">
                                <div
                                    class="bg-indigo-500 h-2 rounded-full transition-all duration-300"
                                    :style="{ width: progressPercentage + '%' }"
                                ></div>
                            </div>
                            <p v-if="order.minimum_order_amount" class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                {{ order.total_amount >= order.minimum_order_amount ? ' 已達成起送門檻！' : `還差 $${order.minimum_order_amount - order.total_amount} 達標` }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-gray-400 font-medium">福委/公司補助規則</p>
                            <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                                {{ order.subsidy_per_person > 0 ? `每位參與同仁補助 $${order.subsidy_per_person}` : '本活動無補助 (全額自費)' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                目前已有 <strong class="text-indigo-600 font-bold">{{ order.participants_count }}</strong> 位同仁點餐
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 點餐輸入卡片 (當活動 open 且未截止時可用) -->
                <div v-if="isOrderingAllowed" class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">我要點餐 / 新增品項</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">填寫品項名稱、單價與規格（如甜度、冰塊、口味），系統自動計費！</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitAddItem" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
                        <div class="lg:col-span-2">
                            <label class="block font-semibold text-gray-600 dark:text-gray-300 mb-1">餐點/飲料名稱 *</label>
                            <input
                                v-model="addItemForm.item_name"
                                type="text"
                                required
                                placeholder="例：四季春茶、招牌雞排飯"
                                class="w-full rounded-xl bg-gray-50 text-gray-900 border-gray-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100"
                            />
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-600 dark:text-gray-300 mb-1">單價 ($) *</label>
                            <input
                                v-model="addItemForm.unit_price"
                                type="number" step="any"
                                min="0"
                                required
                                placeholder="例：55"
                                class="w-full rounded-xl bg-gray-50 text-gray-900 border-gray-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100"
                            />
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-600 dark:text-gray-300 mb-1">數量 *</label>
                            <input
                                v-model="addItemForm.quantity"
                                type="number"
                                min="1"
                                max="99"
                                required
                                class="w-full rounded-xl bg-gray-50 text-gray-900 border-gray-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100"
                            />
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-600 dark:text-gray-300 mb-1">規格/客製化</label>
                            <input
                                v-model="addItemForm.options"
                                type="text"
                                placeholder="例：微糖微冰、大杯、加珍珠"
                                class="w-full rounded-xl bg-gray-50 text-gray-900 border-gray-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100"
                            />
                        </div>

                        <div class="sm:col-span-2 lg:col-span-4">
                            <label class="block font-semibold text-gray-600 dark:text-gray-300 mb-1">備註</label>
                            <input
                                v-model="addItemForm.notes"
                                type="text"
                                placeholder="例：請幫忙分開裝袋"
                                class="w-full rounded-xl bg-gray-50 text-gray-900 border-gray-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100"
                            />
                        </div>

                        <div class="flex items-end">
                            <button
                                type="submit"
                                :disabled="addItemForm.processing"
                                class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-1.5 active:scale-95"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>送出點餐</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div v-else class="p-4 bg-gray-100 dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 text-center text-xs text-gray-500 dark:text-gray-400">
                     本活動已截止或結單，目前無法新增點餐。
                </div>

                <!-- 點餐明細 Tabs 切換 -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                    <div class="border-b border-gray-100 dark:border-gray-700 px-6 pt-4 flex flex-wrap gap-4 text-xs font-bold">
                        <button
                            @click="activeTab = 'summary'"
                            :class="[
                                'pb-3 border-b-2 transition flex items-center gap-1.5',
                                activeTab === 'summary' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                            ]"
                        >
                            <span>廚房出餐/電話下單彙總 ({{ summaryItems.length }})</span>
                        </button>
                        <button
                            @click="activeTab = 'settlement'"
                            :class="[
                                'pb-3 border-b-2 transition flex items-center gap-1.5',
                                activeTab === 'settlement' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                            ]"
                        >
                            <span>同仁自費結算清冊 ({{ userSettlements.length }})</span>
                        </button>
                        <button
                            @click="activeTab = 'all'"
                            :class="[
                                'pb-3 border-b-2 transition flex items-center gap-1.5',
                                activeTab === 'all' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                            ]"
                        >
                            <span>全部品項明細 ({{ order.items?.length || 0 }})</span>
                        </button>
                        <button
                            @click="activeTab = 'my'"
                            :class="[
                                'pb-3 border-b-2 transition flex items-center gap-1.5',
                                activeTab === 'my' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            <span>我的點餐 ({{ myItems.length }})</span>
                        </button>
                    </div>

                    <!-- 1. 廚房出餐/電話下單彙總 Tab -->
                    <div v-if="activeTab === 'summary'" class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">店家出餐統計彙整表</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">依品項與規格自動合併統計數量，供行政撥打電話下單或核對出餐</p>
                            </div>
                        </div>

                        <div v-if="summaryItems.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-700 text-gray-400">
                                        <th class="py-2.5 font-semibold">餐點品項</th>
                                        <th class="py-2.5 font-semibold">規格 / 備註</th>
                                        <th class="py-2.5 font-semibold text-center">單價</th>
                                        <th class="py-2.5 font-semibold text-center">總數量</th>
                                        <th class="py-2.5 font-semibold text-right">小計</th>
                                        <th class="py-2.5 font-semibold pl-4">點餐同仁</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                    <tr v-for="(item, idx) in summaryItems" :key="idx" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30">
                                        <td class="py-3 font-bold text-gray-800 dark:text-gray-200">{{ item.item_name }}</td>
                                        <td class="py-3 text-gray-500 dark:text-gray-400">
                                            <span v-if="item.options" class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-[11px] font-mono">
                                                {{ item.options }}
                                            </span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="py-3 text-center font-mono text-gray-600 dark:text-gray-300">${{ item.unit_price }}</td>
                                        <td class="py-3 text-center">
                                            <span class="px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-extrabold text-xs">
                                                x {{ item.total_quantity }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-right font-mono font-bold text-gray-900 dark:text-gray-100">
                                            ${{ Number(item.subtotal).toLocaleString() }}
                                        </td>
                                        <td class="py-3 pl-4 text-gray-500 dark:text-gray-400">
                                            {{ item.users.join(', ') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-12 text-center text-xs text-gray-400">
                            目前尚未有任何同仁點餐
                        </div>
                    </div>

                    <!-- 2. 同仁自費結算清冊 Tab -->
                    <div v-if="activeTab === 'settlement'" class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">同仁結算與自費收退清冊</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">點餐總金額扣除公司每人補助額度後之應付自費金額</p>
                            </div>
                        </div>

                        <div v-if="userSettlements.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-700 text-gray-400">
                                        <th class="py-2.5 font-semibold">同仁姓名</th>
                                        <th class="py-2.5 font-semibold text-center">點餐件數</th>
                                        <th class="py-2.5 font-semibold text-right">點餐總額</th>
                                        <th class="py-2.5 font-semibold text-right">公司補助</th>
                                        <th class="py-2.5 font-semibold text-right">應付自費金額</th>
                                        <th class="py-2.5 font-semibold text-center">繳費狀態</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                    <tr v-for="user in userSettlements" :key="user.user_id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30">
                                        <td class="py-3 font-bold text-gray-800 dark:text-gray-200">{{ user.user_name }}</td>
                                        <td class="py-3 text-center font-mono">{{ user.items_count }} 件</td>
                                        <td class="py-3 text-right font-mono">${{ Number(user.total_amount).toLocaleString() }}</td>
                                        <td class="py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                            - ${{ Number(user.subsidy).toLocaleString() }}
                                        </td>
                                        <td class="py-3 text-right font-mono font-bold text-base text-indigo-600 dark:text-indigo-400">
                                            ${{ Number(user.net_payable).toLocaleString() }}
                                        </td>
                                        <td class="py-3 text-center">
                                            <span
                                                :class="[
                                                    'px-2 py-0.5 rounded text-[11px] font-bold',
                                                    user.is_fully_paid ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300'
                                                ]"
                                            >
                                                {{ user.is_fully_paid ? '已結清付訖' : '待繳費' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-12 text-center text-xs text-gray-400">
                            目前尚未有任何同仁點餐
                        </div>
                    </div>

                    <!-- 3. 全部品項明細 Tab -->
                    <div v-if="activeTab === 'all'" class="p-6">
                        <div v-if="order.items?.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-700 text-gray-400">
                                        <th class="py-2.5 font-semibold">點餐同仁</th>
                                        <th class="py-2.5 font-semibold">餐點品項</th>
                                        <th class="py-2.5 font-semibold">規格 / 備註</th>
                                        <th class="py-2.5 font-semibold text-center">單價</th>
                                        <th class="py-2.5 font-semibold text-center">數量</th>
                                        <th class="py-2.5 font-semibold text-right">小計</th>
                                        <th class="py-2.5 font-semibold text-center">自費狀態</th>
                                        <th v-if="canManage" class="py-2.5 font-semibold text-right">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                    <tr v-for="item in order.items" :key="item.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30">
                                        <td class="py-3 font-semibold text-gray-800 dark:text-gray-200">{{ item.user?.name }}</td>
                                        <td class="py-3 font-bold text-gray-900 dark:text-gray-100">{{ item.item_name }}</td>
                                        <td class="py-3 text-gray-500 dark:text-gray-400">{{ item.options || '-' }}</td>
                                        <td class="py-3 text-center font-mono">${{ item.unit_price }}</td>
                                        <td class="py-3 text-center font-mono font-bold">{{ item.quantity }}</td>
                                        <td class="py-3 text-right font-mono font-bold">${{ Number(item.subtotal).toLocaleString() }}</td>
                                        <td class="py-3 text-center">
                                            <button
                                                v-if="canManage"
                                                @click="togglePayment(item.id)"
                                                :class="[
                                                    'px-2 py-0.5 rounded text-[11px] font-bold cursor-pointer transition',
                                                    item.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-amber-100 text-amber-800 hover:bg-amber-200'
                                                ]"
                                            >
                                                {{ item.payment_status === 'paid' ? '已收訖 (點擊改未收)' : '未繳自費 (點擊改已收)' }}
                                            </button>
                                            <span
                                                v-else
                                                :class="[
                                                    'px-2 py-0.5 rounded text-[11px] font-bold',
                                                    item.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                                                ]"
                                            >
                                                {{ item.payment_status === 'paid' ? '已收款' : '未付款' }}
                                            </span>
                                        </td>
                                        <td v-if="canManage" class="py-3 text-right">
                                            <button
                                                @click="removeItem(item.id)"
                                                class="text-red-600 hover:text-red-800 text-xs font-semibold"
                                            >
                                                刪除
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-12 text-center text-xs text-gray-400">
                            目前尚未有任何品項明細
                        </div>
                    </div>

                    <!-- 4. 我的點餐 Tab -->
                    <div v-if="activeTab === 'my'" class="p-6">
                        <div v-if="myItems.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-700 text-gray-400">
                                        <th class="py-2.5 font-semibold">餐點品項</th>
                                        <th class="py-2.5 font-semibold">規格 / 備註</th>
                                        <th class="py-2.5 font-semibold text-center">單價</th>
                                        <th class="py-2.5 font-semibold text-center">數量</th>
                                        <th class="py-2.5 font-semibold text-right">小計</th>
                                        <th class="py-2.5 font-semibold text-center">繳費狀態</th>
                                        <th v-if="isOrderingAllowed" class="py-2.5 font-semibold text-right">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                    <tr v-for="item in myItems" :key="item.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30">
                                        <td class="py-3 font-bold text-gray-900 dark:text-gray-100">{{ item.item_name }}</td>
                                        <td class="py-3 text-gray-500 dark:text-gray-400">{{ item.options || '-' }}</td>
                                        <td class="py-3 text-center font-mono">${{ item.unit_price }}</td>
                                        <td class="py-3 text-center font-mono font-bold">{{ item.quantity }}</td>
                                        <td class="py-3 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">${{ Number(item.subtotal).toLocaleString() }}</td>
                                        <td class="py-3 text-center">
                                            <span
                                                :class="[
                                                    'px-2 py-0.5 rounded text-[11px] font-bold',
                                                    item.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                                                ]"
                                            >
                                                {{ item.payment_status === 'paid' ? '已收款' : '未付款' }}
                                            </span>
                                        </td>
                                        <td v-if="isOrderingAllowed" class="py-3 text-right">
                                            <button
                                                @click="removeItem(item.id)"
                                                class="text-red-600 hover:text-red-800 text-xs font-semibold"
                                            >
                                                取消此項
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-12 text-center text-xs text-gray-400">
                            您尚未在此活動中點餐
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
