<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    events: Array,
    departments: Array,
    filters: Object,
});

// 當前檢視的年月份 (YYYY-MM)
const currentMonth = ref(props.filters.month || new Date().toISOString().slice(0, 7));
const selectedType = ref(props.filters.type || 'all');
const selectedDept = ref(props.filters.department_id || '');

// 選中的日期或事件詳情 Modal
const selectedEvent = ref(null);
const selectedDateEvents = ref(null);
const selectedDateStr = ref('');

// 重新整理並向後端請求資料
const updateView = (newMonth = currentMonth.value) => {
    currentMonth.value = newMonth;
    router.get(
        route('calendar.index'),
        {
            month: currentMonth.value,
            type: selectedType.value,
            department_id: selectedDept.value || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

// 切換月份
const prevMonth = () => {
    const d = new Date(currentMonth.value + '-01');
    d.setMonth(d.getMonth() - 1);
    updateView(d.toISOString().slice(0, 7));
};

const nextMonth = () => {
    const d = new Date(currentMonth.value + '-01');
    d.setMonth(d.getMonth() + 1);
    updateView(d.toISOString().slice(0, 7));
};

const goToday = () => {
    const todayStr = new Date().toISOString().slice(0, 7);
    updateView(todayStr);
};

// 格式化當前月份標題 (例如：2026 年 10 月)
const monthTitle = computed(() => {
    const [year, month] = currentMonth.value.split('-');
    return `${year} 年 ${parseInt(month, 10)} 月`;
});

// 計算月曆網格日期 (42 天標準月曆網格)
const calendarDays = computed(() => {
    const [year, month] = currentMonth.value.split('-').map(Number);
    const firstDayOfMonth = new Date(year, month - 1, 1);
    const lastDayOfMonth = new Date(year, month, 0);

    const startDayOfWeek = firstDayOfMonth.getDay(); // 0 是週日
    const totalDaysInMonth = lastDayOfMonth.getDate();

    const days = [];
    const todayStr = new Date().toISOString().slice(0, 10);

    // 1. 上個月補足的前置天數
    const prevMonthLastDay = new Date(year, month - 1, 0).getDate();
    for (let i = startDayOfWeek - 1; i >= 0; i--) {
        const d = prevMonthLastDay - i;
        const dateObj = new Date(year, month - 2, d);
        const dateStr = dateObj.toISOString().slice(0, 10);
        days.push({
            dateStr,
            dayNumber: d,
            isCurrentMonth: false,
            isToday: dateStr === todayStr,
            isWeekend: dateObj.getDay() === 0 || dateObj.getDay() === 6,
        });
    }

    // 2. 當月天數
    for (let d = 1; d <= totalDaysInMonth; d++) {
        const dateObj = new Date(year, month - 1, d);
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        days.push({
            dateStr,
            dayNumber: d,
            isCurrentMonth: true,
            isToday: dateStr === todayStr,
            isWeekend: dateObj.getDay() === 0 || dateObj.getDay() === 6,
        });
    }

    // 3. 次月補足的後置天數 (補滿 35 或 42 格)
    const remaining = (7 - (days.length % 7)) % 7;
    for (let d = 1; d <= remaining; d++) {
        const dateObj = new Date(year, month, d);
        const dateStr = dateObj.toISOString().slice(0, 10);
        days.push({
            dateStr,
            dayNumber: d,
            isCurrentMonth: false,
            isToday: dateStr === todayStr,
            isWeekend: dateObj.getDay() === 0 || dateObj.getDay() === 6,
        });
    }

    return days;
});

// 將事件按日期歸類
const eventsByDate = computed(() => {
    const map = {};
    if (!props.events) return map;

    for (const ev of props.events) {
        const dateKey = ev.date;
        if (!map[dateKey]) {
            map[dateKey] = [];
        }
        map[dateKey].push(ev);

        // 如果是跨天的請假事件，在區間內每日皆顯示
        if (ev.type === 'leave' && ev.end_date && ev.end_date !== ev.date) {
            let cur = new Date(ev.date);
            const end = new Date(ev.end_date);
            cur.setDate(cur.getDate() + 1);
            while (cur <= end) {
                const k = cur.toISOString().slice(0, 10);
                if (!map[k]) {
                    map[k] = [];
                }
                // 避免重複推入
                if (!map[k].some(x => x.id === ev.id)) {
                    map[k].push(ev);
                }
                cur.setDate(cur.getDate() + 1);
            }
        }
    }
    return map;
});

// 格式化時間 (HH:mm)
const formatTime = (isoString) => {
    if (!isoString) return '';
    return new Date(isoString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

// 取得事件對應樣式
const getEventBadgeClass = (type) => {
    switch (type) {
        case 'meeting':
            return 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100';
        case 'leave':
            return 'bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100';
        case 'announcement':
            return 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100';
        default:
            return 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100';
    }
};

// 點擊事件卡片開啟詳情
const openEventModal = (ev) => {
    selectedEvent.value = ev;
};

// 點擊「+N 更多」開啟當日所有事件列表
const openDateEventsModal = (dateStr, evList) => {
    selectedDateStr.value = dateStr;
    selectedDateEvents.value = evList;
};
</script>

<template>
    <Head title="全景綜合行事曆" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        全景綜合行事曆 (Calendar Hub)
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        統一整合會議室借用排程、同仁差勤休假與公司重大公告日程
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="route('calendar.export-ics', { month: currentMonth, type: selectedType, department_id: selectedDept || undefined })"
                        class="px-3.5 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg shadow-sm hover:bg-emerald-100 transition inline-flex items-center gap-1.5"
                        title="匯出 RFC 5545 標準 iCalendar 行事曆檔案，可直接匯入 Google 日曆、Apple Calendar 與 Outlook"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>匯出 .ics</span>
                    </a>
                    <Link
                        :href="route('meeting-rooms.index')"
                        class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition"
                    >
                        預約會議室
                    </Link>
                    <Link
                        :href="route('forms.index')"
                        class="px-3.5 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 transition"
                    >
                        申請假單差勤
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 工具列與篩選列 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-5 flex flex-col md:flex-row items-center justify-between gap-4">
                    <!-- 年月份導覽切換 -->
                    <div class="flex items-center space-x-2">
                        <button
                            @click="prevMonth"
                            class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            title="上個月"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 min-w-[140px] text-center font-mono">
                            {{ monthTitle }}
                        </h3>
                        <button
                            @click="nextMonth"
                            class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition"
                            title="下個月"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                        <button
                            @click="goToday"
                            class="ml-2 px-3 py-1.5 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                        >
                            今天
                        </button>
                    </div>

                    <!-- 事件類型切換與部門篩選 -->
                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto justify-end">
                        <!-- 類型切換 Radio/Buttons -->
                        <div class="inline-flex bg-gray-100 p-1 rounded-lg text-xs font-medium">
                            <button
                                @click="selectedType = 'all'; updateView()"
                                :class="selectedType === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                class="px-3 py-1.5 rounded-md transition"
                            >
                                全部
                            </button>
                            <button
                                @click="selectedType = 'meeting'; updateView()"
                                :class="selectedType === 'meeting' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600 hover:text-blue-700'"
                                class="px-3 py-1.5 rounded-md transition flex items-center space-x-1"
                            >
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span>會議</span>
                            </button>
                            <button
                                @click="selectedType = 'leave'; updateView()"
                                :class="selectedType === 'leave' ? 'bg-white text-purple-700 shadow-sm' : 'text-gray-600 hover:text-purple-700'"
                                class="px-3 py-1.5 rounded-md transition flex items-center space-x-1"
                            >
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                <span>休假/差勤</span>
                            </button>
                            <button
                                @click="selectedType = 'announcement'; updateView()"
                                :class="selectedType === 'announcement' ? 'bg-white text-amber-700 shadow-sm' : 'text-gray-600 hover:text-amber-700'"
                                class="px-3 py-1.5 rounded-md transition flex items-center space-x-1"
                            >
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span>公告</span>
                            </button>
                        </div>

                        <!-- 部門篩選 -->
                        <select
                            v-model="selectedDept"
                            @change="updateView()"
                            class="text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                        >
                            <option value="">全體部門</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- 核心日曆網格 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- 星期表頭 -->
                    <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50/80 text-center text-xs font-bold text-gray-600 py-3">
                        <span class="text-rose-600">週日</span>
                        <span>週一</span>
                        <span>週二</span>
                        <span>週三</span>
                        <span>週四</span>
                        <span>週五</span>
                        <span class="text-rose-600">週六</span>
                    </div>

                    <!-- 日期網格 -->
                    <div class="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-gray-100 bg-gray-100/50">
                        <div
                            v-for="day in calendarDays"
                            :key="day.dateStr"
                            :class="[
                                'min-h-[120px] sm:min-h-[140px] p-2 flex flex-col justify-between transition group',
                                day.isCurrentMonth ? 'bg-white' : 'bg-gray-50/50 text-gray-400',
                                day.isWeekend && day.isCurrentMonth ? 'bg-slate-50/40' : '',
                            ]"
                        >
                            <!-- 日期數字列 -->
                            <div class="flex items-center justify-between mb-1.5">
                                <span
                                    :class="[
                                        'text-xs font-mono font-bold inline-flex items-center justify-center w-6 h-6 rounded-full',
                                        day.isToday ? 'bg-indigo-600 text-white shadow-sm' : (day.isCurrentMonth ? 'text-gray-800' : 'text-gray-400'),
                                    ]"
                                >
                                    {{ day.dayNumber }}
                                </span>
                                <span
                                    v-if="eventsByDate[day.dateStr]?.length > 0"
                                    class="text-[10px] text-gray-400 font-mono"
                                >
                                    {{ eventsByDate[day.dateStr].length }} 則
                                </span>
                            </div>

                            <!-- 當日事件清單列表 -->
                            <div class="space-y-1 flex-1 overflow-hidden">
                                <div
                                    v-for="(ev, idx) in (eventsByDate[day.dateStr] || []).slice(0, 3)"
                                    :key="ev.id + '_' + idx"
                                    @click="openEventModal(ev)"
                                    :class="[
                                        'px-1.5 py-1 text-[11px] rounded border truncate cursor-pointer transition select-none flex items-center space-x-1 font-medium',
                                        getEventBadgeClass(ev.type),
                                    ]"
                                    :title="ev.title"
                                >
                                    <span v-if="ev.type === 'meeting'" class="font-mono text-[10px] shrink-0 opacity-75">
                                        {{ formatTime(ev.start) }}
                                    </span>
                                    <span class="truncate">{{ ev.title }}</span>
                                </div>

                                <!-- 超出 3 筆顯示更多按鈕 -->
                                <button
                                    v-if="eventsByDate[day.dateStr]?.length > 3"
                                    @click="openDateEventsModal(day.dateStr, eventsByDate[day.dateStr])"
                                    class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 hover:underline block w-full text-left pt-0.5"
                                >
                                    +{{ eventsByDate[day.dateStr].length - 3 }} 則更多...
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 單一事件詳情彈窗 Modal -->
        <div
            v-if="selectedEvent"
            class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center space-x-2">
                        <span
                            :class="[
                                'px-2 py-0.5 text-xs font-bold rounded-full',
                                selectedEvent.type === 'meeting' ? 'bg-blue-100 text-blue-700' :
                                selectedEvent.type === 'leave' ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700'
                            ]"
                        >
                            {{ selectedEvent.type === 'meeting' ? '會議預約' : (selectedEvent.type === 'leave' ? '同仁請假/差勤' : '公司公告') }}
                        </span>
                        <h3 class="text-base font-bold text-gray-900 truncate max-w-[220px]">
                            {{ selectedEvent.title }}
                        </h3>
                    </div>
                    <button
                        @click="selectedEvent = null"
                        class="text-gray-400 hover:text-gray-600 text-xl font-bold"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-3 text-xs text-gray-600">
                    <!-- 會議詳情 -->
                    <template v-if="selectedEvent.type === 'meeting'">
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">會議室與地點</span>
                            <span class="font-semibold text-gray-800">{{ selectedEvent.location }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">借用同仁</span>
                            <span class="font-semibold text-gray-800">{{ selectedEvent.user_name }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">預計參與人數</span>
                            <span class="font-semibold text-gray-800">{{ selectedEvent.attendees_count }} 人</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">會議時間</span>
                            <span class="font-mono font-semibold text-indigo-600">
                                {{ selectedEvent.date }} {{ formatTime(selectedEvent.start) }} ~ {{ formatTime(selectedEvent.end) }}
                            </span>
                        </div>
                        <div v-if="selectedEvent.details" class="pt-2">
                            <span class="text-gray-400 block mb-1">會議說明 / 備註</span>
                            <p class="p-2.5 bg-gray-50 rounded-lg text-gray-700">{{ selectedEvent.details }}</p>
                        </div>
                    </template>

                    <!-- 請假差勤詳情 -->
                    <template v-if="selectedEvent.type === 'leave'">
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">同仁姓名</span>
                            <span class="font-semibold text-gray-800">{{ selectedEvent.user_name }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">所屬部門</span>
                            <span class="font-semibold text-gray-800">{{ selectedEvent.department_name }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">差勤類別</span>
                            <span class="font-semibold text-purple-700">{{ selectedEvent.leave_type }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">期間與天數</span>
                            <span class="font-mono font-semibold text-gray-800">
                                {{ selectedEvent.date }} ~ {{ selectedEvent.end_date }} (共 {{ selectedEvent.days }} 天)
                            </span>
                        </div>
                        <div v-if="selectedEvent.reason" class="pt-2">
                            <span class="text-gray-400 block mb-1">事由備註</span>
                            <p class="p-2.5 bg-gray-50 rounded-lg text-gray-700">{{ selectedEvent.reason }}</p>
                        </div>
                    </template>

                    <!-- 公告詳情 -->
                    <template v-if="selectedEvent.type === 'announcement'">
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">公告類型</span>
                            <span class="font-semibold text-gray-800">{{ selectedEvent.category || '一般公告' }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span class="text-gray-400">發布日期</span>
                            <span class="font-mono font-semibold text-gray-800">{{ selectedEvent.date }}</span>
                        </div>
                        <div class="pt-3 text-center">
                            <Link
                                :href="route('announcements.show', selectedEvent.raw_id)"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition"
                            >
                                閱讀公告完整內容 &rarr;
                            </Link>
                        </div>
                    </template>
                </div>

                <div class="pt-3 border-t flex items-center justify-between">
                    <div>
                        <a
                            v-if="selectedEvent.type === 'meeting'"
                            :href="route('meeting-rooms.bookings.export-ics', selectedEvent.raw_id)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition"
                        >
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>匯出會議 .ics</span>
                        </a>
                    </div>
                    <button
                        @click="selectedEvent = null"
                        class="px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"
                    >
                        關閉
                    </button>
                </div>
            </div>
        </div>

        <!-- 當日全體事件列表彈窗 Modal -->
        <div
            v-if="selectedDateEvents"
            class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 font-mono">
                            {{ selectedDateStr }} 當日事件明細
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">累計 {{ selectedDateEvents.length }} 則行程排程</p>
                    </div>
                    <button
                        @click="selectedDateEvents = null"
                        class="text-gray-400 hover:text-gray-600 text-xl font-bold"
                    >
                        &times;
                    </button>
                </div>

                <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto space-y-2 pr-1">
                    <div
                        v-for="ev in selectedDateEvents"
                        :key="ev.id"
                        @click="openEventModal(ev); selectedDateEvents = null"
                        class="p-3 rounded-lg border hover:bg-gray-50 transition cursor-pointer flex items-center justify-between"
                        :class="getEventBadgeClass(ev.type)"
                    >
                        <div class="space-y-0.5">
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold">{{ ev.title }}</span>
                            </div>
                            <p class="text-[11px] opacity-75">
                                {{ ev.user_name }} · {{ ev.location || ev.department_name || ev.category }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span v-if="ev.type === 'meeting'" class="text-xs font-mono font-bold">
                                {{ formatTime(ev.start) }}
                            </span>
                            <span class="text-xs text-gray-400 block">&rarr;</span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t flex justify-end">
                    <button
                        @click="selectedDateEvents = null"
                        class="px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition"
                    >
                        關閉
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
