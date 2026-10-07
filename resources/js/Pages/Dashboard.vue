<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    announcements: Array,
    pendingApprovals: Array,
    myRequests: Array,
    stats: Object,
});

const priorityBadge = (priority) => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-800 border-red-200';
        case 'high': return 'bg-amber-100 text-amber-800 border-amber-200';
        default: return 'bg-blue-100 text-blue-800 border-blue-200';
    }
};

const statusBadge = (status) => {
    switch (status) {
        case 'approved': return 'bg-emerald-100 text-emerald-800';
        case 'rejected': return 'bg-rose-100 text-rose-800';
        default: return 'bg-amber-100 text-amber-800';
    }
};
</script>

<template>
    <Head title="總覽儀表板" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-gray-800">
                    工作台總覽儀表板
                </h2>
                <span class="text-sm text-gray-500">歡迎回到 EIP 企業系統</span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 數據統計看板 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">未讀企業公告</p>
                            <p class="text-3xl font-extrabold text-blue-600 mt-1">{{ stats.unreadAnnouncementsCount }}</p>
                        </div>
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">待我審批單據</p>
                            <p class="text-3xl font-extrabold text-amber-600 mt-1">{{ stats.pendingApprovalsCount }}</p>
                        </div>
                        <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">我進行中的申請</p>
                            <p class="text-3xl font-extrabold text-emerald-600 mt-1">{{ stats.myPendingRequestsCount }}</p>
                        </div>
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                    </div>
                </div>

                <!-- 主體區塊：最新公告 與 簽核動態 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 最新企業公告 -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 bg-blue-600 rounded-full"></span>
                                <h3 class="font-bold text-gray-900 text-lg">重要企業公告</h3>
                            </div>
                            <Link :href="route('announcements.index')" class="text-sm text-blue-600 hover:underline">查看全部 &rarr;</Link>
                        </div>
                        <div class="mt-4 divide-y divide-gray-100 flex-1">
                            <div v-for="item in announcements" :key="item.id" class="py-3.5 first:pt-0 last:pb-0 flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <span v-if="item.is_pinned" class="px-2 py-0.5 text-xs font-semibold bg-rose-50 text-rose-600 border border-rose-200 rounded">置頂</span>
                                        <span :class="['px-2 py-0.5 text-xs font-medium border rounded', priorityBadge(item.priority)]">{{ item.priority === 'urgent' ? '緊急' : (item.priority === 'high' ? '重要' : '一般') }}</span>
                                        <Link :href="route('announcements.show', item.id)" class="text-base font-semibold text-gray-900 hover:text-blue-600 line-clamp-1">
                                            {{ item.title }}
                                        </Link>
                                    </div>
                                    <p class="text-xs text-gray-400">發布者：{{ item.author?.name }} · {{ new Date(item.published_at).toLocaleDateString() }}</p>
                                </div>
                                <span v-if="!item.is_read" class="w-2 h-2 rounded-full bg-red-500 shrink-0 mt-2" title="未讀"></span>
                            </div>
                            <div v-if="announcements.length === 0" class="py-8 text-center text-sm text-gray-400">目前尚無公告</div>
                        </div>
                    </div>

                    <!-- 待辦簽核與最近申請 -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col space-y-6">
                        <!-- 待我審核 -->
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 text-lg">待我審批</h3>
                                </div>
                                <Link :href="route('forms.index')" class="text-sm text-blue-600 hover:underline">進入簽核中心 &rarr;</Link>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div v-for="approval in pendingApprovals" :key="approval.id" class="p-3 rounded-lg bg-amber-50/60 border border-amber-100 flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-sm text-gray-900">{{ approval.form_request?.title }}</p>
                                        <p class="text-xs text-gray-500">申請人：{{ approval.form_request?.user?.name }} ({{ approval.form_request?.form?.name }})</p>
                                    </div>
                                    <Link :href="route('forms.show', approval.form_request_id)" class="px-3 py-1.5 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm">
                                        審核
                                    </Link>
                                </div>
                                <div v-if="pendingApprovals.length === 0" class="py-4 text-center text-xs text-gray-400">目前無待審核單據</div>
                            </div>
                        </div>

                        <!-- 我送出的申請單 -->
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 text-base">我的最近申請</h3>
                                </div>
                                <Link :href="route('forms.index')" class="text-xs text-gray-500 hover:underline">申請新表單</Link>
                            </div>
                            <div class="mt-3 divide-y divide-gray-100">
                                <div v-for="req in myRequests" :key="req.id" class="py-2.5 flex items-center justify-between text-sm">
                                    <div class="truncate mr-2">
                                        <Link :href="route('forms.show', req.id)" class="font-medium text-gray-800 hover:text-blue-600 truncate block">
                                            {{ req.title }}
                                        </Link>
                                        <span class="text-xs text-gray-400">{{ req.request_no }}</span>
                                    </div>
                                    <span :class="['px-2.5 py-1 text-xs font-semibold rounded-full', statusBadge(req.status)]">
                                        {{ req.status === 'approved' ? '已核准' : (req.status === 'rejected' ? '已駁回' : '審批中') }}
                                    </span>
                                </div>
                                <div v-if="myRequests.length === 0" class="py-4 text-center text-xs text-gray-400">尚未發起任何表單申請</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
