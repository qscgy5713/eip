<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    reports: Object,
    myCurrentWeekReport: Object,
    currentWeekInfo: Object,
    teamStats: Object,
    departments: Array,
    canManage: Boolean,
    filters: Object,
});

const currentView = ref(props.filters.view || 'my');
const selectedYear = ref(props.filters.year);
const selectedWeek = ref(props.filters.week);
const selectedStatus = ref(props.filters.status);
const selectedDept = ref(props.filters.department_id);
const searchKeyword = ref(props.filters.search);

const applyFilter = () => {
    router.get(
        route('weekly-reports.index'),
        {
            view: currentView.value,
            year: selectedYear.value,
            week: selectedWeek.value,
            status: selectedStatus.value,
            department_id: selectedDept.value,
            search: searchKeyword.value,
        },
        { preserveState: true, replace: true }
    );
};

const switchView = (view) => {
    currentView.value = view;
    applyFilter();
};

const sendRemind = (userId, userName) => {
    if (confirm(`確定要向「${userName}」發送第 ${props.currentWeekInfo.week_number} 週週報催繳提醒嗎？`)) {
        router.post(route('weekly-reports.remind'), {
            user_id: userId,
            year: props.currentWeekInfo.year,
            week_number: props.currentWeekInfo.week_number,
        }, {
            preserveScroll: true,
        });
    }
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'draft':
            return { text: '草稿暫存', class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' };
        case 'submitted':
            return { text: '已提交待點評', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' };
        case 'reviewed':
            return { text: '主管已點評', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' };
        default:
            return { text: status, class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' };
    }
};
</script>

<template>
    <Head title="工作週報與團隊協同簡報" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>📝 工作週報與團隊協同簡報</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-cyan-100 text-cyan-800 dark:bg-cyan-900/50 dark:text-cyan-300">
                            {{ currentWeekInfo.year }} 年 第 {{ currentWeekInfo.week_number }} 週
                        </span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        週期時間：{{ currentWeekInfo.start_date }} ~ {{ currentWeekInfo.end_date }}
                    </p>
                </div>
                <div>
                    <Link
                        v-if="!myCurrentWeekReport"
                        :href="route('weekly-reports.create', { year: currentWeekInfo.year, week: currentWeekInfo.week_number })"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        撰寫本週週報 (第 {{ currentWeekInfo.week_number }} 週)
                    </Link>
                    <Link
                        v-else
                        :href="route('weekly-reports.show', myCurrentWeekReport.id)"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        檢視我本週已交週報
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 統計與催繳提醒卡片 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- 我本週提交狀態 -->
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">我本週繳交狀態</p>
                            <p class="text-lg font-bold mt-1 text-gray-900 dark:text-gray-100">
                                <span v-if="!myCurrentWeekReport" class="text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> 尚未填寫提交
                                </span>
                                <span v-else class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> {{ myCurrentWeekReport.status_label }}
                                </span>
                            </p>
                            <p class="text-[11px] text-gray-400 mt-1">週五下班前請完成總結</p>
                        </div>
                        <div class="p-3 bg-cyan-50 dark:bg-cyan-950/50 text-cyan-600 dark:text-cyan-400 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                    </div>

                    <!-- 團隊繳交統計 (主管/管理員可見) -->
                    <template v-if="canManage && teamStats">
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">部門同仁本週已交率</p>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                                        {{ teamStats.total_members ? Math.round((teamStats.submitted_count / teamStats.total_members) * 100) : 0 }}%
                                    </p>
                                    <span class="text-xs text-gray-400">
                                        ({{ teamStats.submitted_count }} / {{ teamStats.total_members }} 人)
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">所轄部門活躍編制</p>
                            </div>
                            <div class="p-3 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                        </div>

                        <!-- 待催繳人數卡片 -->
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">當週尚未繳交</p>
                                <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">
                                    {{ teamStats.unsubmitted_count }} <span class="text-xs font-normal text-gray-400">人</span>
                                </p>
                                <p class="text-[11px] text-gray-400 mt-1">可點擊一鍵提醒催繳</p>
                            </div>
                            <div class="p-3 bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 rounded-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- 未繳交同仁快速提醒列表 (僅主管可見且有未繳交同仁時) -->
                <div v-if="canManage && teamStats && teamStats.unsubmitted_users.length > 0" class="p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/60 rounded-xl">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            第 {{ currentWeekInfo.week_number }} 週尚未繳交週報同仁 ({{ teamStats.unsubmitted_users.length }} 人)：
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <div
                            v-for="unUser in teamStats.unsubmitted_users"
                            :key="unUser.id"
                            class="inline-flex items-center gap-2 px-3 py-1 bg-white dark:bg-gray-800 border border-amber-200 dark:border-amber-800 rounded-lg text-xs"
                        >
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ unUser.name }}</span>
                            <span class="text-gray-400 text-[10px]">{{ unUser.job_title || '同仁' }}</span>
                            <button
                                @click="sendRemind(unUser.id, unUser.name)"
                                class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 hover:underline"
                            >
                                🔔 提醒催繳
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 導覽切換與多維篩選列 -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-700 pb-3">
                        <!-- 視圖切換 Tabs -->
                        <div class="flex items-center space-x-1 bg-gray-100 dark:bg-gray-700/60 p-1 rounded-lg">
                            <button
                                @click="switchView('my')"
                                :class="[
                                    'px-4 py-1.5 text-xs font-bold rounded-md transition',
                                    currentView === 'my'
                                        ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                                ]"
                            >
                                我的週報存摺
                            </button>
                            <button
                                v-if="canManage"
                                @click="switchView('team')"
                                :class="[
                                    'px-4 py-1.5 text-xs font-bold rounded-md transition',
                                    currentView === 'team'
                                        ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                                ]"
                            >
                                部門同仁週報總覽
                            </button>
                        </div>

                        <!-- 關鍵字搜尋 -->
                        <div class="flex items-center gap-2">
                            <input
                                v-model="searchKeyword"
                                @keyup.enter="applyFilter"
                                type="text"
                                placeholder="搜尋摘要/完成事項/同仁姓名..."
                                class="text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500 py-1.5 px-3 w-56"
                            />
                            <button
                                @click="applyFilter"
                                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-semibold rounded-lg transition"
                            >
                                查詢
                            </button>
                        </div>
                    </div>

                    <!-- 篩選器列 -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div>
                            <label class="block text-gray-500 dark:text-gray-400 mb-1">年度</label>
                            <select
                                v-model="selectedYear"
                                @change="applyFilter"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-1.5"
                            >
                                <option :value="2026">2026 年</option>
                                <option :value="2025">2025 年</option>
                            </select>
                        </div>

                        <div v-if="currentView === 'team'">
                            <label class="block text-gray-500 dark:text-gray-400 mb-1">週次</label>
                            <input
                                v-model="selectedWeek"
                                @change="applyFilter"
                                type="number"
                                min="1"
                                max="53"
                                placeholder="週次 (例如 41)"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-1.5"
                            />
                        </div>

                        <div>
                            <label class="block text-gray-500 dark:text-gray-400 mb-1">審查狀態</label>
                            <select
                                v-model="selectedStatus"
                                @change="applyFilter"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-1.5"
                            >
                                <option value="all">全部狀態</option>
                                <option value="draft">草稿中</option>
                                <option value="submitted">已提交待點評</option>
                                <option value="reviewed">主管已點評</option>
                            </select>
                        </div>

                        <div v-if="departments && departments.length > 0 && currentView === 'team'">
                            <label class="block text-gray-500 dark:text-gray-400 mb-1">部門</label>
                            <select
                                v-model="selectedDept"
                                @change="applyFilter"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-1.5"
                            >
                                <option value="">全公司部門</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 週報卡片列表 -->
                <div class="space-y-4">
                    <div
                        v-for="rep in reports.data"
                        :key="rep.id"
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm p-5 hover:border-indigo-300 dark:hover:border-indigo-600 transition"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-700 pb-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-cyan-500 text-white font-bold flex items-center justify-center text-sm shadow-xs shrink-0">
                                    {{ rep.user?.name.charAt(0) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">
                                            {{ rep.user?.name }}
                                        </h3>
                                        <span class="text-xs text-gray-400">
                                            ({{ rep.department?.name || '無部門' }} · {{ rep.user?.job_title || '同仁' }})
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ rep.year }} 年第 {{ rep.week_number }} 週 ({{ rep.start_date }} ~ {{ rep.end_date }})
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span :class="['px-2.5 py-1 text-xs font-bold rounded-full', getStatusBadge(rep.status).class]">
                                    {{ getStatusBadge(rep.status).text }}
                                </span>
                                <Link
                                    :href="route('weekly-reports.show', rep.id)"
                                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition"
                                >
                                    查看詳情 &rarr;
                                </Link>
                            </div>
                        </div>

                        <!-- 週報內容簡報 -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            <div class="md:col-span-1 bg-gray-50 dark:bg-gray-700/40 p-3 rounded-lg">
                                <p class="font-bold text-gray-600 dark:text-gray-300 mb-1">📌 本週重點概述：</p>
                                <p class="text-gray-800 dark:text-gray-200 line-clamp-3 leading-relaxed whitespace-pre-line">{{ rep.summary }}</p>
                            </div>
                            <div class="md:col-span-1 bg-gray-50 dark:bg-gray-700/40 p-3 rounded-lg">
                                <p class="font-bold text-emerald-700 dark:text-emerald-400 mb-1">🎯 具體完成事項：</p>
                                <p class="text-gray-800 dark:text-gray-200 line-clamp-3 leading-relaxed whitespace-pre-line">{{ rep.achievements }}</p>
                            </div>
                            <div class="md:col-span-1 bg-gray-50 dark:bg-gray-700/40 p-3 rounded-lg">
                                <p class="font-bold text-blue-700 dark:text-blue-400 mb-1">🚀 下週工作計畫：</p>
                                <p class="text-gray-800 dark:text-gray-200 line-clamp-3 leading-relaxed whitespace-pre-line">{{ rep.plans_next_week }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- 空狀態 -->
                    <div v-if="reports.data.length === 0" class="p-12 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 dark:bg-gray-700/50 flex items-center justify-center text-gray-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <h4 class="text-base font-bold text-gray-700 dark:text-gray-300">查無相關工作週報紀錄</h4>
                        <p class="text-xs text-gray-400 mt-1">目前條件下尚無提交之工作週報，您可以點擊右上角按鈕開始撰寫！</p>
                    </div>

                    <!-- 分頁導覽 -->
                    <div v-if="reports.links && reports.links.length > 3" class="flex justify-center space-x-1 pt-4">
                        <template v-for="(link, idx) in reports.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-1.5 text-xs font-medium rounded-md transition',
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'
                                ]"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="px-3 py-1.5 text-xs font-medium rounded-md text-gray-400 dark:text-gray-600 border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 cursor-not-allowed"
                            />
                        </template>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
