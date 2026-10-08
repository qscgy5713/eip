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
    geofenceConfig: Object,
});

const currentTime = ref(new Date().toLocaleTimeString('zh-TW', { hour12: false }));
let timer = null;

// GPS 地理圍欄即時定位狀態
const isLocating = ref(false);
const locationStatus = ref('pending'); // 'locating', 'in_fence', 'out_of_fence', 'denied', 'unsupported'
const currentCoords = ref(null); // { latitude, longitude, accuracy }
const detectedDistance = ref(null);
const fieldWorkNote = ref('');
const noteError = ref('');

// 超出範圍未填事由時的彈出對話視窗
const showReasonModal = ref(false);
const pendingAction = ref(null); // 'clockIn' | 'clockOut'

// Haversine 大圓距離計算
const calcDistance = (lat1, lon1, lat2, lon2) => {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return Math.round(R * c);
};

const formatDistance = (meters) => {
    if (meters === null || meters === undefined) return '-';
    if (meters < 1000) return `${meters}m`;
    return `${(meters / 1000).toFixed(1)}km`;
};

const requestGeolocation = () => {
    if (!navigator.geolocation) {
        locationStatus.value = 'unsupported';
        return;
    }

    isLocating.value = true;
    locationStatus.value = 'locating';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            isLocating.value = false;
            currentCoords.value = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: Math.round(position.coords.accuracy),
            };

            if (props.geofenceConfig?.has_location) {
                const dist = calcDistance(
                    position.coords.latitude,
                    position.coords.longitude,
                    props.geofenceConfig.lat,
                    props.geofenceConfig.lng
                );
                detectedDistance.value = dist;
                locationStatus.value = dist <= (props.geofenceConfig.radius || 500) ? 'in_fence' : 'out_of_fence';
            } else {
                locationStatus.value = 'all_remote';
                detectedDistance.value = null;
            }
        },
        (error) => {
            isLocating.value = false;
            locationStatus.value = 'denied';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
};

onMounted(() => {
    timer = setInterval(() => {
        currentTime.value = new Date().toLocaleTimeString('zh-TW', { hour12: false });
    }, 1000);
    requestGeolocation();
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const clockInForm = useForm({
    latitude: null,
    longitude: null,
    field_work_note: '',
});

const clockOutForm = useForm({
    latitude: null,
    longitude: null,
    field_work_note: '',
});

const handleClockIn = () => {
    noteError.value = '';
    // 若超出圍欄且未填事由，主動跳出填寫對話框
    if (locationStatus.value === 'out_of_fence' && !fieldWorkNote.value.trim()) {
        pendingAction.value = 'clockIn';
        showReasonModal.value = true;
        return;
    }

    executeClockIn();
};

const executeClockIn = () => {
    clockInForm.latitude = currentCoords.value?.latitude ?? null;
    clockInForm.longitude = currentCoords.value?.longitude ?? null;
    clockInForm.field_work_note = fieldWorkNote.value.trim();
    clockInForm.post(route('attendance.clockIn'), {
        preserveScroll: true,
        onSuccess: () => {
            fieldWorkNote.value = '';
            showReasonModal.value = false;
        },
    });
};

const handleClockOut = () => {
    noteError.value = '';
    // 若超出圍欄且未填事由，主動跳出填寫對話框
    if (locationStatus.value === 'out_of_fence' && !fieldWorkNote.value.trim()) {
        pendingAction.value = 'clockOut';
        showReasonModal.value = true;
        return;
    }

    executeClockOut();
};

const executeClockOut = () => {
    clockOutForm.latitude = currentCoords.value?.latitude ?? null;
    clockOutForm.longitude = currentCoords.value?.longitude ?? null;
    clockOutForm.field_work_note = fieldWorkNote.value.trim();
    clockOutForm.post(route('attendance.clockOut'), {
        preserveScroll: true,
        onSuccess: () => {
            fieldWorkNote.value = '';
            showReasonModal.value = false;
        },
    });
};

const confirmModalSubmit = () => {
    if (!fieldWorkNote.value.trim()) {
        noteError.value = '超出公司打卡範圍，必須填入外勤/遠端事由方可完成打卡！';
        return;
    }

    if (pendingAction.value === 'clockIn') {
        executeClockIn();
    } else if (pendingAction.value === 'clockOut') {
        executeClockOut();
    }
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

const typeBadge = (type) => {
    switch (type) {
        case 'office': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'remote': return 'bg-purple-50 text-purple-700 border-purple-200';
        default: return 'bg-gray-50 text-gray-600 border-gray-200';
    }
};

const typeLabel = (type) => {
    switch (type) {
        case 'office': return '辦公室內勤';
        case 'remote': return '外勤/遠端';
        default: return 'IP網段';
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
                    <span class="text-sm text-gray-500">智慧 GPS 圍欄打卡、工時記錄與出勤異常統計</span>
                </div>
                <div class="flex items-center space-x-2">
                    <Link
                        v-if="['hr', 'admin'].includes($page.props.auth.user.role)"
                        :href="route('attendance.settings')"
                        class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>圍欄與半徑設定</span>
                    </Link>
                    <Link
                        v-if="['hr', 'admin', 'manager'].includes($page.props.auth.user.role)"
                        :href="route('attendance.reports.index')"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <span>考勤月報統計</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 今日即時打卡卡片 -->
                <div class="bg-gradient-to-r from-blue-700 to-indigo-800 rounded-2xl shadow-lg p-6 sm:p-8 text-white space-y-5">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
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
                                        'px-6 py-3 rounded-xl font-bold text-sm shadow transition flex items-center space-x-1.5',
                                        !todayAttendance?.clock_in_at ? 'bg-emerald-500 hover:bg-emerald-600 text-white shadow-emerald-900/20' : 'bg-gray-400/40 text-gray-300 cursor-not-allowed'
                                    ]"
                                >
                                    <svg v-if="clockInForm.processing" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>{{ todayAttendance?.clock_in_at ? '已上班打卡' : '上班簽到' }}</span>
                                </button>

                                <button
                                    @click="handleClockOut"
                                    :disabled="!todayAttendance?.clock_in_at || clockOutForm.processing"
                                    :class="[
                                        'px-6 py-3 rounded-xl font-bold text-sm shadow transition flex items-center space-x-1.5',
                                        todayAttendance?.clock_in_at ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-amber-900/20' : 'bg-gray-400/40 text-gray-300 cursor-not-allowed'
                                    ]"
                                >
                                    <svg v-if="clockOutForm.processing" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>{{ todayAttendance?.clock_out_at ? '更新下班簽退' : '下班簽退' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- GPS 地理圍欄即時狀態提示條 -->
                    <div class="bg-black/20 rounded-xl p-3 text-xs flex flex-col sm:flex-row items-center justify-between gap-3 border border-white/10">
                        <div class="flex items-center space-x-2">
                            <span v-if="isLocating" class="flex items-center space-x-1 text-blue-200">
                                <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>正在獲取 GPS 經緯度定位...</span>
                            </span>
                            <span v-else-if="locationStatus === 'in_fence'" class="flex items-center space-x-1.5 text-emerald-300 font-semibold">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>已定位：位於【{{ geofenceConfig?.name }}】圍欄內（距中心 {{ formatDistance(detectedDistance) }}，半徑 {{ geofenceConfig?.radius }}m）— 辦公室內勤模式</span>
                            </span>
                            <span v-else-if="locationStatus === 'out_of_fence'" class="flex items-center space-x-1.5 text-purple-200 font-semibold">
                                <svg class="w-4 h-4 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>已定位：位於公司圍欄外（距總部 {{ formatDistance(detectedDistance) }}）— 外勤/遠端模式 (需填寫事由)</span>
                            </span>
                            <span v-else-if="locationStatus === 'all_remote'" class="flex items-center space-x-1.5 text-blue-200 font-semibold">
                                <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                                <span>已定位：公司採【全遠端/自由地點】模式（未設固定打卡圍欄）— 遠端打卡模式</span>
                            </span>
                            <span v-else class="text-gray-300">
                                尚未取得 GPS 定位（將以辦公室 IP 網段打卡）
                            </span>
                        </div>

                        <div class="flex items-center space-x-3 shrink-0">
                            <button
                                @click="requestGeolocation"
                                type="button"
                                class="px-2.5 py-1 rounded bg-white/20 hover:bg-white/30 text-white transition text-[11px] flex items-center space-x-1"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>重新定位</span>
                            </button>
                            <span class="text-blue-200 text-[11px] font-mono">
                                總部基準：{{ geofenceConfig?.has_location ? `${geofenceConfig?.lat}, ${geofenceConfig?.lng}` : '全遠端 (未設座標)' }}
                            </span>
                        </div>
                    </div>

                    <!-- 外勤事由輸入 (僅外勤打卡時顯示，強制必填) -->
                    <div v-if="locationStatus === 'out_of_fence'" class="bg-purple-900/40 border border-purple-400/40 rounded-xl p-3.5 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-purple-200">
                                外勤 / 出差遠端事由說明 <span class="text-rose-300 font-bold">(超出範圍必填 *)</span>
                            </label>
                            <span class="text-[11px] text-purple-200/80">未填寫事由將無法打卡</span>
                        </div>
                        <input
                            type="text"
                            v-model="fieldWorkNote"
                            @input="noteError = ''"
                            placeholder="如：拜訪客戶展示產品、外部會議、居家遠端辦公..."
                            class="w-full text-xs rounded-lg bg-white/10 text-white placeholder-purple-200/60 border-purple-300/40 focus:border-rose-400 focus:ring-rose-400"
                        />
                        <p v-if="noteError" class="text-xs text-rose-300 font-semibold flex items-center pt-0.5">
                            <svg class="w-3.5 h-3.5 mr-1 text-rose-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ noteError }}
                        </p>
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
                                <div class="flex items-center space-x-1.5">
                                    <p class="text-sm font-bold text-gray-900">{{ member.name }}</p>
                                    <span v-if="member.clock_in_at" :class="['px-1.5 py-0.2 text-[9px] font-bold rounded border', typeBadge(member.clock_in_type)]">
                                        {{ typeLabel(member.clock_in_type) }}
                                    </span>
                                </div>
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
                                    <th class="py-3 px-4">打卡地點 / 型態</th>
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
                                    <td class="py-3 px-4">
                                        <div class="flex flex-col space-y-1">
                                            <div class="flex items-center space-x-1.5">
                                                <span :class="['px-2 py-0.5 text-[10px] font-bold rounded border', typeBadge(att.clock_in_type)]">
                                                    {{ typeLabel(att.clock_in_type) }}
                                                    <template v-if="att.clock_in_distance">({{ formatDistance(att.clock_in_distance) }})</template>
                                                </span>
                                                <a
                                                    v-if="att.clock_in_lat && att.clock_in_lng"
                                                    :href="`https://maps.google.com/?q=${att.clock_in_lat},${att.clock_in_lng}`"
                                                    target="_blank"
                                                    class="text-[10px] text-blue-600 hover:underline flex items-center"
                                                    title="在 Google Maps 查看打卡經緯度"
                                                >
                                                    📍 地圖
                                                </a>
                                            </div>
                                            <p v-if="att.field_work_note" class="text-[10px] text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded border border-purple-100">
                                                事由：{{ att.field_work_note }}
                                            </p>
                                        </div>
                                    </td>
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
                                    <td colspan="8" class="py-8 text-center text-gray-400">此月份尚無打卡紀錄</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 超出範圍強制填寫事由 Modal -->
        <div v-if="showReasonModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-gray-100 overflow-hidden transform transition-all p-6 space-y-5">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">超出打卡範圍提示</h3>
                        <p class="text-xs text-gray-500">距總部 {{ formatDistance(detectedDistance) }}（超過限制 {{ geofenceConfig?.radius }}m）</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-700">
                        請說明外勤 / 遠端事由 <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        v-model="fieldWorkNote"
                        rows="3"
                        required
                        autofocus
                        placeholder="請填寫外勤客戶拜訪、公出差旅或居家辦公事由（未填寫將無法完成打卡）..."
                        class="w-full text-xs rounded-xl border border-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm placeholder-gray-400 p-3"
                    ></textarea>
                    <p v-if="noteError" class="text-xs text-rose-600 font-semibold">{{ noteError }}</p>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-2">
                    <button
                        type="button"
                        @click="showReasonModal = false"
                        class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                    >
                        取消打卡
                    </button>
                    <button
                        type="button"
                        @click="confirmModalSubmit"
                        :disabled="clockInForm.processing || clockOutForm.processing"
                        class="px-5 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow transition disabled:opacity-50"
                    >
                        填寫完成，確認打卡
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
