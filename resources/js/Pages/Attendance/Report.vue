<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    userReports: Array,
    summaryStats: Object,
    departments: Array,
    filters: Object,
    canExport: Boolean,
});

const month = ref(props.filters.month);
const departmentId = ref(props.filters.department_id || '');
const search = ref(props.filters.search || '');
const expandedUsers = ref(new Set());

const toggleExpand = (userId) => {
    if (expandedUsers.value.has(userId)) {
        expandedUsers.value.delete(userId);
    } else {
        expandedUsers.value.add(userId);
    }
};

const applyFilter = () => {
    router.get(route('attendance.reports.index'), {
        month: month.value,
        department_id: departmentId.value || undefined,
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const changeMonth = (offset) => {
    const [y, m] = month.value.split('-').map(Number);
    const date = new Date(y, m - 1 + offset, 1);
    const newY = date.getFullYear();
    const newM = String(date.getMonth() + 1).padStart(2, '0');
    month.value = `${newY}-${newM}`;
    applyFilter();
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'normal':
            return { text: '正常', class: 'bg-emerald-100 text-emerald-800' };
        case 'late':
            return { text: '遲到', class: 'bg-amber-100 text-amber-800' };
        case 'early_leave':
            return { text: '早退', class: 'bg-rose-100 text-rose-800' };
        default:
            return { text: status, class: 'bg-gray-100 text-gray-700' };
    }
};

const getExportSummaryUrl = () => {
    const params = new URLSearchParams({
        month: month.value,
        ...(departmentId.value && { department_id: departmentId.value }),
    });
    return `${route('attendance.reports.exportSummary')}?${params.toString()}`;
};

const getExportDetailsUrl = () => {
    const params = new URLSearchParams({
        month: month.value,
        ...(departmentId.value && { department_id: departmentId.value }),
    });
    return `${route('attendance.reports.exportDetails')}?${params.toString()}`;
};
</script>

<template>
    <Head title="考勤月報統計與工時結算" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800">考勤月報統計與工時結算</h2>
                    <p class="text-xs text-gray-500 mt-1">HR 與主管專屬：出勤統計、異常工時排查與 Excel 報表匯出</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('attendance.index')"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition"
                    >
                        &larr; 返回每日打卡
                    </Link>
                    <a
                        v-if="canExport"
                        :href="getExportSummaryUrl()"
                        class="px-4 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg shadow-sm hover:bg-indigo-100 transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>匯出月度彙總 CSV</span>
                    </a>
                    <a
                        v-if="canExport"
                        :href="getExportDetailsUrl()"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>匯出每日明細 CSV</span>
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                <!-- 篩選列 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-center">
                        <!-- 月份切換 -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">統計月份</label>
                            <div class="flex items-center space-x-1">
                                <button
                                    type="button"
                                    @click="changeMonth(-1)"
                                    class="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600 text-xs font-bold"
                                    title="上個月"
                                >
                                    &larr;
                                </button>
                                <input
                                    v-model="month"
                                    type="month"
                                    @change="applyFilter"
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 font-bold text-gray-800"
                                />
                                <button
                                    type="button"
                                    @click="changeMonth(1)"
                                    class="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600 text-xs font-bold"
                                    title="下個月"
                                >
                                    &rarr;
                                </button>
                            </div>
                        </div>

                        <!-- 部門篩選 -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">部門</label>
                            <select
                                v-model="departmentId"
                                @change="applyFilter"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">全部部門</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    {{ dept.name }}
                                </option>
                            </select>
                        </div>

                        <!-- 員工搜尋 -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">員工搜尋</label>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="姓名 / 工號 / Email..."
                                @keyup.enter="applyFilter"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div class="flex items-end space-x-2 pt-4 sm:pt-0">
                            <button
                                type="button"
                                @click="applyFilter"
                                class="flex-1 py-2 px-4 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-lg shadow-sm transition"
                            >
                                篩選查詢
                            </button>
                            <button
                                type="button"
                                @click="search = ''; departmentId = ''; applyFilter();"
                                class="py-2 px-3 border border-gray-300 text-gray-600 hover:bg-gray-50 text-xs rounded-lg transition"
                                title="清除條件"
                            >
                                重設
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4 大 KPI 統計概覽卡片 -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center space-x-4">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-medium">在職統計人數</p>
                            <p class="text-xl font-bold text-gray-900 mt-0.5">{{ summaryStats.total_users }} <span class="text-xs font-normal text-gray-400">人</span></p>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center space-x-4">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-medium">總工時累計</p>
                            <p class="text-xl font-bold text-gray-900 mt-0.5">{{ summaryStats.total_work_hours }} <span class="text-xs font-normal text-gray-400">小時</span></p>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center space-x-4">
                        <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-medium">全體遲到人次</p>
                            <p class="text-xl font-bold text-amber-700 mt-0.5">{{ summaryStats.total_late_count }} <span class="text-xs font-normal text-gray-400">次</span></p>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center space-x-4">
                        <div class="p-3 bg-rose-50 text-rose-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-medium">全體早退人次</p>
                            <p class="text-xl font-bold text-rose-700 mt-0.5">{{ summaryStats.total_early_leave_count }} <span class="text-xs font-normal text-gray-400">次</span></p>
                        </div>
                    </div>
                </div>

                <!-- 員工考勤彙總表 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-base text-gray-900">
                            {{ month }} 月份各員工作勤明細表 (共 {{ userReports.length }} 員)
                        </h3>
                        <span class="text-xs text-gray-400">點擊列右側可展開每日打卡打點紀錄</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-gray-50/80 text-gray-500 border-b border-gray-100">
                                    <th class="py-3 px-4 font-semibold">工號 / 員工姓名</th>
                                    <th class="py-3 px-4 font-semibold">部門 / 職稱</th>
                                    <th class="py-3 px-4 font-semibold text-center">出勤天數</th>
                                    <th class="py-3 px-4 font-semibold text-center">累計工時</th>
                                    <th class="py-3 px-4 font-semibold text-center">遲到次數</th>
                                    <th class="py-3 px-4 font-semibold text-center">早退次數</th>
                                    <th class="py-3 px-4 font-semibold text-center">每日明細</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template v-for="u in userReports" :key="u.id">
                                    <tr class="hover:bg-gray-50/80 transition cursor-pointer" @click="toggleExpand(u.id)">
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-gray-900">{{ u.name }}</div>
                                            <div class="text-[11px] text-gray-400 font-mono">{{ u.employee_no || '-' }}</div>
                                        </td>
                                        <td class="py-3 px-4 text-gray-600">
                                            <div>{{ u.department }}</div>
                                            <div class="text-[11px] text-gray-400">{{ u.job_title }}</div>
                                        </td>
                                        <td class="py-3 px-4 text-center font-bold text-gray-800">
                                            {{ u.present_days }} 天
                                        </td>
                                        <td class="py-3 px-4 text-center font-bold text-indigo-700">
                                            {{ u.total_hours }} 小時
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span v-if="u.late_count > 0" class="px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-800">
                                                {{ u.late_count }} 次
                                            </span>
                                            <span v-else class="text-gray-300">0</span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span v-if="u.early_leave_count > 0" class="px-2 py-0.5 rounded-full font-bold bg-rose-100 text-rose-800">
                                                {{ u.early_leave_count }} 次
                                            </span>
                                            <span v-else class="text-gray-300">0</span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <button
                                                type="button"
                                                class="text-indigo-600 hover:text-indigo-900 font-bold text-xs"
                                            >
                                                {{ expandedUsers.has(u.id) ? '收起 ▲' : '展開每日 ▼' }}
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- 展開子列：每日打卡紀錄 -->
                                    <tr v-if="expandedUsers.has(u.id)" class="bg-indigo-50/30">
                                        <td colspan="7" class="p-4">
                                            <div class="bg-white rounded-lg border border-indigo-100 p-4 shadow-sm">
                                                <h4 class="font-bold text-xs text-indigo-950 mb-3 flex items-center justify-between">
                                                    <span>{{ u.name }} 當月每日出勤軌跡 (共 {{ u.daily_records.length }} 筆)</span>
                                                    <span class="text-gray-400 font-normal">平均每日工時：{{ u.present_days > 0 ? (u.total_hours / u.present_days).toFixed(1) : 0 }} 小時</span>
                                                </h4>

                                                <div v-if="u.daily_records.length > 0" class="overflow-x-auto">
                                                    <table class="w-full text-left text-xs">
                                                        <thead>
                                                            <tr class="border-b border-gray-100 text-gray-400 text-[11px]">
                                                                <th class="py-2 px-3">日期</th>
                                                                <th class="py-2 px-3">上班打卡</th>
                                                                <th class="py-2 px-3">下班打卡</th>
                                                                <th class="py-2 px-3 text-center">當日工時</th>
                                                                <th class="py-2 px-3 text-center">出勤狀態</th>
                                                                <th class="py-2 px-3">備註說明</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-50">
                                                            <tr v-for="day in u.daily_records" :key="day.id" class="hover:bg-gray-50/50">
                                                                <td class="py-2 px-3 font-mono font-medium text-gray-700">{{ day.date }}</td>
                                                                <td class="py-2 px-3 font-mono text-gray-600">{{ day.clock_in }}</td>
                                                                <td class="py-2 px-3 font-mono text-gray-600">{{ day.clock_out }}</td>
                                                                <td class="py-2 px-3 text-center font-bold text-gray-800">{{ day.work_hours }}h</td>
                                                                <td class="py-2 px-3 text-center">
                                                                    <span :class="['px-2 py-0.5 rounded text-[10px]', getStatusBadge(day.status).class]">
                                                                        {{ getStatusBadge(day.status).text }}
                                                                    </span>
                                                                </td>
                                                                <td class="py-2 px-3 text-gray-400 truncate max-w-xs">{{ day.note || '-' }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div v-else class="text-center py-4 text-gray-400 text-xs">
                                                    該員工本月份尚無任何打卡紀錄
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <tr v-if="userReports.length === 0">
                                    <td colspan="7" class="text-center py-12 text-gray-400">
                                        查無符合條件的員工考勤數據
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
