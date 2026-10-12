<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    reservations: Object,
    metrics: Object,
    filters: Object,
    canManageAll: Boolean,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const visit_date = ref(props.filters.visit_date || '');
const scope = ref(props.filters.scope || 'mine');

// 前台快速簽到 Modal
const showCheckInModal = ref(false);
const activeReservation = ref(null);
const badgeNumber = ref('');
const isSubmittingCheckIn = ref(false);

const applyFilters = () => {
    router.get(route('visitor-reservations.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        visit_date: visit_date.value || undefined,
        scope: scope.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    search.value = '';
    status.value = '';
    visit_date.value = '';
    scope.value = props.canManageAll ? 'all' : 'mine';
    applyFilters();
};

const openCheckInModal = (reservation) => {
    activeReservation.value = reservation;
    badgeNumber.value = reservation.badge_number || '';
    showCheckInModal.value = true;
};

const submitCheckIn = () => {
    if (!activeReservation.value) return;
    isSubmittingCheckIn.value = true;
    router.post(route('visitor-reservations.check-in', activeReservation.value.id), {
        badge_number: badgeNumber.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showCheckInModal.value = false;
            activeReservation.value = null;
            badgeNumber.value = '';
        },
        onFinish: () => {
            isSubmittingCheckIn.value = false;
        },
    });
};

const handleCheckOut = (reservation) => {
    if (confirm(`確定要為貴賓「${reservation.visitor_name}」辦理離廠簽退手續嗎？`)) {
        router.post(route('visitor-reservations.check-out', reservation.id), {}, {
            preserveScroll: true,
        });
    }
};

const handleExport = () => {
    window.location.href = route('visitor-reservations.export', {
        start_date: visit_date.value || undefined,
    });
};
</script>

<template>
    <Head title="企業訪客預約與門禁貴賓接待" />

    <AuthenticatedLayout>
        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- 標題與主動作區 -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                            </svg>
                        </span>
                        <span>企業訪客預約與門禁貴賓接待</span>
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        發行專屬訪客通行卡代碼、前台迎賓簽到登記、即時接待推播與 ISO 27001 實體安全日誌
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="handleExport"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-xs transition"
                    >
                        <svg class="w-4 h-4 mr-1.5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        匯出出入稽核日誌
                    </button>
                    <Link
                        :href="route('visitor-reservations.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        預約新訪客
                    </Link>
                </div>
            </div>

            <!-- 今日數據指標小卡 -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">今日預約總人數</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-2">{{ metrics.total_today }} <span class="text-xs font-normal text-gray-400">位</span></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-blue-600 dark:text-blue-400">待抵達報到</p>
                    <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-2">{{ metrics.expected_today }} <span class="text-xs font-normal text-gray-400">位</span></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">已簽到會談中</p>
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-2">{{ metrics.checked_in_today }} <span class="text-xs font-normal text-gray-400">位</span></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">今日已簽退離廠</p>
                    <p class="text-2xl font-black text-gray-700 dark:text-gray-300 mt-2">{{ metrics.checked_out_today }} <span class="text-xs font-normal text-gray-400">位</span></p>
                </div>
            </div>

            <!-- 多維過濾欄 -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                    <!-- Scope 切換 -->
                    <div class="inline-flex rounded-xl bg-gray-100 dark:bg-gray-700 p-1">
                        <button
                            type="button"
                            @click="scope = 'mine'; applyFilters()"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                                scope === 'mine' ? 'bg-white dark:bg-gray-800 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                            ]"
                        >
                            我接待的
                        </button>
                        <button
                            type="button"
                            @click="scope = 'all'; applyFilters()"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                                scope === 'all' ? 'bg-white dark:bg-gray-800 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                            ]"
                        >
                            全廠預約大廳
                        </button>
                    </div>

                    <!-- 狀態選單 -->
                    <select
                        v-model="status"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-200 py-1.5 px-3 focus:ring-sky-500"
                    >
                        <option value="">所有狀態</option>
                        <option value="expected">預約來訪中</option>
                        <option value="checked_in">已簽到會談中</option>
                        <option value="checked_out">已離廠簽退</option>
                        <option value="cancelled">已取消預約</option>
                    </select>

                    <!-- 日期篩選 -->
                    <input
                        type="date"
                        v-model="visit_date"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-200 py-1.5 px-3 focus:ring-sky-500"
                    />
                </div>

                <!-- 關鍵字搜尋 -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <div class="relative w-full md:w-64">
                        <input
                            type="text"
                            v-model="search"
                            @keydown.enter="applyFilters"
                            placeholder="搜尋姓名、公司、通行代碼、卡號..."
                            class="w-full pl-9 pr-3 py-1.5 rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-200 focus:ring-sky-500"
                        />
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button
                        @click="applyFilters"
                        class="px-3 py-1.5 bg-gray-900 dark:bg-gray-700 hover:bg-gray-800 text-white text-xs font-bold rounded-xl transition"
                    >
                        查詢
                    </button>
                    <button
                        v-if="search || status || visit_date"
                        @click="resetFilters"
                        class="px-2.5 py-1.5 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                    >
                        重設
                    </button>
                </div>
            </div>

            <!-- 訪客資料總表 -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 font-semibold dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3">通行碼</th>
                                <th class="px-5 py-3">貴賓資訊</th>
                                <th class="px-5 py-3">來訪時間</th>
                                <th class="px-5 py-3">事由 / 會議室</th>
                                <th class="px-5 py-3">接待員工</th>
                                <th class="px-5 py-3">狀態</th>
                                <th class="px-5 py-3 text-right">前台操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-300">
                            <tr
                                v-for="reservation in reservations.data"
                                :key="reservation.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-700/40 transition"
                            >
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded-md">
                                        {{ reservation.reservation_code }}
                                    </span>
                                    <div v-if="reservation.badge_number" class="text-2xs text-gray-400 mt-1">
                                        卡號: {{ reservation.badge_number }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-gray-900 dark:text-gray-100 text-sm">
                                        {{ reservation.visitor_name }}
                                        <span v-if="reservation.visitor_count > 1" class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                            (共 {{ reservation.visitor_count }} 人)
                                        </span>
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400 text-xs">
                                        {{ reservation.visitor_company || '個人訪客' }}
                                        <span v-if="reservation.visitor_phone">· {{ reservation.visitor_phone }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">
                                        {{ new Date(reservation.visit_date).toLocaleDateString() }}
                                    </div>
                                    <div class="text-gray-400 text-2xs">
                                        {{ reservation.expected_arrival_time ? `預計 ${reservation.expected_arrival_time}` : '全日預約' }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ reservation.purpose_label }}
                                    </div>
                                    <div class="text-gray-400 text-2xs">
                                        {{ reservation.meeting_room?.name || '無指定會議室' }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ reservation.host?.name }}
                                    </div>
                                    <div class="text-gray-400 text-2xs">
                                        {{ reservation.host?.department?.name || reservation.host?.department || '總部' }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'px-2.5 py-1 rounded-full text-xs font-bold inline-block',
                                            reservation.status === 'expected' ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300' :
                                            reservation.status === 'checked_in' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' :
                                            reservation.status === 'checked_out' ? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' :
                                            'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'
                                        ]"
                                    >
                                        {{ reservation.status_label }}
                                    </span>
                                    <div v-if="reservation.actual_check_in_at" class="text-2xs text-gray-400 mt-1">
                                        簽到: {{ new Date(reservation.actual_check_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-right space-x-2">
                                    <!-- 前台簽到 -->
                                    <button
                                        v-if="reservation.status === 'expected'"
                                        @click="openCheckInModal(reservation)"
                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition"
                                    >
                                        簽到
                                    </button>
                                    <!-- 離廠簽退 -->
                                    <button
                                        v-if="reservation.status === 'checked_in'"
                                        @click="handleCheckOut(reservation)"
                                        class="px-2.5 py-1 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-xs font-bold shadow-xs transition"
                                    >
                                        簽退
                                    </button>
                                    <!-- 檢視數位通行卡 -->
                                    <Link
                                        :href="route('visitor-reservations.show', reservation.id)"
                                        class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-bold transition"
                                    >
                                        詳情
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="reservations.data.length === 0">
                                <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                                    <div class="inline-flex p-3 bg-gray-100 dark:bg-gray-700 rounded-full mb-2">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">查無任何訪客預約紀錄</p>
                                    <p class="text-xs text-gray-400 mt-1">點擊上方「預約新訪客」即可發行通行碼</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 分頁按鈕 -->
                <div v-if="reservations.links && reservations.links.length > 3" class="px-5 py-4 border-t border-gray-100 dark:border-gray-700 flex justify-center gap-1">
                    <template v-for="(link, i) in reservations.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                                link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'
                            ]"
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- 前台簽到 Check-in Modal -->
        <div v-if="showCheckInModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="p-1.5 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        貴賓前台簽到登記
                    </h3>
                    <button @click="showCheckInModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="mt-4 space-y-4">
                    <div class="bg-sky-50 dark:bg-sky-950/40 p-3.5 rounded-xl border border-sky-100 dark:border-sky-900/60 text-xs">
                        <p class="font-bold text-sky-900 dark:text-sky-200">
                            {{ activeReservation?.visitor_name }}
                            <span v-if="activeReservation?.visitor_company">({{ activeReservation?.visitor_company }})</span>
                        </p>
                        <p class="text-sky-700 dark:text-sky-400 mt-1">
                            通行碼：{{ activeReservation?.reservation_code }} · 接待員工：{{ activeReservation?.host?.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            發放門禁感應卡 / 訪客識別證號 (可選)
                        </label>
                        <input
                            type="text"
                            v-model="badgeNumber"
                            placeholder="例如：CARD-08 或 訪客證 12 號"
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-emerald-500"
                        />
                        <p class="text-2xs text-gray-400 mt-1">完成簽到後，系統將自動推播即時通知至接待同仁桌面！</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        @click="showCheckInModal = false"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                    >
                        取消
                    </button>
                    <button
                        @click="submitCheckIn"
                        :disabled="isSubmittingCheckIn"
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition"
                    >
                        確認簽到並通知接待人
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
