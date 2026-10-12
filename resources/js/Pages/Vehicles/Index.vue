<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { alertDialog, confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    vehicles: Array,
    dispatches: Object,
    metrics: Object,
    filters: Object,
    canManageAll: Boolean,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const vehicle_id = ref(props.filters.vehicle_id || '');
const scope = ref(props.filters.scope || 'mine');

// 還車 Modal
const showReturnModal = ref(false);
const activeDispatch = ref(null);
const endMileage = ref(0);
const returnNotes = ref('');
const isSubmittingReturn = ref(false);

// 駁回 Modal
const showRejectModal = ref(false);
const rejectingDispatch = ref(null);
const rejectionReason = ref('');

const applyFilters = () => {
    router.get(route('vehicles.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        vehicle_id: vehicle_id.value || undefined,
        scope: scope.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    search.value = '';
    status.value = '';
    vehicle_id.value = '';
    scope.value = props.canManageAll ? 'all' : 'mine';
    applyFilters();
};

const handleApprove = async (dispatch) => {
    if (await confirmDialog(`確定核准同仁「${dispatch.user?.name}」的公務派車單（${dispatch.dispatch_number}）嗎？`)) {
        router.patch(route('vehicles.approve', dispatch.id), {}, { preserveScroll: true });
    }
};

const openRejectModal = (dispatch) => {
    rejectingDispatch.value = dispatch;
    rejectionReason.value = '';
    showRejectModal.value = true;
};

const submitReject = () => {
    if (!rejectingDispatch.value || !rejectionReason.value.trim()) {
        alertDialog('請填寫駁回原因');
        return;
    }
    router.patch(route('vehicles.reject', rejectingDispatch.value.id), {
        rejection_reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showRejectModal.value = false;
            rejectingDispatch.value = null;
        },
    });
};

const handleStartTrip = async (dispatch) => {
    if (await confirmDialog(`確定領車出發嗎？出車時表定里程為 ${dispatch.vehicle?.current_mileage || 0} km。`)) {
        router.post(route('vehicles.start', dispatch.id), {}, { preserveScroll: true });
    }
};

const openReturnModal = (dispatch) => {
    activeDispatch.value = dispatch;
    endMileage.value = (dispatch.start_mileage || dispatch.vehicle?.current_mileage || 0);
    returnNotes.value = '';
    showReturnModal.value = true;
};

const submitReturn = () => {
    if (!activeDispatch.value) return;
    if (endMileage.value < (activeDispatch.value.start_mileage || 0)) {
        alertDialog(`歸還里程數不可低於出車時里程 (${activeDispatch.value.start_mileage} km)！`);
        return;
    }
    isSubmittingReturn.value = true;
    router.post(route('vehicles.return', activeDispatch.value.id), {
        end_mileage: endMileage.value,
        return_notes: returnNotes.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showReturnModal.value = false;
            activeDispatch.value = null;
        },
        onFinish: () => {
            isSubmittingReturn.value = false;
        },
    });
};

const handleExport = () => {
    window.location.href = route('vehicles.export');
};
</script>

<template>
    <Head title="企業公務車輛借調與出差派車" />

    <AuthenticatedLayout>
        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- 標題與主動作列 -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4m-11-7l2.5-5h9L19 10m-14 0h14m-14 0a2 2 0 00-2 2v5a2 2 0 002 2h1m12 0h1a2 2 0 002-2v-5a2 2 0 00-2-2" />
                            </svg>
                        </span>
                        <span>企業公務車輛借調與出差派車</span>
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        公務車輛即時在庫車況、智慧時段防重疊借調、出車還車里程油料回報與審批稽核
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
                        匯出派車紀錄 CSV
                    </button>
                    <Link
                        :href="route('vehicles.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        預約借用派車
                    </Link>
                </div>
            </div>

            <!-- 營運指標小卡 -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">公務車總車數</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-2">{{ metrics.total_vehicles }} <span class="text-xs font-normal text-gray-400">輛</span></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">在庫隨時可借</p>
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-2">{{ metrics.available_vehicles }} <span class="text-xs font-normal text-gray-400">輛</span></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-indigo-600 dark:text-indigo-400">派車出勤行駛中</p>
                    <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-2">{{ metrics.in_use_vehicles }} <span class="text-xs font-normal text-gray-400">輛</span></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-amber-600 dark:text-amber-400">待審批派車單</p>
                    <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-2">{{ metrics.pending_dispatches }} <span class="text-xs font-normal text-gray-400">單</span></p>
                </div>
            </div>

            <!-- 車隊車輛名冊卡片橫向導覽 -->
            <div class="space-y-2">
                <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400">車隊現況與車位一覽</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div
                        v-for="v in vehicles"
                        :key="v.id"
                        class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-2 py-0.5 rounded-md font-mono font-bold text-xs bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    {{ v.plate_number }}
                                </span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-2xs font-bold',
                                        v.status === 'available' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' :
                                        v.status === 'in_use' ? 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300' :
                                        'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                                    ]"
                                >
                                    {{ v.status_label }}
                                </span>
                            </div>
                            <h4 class="font-bold text-gray-900 dark:text-gray-100 text-sm mt-2 truncate">{{ v.name }}</h4>
                            <div class="mt-2 space-y-1 text-2xs text-gray-500 dark:text-gray-400">
                                <p>型態：{{ v.vehicle_type_label }} · 乘載 {{ v.capacity }} 人</p>
                                <p>能源：{{ v.fuel_type_label }} · 車位：{{ v.parking_location || '總部平面停車場' }}</p>
                                <p>目前累積里程：<strong class="text-gray-700 dark:text-gray-300">{{ v.current_mileage.toLocaleString() }}</strong> km</p>
                            </div>
                        </div>

                        <div class="mt-3 pt-2 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-2xs">
                            <span v-if="v.needs_maintenance" class="text-red-600 font-bold flex items-center gap-1">
                                ⚠ 達 5,000 km 應定期保養
                            </span>
                            <span v-else class="text-gray-400">
                                車況檢驗良好
                            </span>
                            <Link
                                :href="route('vehicles.create', { vehicle_id: v.id })"
                                class="text-amber-600 dark:text-amber-400 font-bold hover:underline"
                            >
                                預約此車 &rarr;
                            </Link>
                        </div>
                    </div>
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
                                scope === 'mine' ? 'bg-white dark:bg-gray-800 text-amber-600 dark:text-amber-400 shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                            ]"
                        >
                            我申請的派車
                        </button>
                        <button
                            type="button"
                            @click="scope = 'all'; applyFilters()"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-bold transition',
                                scope === 'all' ? 'bg-white dark:bg-gray-800 text-amber-600 dark:text-amber-400 shadow-xs' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                            ]"
                        >
                            全廠派車總表
                        </button>
                    </div>

                    <!-- 狀態選單 -->
                    <select
                        v-model="status"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-200 py-1.5 px-3 focus:ring-amber-500"
                    >
                        <option value="">所有狀態</option>
                        <option value="pending">待審批簽核</option>
                        <option value="approved">已核准 (待出車)</option>
                        <option value="active">出勤行駛中</option>
                        <option value="completed">已完成還車</option>
                        <option value="rejected">已駁回申請</option>
                        <option value="cancelled">已取消預約</option>
                    </select>

                    <!-- 車輛選單 -->
                    <select
                        v-model="vehicle_id"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-200 py-1.5 px-3 focus:ring-amber-500"
                    >
                        <option value="">所有公務車輛</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">
                            {{ v.plate_number }} ({{ v.name }})
                        </option>
                    </select>
                </div>

                <!-- 關鍵字搜尋 -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <div class="relative w-full md:w-64">
                        <input
                            type="text"
                            v-model="search"
                            @keydown.enter="applyFilters"
                            placeholder="搜尋單號、目的地、姓名、車牌..."
                            class="w-full pl-9 pr-3 py-1.5 rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-200 focus:ring-amber-500"
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
                        v-if="search || status || vehicle_id"
                        @click="resetFilters"
                        class="px-2.5 py-1.5 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                    >
                        重設
                    </button>
                </div>
            </div>

            <!-- 派車資料表格 -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 font-semibold dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3">派車單號</th>
                                <th class="px-5 py-3">借用同仁</th>
                                <th class="px-5 py-3">公務車款</th>
                                <th class="px-5 py-3">目的地 / 事由</th>
                                <th class="px-5 py-3">預約用車時段</th>
                                <th class="px-5 py-3">里程回報</th>
                                <th class="px-5 py-3">狀態</th>
                                <th class="px-5 py-3 text-right">行程操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-300">
                            <tr
                                v-for="d in dispatches.data"
                                :key="d.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-700/40 transition"
                            >
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span class="font-mono font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 rounded-md">
                                        {{ d.dispatch_number }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-bold text-gray-900 dark:text-gray-100">
                                        {{ d.user?.name }}
                                    </div>
                                    <div class="text-gray-400 text-2xs">
                                        {{ d.user?.department?.name || '總部' }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-bold text-gray-900 dark:text-gray-100">
                                        {{ d.vehicle?.plate_number }}
                                    </div>
                                    <div class="text-gray-400 text-2xs">
                                        {{ d.vehicle?.name }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-gray-800 dark:text-gray-200">
                                        {{ d.destination }}
                                    </div>
                                    <div class="text-gray-400 text-2xs">
                                        {{ d.purpose_label }} ({{ d.passenger_count }} 人)
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ new Date(d.start_time).toLocaleString('zh-TW', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                    </div>
                                    <div class="text-gray-400 text-2xs">
                                        至 {{ new Date(d.end_time).toLocaleString('zh-TW', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div v-if="d.mileage_traveled !== null" class="font-bold text-emerald-600 dark:text-emerald-400">
                                        行駛 {{ d.mileage_traveled }} km
                                    </div>
                                    <div v-else-if="d.start_mileage" class="text-gray-400 text-2xs">
                                        起：{{ d.start_mileage }} km
                                    </div>
                                    <div v-else class="text-gray-400 text-2xs">未填</div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'px-2.5 py-1 rounded-full text-xs font-bold inline-block',
                                            d.status === 'pending' ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300' :
                                            d.status === 'approved' ? 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300' :
                                            d.status === 'active' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' :
                                            d.status === 'completed' ? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' :
                                            'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300'
                                        ]"
                                    >
                                        {{ d.status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-right space-x-1.5">
                                    <!-- 總務主管審核按鈕 -->
                                    <template v-if="canManageAll && d.status === 'pending'">
                                        <button
                                            @click="handleApprove(d)"
                                            class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition"
                                        >
                                            核准
                                        </button>
                                        <button
                                            @click="openRejectModal(d)"
                                            class="px-2 py-1 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-lg text-xs font-bold transition"
                                        >
                                            駁回
                                        </button>
                                    </template>

                                    <!-- 領車出發 -->
                                    <button
                                        v-if="d.status === 'approved'"
                                        @click="handleStartTrip(d)"
                                        class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-xs transition"
                                    >
                                        領車出發
                                    </button>

                                    <!-- 還車檢核 -->
                                    <button
                                        v-if="d.status === 'active'"
                                        @click="openReturnModal(d)"
                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition"
                                    >
                                        還車結案
                                    </button>

                                    <!-- 詳情 -->
                                    <Link
                                        :href="route('vehicles.show', d.id)"
                                        class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-bold transition"
                                    >
                                        詳情
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="dispatches.data.length === 0">
                                <td colspan="8" class="px-5 py-12 text-center text-gray-400">
                                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">查無任何派車借調紀錄</p>
                                    <p class="text-xs text-gray-400 mt-1">點擊上方「預約借用派車」發起公務出差用車</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 分頁按鈕 -->
                <div v-if="dispatches.links && dispatches.links.length > 3" class="px-5 py-4 border-t border-gray-100 dark:border-gray-700 flex justify-center gap-1">
                    <template v-for="(link, i) in dispatches.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                                link.active ? 'bg-amber-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'
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

        <!-- 還車登記 Modal -->
        <div v-if="showReturnModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">公務車還車檢核與里程回報</h3>
                    <button @click="showReturnModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="mt-4 space-y-4">
                    <div class="bg-amber-50 dark:bg-amber-950/40 p-3.5 rounded-xl border border-amber-100 dark:border-amber-900/60">
                        <p class="font-bold text-amber-900 dark:text-amber-200">
                            {{ activeDispatch?.vehicle?.plate_number }} ({{ activeDispatch?.vehicle?.name }})
                        </p>
                        <p class="text-amber-700 dark:text-amber-400 mt-1">
                            出發里程：{{ activeDispatch?.start_mileage }} km · 借用人：{{ activeDispatch?.user?.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                            歸還時儀表板總里程數 (km) <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            v-model="endMileage"
                            :min="activeDispatch?.start_mileage || 0"
                            required
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-amber-500 font-mono font-bold"
                        />
                        <p v-if="endMileage > (activeDispatch?.start_mileage || 0)" class="text-emerald-600 font-bold mt-1">
                            本次累計行駛：{{ endMileage - (activeDispatch?.start_mileage || 0) }} km
                        </p>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                            油料/電量與車況檢核回報 (可選)
                        </label>
                        <textarea
                            v-model="returnNotes"
                            rows="3"
                            placeholder="例如：已加滿 95 無鉛汽油（檢附收據）、車內已整理清潔、煞車與胎壓正常..."
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-amber-500"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        @click="showReturnModal = false"
                        class="px-4 py-2 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        取消
                    </button>
                    <button
                        @click="submitReturn"
                        :disabled="isSubmittingReturn"
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-md transition"
                    >
                        確認歸還車輛
                    </button>
                </div>
            </div>
        </div>

        <!-- 駁回 Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-red-600">駁回派車借調單</h3>
                    <button @click="showRejectModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="mt-4 space-y-3">
                    <p class="text-gray-600 dark:text-gray-300">
                        確定要駁回單號「{{ rejectingDispatch?.dispatch_number }}」嗎？請輸入原因：
                    </p>
                    <textarea
                        v-model="rejectionReason"
                        rows="3"
                        required
                        placeholder="請詳細填寫退回原因，例如：該時段有緊急高階接待公務、請改搭高鐵出差..."
                        class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-red-500"
                    ></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button @click="showRejectModal = false" class="px-4 py-2 rounded-xl text-gray-500 font-bold hover:bg-gray-100 dark:text-gray-400">
                        取消
                    </button>
                    <button @click="submitReject" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold transition">
                        確認駁回
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
