<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    dispatch: Object,
    canManage: Boolean,
});

const page = usePage();
const currentUser = page.props.auth.user;
const isApplicant = currentUser.id === props.dispatch.user_id;

// 審核駁回 Modal
const showRejectModal = ref(false);
const rejectForm = useForm({
    rejection_reason: '',
});

// 領車出發 Modal
const showStartModal = ref(false);
const startForm = useForm({
    start_mileage: props.dispatch.vehicle.current_mileage,
});

// 還車結案 Modal
const showReturnModal = ref(false);
const returnForm = useForm({
    end_mileage: props.dispatch.vehicle.current_mileage,
    return_notes: '',
});

const approveDispatch = () => {
    if (confirm('確定要核准此筆公務派車申請單嗎？')) {
        useForm({}).post(route('vehicles.approve', props.dispatch.id));
    }
};

const submitReject = () => {
    rejectForm.post(route('vehicles.reject', props.dispatch.id), {
        onSuccess: () => {
            showRejectModal.value = false;
            rejectForm.reset();
        },
    });
};

const submitStart = () => {
    startForm.post(route('vehicles.start', props.dispatch.id), {
        onSuccess: () => {
            showStartModal.value = false;
        },
    });
};

const submitReturn = () => {
    returnForm.post(route('vehicles.return', props.dispatch.id), {
        onSuccess: () => {
            showReturnModal.value = false;
        },
    });
};

const cancelDispatch = () => {
    if (confirm('確定要取消此筆公務派車申請嗎？')) {
        useForm({}).post(route('vehicles.cancel', props.dispatch.id));
    }
};

const printDispatchOrder = () => {
    window.print();
};
</script>

<template>
    <Head :title="`派車單詳情 - ${dispatch.dispatch_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('vehicles.index')"
                        class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition dark:text-gray-400"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                                公務派車單 {{ dispatch.dispatch_number }}
                            </h2>
                            <span :class="['px-2.5 py-0.5 text-xs font-semibold rounded-full', dispatch.status_color]">
                                {{ dispatch.status_label }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            申請同仁：{{ dispatch.user?.name }}（{{ dispatch.user?.department?.name || '總部' }}）· 申請於 {{ new Date(dispatch.created_at).toLocaleString() }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="printDispatchOrder"
                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition inline-flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        列印派車憑單
                    </button>
                    
                    <!-- 申請人取消按鈕 -->
                    <button
                        v-if="isApplicant && (dispatch.status === 'pending' || dispatch.status === 'approved')"
                        @click="cancelDispatch"
                        class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-sm font-medium rounded-lg transition"
                    >
                        撤銷申請
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- 駁回警示橫幅 -->
            <div v-if="dispatch.status === 'rejected'" class="p-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <h3 class="text-sm font-bold text-red-800 dark:text-red-200">此派車申請已被退回 / 駁回</h3>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">審核人員：{{ dispatch.approver?.name || '管理單位' }}</p>
                        <p class="text-sm text-red-700 dark:text-red-300 font-medium mt-0.5">退回原因：{{ dispatch.rejection_reason || '無填寫原因' }}</p>
                    </div>
                </div>
            </div>

            <!-- 主管/總務 審核與作業快捷操作列 -->
            <div v-if="canManage" class="bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-amber-500/10 dark:from-amber-900/30 dark:to-orange-900/30 border border-amber-300/60 dark:border-amber-700/60 rounded-xl p-5 print:hidden shadow-sm">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-amber-900 dark:text-amber-200 flex items-center gap-2">
                            <span>車輛調度與派車管理控制台</span>
                        </h3>
                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                            您擁有公務車輛調派審核與車匙交付、還車登錄權限。
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- 待審核狀態操作 -->
                        <template v-if="dispatch.status === 'pending'">
                            <button
                                @click="approveDispatch"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-lg shadow-sm transition inline-flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                核准派車
                            </button>
                            <button
                                @click="showRejectModal = true"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium text-sm rounded-lg shadow-sm transition inline-flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                駁回申請
                            </button>
                        </template>

                        <!-- 已核准 -> 領車出發 -->
                        <template v-if="dispatch.status === 'approved'">
                            <button
                                @click="showStartModal = true"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow-sm transition inline-flex items-center gap-1.5"
                            >
                                 交付鑰匙 · 領車出發
                            </button>
                        </template>

                        <!-- 進行中 -> 還車回報 -->
                        <template v-if="dispatch.status === 'active'">
                            <button
                                @click="showReturnModal = true"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow-sm transition inline-flex items-center gap-1.5"
                            >
                                 收回鑰匙 · 還車里程登錄
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- 兩欄主內容：左側出差與行程卡片、右側車輛規格與時間軸 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- 左側：派車出差明細 (佔 2 欄) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- 行程資訊卡 -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <span>公務出差行程明細</span>
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">事由：{{ dispatch.purpose_label }}</span>
                        </h3>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">目的地 / 行程地點</dt>
                                <dd class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ dispatch.destination }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">乘載同仁與同行人數</dt>
                                <dd class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ dispatch.passenger_count }} 人同行
                                </dd>
                            </div>

                            <div class="sm:col-span-2 bg-gray-50 dark:bg-gray-750 p-3.5 rounded-lg border border-gray-100 dark:border-gray-700">
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">預約用車時段</dt>
                                <dd class="text-sm font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-2">
                                    <span>{{ new Date(dispatch.start_time).toLocaleString() }}</span>
                                    <span>➔</span>
                                    <span>{{ new Date(dispatch.end_time).toLocaleString() }}</span>
                                </dd>
                            </div>

                            <div v-if="dispatch.return_notes" class="sm:col-span-2">
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">出差詳細事由與備註</dt>
                                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 p-3 rounded-lg border border-gray-100 dark:border-gray-750 whitespace-pre-wrap">
                                    {{ dispatch.return_notes }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- 里程與燃料稽核卡片 -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                            <span>里程行駛與車況查核</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="p-4 bg-gray-50 dark:bg-gray-750 rounded-xl border border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">出發起步里程</p>
                                <p class="text-xl font-black text-gray-900 dark:text-gray-100 mt-1 font-mono">
                                    {{ dispatch.start_mileage ? dispatch.start_mileage.toLocaleString() + ' km' : '尚未出發' }}
                                </p>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-750 rounded-xl border border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">還車歸檔里程</p>
                                <p class="text-xl font-black text-gray-900 dark:text-gray-100 mt-1 font-mono">
                                    {{ dispatch.end_mileage ? dispatch.end_mileage.toLocaleString() + ' km' : '尚未還車' }}
                                </p>
                            </div>
                            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800">
                                <p class="text-xs text-amber-700 dark:text-amber-400 font-medium">本次總行駛里程</p>
                                <p class="text-xl font-black text-amber-600 dark:text-amber-400 mt-1 font-mono">
                                    {{ dispatch.mileage_traveled !== null ? dispatch.mileage_traveled.toLocaleString() + ' km' : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右側：配屬車輛卡片與審核資訊 (佔 1 欄) -->
                <div class="space-y-6">
                    <!-- 配屬公務車輛規格 -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/40 rounded-xl flex items-center justify-center text-amber-600 dark:text-amber-400 text-2xl font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-gray-100 text-lg">
                                    {{ dispatch.vehicle.name }}
                                </h4>
                                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 font-mono font-bold text-gray-700 dark:text-gray-300 rounded text-xs">
                                    {{ dispatch.vehicle.plate_number }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2.5 text-xs text-gray-600 dark:text-gray-400 border-t border-gray-100 dark:border-gray-700 pt-3">
                            <div class="flex justify-between">
                                <span>車輛類型：</span>
                                <span class="font-medium text-gray-900 dark:text-gray-200">{{ dispatch.vehicle.vehicle_type_label }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>乘坐承載限制：</span>
                                <span class="font-medium text-gray-900 dark:text-gray-200">{{ dispatch.vehicle.capacity }} 人座</span>
                            </div>
                            <div class="flex justify-between">
                                <span>目前總里程：</span>
                                <span class="font-medium text-gray-900 dark:text-gray-200 font-mono">{{ dispatch.vehicle.current_mileage.toLocaleString() }} km</span>
                            </div>
                            <div class="flex justify-between">
                                <span>下次定期保養預估：</span>
                                <span class="font-medium text-gray-900 dark:text-gray-200 font-mono">
                                    {{ dispatch.vehicle.last_maintenance_mileage ? (dispatch.vehicle.last_maintenance_mileage + 5000).toLocaleString() + ' km' : '無紀錄' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 審核與申請人資訊 -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6 border border-gray-100 dark:border-gray-700 space-y-4">
                        <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100">
                            行政簽核流程資訊
                        </h4>

                        <div class="space-y-3 text-xs">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 flex items-center justify-center font-bold">
                                    申
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ dispatch.user?.name }}</p>
                                    <p class="text-gray-500 dark:text-gray-400">{{ dispatch.user?.department?.name || '總部' }} · 申請同仁</p>
                                </div>
                            </div>

                            <div v-if="dispatch.approver" class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 flex items-center justify-center font-bold">
                                    核
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ dispatch.approver?.name }}</p>
                                    <p class="text-gray-500 dark:text-gray-400">調派審核主管</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 駁回 Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">駁回公務派車申請</h3>
                <p class="text-xs text-gray-500 mb-4 dark:text-gray-400">請填寫具體駁回理由，系統將發送通知退回給申請同仁。</p>
                <form @submit.prevent="submitReject" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">駁回原因 *</label>
                        <textarea
                            v-model="rejectForm.rejection_reason"
                            rows="3"
                            required
                            placeholder="例如：當日時段已有高階主管公務出差需求、請改搭乘高鐵或改選其他車種..."
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showRejectModal = false"
                            class="px-4 py-2 border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300 rounded-lg text-sm"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="rejectForm.processing"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium"
                        >
                            確認駁回
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 領車出發 Modal -->
        <div v-if="showStartModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">交付鑰匙 · 領車出發登錄</h3>
                <p class="text-xs text-gray-500 mb-4 dark:text-gray-400">請確認目前儀表板里程數，確認出發後車輛狀態將轉為「使用中」。</p>
                <form @submit.prevent="submitStart" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">出發儀表里程 (km) *</label>
                        <input
                            type="number"
                            v-model="startForm.start_mileage"
                            required
                            min="0"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono"
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showStartModal = false"
                            class="px-4 py-2 border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300 rounded-lg text-sm"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="startForm.processing"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium"
                        >
                            確認領車出發
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 還車里程登錄 Modal -->
        <div v-if="showReturnModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">收回鑰匙 · 還車里程登錄</h3>
                <p class="text-xs text-gray-500 mb-4 dark:text-gray-400">請抄錄歸還時儀表板最新里程數，系統將自動計算本次出差行駛總里程。</p>
                <form @submit.prevent="submitReturn" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">歸還儀表里程 (km) *</label>
                        <input
                            type="number"
                            v-model="returnForm.end_mileage"
                            required
                            :min="dispatch.start_mileage || dispatch.vehicle.current_mileage"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono"
                        />
                        <p class="text-xs text-gray-400 mt-1">出發里程：{{ dispatch.start_mileage || dispatch.vehicle.current_mileage }} km</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">車況檢查與油料備註</label>
                        <textarea
                            v-model="returnForm.notes"
                            rows="2"
                            placeholder="油箱已加滿、車容整潔無刮傷..."
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showReturnModal = false"
                            class="px-4 py-2 border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300 rounded-lg text-sm"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="returnForm.processing"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium"
                        >
                            完成還車結案
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
