<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { alertDialog, confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    reservation: Object,
    canManage: Boolean,
});

const isCheckInOpen = ref(false);
const badgeNumber = ref(props.reservation.badge_number || '');
const isSubmitting = ref(false);

const copyCode = () => {
    navigator.clipboard.writeText(props.reservation.reservation_code);
    alertDialog(`通行代碼「${props.reservation.reservation_code}」已複製至剪貼簿！`);
};

const handleCheckIn = () => {
    isSubmitting.value = true;
    router.post(route('visitor-reservations.check-in', props.reservation.id), {
        badge_number: badgeNumber.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isCheckInOpen.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const handleCheckOut = async () => {
    if (await confirmDialog('確定辦理貴賓離廠簽退手續嗎？')) {
        router.post(route('visitor-reservations.check-out', props.reservation.id), {}, {
            preserveScroll: true,
        });
    }
};

const handleCancel = async () => {
    if (await confirmDialog('確定要取消此筆訪客預約嗎？')) {
        router.post(route('visitor-reservations.cancel', props.reservation.id), {}, {
            preserveScroll: true,
        });
    }
};

const printPass = () => {
    window.print();
};
</script>

<template>
    <Head :title="`訪客通行卡 - ${reservation.visitor_name}`" />

    <AuthenticatedLayout>
        <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- 頂部操作與麵包屑 (列印時隱藏) -->
            <div class="print:hidden flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                    <Link :href="route('visitor-reservations.index')" class="hover:underline">訪客接待大廳</Link>
                    <span>/</span>
                    <span class="text-gray-800 dark:text-gray-200 font-semibold">{{ reservation.reservation_code }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="printPass"
                        class="px-3.5 py-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-xs transition inline-flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        列印通行識別卡
                    </button>
                    <button
                        v-if="reservation.status === 'expected'"
                        @click="isCheckInOpen = true"
                        class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition"
                    >
                        前台簽到 (Check-in)
                    </button>
                    <button
                        v-if="reservation.status === 'checked_in'"
                        @click="handleCheckOut"
                        class="px-4 py-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded-xl text-xs font-bold shadow-md transition"
                    >
                        離廠簽退 (Check-out)
                    </button>
                    <button
                        v-if="canManage && reservation.status === 'expected'"
                        @click="handleCancel"
                        class="px-3 py-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-xs font-bold transition"
                    >
                        取消預約
                    </button>
                </div>
            </div>

            <!-- 數位訪客通行卡本體 (支援實體列印) -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-xl overflow-hidden print:border-2 print:border-black print:shadow-none">
                <!-- 卡片頂部漸層橫幅 -->
                <div class="bg-gradient-to-r from-sky-600 via-indigo-600 to-blue-700 p-6 sm:p-8 text-white relative">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-white/20 backdrop-blur-xs dark:bg-gray-800">
                                VIP VISITOR PASS · 數位貴賓通行證
                            </span>
                            <h2 class="text-2xl sm:text-3xl font-black mt-2 tracking-tight">
                                {{ reservation.visitor_name }}
                                <span v-if="reservation.visitor_count > 1" class="text-sm font-normal text-sky-100">
                                    (同行 {{ reservation.visitor_count }} 人)
                                </span>
                            </h2>
                            <p class="text-xs sm:text-sm text-sky-100 mt-1 font-medium">
                                {{ reservation.visitor_company || '貴賓個人預約' }}
                            </p>
                        </div>

                        <!-- 唯一通行代碼卡塊 -->
                        <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 text-center shrink-0 dark:bg-gray-800">
                            <p class="text-2xs text-sky-200 font-mono">Reservation Code</p>
                            <p class="text-xl sm:text-2xl font-mono font-black text-white mt-0.5">
                                {{ reservation.reservation_code }}
                            </p>
                            <button
                                @click="copyCode"
                                class="print:hidden mt-2 text-2xs text-sky-100 underline hover:text-white inline-flex items-center gap-1"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                複製代碼
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 訪客資訊格 -->
                <div class="p-6 sm:p-8 space-y-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pb-6 border-b border-gray-100 dark:border-gray-700 text-xs">
                        <div>
                            <p class="text-gray-400 font-medium">預約來訪日期</p>
                            <p class="font-bold text-gray-900 dark:text-gray-100 text-sm mt-1">
                                {{ new Date(reservation.visit_date).toLocaleDateString('sv-SE') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium">預估抵達時間</p>
                            <p class="font-bold text-gray-900 dark:text-gray-100 text-sm mt-1">
                                {{ reservation.expected_arrival_time || '全日預約' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium">來訪事由</p>
                            <p class="font-bold text-gray-900 dark:text-gray-100 text-sm mt-1">
                                {{ reservation.purpose_label }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium">通行狀態</p>
                            <p class="mt-1">
                                <span
                                    :class="[
                                        'px-2.5 py-0.5 rounded-full text-xs font-bold inline-block',
                                        reservation.status === 'expected' ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300' :
                                        reservation.status === 'checked_in' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' :
                                        reservation.status === 'checked_out' ? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' :
                                        'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'
                                    ]"
                                >
                                    {{ reservation.status_label }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- 接待人員與空間 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pb-6 border-b border-gray-100 dark:border-gray-700 text-xs">
                        <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-2xl">
                            <p class="text-gray-400 font-semibold mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                接待同仁 (Host)
                            </p>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                {{ reservation.host?.name }}
                            </p>
                            <p class="text-gray-500 dark:text-gray-400 mt-0.5">
                                部門：{{ reservation.host?.department?.name || reservation.host?.department || '未分配' }} · 信箱：{{ reservation.host?.email }}
                            </p>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-2xl">
                            <p class="text-gray-400 font-semibold mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                安排會議室 / 接待區域
                            </p>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                {{ reservation.meeting_room?.name || '一樓大廳訪客接待區' }}
                            </p>
                            <p class="text-gray-500 dark:text-gray-400 mt-0.5">
                                位置：{{ reservation.meeting_room?.location || '大廳 A 區' }} · 容納：{{ reservation.meeting_room?.capacity || 4 }} 人
                            </p>
                        </div>
                    </div>

                    <!-- 門禁打卡與稽核時間軸 -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300">
                            門禁進出與實體安全稽核時間軸
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                            <div class="p-3 rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
                                <p class="text-gray-400">訪客識別門禁卡號</p>
                                <p class="font-bold text-gray-900 dark:text-gray-100 mt-1 font-mono">
                                    {{ reservation.badge_number || '尚未配發感應卡' }}
                                </p>
                            </div>
                            <div class="p-3 rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
                                <p class="text-gray-400">實際簽到時間 (Check-in)</p>
                                <p class="font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                                    {{ reservation.actual_check_in_at ? new Date(reservation.actual_check_in_at).toLocaleString() : '尚未抵達' }}
                                </p>
                            </div>
                            <div class="p-3 rounded-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
                                <p class="text-gray-400">實際簽退時間 (Check-out)</p>
                                <p class="font-bold text-gray-700 dark:text-gray-300 mt-1">
                                    {{ reservation.actual_check_out_at ? new Date(reservation.actual_check_out_at).toLocaleString() : '會談中 / 尚未簽退' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 接待特別備註 -->
                    <div v-if="reservation.notes" class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 text-xs">
                        <p class="font-bold text-amber-800 dark:text-amber-300">接待特別備註：</p>
                        <p class="text-amber-700 dark:text-amber-200 mt-1 whitespace-pre-line">{{ reservation.notes }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Check-in Modal -->
        <div v-if="isCheckInOpen" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">前台訪客簽到登記</h3>
                    <button @click="isCheckInOpen = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="mt-4 space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                            配發門禁感應卡號 (可選)
                        </label>
                        <input
                            type="text"
                            v-model="badgeNumber"
                            placeholder="例：CARD-102"
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-emerald-500"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        @click="isCheckInOpen = false"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        取消
                    </button>
                    <button
                        @click="handleCheckIn"
                        :disabled="isSubmitting"
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition"
                    >
                        確認簽到並推播通知接待人
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
