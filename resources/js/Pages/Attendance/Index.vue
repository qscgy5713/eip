<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    attendances: Array,
    todayAttendance: Object,
    month: String,
    stats: Object,
    teamAttendances: Array,
});

const currentTime = ref(new Date().toLocaleTimeString('zh-TW', { hour12: false }));
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        currentTime.value = new Date().toLocaleTimeString('zh-TW', { hour12: false });
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const clockInForm = useForm({});
const clockOutForm = useForm({});

const handleClockIn = () => {
    clockInForm.post(route('attendance.clockIn'));
};

const handleClockOut = () => {
    clockOutForm.post(route('attendance.clockOut'));
};

const changeMonth = (e) => {
    router.get(route('attendance.index'), { month: e.target.value }, { preserveState: true });
};

const statusBadge = (status) => {
    switch (status) {
        case 'normal': return 'bg-emerald-100 text-emerald-800';
        case 'late': return 'bg-rose-100 text-rose-800';
        case 'early_leave': return 'bg-amber-100 text-amber-800';
        case 'absent': return 'bg-gray-100 text-gray-500';
        default: return 'bg-blue-100 text-blue-800';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'normal': return '正常出勤';
        case 'late': return '遲到';
        case 'early_leave': return '早退';
        case 'absent': return '未出勤';
        default: return '正常';
    }
};
</script>

<template>
    <Head title="考勤與打卡管理" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800">考勤打卡管理</h2>
                    <span class="text-sm text-gray-500">標準工時記錄與出勤異常統計</span>
                </div>
                <div v-if="['hr', 'admin', 'manager'].includes($page.props.auth.user.role)">
                    <Link
                        :href="route('attendance.reports.index')"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <span>考勤月報統計與工時結算</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 今日即時打卡卡片 -->
                <div class="bg-gradient-to-r from-blue-700 to-indigo-800 rounded-2xl shadow-lg p-6 sm:p-8 text-white flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="space-y-2 text-center md:text-left">
                        <p class="text-blue-200 text-xs font-semibold tracking-wider uppercase">今日工作排程 · 09:00 - 18:00</p>
                        <div class="text-4xl sm:text-5xl font-mono font-extrabold tracking-tight">{{ currentTime }}</div>
                        <p class="text-sm text-blue-100">{{ new Date().toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' }) }}</p>
                    </div>

                    <!-- 打卡動作按鈕區 -->
                    <div class="flex flex-col sm:flex-row items-center gap-4 bg-white/10 backdrop-blur-md p-4 rounded-xl border border-white/20">
                        <div class="text-center sm:text-right px-2">
                            <p class="text-xs text-blue-200">今日狀態</p>
                            <p class="text-sm font-bold text-white mt-0.5">
                                <span v-if="!todayAttendance">尚未打卡</span>
                                <span v-else-if="todayAttendance.clock_in_at && !todayAttendance.clock_out_at" class="text-amber-300">上班中 ({{ new Date(todayAttendance.clock_in_at).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' }) }} 簽到)</span>
                                <span v-else class="text-emerald-300">已結算 (工時: {{ todayAttendance.work_hours }}h)</span>
                            </p>
                        </div>

                        <div class="flex items-center space-x-3">
                            <button
                                @click="handleClockIn"
                                :disabled="!!todayAttendance?.clock_in_at || clockInForm.processing"
                                :class="[
                                    'px-6 py-3 rounded-xl font-bold text-sm shadow transition',
                                    !todayAttendance?.clock_in_at ? 'bg-emerald-500 hover:bg-emerald-600 text-white shadow-emerald-900/20' : 'bg-gray-400/40 text-gray-300 cursor-not-allowed'
                                ]"
                            >
                                {{ todayAttendance?.clock_in_at ? '已上班打卡' : '上班簽到' }}
                            </button>

                            <button
                                @click="handleClockOut"
                                :disabled="!todayAttendance?.clock_in_at || clockOutForm.processing"
                                :class="[
                                    'px-6 py-3 rounded-xl font-bold text-sm shadow transition',
                                    todayAttendance?.clock_in_at ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-amber-900/20' : 'bg-gray-400/40 text-gray-300 cursor-not-allowed'
                                ]"
                            >
                                {{ todayAttendance?.clock_out_at ? '更新下班簽退' : '下班簽退' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 本月出勤統計 -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm text-center">
                        <p class="text-xs font-semibold text-gray-400 uppercase">本月累計工時</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.totalWorkHours }} <span class="text-xs text-gray-400 font-normal">小時</span></p>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm text-center">
                        <p class="text-xs font-semibold text-gray-400 uppercase">出勤天數</p>
                        <p class="text-2xl font-bold text-blue-600 mt-1">{{ stats.daysWorked }} <span class="text-xs text-gray-400 font-normal">天</span></p>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm text-center">
                        <p class="text-xs font-semibold text-gray-400 uppercase">遲到次數</p>
                        <p class="text-2xl font-bold text-rose-600 mt-1">{{ stats.lateCount }} <span class="text-xs text-gray-400 font-normal">次</span></p>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm text-center">
                        <p class="text-xs font-semibold text-gray-400 uppercase">早退次數</p>
                        <p class="text-2xl font-bold text-amber-600 mt-1">{{ stats.earlyLeaveCount }} <span class="text-xs text-gray-400 font-normal">次</span></p>
                    </div>
                </div>

                <!-- 團隊/部門出勤監控 (主管與管理員專屬) -->
                <div v-if="teamAttendances && teamAttendances.length > 0" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h3 class="font-bold text-gray-900 text-base flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 bg-indigo-600 rounded-full"></span>
                            <span>團隊今日出勤即時概況</span>
                        </h3>
                        <span class="text-xs text-gray-400">共 {{ teamAttendances.length }} 位同仁</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                        <div v-for="member in teamAttendances" :key="member.id" class="p-3 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ member.name }}</p>
                                <p class="text-[11px] text-gray-500">{{ member.department }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ member.clock_in_at ? `${member.clock_in_at} 上班` : '尚未打卡' }}</p>
                            </div>
                            <span :class="['px-2 py-0.5 text-xs font-semibold rounded', statusBadge(member.status)]">
                                {{ statusLabel(member.status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 個人出勤歷史明細 -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h3 class="font-bold text-gray-900 text-base">個人出勤明細紀錄</h3>
                        <input type="month" :value="month" @change="changeMonth" class="text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-1.5" />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600">
                            <thead class="bg-gray-50 text-gray-700 font-semibold uppercase border-b border-gray-200">
                                <tr>
                                    <th class="py-3 px-4">日期</th>
                                    <th class="py-3 px-4">上班打卡</th>
                                    <th class="py-3 px-4">上班 IP</th>
                                    <th class="py-3 px-4">下班打卡</th>
                                    <th class="py-3 px-4">下班 IP</th>
                                    <th class="py-3 px-4">當日工時</th>
                                    <th class="py-3 px-4">狀態</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="att in attendances" :key="att.id" class="hover:bg-gray-50/60">
                                    <td class="py-3 px-4 font-mono font-medium text-gray-900">{{ att.date ? String(att.date).split('T')[0] : '-' }}</td>
                                    <td class="py-3 px-4">{{ att.clock_in_at ? new Date(att.clock_in_at).toLocaleTimeString() : '-' }}</td>
                                    <td class="py-3 px-4 text-gray-400 font-mono">{{ att.clock_in_ip || '-' }}</td>
                                    <td class="py-3 px-4">{{ att.clock_out_at ? new Date(att.clock_out_at).toLocaleTimeString() : '-' }}</td>
                                    <td class="py-3 px-4 text-gray-400 font-mono">{{ att.clock_out_ip || '-' }}</td>
                                    <td class="py-3 px-4 font-semibold text-gray-900">{{ att.work_hours }}h</td>
                                    <td class="py-3 px-4">
                                        <span :class="['px-2 py-0.5 rounded text-[11px] font-semibold', statusBadge(att.status)]">
                                            {{ statusLabel(att.status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="attendances.length === 0">
                                    <td colspan="7" class="py-8 text-center text-gray-400">此月份尚無打卡紀錄</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
