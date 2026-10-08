<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface LeaveBalance {
    id: number;
    user_id: number;
    year: number;
    leave_type: string;
    allocated_days: number;
    used_days: number;
    pending_days: number;
    available_days: number;
    type_label: string;
    is_hard_quota: boolean;
    note: string | null;
}

interface UserSummary {
    id: number;
    name: string;
    email: string;
    employee_no?: string;
    department?: {
        id: number;
        name: string;
    };
    leaveBalances: LeaveBalance[];
}

interface PaginationMeta<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    myBalances: LeaveBalance[];
    selectedYear: number;
    canManage: boolean;
    managedUsers?: PaginationMeta<UserSummary> | null;
    departments: Array<{ id: number; name: string }>;
    leaveTypesMeta: Record<string, { name: string; is_hard_quota: boolean; default_allocated: number; description: string }>;
    filters: {
        department_id: string;
        search: string;
        year: number;
    };
}>();

const currentYear = new Date().getFullYear();
const yearOptions = [currentYear - 1, currentYear, currentYear + 1];

const filterForm = ref({
    year: props.selectedYear,
    department_id: props.filters.department_id || '',
    search: props.filters.search || '',
});

const applyFilters = () => {
    router.get(route('leave-balances.index'), {
        year: filterForm.value.year,
        department_id: filterForm.value.department_id,
        search: filterForm.value.search,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const changeYear = (yr: number) => {
    filterForm.value.year = yr;
    applyFilters();
};

// 調整單一同仁額度 Modal 狀態
const showEditModal = ref(false);
const editingUser = ref<UserSummary | null>(null);

const editForm = useForm({
    year: props.selectedYear,
    leave_type: 'annual',
    allocated_days: 7.0,
    note: '',
});

const openEditModal = (user: UserSummary, defaultType: string = 'annual') => {
    editingUser.value = user;
    const balance = user.leaveBalances.find(b => b.leave_type === defaultType);
    editForm.year = props.selectedYear;
    editForm.leave_type = defaultType;
    editForm.allocated_days = balance ? balance.allocated_days : 7.0;
    editForm.note = balance?.note || '';
    showEditModal.value = true;
};

const handleTypeChangeInModal = () => {
    if (!editingUser.value) return;
    const balance = editingUser.value.leaveBalances.find(b => b.leave_type === editForm.leave_type);
    if (balance) {
        editForm.allocated_days = balance.allocated_days;
        editForm.note = balance.note || '';
    } else {
        const meta = props.leaveTypesMeta[editForm.leave_type];
        editForm.allocated_days = meta ? meta.default_allocated : 0;
        editForm.note = '';
    }
};

const submitEditForm = () => {
    if (!editingUser.value) return;
    editForm.put(route('leave-balances.update', editingUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

// 批次初始化 Modal 狀態
const showBatchModal = ref(false);
const batchForm = useForm({
    year: props.selectedYear,
    department_id: '',
});

const submitBatchInit = () => {
    batchForm.post(route('leave-balances.batchInit'), {
        preserveScroll: true,
        onSuccess: () => {
            showBatchModal.value = false;
        },
    });
};

const getUserBalanceByType = (user: UserSummary, type: string): LeaveBalance | undefined => {
    return user.leaveBalances.find(b => b.leave_type === type);
};
</script>

<template>
    <Head title="特休與假別額度管理" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        特休與假別額度管理
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        掌握個人各類假別年度剩餘天數與配額，人事部門專屬配額維護與自動扣減。
                    </p>
                </div>

                <!-- 年度切換與管理功能列 -->
                <div class="flex items-center gap-3">
                    <div class="inline-flex rounded-lg bg-gray-100 p-1 border border-gray-200">
                        <button
                            v-for="yr in yearOptions"
                            :key="yr"
                            type="button"
                            @click="changeYear(yr)"
                            :class="[
                                'px-3 py-1.5 text-xs font-semibold rounded-md transition duration-150',
                                filterForm.year === yr
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-600 hover:text-gray-900'
                            ]"
                        >
                            {{ yr }} 年度
                        </button>
                    </div>

                    <button
                        v-if="canManage"
                        type="button"
                        @click="showBatchModal = true"
                        class="inline-flex items-center px-3.5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        批次初始化配額
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- 個人年度休假額度卡片 -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7 7z" />
                            </svg>
                            我的休假額度 ({{ filterForm.year }} 年度)
                        </h3>
                        <span class="text-xs text-gray-500">
                            ※ 申請請假單經簽核通過後，系統將自動扣減並即時更新
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div
                            v-for="b in myBalances"
                            :key="b.id"
                            class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:border-gray-300 transition"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-gray-900">{{ b.type_label }}</span>
                                <span
                                    v-if="b.is_hard_quota"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                >
                                    額度管制
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600"
                                >
                                    常規假別
                                </span>
                            </div>

                            <div class="mt-4 flex items-baseline justify-between">
                                <div>
                                    <span class="text-3xl font-extrabold tracking-tight text-gray-900">
                                        {{ b.available_days }}
                                    </span>
                                    <span class="ml-1 text-sm text-gray-500">天可用</span>
                                </div>
                                <div class="text-right text-xs text-gray-500">
                                    核給上限：<span class="font-semibold text-gray-700">{{ b.allocated_days }}</span> 天
                                </div>
                            </div>

                            <!-- 進度條 -->
                            <div class="mt-4">
                                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div
                                        class="h-2 rounded-full transition-all duration-300"
                                        :class="b.is_hard_quota ? 'bg-indigo-600' : 'bg-blue-500'"
                                        :style="{
                                            width: `${b.allocated_days > 0 ? Math.min(100, Math.round(((b.used_days + b.pending_days) / b.allocated_days) * 100)) : 0}%`
                                        }"
                                    ></div>
                                </div>
                            </div>

                            <!-- 明細欄位 -->
                            <div class="mt-4 pt-3 border-t border-gray-100 grid grid-cols-2 text-xs text-gray-500">
                                <div>
                                    已核准使用：<span class="font-semibold text-gray-800">{{ b.used_days }}</span> 天
                                </div>
                                <div class="text-right">
                                    審核中凍結：<span class="font-semibold text-amber-600">{{ b.pending_days }}</span> 天
                                </div>
                            </div>

                            <p v-if="b.note" class="mt-2 text-[11px] text-gray-400 truncate" :title="b.note">
                                備註：{{ b.note }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- HR / 管理員專屬：同仁配額管轄清單 -->
                <div v-if="canManage && managedUsers" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-5 sm:p-6 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    全體同仁休假配額管轄 (HR / 管理員)
                                </h3>
                                <p class="mt-1 text-xs text-gray-500">
                                    查詢同仁法定特休與各類假別天數，支援個別快速調整與備註維護。
                                </p>
                            </div>

                            <!-- 篩選列 -->
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="w-40">
                                    <select
                                        v-model="filterForm.department_id"
                                        @change="applyFilters"
                                        class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">全部部門</option>
                                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                            {{ dept.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="relative w-52">
                                    <input
                                        v-model="filterForm.search"
                                        @keyup.enter="applyFilters"
                                        type="text"
                                        placeholder="搜尋姓名 / 工號..."
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
                        </div>
                    </div>

                    <!-- 同仁配額表格 -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50 text-gray-600 font-semibold">
                                <tr>
                                    <th class="py-3 px-4">同仁資訊</th>
                                    <th class="py-3 px-4">部門</th>
                                    <th class="py-3 px-4">特休 (可用/總計)</th>
                                    <th class="py-3 px-4">補休 (可用/總計)</th>
                                    <th class="py-3 px-4">病假 (已請/上限)</th>
                                    <th class="py-3 px-4">事假 (已請/上限)</th>
                                    <th class="py-3 px-4 text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="user in managedUsers.data"
                                    :key="user.id"
                                    class="hover:bg-gray-50 transition"
                                >
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-xs">
                                                {{ user.name.slice(0, 1) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-900">{{ user.name }}</div>
                                                <div class="text-[11px] text-gray-400">
                                                    {{ user.employee_no ? `工號：${user.employee_no}` : user.email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-gray-600">
                                        {{ user.department?.name || '無部門' }}
                                    </td>

                                    <!-- 特休 -->
                                    <td class="py-3.5 px-4">
                                        <span class="font-bold text-emerald-600">
                                            {{ getUserBalanceByType(user, 'annual')?.available_days ?? 0 }}
                                        </span>
                                        <span class="text-gray-400"> / </span>
                                        <span class="text-gray-700">
                                            {{ getUserBalanceByType(user, 'annual')?.allocated_days ?? 0 }} 天
                                        </span>
                                    </td>

                                    <!-- 補休 -->
                                    <td class="py-3.5 px-4">
                                        <span class="font-bold text-amber-600">
                                            {{ getUserBalanceByType(user, 'compensatory')?.available_days ?? 0 }}
                                        </span>
                                        <span class="text-gray-400"> / </span>
                                        <span class="text-gray-700">
                                            {{ getUserBalanceByType(user, 'compensatory')?.allocated_days ?? 0 }} 天
                                        </span>
                                    </td>

                                    <!-- 病假 -->
                                    <td class="py-3.5 px-4 text-gray-600">
                                        <span>{{ getUserBalanceByType(user, 'sick')?.used_days ?? 0 }}</span>
                                        <span class="text-gray-400"> / </span>
                                        <span>{{ getUserBalanceByType(user, 'sick')?.allocated_days ?? 30 }} 天</span>
                                    </td>

                                    <!-- 事假 -->
                                    <td class="py-3.5 px-4 text-gray-600">
                                        <span>{{ getUserBalanceByType(user, 'personal')?.used_days ?? 0 }}</span>
                                        <span class="text-gray-400"> / </span>
                                        <span>{{ getUserBalanceByType(user, 'personal')?.allocated_days ?? 14 }} 天</span>
                                    </td>

                                    <!-- 操作 -->
                                    <td class="py-3.5 px-4 text-right">
                                        <button
                                            type="button"
                                            @click="openEditModal(user)"
                                            class="inline-flex items-center px-2.5 py-1 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-md transition"
                                        >
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            調整額度
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="managedUsers.data.length === 0">
                                    <td colspan="7" class="py-8 text-center text-gray-400">
                                        查無符合條件之同仁名單
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 分頁列 -->
                    <div v-if="managedUsers.last_page > 1" class="p-4 border-t border-gray-200 flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            共 {{ managedUsers.total }} 位同仁
                        </div>
                        <div class="flex items-center gap-1">
                            <template v-for="(link, i) in managedUsers.links" :key="i">
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

        <!-- 調整額度 Modal -->
        <div v-if="showEditModal && editingUser" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="showEditModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <h4 class="text-base font-bold text-gray-900">
                            調整同仁休假配額
                        </h4>
                        <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="bg-gray-50 p-3 rounded-lg text-xs text-gray-600 flex justify-between">
                            <span>同仁：<strong class="text-gray-900">{{ editingUser.name }}</strong></span>
                            <span>年度：<strong class="text-gray-900">{{ editForm.year }}</strong></span>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">假別選擇</label>
                            <select
                                v-model="editForm.leave_type"
                                @change="handleTypeChangeInModal"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="(meta, key) in leaveTypesMeta" :key="key" :value="key">
                                    {{ meta.name }} ({{ meta.is_hard_quota ? '硬性額度' : '常規上限' }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                核給天數 (天)
                            </label>
                            <input
                                v-model.number="editForm.allocated_days"
                                type="number"
                                step="0.5"
                                min="0"
                                max="365"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p class="mt-1 text-[11px] text-gray-400">
                                支援 0.5 天（半天）精度，可隨同仁年資累計動態增減
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">異動備註 / 說明</label>
                            <input
                                v-model="editForm.note"
                                type="text"
                                placeholder="例如：到職滿兩年法定特休、加班時數轉補休"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                        >
                            取消
                        </button>
                        <button
                            type="button"
                            @click="submitEditForm"
                            :disabled="editForm.processing"
                            class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition disabled:opacity-50"
                        >
                            儲存變更
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 批次初始化 Modal -->
        <div v-if="showBatchModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="showBatchModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <h4 class="text-base font-bold text-gray-900">
                            批次初始化年度休假配額
                        </h4>
                        <button type="button" @click="showBatchModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="p-3 bg-amber-50 rounded-lg text-xs text-amber-800 border border-amber-200">
                            此功能將為選定範圍內尚未建立該年度配額的同仁，自動帶入系統標準法定天數（預設特休 7 天、病假 30 天、事假 14 天）。已有配額之同仁將完整予以保留。
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">目標年度</label>
                            <select
                                v-model.number="batchForm.year"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="yr in yearOptions" :key="yr" :value="yr">
                                    {{ yr }} 年度
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">目標部門 (選填)</label>
                            <select
                                v-model="batchForm.department_id"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">全公司同仁</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    {{ dept.name }}
                                </option>
                            </select>
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
                            @click="submitBatchInit"
                            :disabled="batchForm.processing"
                            class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition disabled:opacity-50"
                        >
                            執行初始化
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
