<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    myTasks: Array,
    myAssets: Array,
    myWeeklyReport: Object,
    currentWeekNumber: Number,
    latestPayslip: Object,
    activeMealOrders: Array,
    myActiveTickets: Array,
    todayVisitors: Array,
    myActiveDispatches: Array,
    upcomingTrainings: Array,
    latestKudos: Array,
});

const showSalary = ref(false);

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
        case 'urgent': return 'bg-red-100 dark:bg-red-950/40 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800';
        case 'high': return 'bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        default: return 'bg-indigo-100 dark:bg-indigo-950/40 text-indigo-800 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800';
    }
};

const statusBadge = (status) => {
    switch (status) {
        case 'approved': return 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300';
        case 'rejected': return 'bg-red-100 dark:bg-red-950/40 text-red-800 dark:text-red-300';
        default: return 'bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300';
    }
};
</script>

<template>
    <Head title="總覽儀表板" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100">
                    工作台總覽儀表板
                </h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">歡迎回到 EIP 企業系統</span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6 px-4">
                <!-- 提示/錯誤訊息 -->
                <div v-if="$page.props.flash?.error" class="p-4 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 rounded-xl flex items-center justify-between text-red-800 dark:text-red-300 shadow-sm text-sm">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                    <Link :href="route('attendance.index')" class="text-xs font-semibold text-red-700 dark:text-red-300 underline hover:text-red-900 dark:hover:text-red-200 shrink-0 ml-4">
                        前往考勤頁面填寫事由打卡 →
                    </Link>
                </div>

                <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 rounded-xl flex items-center text-emerald-800 dark:text-emerald-300 shadow-sm text-sm">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <!-- 今日快捷打卡區 -->
                <div class="bg-gradient-to-r from-gray-900 to-indigo-950 rounded-xl p-5 text-white shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-indigo-200">今日出勤狀態</p>
                            <p class="text-base font-bold text-white">
                                <span v-if="!todayAttendance" class="text-amber-400">尚未打卡簽到</span>
                                <span v-else-if="todayAttendance.clock_in_at && !todayAttendance.clock_out_at" class="text-indigo-300">上班簽到：{{ new Date(todayAttendance.clock_in_at).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' }) }} (工作中)</span>
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
                        <Link :href="route('attendance.index')" class="px-4 py-2.5 rounded-lg bg-white/10 hover:bg-white/20 text-indigo-100 text-xs font-semibold border border-white/10 transition dark:bg-gray-800">
                            查看考勤月報 &rarr;
                        </Link>
                    </div>
                </div>

                <!-- 最新薪資通知橫幅 (若有已發布薪資條) -->
                <div v-if="latestPayslip" class="bg-gradient-to-r from-gray-900 to-indigo-950 rounded-xl p-5 text-white shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4 border border-emerald-800/40">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-300 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/30 text-emerald-200 border border-emerald-400/30">薪酬已發放</span>
                                <p class="text-xs text-emerald-200">{{ latestPayslip.period_label }} 薪資單已送達</p>
                            </div>
                            <p class="text-base font-bold text-white mt-1 flex items-center gap-2">
                                實發金額：
                                <span class="font-mono text-emerald-300 text-lg">
                                    {{ showSalary ? '$' + Number(latestPayslip.net_pay).toLocaleString() : '••••••••' }}
                                </span>
                                <button
                                    @click="showSalary = !showSalary"
                                    type="button"
                                    class="text-xs text-emerald-300/80 hover:text-white underline ml-1 focus:outline-none inline-flex items-center gap-1"
                                >
                                    <svg v-if="showSalary" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    {{ showSalary ? '遮罩防窺' : '顯示金額' }}
                                </button>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <Link
                            :href="route('payslips.show', latestPayslip.id)"
                            class="px-4 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs shadow-md transition inline-flex items-center gap-1.5"
                        >
                            <span>檢視明細 / 列印</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </Link>
                        <Link
                            :href="route('payslips.index')"
                            class="px-3.5 py-2.5 rounded-lg bg-white/10 hover:bg-white/20 text-emerald-100 text-xs font-semibold border border-white/10 transition dark:bg-gray-800"
                        >
                            歷史薪資存摺
                        </Link>
                    </div>
                </div>

                <!-- 常用快捷行動列 (Quick Action Bar) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <Link :href="route('forms.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">表單申辦</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">請假、加班、簽呈</p>
                        </div>
                    </Link>
                    <Link :href="route('payslips.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">薪資明細</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">個人存摺與單據</p>
                        </div>
                    </Link>
                    <Link :href="route('weekly-reports.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">工作週報</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">每週進度與回報</p>
                        </div>
                    </Link>
                    <Link :href="route('projects.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">專案看板</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">任務協同與進度</p>
                        </div>
                    </Link>
                    <Link :href="route('assets.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">資產設備</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">借調與保管清冊</p>
                        </div>
                    </Link>
                    <Link :href="route('meeting-rooms.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">會議室預約</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">設備借用與時段</p>
                        </div>
                    </Link>
                    <Link :href="route('leave-balances.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">假勤存摺</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">特休與補休明細</p>
                        </div>
                    </Link>
                    <Link :href="route('org-management.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">組織通訊錄</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">同仁分機與主管</p>
                        </div>
                    </Link>
                    <Link :href="route('documents.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">企業知識庫</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">規章辦法與手冊</p>
                        </div>
                    </Link>
                    <Link :href="route('meal-orders.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">訂餐揪團</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">午餐下午茶開團</p>
                        </div>
                    </Link>
                    <Link :href="route('tickets.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">服務叫修</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">IT總務故障通報</p>
                        </div>
                    </Link>
                    <Link :href="route('visitor-reservations.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">訪客接待</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">預約通行與門禁</p>
                        </div>
                    </Link>
                    <Link :href="route('vehicles.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4m-11-7l2.5-5h9L19 10m-14 0h14m-14 0a2 2 0 00-2 2v5a2 2 0 002 2h1m12 0h1a2 2 0 002-2v-5a2 2 0 00-2-2"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">公務派車</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">公務借調與出差</p>
                        </div>
                    </Link>
                    <Link :href="route('trainings.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v7"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">企業內訓</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">課程研習與學分</p>
                        </div>
                    </Link>
                    <Link :href="route('recognition.index')" class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:bg-indigo-500 group-hover:text-white transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200">感謝牆與福利</p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500">感謝幣與點數商城</p>
                        </div>
                    </Link>
                </div>

                <!-- 團隊出勤快報 (主管/人事/管理員專屬) -->
                <div v-if="teamAttendanceSnapshot" class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-indigo-100 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-indigo-100/60 dark:border-gray-700">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 bg-indigo-600 rounded-full animate-pulse"></span>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">團隊今日出勤快報 (主管管理視圖)</h3>
                        </div>
                        <Link :href="route('attendance.index')" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 hover:underline">
                            考勤詳情報告 &rarr;
                        </Link>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                        <div class="bg-white/80 dark:bg-gray-700/60 p-3 rounded-lg border border-gray-100 dark:border-gray-600">
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">部門在職同仁</p>
                            <p class="text-xl font-black text-gray-800 dark:text-gray-100 mt-1">{{ teamAttendanceSnapshot.total_members }} <span class="text-xs font-normal text-gray-400">人</span></p>
                        </div>
                        <div class="bg-emerald-50/70 dark:bg-emerald-950/30 p-3 rounded-lg border border-emerald-100 dark:border-emerald-800/60">
                            <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">今日已打卡出勤</p>
                            <p class="text-xl font-black text-emerald-700 dark:text-emerald-300 mt-1">{{ teamAttendanceSnapshot.clocked_in_count }} <span class="text-xs font-normal text-emerald-600 dark:text-emerald-500">人</span></p>
                        </div>
                        <div class="bg-indigo-50/70 dark:bg-indigo-950/30 p-3 rounded-lg border border-indigo-100 dark:border-indigo-800/60">
                            <p class="text-xs text-indigo-700 dark:text-indigo-400 font-medium">今日核准請假中</p>
                            <p class="text-xl font-black text-indigo-700 dark:text-indigo-300 mt-1">{{ teamAttendanceSnapshot.on_leave_count }} <span class="text-xs font-normal text-indigo-600 dark:text-indigo-500">人</span></p>
                        </div>
                        <div class="bg-amber-50/70 dark:bg-amber-950/30 p-3 rounded-lg border border-amber-100 dark:border-amber-800/60">
                            <p class="text-xs text-amber-700 dark:text-amber-400 font-medium">尚未出勤打卡</p>
                            <p class="text-xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ teamAttendanceSnapshot.unclocked_count }} <span class="text-xs font-normal text-amber-600 dark:text-amber-500">人</span></p>
                        </div>
                    </div>
                </div>

                <!-- 數據統計看板 & 個人休假摘要 -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">未讀企業公告</p>
                            <p class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ stats.unreadAnnouncementsCount }}</p>
                        </div>
                        <div class="p-2.5 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        </div>
                    </div>
                    <Link
                        :href="route('approvals.index')"
                        class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between hover:border-indigo-300 dark:hover:border-indigo-500 hover:shadow-md transition cursor-pointer group"
                    >
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 group-hover:text-indigo-700 dark:group-hover:text-indigo-400 transition">待我審批單據</p>
                            <p class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ stats.pendingApprovalsCount }}</p>
                        </div>
                        <div class="p-2.5 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded-xl group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/60 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </Link>
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">我進行中的申請</p>
                            <p class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ stats.myPendingRequestsCount }}</p>
                        </div>
                        <div class="p-2.5 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                    </div>
                    <!-- 個人休假餘額小卡 -->
                    <Link
                        :href="route('leave-balances.index')"
                        class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between hover:border-indigo-300 dark:hover:border-indigo-500 hover:shadow-md transition group"
                    >
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 group-hover:text-indigo-700 dark:group-hover:text-indigo-400 transition">我的可用假額度</p>
                            <div class="mt-1 flex items-baseline space-x-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400">特休: <strong class="text-base text-indigo-600 dark:text-indigo-400">{{ myLeaveSummary?.annual_available ?? 0 }}</strong> 天</span>
                                <span class="text-xs text-gray-300 dark:text-gray-600">|</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">補休: <strong class="text-base text-indigo-600 dark:text-indigo-400">{{ myLeaveSummary?.compensatory_available ?? 0 }}</strong> 天</span>
                            </div>
                        </div>
                        <div class="p-2.5 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded-xl group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/60 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    </Link>
                </div>

                <!-- 主體區塊：最新公告 與 簽核動態 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- 最新企業公告 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 bg-indigo-600 rounded-full"></span>
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-lg">重要企業公告</h3>
                            </div>
                            <Link :href="route('announcements.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">查看全部 &rarr;</Link>
                        </div>
                        <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-700 flex-1">
                            <div v-for="item in announcements" :key="item.id" class="py-3.5 first:pt-0 last:pb-0 flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <span v-if="item.is_pinned" class="px-2 py-0.5 text-xs font-semibold bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded">置頂</span>
                                        <span :class="['px-2 py-0.5 text-xs font-medium border rounded', priorityBadge(item.priority)]">{{ item.priority === 'urgent' ? '緊急' : (item.priority === 'high' ? '重要' : '一般') }}</span>
                                        <Link :href="route('announcements.show', item.id)" class="text-base font-semibold text-gray-900 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 line-clamp-1">
                                            {{ item.title }}
                                        </Link>
                                    </div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">發布者：{{ item.author?.name }} · {{ new Date(item.published_at).toLocaleDateString('sv-SE') }}</p>
                                </div>
                                <span v-if="!item.is_read" class="w-2 h-2 rounded-full bg-red-500 shrink-0 mt-2" title="未讀"></span>
                            </div>
                            <div v-if="announcements.length === 0" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">目前尚無公告</div>
                        </div>
                    </div>

                    <!-- 同仁投票調查專區 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-lg">同仁投票調查</h3>
                            </div>
                            <Link :href="route('polls.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-semibold hover:underline">
                                投票專區 &rarr;
                            </Link>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div
                                v-for="poll in activePolls"
                                :key="poll.id"
                                class="p-3.5 rounded-lg bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-between gap-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 shrink-0">
                                            {{ poll.is_multiple_choice ? '複選' : '單選' }}
                                        </span>
                                        <Link :href="route('polls.show', poll.id)" class="font-semibold text-sm text-gray-900 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 truncate block">
                                            {{ poll.title }}
                                        </Link>
                                    </div>
                                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ poll.voters_count }} 人參與</span>
                                        <span v-if="poll.has_voted" class="text-emerald-600 dark:text-emerald-400 font-medium">✓ 您已投票</span>
                                        <span v-else class="text-amber-600 dark:text-amber-400 font-medium">尚未投票</span>
                                    </div>
                                </div>
                                <Link
                                    :href="route('polls.show', poll.id)"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg shadow-2xs shrink-0 transition-colors"
                                    :class="poll.has_voted ? 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                                >
                                    {{ poll.has_voted ? '看結果' : '投票' }}
                                </Link>
                            </div>
                            <div v-if="!activePolls || activePolls.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                目前尚無進行中的同仁投票活動
                            </div>
                        </div>
                    </div>

                    <!-- 我的專案任務專區 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-lg">我的專案任務</h3>
                                <span v-if="stats.myPendingTasksCount" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300">
                                    {{ stats.myPendingTasksCount }} 件待辦
                                </span>
                            </div>
                            <Link :href="route('projects.index')" class="text-sm text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                專案看板 &rarr;
                            </Link>
                        </div>
                        <div class="mt-4 space-y-2.5">
                            <div
                                v-for="task in myTasks"
                                :key="task.id"
                                class="p-3 rounded-lg bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-between gap-3 text-xs"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-1.5 py-0.2 text-[10px] font-mono font-bold rounded bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 shrink-0">
                                            {{ task.project?.code }}
                                        </span>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                            {{ task.title }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                        <span class="truncate">專案：{{ task.project?.name }}</span>
                                        <span v-if="task.due_date" :class="task.is_overdue ? 'text-red-600 dark:text-red-400 font-bold shrink-0' : 'shrink-0'">
                                            · {{ task.is_overdue ? '已逾期：' : '截止：' }}{{ new Date(task.due_date).toLocaleDateString('sv-SE') }}
                                        </span>
                                    </div>
                                </div>
                                <Link
                                    :href="route('projects.show', task.project_id)"
                                    class="px-2.5 py-1 text-xs font-semibold rounded bg-white dark:bg-gray-700 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-50 dark:hover:bg-gray-600 transition shrink-0"
                                >
                                    看板
                                </Link>
                            </div>
                            <div v-if="!myTasks || myTasks.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                目前無指派給您的待辦專案任務
                            </div>
                        </div>
                    </div>

                    <!-- 我保管的公務資產 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">我借用的公務資產</h3>
                                <span v-if="stats.myHeldAssetsCount" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300">
                                    {{ stats.myHeldAssetsCount }} 件在席
                                </span>
                            </div>
                            <Link :href="route('assets.index', { my_assets: true })" class="text-sm text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                我的資產清冊 &rarr;
                            </Link>
                        </div>
                        <div class="mt-4 space-y-2.5">
                            <div
                                v-for="asset in myAssets"
                                :key="asset.id"
                                class="p-3 rounded-lg bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-between gap-3 text-xs"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-1.5 py-0.2 text-[10px] font-mono font-bold rounded bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 shrink-0">
                                            {{ asset.asset_tag }}
                                        </span>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                            {{ asset.name }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                        <span class="truncate">地點：{{ asset.location || '總部辦公室' }}</span>
                                        <span v-if="asset.expected_return_date" :class="asset.is_overdue ? 'text-red-600 dark:text-red-400 font-bold shrink-0' : 'shrink-0'">
                                            · {{ asset.is_overdue ? '已逾期應還：' : '預計歸還：' }}{{ asset.expected_return_date }}
                                        </span>
                                    </div>
                                </div>
                                <Link
                                    :href="route('assets.show', asset.id)"
                                    class="px-2.5 py-1 text-xs font-semibold rounded bg-white dark:bg-gray-700 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-50 dark:hover:bg-gray-600 transition shrink-0"
                                >
                                    查看
                                </Link>
                            </div>
                            <div v-if="!myAssets || myAssets.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                目前無由您名下保管借調之公務資產
                            </div>
                        </div>
                    </div>

                    <!-- 本週工作週報專區 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">本週工作週報 (第 {{ currentWeekNumber }} 週)</h3>
                                </div>
                                <span
                                    v-if="myWeeklyReport"
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-bold',
                                        myWeeklyReport.status === 'reviewed' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' :
                                        myWeeklyReport.status === 'submitted' ? 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300' :
                                        'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'
                                    ]"
                                >
                                    {{ myWeeklyReport.status_label }}
                                </span>
                                <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300">
                                    尚未填寫
                                </span>
                            </div>
                            <div class="mt-4">
                                <template v-if="myWeeklyReport">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">工作重點摘要：</p>
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 mt-1 line-clamp-2">{{ myWeeklyReport.summary }}</p>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-2">
                                        提交時間：{{ new Date(myWeeklyReport.submitted_at || myWeeklyReport.created_at).toLocaleString() }}
                                    </p>
                                </template>
                                <template v-else>
                                    <div class="p-3 bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-900/40 rounded-lg text-xs text-amber-800 dark:text-amber-300">
                                        <p class="font-semibold">提醒您本週尚未提交工作週報</p>
                                        <p class="text-[11px] mt-1 text-amber-700 dark:text-amber-400">總結本週完成任務與跨部門成果，方便主管點評與團隊同步。</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                            <Link
                                v-if="myWeeklyReport"
                                :href="route('weekly-reports.show', myWeeklyReport.id)"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
                            >
                                檢視我的週報 &rarr;
                            </Link>
                            <Link
                                v-else
                                :href="route('weekly-reports.create')"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition"
                            >
                                立即撰寫本週週報 &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- 進行中同仁訂餐與下午茶揪團 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full animate-pulse"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">進行中訂餐揪團</h3>
                                </div>
                                <Link :href="route('meal-orders.index')" class="text-sm text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                    全部揪團 &rarr;
                                </Link>
                            </div>
                            <div class="mt-4 space-y-2.5">
                                <div
                                    v-for="order in activeMealOrders"
                                    :key="order.id"
                                    class="p-3 rounded-lg bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-between gap-3 text-xs"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 shrink-0">
                                                {{ order.restaurant_name }}
                                            </span>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                {{ order.title }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                            <span>發起人：{{ order.creator_name }}</span>
                                            <span>· 已有 {{ order.participants_count }} 人點餐</span>
                                            <span v-if="order.cutoff_time" class="text-red-600 dark:text-red-400 font-semibold">
                                                · 截止：{{ order.cutoff_time }}
                                            </span>
                                        </div>
                                    </div>
                                    <Link
                                        :href="route('meal-orders.show', order.id)"
                                        class="px-2.5 py-1 text-xs font-semibold rounded bg-indigo-500 text-white hover:bg-indigo-600 transition shrink-0 shadow-xs"
                                    >
                                        點餐
                                    </Link>
                                </div>
                                <div v-if="!activeMealOrders || activeMealOrders.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                    目前無進行中的揪團訂餐活動，您也可以自行發起！
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                            <Link
                                :href="route('meal-orders.index')"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
                            >
                                我要開團 / 點餐 &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- 我提報的服務叫修工單 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">我的服務叫修工單</h3>
                                    <span v-if="stats.myPendingTicketsCount" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300">
                                        {{ stats.myPendingTicketsCount }} 件處理中
                                    </span>
                                </div>
                                <Link :href="route('tickets.index')" class="text-sm text-red-600 dark:text-red-400 font-semibold hover:underline">
                                    叫修中心 &rarr;
                                </Link>
                            </div>
                            <div class="mt-4 space-y-2.5">
                                <div
                                    v-for="ticket in myActiveTickets"
                                    :key="ticket.id"
                                    class="p-3 rounded-lg bg-red-50/40 dark:bg-red-950/20 border border-red-100 dark:border-red-900/50 flex items-center justify-between gap-3 text-xs"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.2 text-[10px] font-mono font-bold rounded bg-red-100 dark:bg-red-900/60 text-red-700 dark:text-red-300 shrink-0">
                                                {{ ticket.ticket_number }}
                                            </span>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                {{ ticket.title }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                            <span>分類：{{ ticket.category_label }}</span>
                                            <span v-if="ticket.assignee">· 工程師：{{ ticket.assignee.name }}</span>
                                            <span class="font-semibold text-red-600 dark:text-red-400">· {{ ticket.status_label }}</span>
                                        </div>
                                    </div>
                                    <Link
                                        :href="route('tickets.show', ticket.id)"
                                        class="px-2.5 py-1 text-xs font-semibold rounded bg-white dark:bg-gray-700 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-gray-600 transition shrink-0"
                                    >
                                        進度
                                    </Link>
                                </div>
                                <div v-if="!myActiveTickets || myActiveTickets.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                    目前無處理中的故障叫修工單，一切運作良好！
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                            <Link
                                :href="route('tickets.create')"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white shadow-xs transition"
                            >
                                我要報修 / 提工單 &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- 今日預約來訪貴賓 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">今日預約來訪貴賓</h3>
                                    <span v-if="stats.todayVisitorsCount" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300">
                                        {{ stats.todayVisitorsCount }} 位預約
                                    </span>
                                </div>
                                <Link :href="route('visitor-reservations.index')" class="text-sm text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                    前台接待大廳 &rarr;
                                </Link>
                            </div>
                            <div class="mt-4 space-y-2.5">
                                <div
                                    v-for="visitor in todayVisitors"
                                    :key="visitor.id"
                                    class="p-3 rounded-lg bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-between gap-3 text-xs"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.2 text-[10px] font-mono font-bold rounded bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 shrink-0">
                                                {{ visitor.reservation_code }}
                                            </span>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                {{ visitor.visitor_name }}
                                                <span v-if="visitor.visitor_company" class="text-gray-400 font-normal">({{ visitor.visitor_company }})</span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                            <span>預計：{{ visitor.expected_arrival_time || '全天' }}</span>
                                            <span v-if="visitor.meeting_room">· 會議室：{{ visitor.meeting_room.name }}</span>
                                            <span :class="['font-semibold', visitor.status === 'checked_in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-indigo-600 dark:text-indigo-400']">
                                                · {{ visitor.status_label }}
                                            </span>
                                        </div>
                                    </div>
                                    <Link
                                        :href="route('visitor-reservations.show', visitor.id)"
                                        class="px-2.5 py-1 text-xs font-semibold rounded bg-white dark:bg-gray-700 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-50 dark:hover:bg-gray-600 transition shrink-0"
                                    >
                                        通行卡
                                    </Link>
                                </div>
                                <div v-if="!todayVisitors || todayVisitors.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                    今日暫無外部貴賓預約來訪
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                            <Link
                                :href="route('visitor-reservations.create')"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
                            >
                                發起訪客預約 &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- 我的公務派車單 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">我的出差派車單</h3>
                                    <span v-if="stats.myActiveDispatchesCount" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300">
                                        {{ stats.myActiveDispatchesCount }} 單出差中
                                    </span>
                                </div>
                                <Link :href="route('vehicles.index')" class="text-sm text-amber-600 dark:text-amber-400 font-semibold hover:underline">
                                    派車總表 &rarr;
                                </Link>
                            </div>
                            <div class="mt-4 space-y-2.5">
                                <div
                                    v-for="dispatch in myActiveDispatches"
                                    :key="dispatch.id"
                                    class="p-3 rounded-lg bg-amber-50/40 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/50 flex items-center justify-between gap-3 text-xs"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.2 text-[10px] font-mono font-bold rounded bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 shrink-0">
                                                {{ dispatch.vehicle?.plate_number }}
                                            </span>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                {{ dispatch.vehicle?.name }} · {{ dispatch.destination }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                            <span>出發：{{ new Date(dispatch.start_time).toLocaleString('zh-TW', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                                            <span :class="['font-semibold', dispatch.status === 'active' ? 'text-emerald-600 dark:text-emerald-400' : 'text-indigo-600 dark:text-indigo-400']">
                                                · {{ dispatch.status_label }}
                                            </span>
                                        </div>
                                    </div>
                                    <Link
                                        :href="route('vehicles.show', dispatch.id)"
                                        class="px-2.5 py-1 text-xs font-semibold rounded bg-white dark:bg-gray-700 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 hover:bg-amber-50 dark:hover:bg-gray-600 transition shrink-0"
                                    >
                                        行程
                                    </Link>
                                </div>
                                <div v-if="!myActiveDispatches || myActiveDispatches.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                    目前無進行中的公務車借調或出差行程
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                            <Link
                                :href="route('vehicles.create')"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-amber-600 hover:bg-amber-700 text-white shadow-xs transition"
                            >
                                預約派車 &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- 企業內訓課程與員工學習學分進度 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">企業內訓與學分檔案</h3>
                                </div>
                                <span class="px-2 py-0.5 text-2xs font-bold rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                    已獲 {{ stats.myEarnedCredits || 0 }} 學分
                                </span>
                            </div>
                            <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
                                <div v-for="course in upcomingTrainings" :key="course.id" class="py-2.5 flex items-center justify-between text-sm">
                                    <div class="truncate mr-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                                                {{ course.course_code }}
                                            </span>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate text-xs">
                                                {{ course.title }}
                                            </p>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-0.5">
                                            講師：{{ course.instructor }} · {{ course.credits }} 學分 ({{ course.hours }} hrs)
                                        </p>
                                    </div>
                                    <div class="shrink-0">
                                        <span v-if="course.my_registration_status === 'registered'" class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-700">
                                            已報名
                                        </span>
                                        <span v-else-if="course.my_registration_status === 'waitlist'" class="px-2 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-700">
                                            候補中
                                        </span>
                                        <Link
                                            v-else
                                            :href="route('trainings.show', course.id)"
                                            class="px-2.5 py-1 text-xs font-semibold rounded bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-50 transition"
                                        >
                                            報名
                                        </Link>
                                    </div>
                                </div>
                                <div v-if="!upcomingTrainings || upcomingTrainings.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                    近期暫無開辦之熱門內訓課程
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                            <Link
                                :href="route('trainings.index')"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
                            >
                                探索內訓學習中心 &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- 感謝幣與福利點數 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">感謝幣與福利點數</h3>
                                </div>
                                <span class="px-2 py-0.5 text-2xs font-bold rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    {{ stats.myRewardPoints || 0 }} 點
                                </span>
                            </div>
                            <p class="mt-3 text-[11px] text-gray-400">本月還可贈送 {{ stats.myRecognitionQuota ?? 0 }} 枚感謝幣</p>
                            <div class="mt-2 divide-y divide-gray-100 dark:divide-gray-700">
                                <div v-for="kudos in latestKudos" :key="kudos.id" class="py-2.5 text-xs">
                                    <p class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ kudos.sender_name }} 送你 {{ kudos.points }} 枚 · {{ kudos.core_value_label }}
                                    </p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ kudos.message }}</p>
                                </div>
                                <div v-if="!latestKudos || latestKudos.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                    還沒有收到感謝，去文化牆感謝一位同仁吧
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
                            <Link :href="route('welfare.index')" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 transition">
                                福利商城
                            </Link>
                            <Link :href="route('recognition.index')" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow-xs transition">
                                前往文化牆 &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- 待辦簽核與最近申請 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col space-y-6">
                        <!-- 待我審核 -->
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-lg">待我審批</h3>
                                </div>
                                <Link :href="route('approvals.index')" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 font-semibold hover:underline">審批中心 (批次簽核) &rarr;</Link>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div v-for="approval in pendingApprovals" :key="approval.id" class="p-3 rounded-lg bg-amber-50/60 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/50 flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <p class="font-semibold text-sm text-gray-900 dark:text-gray-200">{{ approval.form_request?.title }}</p>
                                            <span v-if="approval.is_delegated" class="px-1.5 py-0.5 text-[10px] font-bold bg-amber-200/80 dark:bg-amber-900/60 text-amber-900 dark:text-amber-300 border border-amber-300 dark:border-amber-700 rounded shrink-0">
                                                代理：{{ approval.approver?.name }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">申請人：{{ approval.form_request?.user?.name }} ({{ approval.form_request?.form?.name }})</p>
                                    </div>
                                    <Link :href="route('forms.show', approval.form_request_id)" class="px-3 py-1.5 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm shrink-0">
                                        審核
                                    </Link>
                                </div>
                                <div v-if="pendingApprovals.length === 0" class="py-4 text-center text-xs text-gray-400 dark:text-gray-500">目前無待審核單據</div>
                            </div>
                        </div>

                        <!-- 我送出的申請單 -->
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">我的最近申請</h3>
                                </div>
                                <Link :href="route('forms.index')" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">申請新表單</Link>
                            </div>
                            <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
                                <div v-for="req in myRequests" :key="req.id" class="py-2.5 flex items-center justify-between text-sm">
                                    <div class="truncate mr-2">
                                        <Link :href="route('forms.show', req.id)" class="font-medium text-gray-800 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 truncate block">
                                            {{ req.title }}
                                        </Link>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ req.request_no }}</span>
                                    </div>
                                    <span :class="['px-2.5 py-1 text-xs font-semibold rounded-full', statusBadge(req.status)]">
                                        {{ req.status === 'approved' ? '已核准' : (req.status === 'rejected' ? '已駁回' : (req.status === 'withdrawn' ? '已撤回' : (req.status === 'revision_required' ? '退回修改中' : '審批中'))) }}
                                    </span>
                                </div>
                                <div v-if="myRequests.length === 0" class="py-4 text-center text-xs text-gray-400 dark:text-gray-500">尚未發起任何表單申請</div>
                            </div>
                        </div>

                        <!-- 即將進行的會議 -->
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">即將進行的會議</h3>
                                </div>
                                <Link :href="route('meeting-rooms.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">預約與借用 &rarr;</Link>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div v-for="b in myUpcomingBookings" :key="b.id" class="p-3 rounded-lg bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-between text-xs">
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-gray-100 text-sm">{{ b.title }}</p>
                                        <p class="text-gray-500 dark:text-gray-400 mt-1 flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            {{ b.room?.name }} ({{ b.room?.location }})
                                        </p>
                                        <p class="text-indigo-700 dark:text-indigo-300 mt-0.5 font-mono flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ new Date(b.start_time).toLocaleDateString('sv-SE') }} {{ new Date(b.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }} ~ {{ new Date(b.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}
                                        </p>
                                    </div>
                                    <Link :href="route('meeting-rooms.index')" class="text-xs text-indigo-600 dark:text-indigo-300 font-medium px-2 py-1 bg-white dark:bg-gray-700 border border-indigo-200 dark:border-indigo-700/60 rounded hover:bg-indigo-50 dark:hover:bg-gray-600">
                                        檢視
                                    </Link>
                                </div>
                                <div v-if="!myUpcomingBookings || myUpcomingBookings.length === 0" class="py-4 text-center text-xs text-gray-400 dark:text-gray-500">
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
