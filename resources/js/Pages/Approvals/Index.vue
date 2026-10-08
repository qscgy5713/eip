<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    pendingRecords: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    stats: {
        type: Object,
        default: () => ({
            total_pending: 0,
            today_new: 0,
            delegated_count: 0,
            leave_count: 0,
        }),
    },
    forms: {
        type: Array,
        default: () => [],
    },
    viewAllCompany: {
        type: Boolean,
        default: false,
    },
    canViewAllCompany: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filterForm = ref({
    form_id: props.filters?.form_id || '',
    filter_type: props.filters?.filter_type || '',
    search: props.filters?.search || '',
    all_company: props.filters?.all_company || false,
});

const applyFilters = () => {
    router.get(route('approvals.index'), {
        form_id: filterForm.value.form_id,
        filter_type: filterForm.value.filter_type,
        search: filterForm.value.search,
        all_company: filterForm.value.all_company ? 1 : 0,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const toggleAllCompany = () => {
    filterForm.value.all_company = !filterForm.value.all_company;
    applyFilters();
};

// 批次選擇邏輯
const selectedIds = ref([]);

const allIdsOnPage = computed(() => {
    return (props.pendingRecords?.data || []).map(r => r.id);
});

const isAllSelected = computed(() => {
    if (allIdsOnPage.value.length === 0) return false;
    return allIdsOnPage.value.every(id => selectedIds.value.includes(id));
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = [...allIdsOnPage.value];
    }
};

const toggleSelectRecord = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

// 批次簽核彈窗與表單
const showBatchModal = ref(false);
const batchActionType = ref('approved'); // 'approved' or 'rejected'
const batchComment = ref('');

const batchForm = useForm({
    record_ids: [],
    action: 'approved',
    comment: '',
});

const openBatchModal = (action) => {
    if (selectedIds.value.length === 0) return;
    batchActionType.value = action;
    batchComment.value = action === 'approved' ? '批次核准通過' : '批次退件駁回';
    showBatchModal.value = true;
};

const submitBatchAction = () => {
    batchForm.record_ids = [...selectedIds.value];
    batchForm.action = batchActionType.value;
    batchForm.comment = batchComment.value;

    batchForm.post(route('approvals.batchAction'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            showBatchModal.value = false;
        },
    });
};

// 單筆快速審批
const singleActionForm = useForm({
    status: 'approved',
    comment: '',
});

const quickApprove = (record) => {
    singleActionForm.status = 'approved';
    singleActionForm.comment = '核准通過';
    singleActionForm.post(route('forms.action', record.form_request_id), {
        preserveScroll: true,
    });
};

const quickReject = (record) => {
    const reason = prompt('請輸入退件駁回原因（選填）：', '退件駁回');
    if (reason === null) return;
    singleActionForm.status = 'rejected';
    singleActionForm.comment = reason;
    singleActionForm.post(route('forms.action', record.form_request_id), {
        preserveScroll: true,
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('zh-TW', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const extractDataSummary = (data, formCode) => {
    if (!data || typeof data !== 'object') return '';
    if (formCode === 'LEAVE') {
        const type = data.leave_type || '休假';
        const days = data.days || 1;
        return `${type} ${days} 天`;
    }
    if (formCode === 'EXPENSE') {
        const amt = data.amount ? `NT$ ${Number(data.amount).toLocaleString()}` : '';
        const item = data.expense_type || '';
        return `${item} ${amt}`.trim();
    }
    if (formCode === 'OVERTIME') {
        const hrs = data.hours || 0;
        return `加班 ${hrs} 小時`;
    }
    // 取前兩個非空 key 呈現
    const keys = Object.keys(data).filter(k => data[k] && typeof data[k] !== 'object').slice(0, 2);
    return keys.map(k => `${data[k]}`).join(' · ');
};
</script>

<template>
    <Head title="主管審批中心" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        主管審批中心
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        集中檢閱全體待審核單據，支援多選一鍵批次核准與批次退件。
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        v-if="canViewAllCompany"
                        type="button"
                        @click="toggleAllCompany"
                        :class="[
                            'inline-flex items-center px-3.5 py-2 text-xs font-semibold rounded-lg border shadow-sm transition',
                            viewAllCompany
                                ? 'bg-indigo-50 border-indigo-300 text-indigo-700 font-bold'
                                : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span>{{ viewAllCompany ? '檢視全公司待審單據' : '僅看待我審核' }}</span>
                    </button>

                    <Link
                        :href="route('forms.index')"
                        class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        表單中心
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- 待審指標卡片 -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <div class="flex items-center justify-between text-xs text-gray-500 font-semibold">
                            <span>待審核單據總數</span>
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        </div>
                        <div class="mt-2 text-3xl font-extrabold text-gray-900">
                            {{ stats.total_pending }}
                        </div>
                        <div class="mt-1 text-[11px] text-gray-400">目前待簽核流程</div>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <div class="flex items-center justify-between text-xs text-gray-500 font-semibold">
                            <span>今日新送審</span>
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        </div>
                        <div class="mt-2 text-3xl font-extrabold text-blue-600">
                            {{ stats.today_new }}
                        </div>
                        <div class="mt-1 text-[11px] text-gray-400">本日新增待審件</div>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <div class="flex items-center justify-between text-xs text-gray-500 font-semibold">
                            <span>職務代理待審</span>
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        </div>
                        <div class="mt-2 text-3xl font-extrabold text-amber-600">
                            {{ stats.delegated_count }}
                        </div>
                        <div class="mt-1 text-[11px] text-gray-400">受託主管出差/請假代簽</div>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <div class="flex items-center justify-between text-xs text-gray-500 font-semibold">
                            <span>差假請假單據</span>
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        </div>
                        <div class="mt-2 text-3xl font-extrabold text-emerald-600">
                            {{ stats.leave_count }}
                        </div>
                        <div class="mt-1 text-[11px] text-gray-400">特休/病事假待審</div>
                    </div>
                </div>

                <!-- 浮動批次操作工具列 (當勾選單據時呈現) -->
                <div
                    v-if="selectedIds.length > 0"
                    class="sticky top-20 z-20 bg-indigo-900 text-white rounded-xl shadow-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4 border border-indigo-700 transition"
                >
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-700 text-sm font-bold">
                            {{ selectedIds.length }}
                        </span>
                        <div class="text-sm font-semibold">
                            已選取 <strong class="text-amber-300">{{ selectedIds.length }}</strong> 筆待審核單據
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button
                            type="button"
                            @click="selectedIds = []"
                            class="px-3 py-1.5 text-xs text-indigo-200 hover:text-white transition"
                        >
                            取消選取
                        </button>

                        <button
                            type="button"
                            @click="openBatchModal('rejected')"
                            class="inline-flex items-center px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-sm transition"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            批次駁回退件
                        </button>

                        <button
                            type="button"
                            @click="openBatchModal('approved')"
                            class="inline-flex items-center px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            一鍵批次核准通過
                        </button>
                    </div>
                </div>

                <!-- 待審列表主卡片 -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-gray-200">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <!-- 篩選列 -->
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="w-40">
                                    <select
                                        v-model="filterForm.form_id"
                                        @change="applyFilters"
                                        class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">全部表單種類</option>
                                        <option v-for="f in forms" :key="f.id" :value="f.id">
                                            {{ f.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="w-36">
                                    <select
                                        v-model="filterForm.filter_type"
                                        @change="applyFilters"
                                        class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">全部審核類型</option>
                                        <option value="direct">親自審查</option>
                                        <option value="delegated">代理待審</option>
                                    </select>
                                </div>

                                <div class="relative w-56">
                                    <input
                                        v-model="filterForm.search"
                                        @keyup.enter="applyFilters"
                                        type="text"
                                        placeholder="搜尋申請人 / 單號 / 主旨..."
                                        class="w-full text-xs rounded-lg border-gray-300 pl-8 focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>

                                <button
                                    type="button"
                                    @click="applyFilters"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                                >
                                    查詢
                                </button>
                            </div>

                            <div class="text-xs text-gray-500">
                                共 <strong>{{ pendingRecords.total || 0 }}</strong> 筆待簽核單據
                            </div>
                        </div>
                    </div>

                    <!-- 待審核清單表格 -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50 text-gray-600 font-semibold">
                                <tr>
                                    <th class="py-3.5 px-4 w-10 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="isAllSelected"
                                            @change="toggleSelectAll"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </th>
                                    <th class="py-3.5 px-4">申請人與部門</th>
                                    <th class="py-3.5 px-4">表單種類 / 單號</th>
                                    <th class="py-3.5 px-4">申請主旨 / 關鍵摘要</th>
                                    <th class="py-3.5 px-4">當前關卡進度</th>
                                    <th class="py-3.5 px-4">送審時間</th>
                                    <th class="py-3.5 px-4 text-right">快速操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="record in (pendingRecords.data || [])"
                                    :key="record.id"
                                    :class="[
                                        'hover:bg-gray-50/80 transition',
                                        selectedIds.includes(record.id) ? 'bg-indigo-50/40' : ''
                                    ]"
                                >
                                    <!-- Checkbox -->
                                    <td class="py-3.5 px-4 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.includes(record.id)"
                                            @change="toggleSelectRecord(record.id)"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </td>

                                    <!-- 申請人 -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-xs flex-shrink-0">
                                                {{ (record.form_request?.user?.name || '').slice(0, 1) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-900">
                                                    {{ record.form_request?.user?.name }}
                                                </div>
                                                <div class="text-[11px] text-gray-400">
                                                    {{ record.form_request?.user?.department?.name || '公司同仁' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 表單名稱 & 單號 -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                                {{ record.form_request?.form?.name }}
                                            </span>
                                            <span
                                                v-if="record.is_delegated"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                                            >
                                                代理簽核
                                            </span>
                                        </div>
                                        <div class="text-[11px] font-mono text-gray-400 mt-0.5">
                                            {{ record.form_request?.request_no }}
                                        </div>
                                    </td>

                                    <!-- 主旨與摘要 -->
                                    <td class="py-3.5 px-4 max-w-xs">
                                        <Link
                                            :href="route('forms.show', record.form_request_id)"
                                            class="font-semibold text-gray-900 hover:text-indigo-600 block truncate"
                                            :title="record.form_request?.title"
                                        >
                                            {{ record.form_request?.title }}
                                        </Link>
                                        <div class="text-[11px] text-gray-500 mt-0.5">
                                            {{ extractDataSummary(record.form_request?.data, record.form_request?.form?.code) }}
                                        </div>
                                    </td>

                                    <!-- 關卡進度 -->
                                    <td class="py-3.5 px-4">
                                        <div class="font-medium text-gray-800">
                                            {{ record.step_title || `第 ${record.step} 關` }}
                                        </div>
                                        <div class="text-[11px] text-gray-400">
                                            進度：關卡 {{ record.step }} / {{ record.form_request?.total_steps || 1 }}
                                        </div>
                                    </td>

                                    <!-- 送審時間 -->
                                    <td class="py-3.5 px-4 text-gray-500 text-[11px]">
                                        {{ formatDate(record.created_at) }}
                                    </td>

                                    <!-- 操作 -->
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <Link
                                                :href="route('forms.show', record.form_request_id)"
                                                class="px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded transition"
                                            >
                                                詳情
                                            </Link>
                                            <button
                                                type="button"
                                                @click="quickReject(record)"
                                                :disabled="singleActionForm.processing"
                                                class="px-2 py-1 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded transition disabled:opacity-50"
                                            >
                                                駁回
                                            </button>
                                            <button
                                                type="button"
                                                @click="quickApprove(record)"
                                                :disabled="singleActionForm.processing"
                                                class="px-2.5 py-1 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition disabled:opacity-50"
                                            >
                                                核准
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="(pendingRecords.data || []).length === 0">
                                    <td colspan="7" class="py-12 text-center text-gray-400">
                                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-400 mb-2">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-600">太棒了！目前沒有任何待審核單據</p>
                                        <p class="text-xs text-gray-400 mt-1">您負責的所有簽核公文均已處理完畢。</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 分頁列 -->
                    <div v-if="pendingRecords.last_page > 1" class="p-4 border-t border-gray-200 flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            共 {{ pendingRecords.total }} 筆單據
                        </div>
                        <div class="flex items-center gap-1">
                            <template v-for="(link, i) in (pendingRecords.links || [])" :key="i">
                                <button
                                    v-if="link.url"
                                    type="button"
                                    @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                                    :class="[
                                        'px-2.5 py-1 text-xs rounded transition',
                                        link.active ? 'bg-indigo-600 text-white font-bold' : 'text-gray-700 hover:bg-gray-100'
                                    ]"
                                    v-html="link.label"
                                ></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 批次簽核確認 Modal -->
        <div v-if="showBatchModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="showBatchModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <h4 class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span
                                :class="[
                                    'w-2.5 h-2.5 rounded-full',
                                    batchActionType === 'approved' ? 'bg-emerald-500' : 'bg-rose-500'
                                ]"
                            ></span>
                            確認批次{{ batchActionType === 'approved' ? '核准' : '駁回' }} ({{ selectedIds.length }} 筆)
                        </h4>
                        <button type="button" @click="showBatchModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div
                            :class="[
                                'p-3 rounded-lg text-xs border',
                                batchActionType === 'approved'
                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                    : 'bg-rose-50 text-rose-800 border-rose-200'
                            ]"
                        >
                            <p class="font-semibold">
                                您即將對已選取的 <strong>{{ selectedIds.length }}</strong> 筆單據執行一鍵批次{{ batchActionType === 'approved' ? '核准' : '退件駁回' }}。
                            </p>
                            <p class="mt-1 text-[11px] opacity-90">
                                系統將自動流轉下一關卡、同步扣除或釋放休假額度，並發送通知給各申請同仁。
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                批次審批意見 / 批註 (選填)
                            </label>
                            <textarea
                                v-model="batchComment"
                                rows="3"
                                placeholder="例如：批次核准通過，准予備查..."
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <button
                            type="button"
                            @click="showBatchModal = false"
                            class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                        >
                            取消
                        </button>
                        <button
                            type="button"
                            @click="submitBatchAction"
                            :disabled="batchForm.processing"
                            :class="[
                                'px-4 py-2 text-xs font-bold text-white rounded-lg shadow-sm transition disabled:opacity-50',
                                batchActionType === 'approved'
                                    ? 'bg-emerald-600 hover:bg-emerald-700'
                                    : 'bg-rose-600 hover:bg-rose-700'
                            ]"
                        >
                            {{ batchForm.processing ? '正在批次處理中...' : `確認批次${batchActionType === 'approved' ? '核准' : '駁回'}` }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
