<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tickets: Object,
    tab: String,
    filters: Object,
    stats: Object,
    isStaff: Boolean,
});

const search = ref(props.filters.search || '');
const category = ref(props.filters.category || '');
const priority = ref(props.filters.priority || '');
const status = ref(props.filters.status || '');

const applyFilters = (newTab = props.tab) => {
    router.get(
        route('tickets.index'),
        {
            tab: newTab,
            search: search.value || undefined,
            category: category.value || undefined,
            priority: priority.value || undefined,
            status: status.value || undefined,
            page: 1,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    search.value = '';
    category.value = '';
    priority.value = '';
    status.value = '';
    applyFilters(props.tab);
};

const getPriorityBadge = (p) => {
    switch (p) {
        case 'urgent': return { label: '特急件', class: 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300 dark:border-rose-800 animate-pulse' };
        case 'high': return { label: '高優先', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800' };
        case 'normal': return { label: '一般', class: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800' };
        default: return { label: '低優先', class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700' };
    }
};

const getStatusBadge = (s) => {
    switch (s) {
        case 'open': return { label: '待處理', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' };
        case 'in_progress': return { label: '處理中', class: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300' };
        case 'pending_user': return { label: '待同仁確認', class: 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300' };
        case 'resolved': return { label: '已完修', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' };
        case 'closed': return { label: '已結案', class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' };
        default: return { label: s, class: 'bg-gray-100 text-gray-700' };
    }
};
</script>

<template>
    <Head title="內部 IT / 總務服務工單中心" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>內部 IT / 總務叫修與服務工單中心</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        電腦硬體故障、系統權限、辦公水電與環境設施線上提單派工
                    </p>
                </div>
                <Link
                    :href="route('tickets.create')"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition active:scale-95 shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>我要提單報修</span>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6 px-4">
                <!-- 統計看板 -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">待受理新工單</p>
                            <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ stats.openCount }} <span class="text-xs font-normal text-gray-400">件</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>

                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">工程師處理中</p>
                            <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1">{{ stats.inProgressCount }} <span class="text-xs font-normal text-gray-400">件</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                    </div>

                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">我提報處理中</p>
                            <p class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">{{ stats.myPendingCount }} <span class="text-xs font-normal text-gray-400">件</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                    </div>

                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">已完修待驗收</p>
                            <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.resolvedCount }} <span class="text-xs font-normal text-gray-400">件</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                </div>

                <!-- 搜尋與篩選條件列 -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 shadow-xs space-y-3">
                    <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-700 pb-3 overflow-x-auto text-xs font-semibold">
                        <button
                            @click="applyFilters('my')"
                            :class="[
                                'px-4 py-2 rounded-xl transition whitespace-nowrap',
                                tab === 'my' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100'
                            ]"
                        >
                             我提報的工單
                        </button>
                        <button
                            @click="applyFilters('assigned')"
                            :class="[
                                'px-4 py-2 rounded-xl transition whitespace-nowrap',
                                tab === 'assigned' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100'
                            ]"
                        >
                             指派給我處理
                        </button>
                        <button
                            @click="applyFilters('pending')"
                            :class="[
                                'px-4 py-2 rounded-xl transition whitespace-nowrap',
                                tab === 'pending' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100'
                            ]"
                        >
                             處理中工單
                        </button>
                        <button
                            v-if="isStaff"
                            @click="applyFilters('all')"
                            :class="[
                                'px-4 py-2 rounded-xl transition whitespace-nowrap',
                                tab === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100'
                            ]"
                        >
                             全體工單總覽 (管理視圖)
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
                        <div class="lg:col-span-2">
                            <input
                                v-model="search"
                                @keyup.enter="applyFilters(tab)"
                                type="text"
                                placeholder="搜尋工單號 (TK-...)、問題主旨或地點..."
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-rose-500 focus:border-rose-500"
                            />
                        </div>

                        <div>
                            <select
                                v-model="category"
                                @change="applyFilters(tab)"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-rose-500 focus:border-rose-500"
                            >
                                <option value="">全部分類</option>
                                <option value="it_hardware">電腦硬體</option>
                                <option value="it_software">軟體與帳號</option>
                                <option value="it_network">網路與通訊</option>
                                <option value="facility">水電與環境設施</option>
                                <option value="stationery">行政與耗材</option>
                                <option value="other">其他叫修</option>
                            </select>
                        </div>

                        <div>
                            <select
                                v-model="priority"
                                @change="applyFilters(tab)"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-rose-500 focus:border-rose-500"
                            >
                                <option value="">全部優先級</option>
                                <option value="urgent">特急件</option>
                                <option value="high">高優先</option>
                                <option value="normal">一般</option>
                                <option value="low">低優先</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                @click="applyFilters(tab)"
                                class="flex-1 py-2 px-3 bg-gray-900 hover:bg-black text-white font-bold rounded-xl transition text-xs shadow-xs"
                            >
                                篩選
                            </button>
                            <button
                                @click="resetFilters"
                                class="py-2 px-3 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition text-xs"
                            >
                                重置
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 工單表格清單 -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                    <div v-if="tickets.data?.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-700 text-gray-400 bg-gray-50/50 dark:bg-gray-900/40">
                                    <th class="py-3 px-4 font-semibold">工單編號</th>
                                    <th class="py-3 px-4 font-semibold">問題主旨 / 故障地點</th>
                                    <th class="py-3 px-4 font-semibold">分類</th>
                                    <th class="py-3 px-4 font-semibold text-center">優先級</th>
                                    <th class="py-3 px-4 font-semibold">提單同仁</th>
                                    <th class="py-3 px-4 font-semibold">承辦工程師</th>
                                    <th class="py-3 px-4 font-semibold text-center">狀態</th>
                                    <th class="py-3 px-4 font-semibold">建立時間</th>
                                    <th class="py-3 px-4 font-semibold text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                                <tr
                                    v-for="ticket in tickets.data"
                                    :key="ticket.id"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition"
                                >
                                    <td class="py-3 px-4 font-mono font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                        <Link :href="route('tickets.show', ticket.id)" class="hover:text-rose-600 hover:underline">
                                            {{ ticket.ticket_number }}
                                        </Link>
                                    </td>
                                    <td class="py-3 px-4 max-w-xs">
                                        <Link :href="route('tickets.show', ticket.id)" class="font-bold text-gray-800 dark:text-gray-200 hover:text-rose-600 line-clamp-1">
                                            {{ ticket.title }}
                                        </Link>
                                        <p v-if="ticket.location" class="text-[11px] text-gray-400 mt-0.5 truncate flex items-center gap-1">
                                            <span>{{ ticket.location }}</span>
                                        </p>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                                            {{ ticket.category_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <span :class="['px-2 py-0.5 rounded-md border text-[11px] font-bold', getPriorityBadge(ticket.priority).class]">
                                            {{ getPriorityBadge(ticket.priority).label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.user?.name }}</p>
                                        <p class="text-[11px] text-gray-400">{{ ticket.user?.department?.name || '公司全員' }}</p>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span v-if="ticket.assignee" class="inline-flex items-center gap-1.5 font-medium text-gray-700 dark:text-gray-300">
                                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                            <span>{{ ticket.assignee.name }}</span>
                                        </span>
                                        <span v-else class="text-amber-600 dark:text-amber-400 font-medium text-[11px]">
                                            待派工受理
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <span :class="['px-2 py-0.5 rounded text-[11px] font-bold', getStatusBadge(ticket.status).class]">
                                            {{ getStatusBadge(ticket.status).label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-gray-400 whitespace-nowrap">
                                        {{ new Date(ticket.created_at).toLocaleDateString('sv-SE') }}
                                    </td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <Link
                                            :href="route('tickets.show', ticket.id)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                                        >
                                            <span>檢視進度</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 空狀態 -->
                    <div v-else class="py-16 text-center">
                        <div class="w-16 h-16 mx-auto rounded-full bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center text-3xl">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 mt-4">查無符合條件的叫修工單</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 max-w-sm mx-auto">
                            如果電腦、網路、水電環境或辦公用品有任何故障，歡迎隨時點擊提單報修！
                        </p>
                        <Link
                            :href="route('tickets.create')"
                            class="mt-5 inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>立即提單報修</span>
                        </Link>
                    </div>
                </div>

                <!-- 分頁按鈕 -->
                <div v-if="tickets.links?.length > 3" class="flex justify-center gap-1">
                    <Link
                        v-for="(link, index) in tickets.links"
                        :key="index"
                        :href="link.url || '#'"
                        :class="[
                            'px-3 py-1.5 text-xs rounded-lg transition font-medium',
                            link.active ? 'bg-indigo-600 text-white font-bold' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700',
                            !link.url ? 'opacity-40 cursor-not-allowed' : ''
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
