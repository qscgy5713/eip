<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    announcements: Array,
    pendingApprovals: Array,
    myRequests: Array,
    stats: Object,
    todayAttendance: Object,
    myUpcomingBookings: Array,
    myLeaveSummary: Object,
    teamAttendanceSnapshot: Object,
    activePolls: Array,
});

const clockInForm = useForm({
    latitude: null,
    longitude: null,
});
const clockOutForm = useForm({
    latitude: null,
    longitude: null,
});

const handleClockIn = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                clockInForm.latitude = pos.coords.latitude;
                clockInForm.longitude = pos.coords.longitude;
                clockInForm.post(route('attendance.clockIn'), { preserveScroll: true });
            },
            () => {
                clockInForm.post(route('attendance.clockIn'), { preserveScroll: true });
            },
            { timeout: 5000 }
        );
    } else {
        clockInForm.post(route('attendance.clockIn'), { preserveScroll: true });
    }
};

const handleClockOut = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                clockOutForm.latitude = pos.coords.latitude;
                clockOutForm.longitude = pos.coords.longitude;
                clockOutForm.post(route('attendance.clockOut'), { preserveScroll: true });
            },
            () => {
                clockOutForm.post(route('attendance.clockOut'), { preserveScroll: true });
            },
            { timeout: 5000 }
        );
    } else {
        clockOutForm.post(route('attendance.clockOut'), { preserveScroll: true });
    }
};

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
                <!-- 提示/錯誤訊息 -->
                <div v-if="$page.props.flash?.error" class="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-center justify-between text-rose-800 shadow-sm text-sm">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                    <Link :href="route('attendance.index')" class="text-xs font-semibold text-rose-700 underline hover:text-rose-900 shrink-0 ml-4">
                        前往考勤頁面填寫事由打卡 →
                    </Link>
                </div>

                <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center text-emerald-800 shadow-sm text-sm">
                    <svg class="w-5 h-5 text-emerald-600 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <!-- 今日快捷打卡區 -->
                <div class="bg-gradient-to-r from-slate-900 to-indigo-950 rounded-xl p-5 text-white shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center text-blue-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-indigo-200">今日出勤狀態</p>
                            <p class="text-base font-bold text-white">
                                <span v-if="!todayAttendance" class="text-amber-400">尚未打卡簽到</span>
                                <span v-else-if="todayAttendance.clock_in_at && !todayAttendance.clock_out_at" class="text-blue-300">上班簽到：{{ new Date(todayAttendance.clock_in_at).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' }) }} (工作中)</span>
                                <span v-else class="text-emerald-300">今日已簽退 (工時 {{ todayAttendance.work_hours }}h)</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button
                            v-if="!todayAttendance?.clock_in_at"
                            @click="handleClockIn"
                            :disabled="clockInForm.processing"
                            class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs shadow-md transition"
                        >
                            上班打卡
                        </button>
                        <button
                            v-else
                            @click="handleClockOut"
                            :disabled="clockOutForm.processing"
                            class="px-5 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-md transition"
                        >
                            {{ todayAttendance?.clock_out_at ? '更新下班卡' : '下班打卡' }}
                        </button>
                        <Link :href="route('attendance.index')" class="px-4 py-2.5 rounded-lg bg-white/10 hover:bg-white/20 text-indigo-100 text-xs font-semibold border border-white/10 transition">
                            查看考勤月報 &rarr;
                        </Link>
                    </div>
                </div>

                <!-- 常用快捷行動列 (Quick Action Bar) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <Link :href="route('forms.index')" class="p-3.5 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-blue-400 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800">表單申辦</p>
                            <p class="text-[11px] text-gray-400">請假、加班、簽呈</p>
                        </div>
                    </Link>
                    <Link :href="route('meeting-rooms.index')" class="p-3.5 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-indigo-400 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800">會議室預約</p>
                            <p class="text-[11px] text-gray-400">設備借用與時段</p>
                        </div>
                    </Link>
                    <Link :href="route('leave-balances.index')" class="p-3.5 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-emerald-400 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800">假勤存摺</p>
                            <p class="text-[11px] text-gray-400">特休與補休明細</p>
                        </div>
                    </Link>
                    <Link :href="route('organization.index')" class="p-3.5 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-amber-400 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800">組織通訊錄</p>
                            <p class="text-[11px] text-gray-400">同仁分機與主管</p>
                        </div>
                    </Link>
                    <Link :href="route('knowledge.index')" class="p-3.5 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-purple-400 hover:shadow-md transition flex items-center space-x-3 group col-span-2 sm:col-span-1">
                        <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800">企業知識庫</p>
                            <p class="text-[11px] text-gray-400">規章辦法與手冊</p>
                        </div>
                    </Link>
                </div>

                <!-- 團隊出勤快報 (主管/人事/管理員專屬) -->
                <div v-if="teamAttendanceSnapshot" class="bg-white rounded-xl p-5 border border-indigo-100 shadow-sm bg-gradient-to-r from-indigo-50/40 via-white to-blue-50/30">
                    <div class="flex items-center justify-between pb-3 border-b border-indigo-100/60">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 bg-indigo-600 rounded-full animate-pulse"></span>
                            <h3 class="text-sm font-bold text-gray-900">團隊今日出勤快報 (主管管理視圖)</h3>
                        </div>
                        <Link :href="route('attendance.index')" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
                            考勤詳情報告 &rarr;
                        </Link>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                        <div class="bg-white/80 p-3 rounded-lg border border-gray-100">
                            <p class="text-xs text-gray-500 font-medium">部門在職同仁</p>
                            <p class="text-xl font-black text-gray-800 mt-1">{{ teamAttendanceSnapshot.total_members }} <span class="text-xs font-normal text-gray-400">人</span></p>
                        </div>
                        <div class="bg-emerald-50/70 p-3 rounded-lg border border-emerald-100">
                            <p class="text-xs text-emerald-700 font-medium">今日已打卡出勤</p>
                            <p class="text-xl font-black text-emerald-700 mt-1">{{ teamAttendanceSnapshot.clocked_in_count }} <span class="text-xs font-normal text-emerald-600">人</span></p>
                        </div>
                        <div class="bg-blue-50/70 p-3 rounded-lg border border-blue-100">
                            <p class="text-xs text-blue-700 font-medium">今日核准請假中</p>
                            <p class="text-xl font-black text-blue-700 mt-1">{{ teamAttendanceSnapshot.on_leave_count }} <span class="text-xs font-normal text-blue-600">人</span></p>
                        </div>
                        <div class="bg-amber-50/70 p-3 rounded-lg border border-amber-100">
                            <p class="text-xs text-amber-700 font-medium">尚未出勤打卡</p>
                            <p class="text-xl font-black text-amber-700 mt-1">{{ teamAttendanceSnapshot.unclocked_count }} <span class="text-xs font-normal text-amber-600">人</span></p>
                        </div>
                    </div>
                </div>

                <!-- 數據統計看板 & 個人休假摘要 -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500">未讀企業公告</p>
                            <p class="text-2xl font-extrabold text-blue-600 mt-1">{{ stats.unreadAnnouncementsCount }}</p>
                        </div>
                        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        </div>
                    </div>
                    <Link
                        :href="route('approvals.index')"
                        class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between hover:border-amber-300 hover:shadow-md transition cursor-pointer group"
                    >
                        <div>
                            <p class="text-xs font-medium text-gray-500 group-hover:text-amber-700 transition">待我審批單據</p>
                            <p class="text-2xl font-extrabold text-amber-600 mt-1">{{ stats.pendingApprovalsCount }}</p>
                        </div>
                        <div class="p-2.5 bg-amber-50 text-amber-600 rounded-xl group-hover:bg-amber-100 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </Link>
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500">我進行中的申請</p>
                            <p class="text-2xl font-extrabold text-emerald-600 mt-1">{{ stats.myPendingRequestsCount }}</p>
                        </div>
                        <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                    </div>
                    <!-- 個人休假餘額小卡 -->
                    <Link
                        :href="route('leave-balances.index')"
                        class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between hover:border-emerald-300 hover:shadow-md transition group"
                    >
                        <div>
                            <p class="text-xs font-medium text-gray-500 group-hover:text-emerald-700 transition">我的可用假額度</p>
                            <div class="mt-1 flex items-baseline space-x-2">
                                <span class="text-xs text-gray-500">特休: <strong class="text-base text-emerald-600">{{ myLeaveSummary?.annual_available ?? 0 }}</strong> 天</span>
                                <span class="text-xs text-gray-300">|</span>
                                <span class="text-xs text-gray-500">補休: <strong class="text-base text-teal-600">{{ myLeaveSummary?.compensatory_available ?? 0 }}</strong> 天</span>
                            </div>
                        </div>
                        <div class="p-2.5 bg-teal-50 text-teal-600 rounded-xl group-hover:bg-teal-100 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    </Link>
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

                    <!-- 同仁投票調查專區 -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                <h3 class="font-bold text-gray-900 text-lg">同仁投票調查</h3>
                            </div>
                            <Link :href="route('polls.index')" class="text-sm text-indigo-600 hover:text-indigo-700 font-semibold hover:underline">
                                投票專區 &rarr;
                            </Link>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div
                                v-for="poll in activePolls"
                                :key="poll.id"
                                class="p-3.5 rounded-lg bg-indigo-50/50 border border-indigo-100 flex items-center justify-between gap-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-indigo-100 text-indigo-700 shrink-0">
                                            {{ poll.is_multiple_choice ? '複選' : '單選' }}
                                        </span>
                                        <Link :href="route('polls.show', poll.id)" class="font-semibold text-sm text-gray-900 hover:text-indigo-600 truncate block">
                                            {{ poll.title }}
                                        </Link>
                                    </div>
                                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                        <span>{{ poll.voters_count }} 人參與</span>
                                        <span v-if="poll.has_voted" class="text-emerald-600 font-medium">✓ 您已投票</span>
                                        <span v-else class="text-amber-600 font-medium">尚未投票</span>
                                    </div>
                                </div>
                                <Link
                                    :href="route('polls.show', poll.id)"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg shadow-2xs shrink-0 transition-colors"
                                    :class="poll.has_voted ? 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                                >
                                    {{ poll.has_voted ? '看結果' : '投票' }}
                                </Link>
                            </div>
                            <div v-if="!activePolls || activePolls.length === 0" class="py-6 text-center text-xs text-gray-400">
                                目前尚無進行中的同仁投票活動
                            </div>
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
                                <Link :href="route('approvals.index')" class="text-sm text-amber-600 hover:text-amber-700 font-semibold hover:underline">審批中心 (批次簽核) &rarr;</Link>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div v-for="approval in pendingApprovals" :key="approval.id" class="p-3 rounded-lg bg-amber-50/60 border border-amber-100 flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <p class="font-semibold text-sm text-gray-900">{{ approval.form_request?.title }}</p>
                                            <span v-if="approval.is_delegated" class="px-1.5 py-0.5 text-[10px] font-bold bg-amber-200/80 text-amber-900 border border-amber-300 rounded shrink-0">
                                                代理：{{ approval.approver?.name }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-0.5">申請人：{{ approval.form_request?.user?.name }} ({{ approval.form_request?.form?.name }})</p>
                                    </div>
                                    <Link :href="route('forms.show', approval.form_request_id)" class="px-3 py-1.5 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm shrink-0">
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

                        <!-- 即將進行的會議 -->
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-blue-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 text-base">即將進行的會議</h3>
                                </div>
                                <Link :href="route('meeting-rooms.index')" class="text-xs text-blue-600 hover:underline">預約與借用 &rarr;</Link>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div v-for="b in myUpcomingBookings" :key="b.id" class="p-3 rounded-lg bg-blue-50/50 border border-blue-100 flex items-center justify-between text-xs">
                                    <div>
                                        <p class="font-bold text-gray-900 text-sm">{{ b.title }}</p>
                                        <p class="text-gray-500 mt-1 flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            {{ b.room?.name }} ({{ b.room?.location }})
                                        </p>
                                        <p class="text-blue-700 mt-0.5 font-mono flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ new Date(b.start_time).toLocaleDateString() }} {{ new Date(b.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }} ~ {{ new Date(b.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}
                                        </p>
                                    </div>
                                    <Link :href="route('meeting-rooms.index')" class="text-xs text-blue-600 font-medium px-2 py-1 bg-white border border-blue-200 rounded hover:bg-blue-50">
                                        檢視
                                    </Link>
                                </div>
                                <div v-if="!myUpcomingBookings || myUpcomingBookings.length === 0" class="py-4 text-center text-xs text-gray-400">
                                    近期無預定會議
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
