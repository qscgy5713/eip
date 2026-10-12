<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    orders: Object,
    filters: Object,
    stats: Object,
});

const showCreateModal = ref(false);

const form = useForm({
    title: '',
    restaurant_name: '',
    restaurant_phone: '',
    menu_url: '',
    minimum_order_amount: '',
    subsidy_per_person: '',
    cutoff_time: '',
    description: '',
});

const openCreateModal = () => {
    // 預設截止時間為今日 11:30 或 15:30
    const now = new Date();
    const defaultCutoff = new Date(now.getTime() + 60 * 60 * 1000);
    const tzOffset = defaultCutoff.getTimezoneOffset() * 60000;
    const localISOTime = new Date(defaultCutoff.getTime() - tzOffset).toISOString().slice(0, 16);

    form.reset();
    form.cutoff_time = localISOTime;
    showCreateModal.value = true;
};

const submitCreate = () => {
    form.post(route('meal-orders.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const filterTab = (status) => {
    router.get(
        route('meal-orders.index'),
        { ...props.filters, status: status === 'all' ? null : status, page: 1 },
        { preserveState: true, replace: true }
    );
};

const getStatusBadge = (order) => {
    if (order.status === 'open') {
        if (order.is_expired) {
            return { label: '已過截止時間', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800' };
        }
        return { label: '進行中開團', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800 animate-pulse' };
    }
    if (order.status === 'closed') {
        return { label: '已結單處理中', class: 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300 border-blue-200 dark:border-blue-800' };
    }
    if (order.status === 'completed') {
        return { label: '已送達發送', class: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700' };
    }
    return { label: '已取消', class: 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800' };
};
</script>

<template>
    <Head title="同仁訂餐與下午茶揪團" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>🍱</span>
                        <span>企業訂餐與下午茶揪團中心</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        午餐、下午茶揪團、公司補助折抵與電話外送統計總整
                    </p>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-bold text-white bg-orange-600 hover:bg-orange-700 rounded-xl shadow-sm transition active:scale-95 shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>發起新揪團</span>
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 統計看板 -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">進行中揪團</p>
                            <p class="text-2xl font-black text-orange-600 dark:text-orange-400 mt-1">{{ stats.openOrders }} <span class="text-xs font-normal text-gray-400">團</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-orange-50 dark:bg-orange-950/40 text-orange-600 dark:text-orange-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </div>
                    </div>

                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">今日發起開團</p>
                            <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1">{{ stats.todayOrders }} <span class="text-xs font-normal text-gray-400">團</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    </div>

                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">我參與點餐</p>
                            <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.myJoinedCount }} <span class="text-xs font-normal text-gray-400">次</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>

                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">我的待繳自費</p>
                            <p class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">${{ Number(stats.myPendingPayable).toLocaleString() }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                </div>

                <!-- 分頁 Tabs -->
                <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-700 pb-3 overflow-x-auto text-xs font-semibold">
                    <button
                        @click="filterTab('all')"
                        :class="[
                            'px-4 py-2 rounded-xl transition whitespace-nowrap',
                            !filters.status ? 'bg-orange-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                        ]"
                    >
                        全部揪團
                    </button>
                    <button
                        @click="filterTab('open')"
                        :class="[
                            'px-4 py-2 rounded-xl transition whitespace-nowrap',
                            filters.status === 'open' ? 'bg-orange-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                        ]"
                    >
                        🔥 進行中開團
                    </button>
                    <button
                        @click="filterTab('closed')"
                        :class="[
                            'px-4 py-2 rounded-xl transition whitespace-nowrap',
                            filters.status === 'closed' ? 'bg-orange-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                        ]"
                    >
                        📦 已結單 (店家出餐中)
                    </button>
                    <button
                        @click="filterTab('completed')"
                        :class="[
                            'px-4 py-2 rounded-xl transition whitespace-nowrap',
                            filters.status === 'completed' ? 'bg-orange-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                        ]"
                    >
                        ✅ 已送達結案
                    </button>
                    <button
                        @click="filterTab('my_created')"
                        :class="[
                            'px-4 py-2 rounded-xl transition whitespace-nowrap',
                            filters.status === 'my_created' ? 'bg-orange-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                        ]"
                    >
                        👑 我發起的揪團
                    </button>
                </div>

                <!-- 揪團活動列表 -->
                <div v-if="orders.data?.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="order in orders.data"
                        :key="order.id"
                        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs hover:shadow-md transition flex flex-col justify-between overflow-hidden"
                    >
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <span :class="['px-2.5 py-1 text-[11px] font-bold rounded-lg border', getStatusBadge(order).class]">
                                    {{ getStatusBadge(order).label }}
                                </span>
                                <span v-if="order.cutoff_time" class="text-xs text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    截止：{{ new Date(order.cutoff_time).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' }) }}
                                </span>
                            </div>

                            <Link :href="route('meal-orders.show', order.id)" class="block group">
                                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition line-clamp-1">
                                    {{ order.title }}
                                </h3>
                                <div class="flex items-center gap-2 mt-1 text-xs text-orange-600 dark:text-orange-400 font-semibold">
                                    <span>🏪 店家：{{ order.restaurant_name }}</span>
                                    <span v-if="order.restaurant_phone" class="text-gray-400 dark:text-gray-500 font-normal">({{ order.restaurant_phone }})</span>
                                </div>
                            </Link>

                            <p v-if="order.description" class="text-xs text-gray-500 dark:text-gray-400 mt-2 line-clamp-2">
                                {{ order.description }}
                            </p>

                            <!-- 外送門檻與補助資訊標籤 -->
                            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60 space-y-2 text-xs">
                                <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                                    <span>目前點餐總額</span>
                                    <span class="font-bold text-gray-900 dark:text-gray-100 font-mono text-sm">
                                        ${{ Number(order.total_amount).toLocaleString() }}
                                    </span>
                                </div>
                                <div v-if="order.minimum_order_amount" class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                    <div
                                        class="bg-orange-500 h-1.5 rounded-full transition-all duration-300"
                                        :style="{ width: Math.min(100, Math.round((order.total_amount / order.minimum_order_amount) * 100)) + '%' }"
                                    ></div>
                                </div>
                                <div v-if="order.minimum_order_amount" class="flex items-center justify-between text-[11px] text-gray-400">
                                    <span>外送門檻 ${{ Number(order.minimum_order_amount).toLocaleString() }}</span>
                                    <span :class="order.total_amount >= order.minimum_order_amount ? 'text-emerald-600 font-bold' : 'text-amber-600'">
                                        {{ order.total_amount >= order.minimum_order_amount ? '已達標 ✓' : `差 $${order.minimum_order_amount - order.total_amount}` }}
                                    </span>
                                </div>
                                <div v-if="order.subsidy_per_person > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold">
                                    <span>🎁 公司補助每人 ${{ order.subsidy_per_person }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-gray-50 dark:bg-gray-900/40 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-orange-100 dark:bg-orange-900/60 text-orange-700 dark:text-orange-300 flex items-center justify-center text-[10px] font-bold">
                                    {{ order.creator?.name?.slice(0, 1) }}
                                </div>
                                <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">{{ order.creator?.name }} 發起</span>
                            </div>

                            <Link
                                :href="route('meal-orders.show', order.id)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-white bg-orange-600 hover:bg-orange-700 rounded-lg shadow-xs transition"
                            >
                                <span>{{ order.status === 'open' && !order.is_expired ? '立即點餐' : '檢視明細' }}</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 空狀態 -->
                <div v-else class="py-16 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <div class="w-16 h-16 mx-auto rounded-full bg-orange-50 dark:bg-orange-950/50 text-orange-600 dark:text-orange-400 flex items-center justify-center text-3xl">
                        🍱
                    </div>
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 mt-4">目前沒有符合條件的訂餐揪團</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 max-w-sm mx-auto">
                        想喝手搖杯或吃午餐便當嗎？點擊下方按鈕即可立即發起開團！
                    </p>
                    <button
                        @click="openCreateModal"
                        class="mt-5 inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-orange-600 hover:bg-orange-700 rounded-xl shadow-xs transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>發起新揪團</span>
                    </button>
                </div>

                <!-- 分頁按鈕 -->
                <div v-if="orders.links?.length > 3" class="flex justify-center gap-1">
                    <Link
                        v-for="(link, index) in orders.links"
                        :key="index"
                        :href="link.url || '#'"
                        :class="[
                            'px-3 py-1.5 text-xs rounded-lg transition font-medium',
                            link.active ? 'bg-orange-600 text-white font-bold' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700',
                            !link.url ? 'opacity-40 cursor-not-allowed' : ''
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- 發起開團 Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🧋</span>
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">發起同仁訂餐 / 下午茶揪團</h3>
                    </div>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="mt-4 space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">揪團活動標題 *</label>
                        <input
                            v-model="form.title"
                            type="text"
                            required
                            placeholder="例：週五午後 50嵐 手搖飲熱血揪團、梁社漢排骨午餐"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-orange-500 focus:border-orange-500"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">店家名稱 *</label>
                            <input
                                v-model="form.restaurant_name"
                                type="text"
                                required
                                placeholder="例：50嵐 (松山興安店)"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-orange-500 focus:border-orange-500"
                            />
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">店家電話</label>
                            <input
                                v-model="form.restaurant_phone"
                                type="text"
                                placeholder="例：02-2712-3456"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-orange-500 focus:border-orange-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">截止時間 *</label>
                            <input
                                v-model="form.cutoff_time"
                                type="datetime-local"
                                required
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-orange-500 focus:border-orange-500"
                            />
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">外送起送門檻 ($)</label>
                            <input
                                v-model="form.minimum_order_amount"
                                type="number"
                                min="0"
                                placeholder="例：300 (免運/外送)"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-orange-500 focus:border-orange-500"
                            />
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">公司補助每人 ($)</label>
                            <input
                                v-model="form.subsidy_per_person"
                                type="number"
                                min="0"
                                placeholder="例：50 (福委補助)"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-orange-500 focus:border-orange-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">菜單連結 / 官方訂餐網址</label>
                        <input
                            v-model="form.menu_url"
                            type="url"
                            placeholder="https://menu.example.com"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-orange-500 focus:border-orange-500"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">備註說明 (取餐位置、繳費方式等)</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="例：預計 14:00 外送達 7F 茶水間，請大家準時拿取；現金或 LINE Pay 給發起人。"
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-orange-500 focus:border-orange-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 text-xs font-bold text-white bg-orange-600 hover:bg-orange-700 rounded-xl shadow-xs transition"
                        >
                            立即發起開團
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
