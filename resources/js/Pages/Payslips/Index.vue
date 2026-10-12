<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    payslips: Object,
    stats: Object,
    filters: Object,
    years: Array,
    users: Array,
    isHrAdmin: Boolean,
});

// 防窺遮罩狀態 (預設關閉防窺，或點擊即刻隱藏所有金額)
const privacyShield = ref(false);

const filterYear = ref(props.filters.year || new Date().getFullYear().toString());
const filterUserId = ref(props.filters.user_id || '');
const filterSearch = ref(props.filters.search || '');

const handleFilter = () => {
    router.get(route('payslips.index'), {
        year: filterYear.value,
        user_id: filterUserId.value || undefined,
        search: filterSearch.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const resetFilter = () => {
    filterYear.value = new Date().getFullYear().toString();
    filterUserId.value = '';
    filterSearch.value = '';
    handleFilter();
};

const formatCurrency = (amount) => {
    if (privacyShield.value) return '••••••';
    return '$' + Number(amount || 0).toLocaleString();
};

// 薪資單建立/編輯 Modal
const showFormModal = ref(false);
const editingPayslip = ref(null);

const form = useForm({
    user_id: '',
    period: new Date().toISOString().substring(0, 7),
    payment_date: new Date().toISOString().substring(0, 10),
    base_salary: 0,
    allowance: 0,
    bonus: 0,
    overtime_pay: 0,
    other_additions: 0,
    labor_insurance: 0,
    health_insurance: 0,
    pension_self: 0,
    tax_withholding: 0,
    other_deductions: 0,
    note: '',
    status: 'draft',
});

// 即時預覽計算
const previewGross = computed(() => {
    return Number(form.base_salary || 0) +
        Number(form.allowance || 0) +
        Number(form.bonus || 0) +
        Number(form.overtime_pay || 0) +
        Number(form.other_additions || 0);
});

const previewDeductions = computed(() => {
    return Number(form.labor_insurance || 0) +
        Number(form.health_insurance || 0) +
        Number(form.pension_self || 0) +
        Number(form.tax_withholding || 0) +
        Number(form.other_deductions || 0);
});

const previewNet = computed(() => {
    return previewGross.value - previewDeductions.value;
});

const openCreateModal = () => {
    editingPayslip.value = null;
    form.reset();
    form.period = new Date().toISOString().substring(0, 7);
    form.payment_date = new Date().toISOString().substring(0, 10);
    form.status = 'draft';
    showFormModal.value = true;
};

const openEditModal = (payslip) => {
    editingPayslip.value = payslip;
    form.user_id = payslip.user_id;
    form.period = payslip.period;
    form.payment_date = payslip.payment_date ? payslip.payment_date.substring(0, 10) : '';
    form.base_salary = payslip.base_salary;
    form.allowance = payslip.allowance;
    form.bonus = payslip.bonus;
    form.overtime_pay = payslip.overtime_pay;
    form.other_additions = payslip.other_additions;
    form.labor_insurance = payslip.labor_insurance;
    form.health_insurance = payslip.health_insurance;
    form.pension_self = payslip.pension_self;
    form.tax_withholding = payslip.tax_withholding;
    form.other_deductions = payslip.other_deductions;
    form.note = payslip.note || '';
    form.status = payslip.status;
    showFormModal.value = true;
};

const submitForm = () => {
    if (editingPayslip.value) {
        form.put(route('payslips.update', editingPayslip.value.id), {
            onSuccess: () => {
                showFormModal.value = false;
            },
        });
    } else {
        form.post(route('payslips.store'), {
            onSuccess: () => {
                showFormModal.value = false;
            },
        });
    }
};

const deletePayslip = (payslip) => {
    if (confirm(`確定要刪除 ${payslip.period} (${payslip.user?.name}) 的薪資單嗎？`)) {
        router.delete(route('payslips.destroy', payslip.id), {
            preserveScroll: true,
        });
    }
};

// 批次發布 Modal
const showBatchModal = ref(false);
const batchForm = useForm({
    period: new Date().toISOString().substring(0, 7),
});

const openBatchModal = () => {
    batchForm.period = new Date().toISOString().substring(0, 7);
    showBatchModal.value = true;
};

const submitBatchPublish = () => {
    if (confirm(`確定要一鍵發布 [${batchForm.period}] 全體同仁的薪資單並發送站內通知嗎？`)) {
        batchForm.post(route('payslips.publishBatch'), {
            onSuccess: () => {
                showBatchModal.value = false;
            },
        });
    }
};
</script>

<template>
    <Head title="個人薪資明細與存摺" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>薪資明細存摺 (Payslips)</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        機密薪酬存摺、歷年發放清冊與法定扣繳明細
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- 辦公室防窺遮罩模式按鈕 -->
                    <button
                        @click="privacyShield = !privacyShield"
                        type="button"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border transition shadow-2xs',
                            privacyShield
                                ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-700'
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:bg-gray-50'
                        ]"
                        title="在辦公室遮蔽金額避免身旁路過同仁窺視"
                    >
                        <svg v-if="privacyShield" class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                        <svg v-else class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>{{ privacyShield ? '已開啟防窺遮罩' : '防窺遮罩模式' }}</span>
                    </button>

                    <!-- HR/Admin 管理按鈕 -->
                    <template v-if="isHrAdmin">
                        <button
                            @click="openBatchModal"
                            type="button"
                            class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition shadow-2xs"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                            <span>批次發布</span>
                        </button>
                        <button
                            @click="openCreateModal"
                            type="button"
                            class="inline-flex items-center gap-1 px-3.5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            <span>新增薪資單</span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- 隱私安全告示 -->
                <div class="p-4 rounded-xl bg-slate-900 text-white flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-sm border border-slate-800">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-emerald-400 tracking-wider">機密安全機制與審計保障</p>
                            <p class="text-xs text-gray-300 mt-0.5">薪資明細僅限本人及授權人資主管調閱；每次查閱均已記錄於系統稽核紀錄（AuditLog）。</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs text-gray-400">目前顯示年度：</span>
                        <select
                            v-model="filterYear"
                            @change="handleFilter"
                            class="text-xs rounded-lg bg-slate-800 border-slate-700 text-white focus:ring-emerald-500 focus:border-emerald-500 py-1.5"
                        >
                            <option v-for="yr in years" :key="yr" :value="yr.toString()">{{ yr }} 年度</option>
                        </select>
                    </div>
                </div>

                <!-- 統計數據看板 (該年度) -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <p class="text-xs text-gray-500 dark:text-gray-400">年度實發累計 (Net Pay)</p>
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-1">
                            {{ formatCurrency(stats?.total_net) }}
                        </p>
                        <p class="text-[11px] text-gray-400 mt-1">扣除各項法定義務後實收</p>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <p class="text-xs text-gray-500 dark:text-gray-400">年度應發總合 (Gross Pay)</p>
                        <p class="text-2xl font-black text-blue-600 dark:text-blue-400 font-mono mt-1">
                            {{ formatCurrency(stats?.total_gross) }}
                        </p>
                        <p class="text-[11px] text-gray-400 mt-1">含本薪、獎金、職務加給</p>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <p class="text-xs text-gray-500 dark:text-gray-400">法定扣繳總合 (Deductions)</p>
                        <p class="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono mt-1">
                            {{ formatCurrency(stats?.total_deductions) }}
                        </p>
                        <p class="text-[11px] text-gray-400 mt-1">勞健保、所得稅代扣、勞退</p>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <p class="text-xs text-gray-500 dark:text-gray-400">累計發放期數</p>
                        <p class="text-2xl font-black text-purple-600 dark:text-purple-400 font-mono mt-1">
                            {{ stats?.count || 0 }} <span class="text-xs font-normal text-gray-400">期</span>
                        </p>
                        <p class="text-[11px] text-gray-400 mt-1">{{ filterYear }} 年度已核發薪資單</p>
                    </div>
                </div>

                <!-- 搜尋與過濾器 (HR/Admin 視圖具備同仁篩選) -->
                <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                        <!-- HR/Admin 員工篩選器 -->
                        <div v-if="isHrAdmin" class="w-full md:w-48">
                            <select
                                v-model="filterUserId"
                                @change="handleFilter"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 py-2 focus:ring-emerald-500"
                            >
                                <option value="">全體同仁薪資條</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">
                                    {{ u.name }} ({{ u.department?.name || '無部門' }})
                                </option>
                            </select>
                        </div>

                        <div class="relative w-full md:w-64">
                            <input
                                v-model="filterSearch"
                                @keyup.enter="handleFilter"
                                type="text"
                                placeholder="搜尋週期 (如 2026-10)..."
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 pl-8 pr-3 py-2 focus:ring-emerald-500"
                            />
                            <svg class="w-4 h-4 absolute left-2.5 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>

                        <button
                            @click="handleFilter"
                            type="button"
                            class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 transition"
                        >
                            篩選
                        </button>
                        <button
                            @click="resetFilter"
                            type="button"
                            class="px-2.5 py-2 text-xs text-gray-500 hover:text-gray-700 transition"
                        >
                            重設
                        </button>
                    </div>

                    <div class="text-xs text-gray-400">
                        共 {{ payslips.total || payslips.data?.length || 0 }} 筆紀錄
                    </div>
                </div>

                <!-- 薪資明細列表 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700/60 text-gray-500 dark:text-gray-400 uppercase border-b border-gray-100 dark:border-gray-700">
                                <tr>
                                    <th scope="col" class="py-3.5 px-4 font-semibold">發放週期</th>
                                    <th v-if="isHrAdmin" scope="col" class="py-3.5 px-4 font-semibold">員工姓名 / 部門</th>
                                    <th scope="col" class="py-3.5 px-4 font-semibold">應發金額</th>
                                    <th scope="col" class="py-3.5 px-4 font-semibold">扣除項目</th>
                                    <th scope="col" class="py-3.5 px-4 font-semibold">實發薪資 (Net)</th>
                                    <th scope="col" class="py-3.5 px-4 font-semibold">發放日期</th>
                                    <th scope="col" class="py-3.5 px-4 font-semibold">狀態</th>
                                    <th scope="col" class="py-3.5 px-4 font-semibold text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr
                                    v-for="item in payslips.data"
                                    :key="item.id"
                                    class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition"
                                >
                                    <td class="py-3.5 px-4 font-mono font-bold text-gray-900 dark:text-gray-100">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                            <span>{{ item.period_label || item.period }}</span>
                                        </div>
                                    </td>
                                    <td v-if="isHrAdmin" class="py-3.5 px-4">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ item.user?.name }}</div>
                                        <div class="text-[11px] text-gray-400">{{ item.user?.department?.name || '無部門' }} · {{ item.user?.job_title || '同仁' }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-blue-600 dark:text-blue-400 font-semibold">
                                        {{ formatCurrency(item.gross_pay) }}
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-rose-600 dark:text-rose-400 font-semibold">
                                        -{{ formatCurrency(item.total_deductions) }}
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-emerald-700 dark:text-emerald-400 font-black text-sm">
                                        {{ formatCurrency(item.net_pay) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-gray-500">
                                        {{ item.payment_date ? item.payment_date.substring(0, 10) : '—' }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-[10px] font-bold',
                                                item.status === 'published'
                                                    ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                                    : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                                            ]"
                                        >
                                            {{ item.status_label || (item.status === 'published' ? '已發布' : '草稿') }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right space-x-2">
                                        <Link
                                            :href="route('payslips.show', item.id)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-semibold hover:bg-emerald-100 transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <span>明細</span>
                                        </Link>

                                        <template v-if="isHrAdmin">
                                            <button
                                                @click="openEditModal(item)"
                                                type="button"
                                                class="px-2 py-1 text-gray-500 hover:text-blue-600 transition"
                                                title="編輯"
                                            >
                                                編輯
                                            </button>
                                            <button
                                                @click="deletePayslip(item)"
                                                type="button"
                                                class="px-2 py-1 text-gray-500 hover:text-rose-600 transition"
                                                title="刪除"
                                            >
                                                刪除
                                            </button>
                                        </template>
                                    </td>
                                </tr>
                                <tr v-if="!payslips.data || payslips.data.length === 0">
                                    <td :colspan="isHrAdmin ? 8 : 7" class="py-12 text-center text-gray-400 dark:text-gray-500">
                                        <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h2.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span>查無相關薪資單明細</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 分頁 -->
                    <div v-if="payslips.links && payslips.links.length > 3" class="p-4 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center text-xs">
                        <span class="text-gray-500">第 {{ payslips.current_page }} 頁 / 共 {{ payslips.last_page }} 頁</span>
                        <div class="flex gap-1">
                            <template v-for="(link, idx) in payslips.links" :key="idx">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'px-3 py-1 rounded transition',
                                        link.active
                                            ? 'bg-emerald-600 text-white font-bold'
                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'
                                    ]"
                                />
                                <span
                                    v-else
                                    v-html="link.label"
                                    class="px-3 py-1 text-gray-400"
                                />
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- HR/Admin 薪資單建立與編輯 Modal -->
        <div
            v-if="showFormModal"
            class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-3xl w-full p-6 space-y-5 shadow-2xl my-8">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        {{ editingPayslip ? '編輯員工薪資條' : '新增個人薪資條' }}
                    </h3>
                    <button @click="showFormModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- 基本設定 -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">員工 *</label>
                            <select
                                v-model="form.user_id"
                                required
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="" disabled>請選擇同仁</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">
                                    {{ u.name }} ({{ u.email }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">發放週期 (YYYY-MM) *</label>
                            <input
                                v-model="form.period"
                                type="text"
                                placeholder="例如 2026-10"
                                required
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">發薪日</label>
                            <input
                                v-model="form.payment_date"
                                type="date"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <!-- 應發項目 -->
                    <div class="p-3.5 rounded-xl bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/50 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-blue-700 dark:text-blue-300">應發項目 (Earnings)</span>
                            <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400">小計: ${{ previewGross.toLocaleString() }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="text-[11px] text-gray-500">底薪/本薪</label>
                                <input v-model.number="form.base_salary" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] text-gray-500">職務/專業加給</label>
                                <input v-model.number="form.allowance" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] text-gray-500">績效/業務獎金</label>
                                <input v-model.number="form.bonus" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] text-gray-500">加班費</label>
                                <input v-model.number="form.overtime_pay" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] text-gray-500">其他應發 (津貼等)</label>
                                <input v-model.number="form.other_additions" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- 應扣項目 -->
                    <div class="p-3.5 rounded-xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/50 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-rose-700 dark:text-rose-300">應扣項目 (Deductions)</span>
                            <span class="text-xs font-mono font-bold text-rose-600 dark:text-rose-400">小計: -${{ previewDeductions.toLocaleString() }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="text-[11px] text-gray-500">勞保自付額</label>
                                <input v-model.number="form.labor_insurance" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] text-gray-500">健保自付額</label>
                                <input v-model.number="form.health_insurance" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] text-gray-500">勞退自提額</label>
                                <input v-model.number="form.pension_self" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] text-gray-500">代扣所得稅</label>
                                <input v-model.number="form.tax_withholding" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] text-gray-500">其他扣款 (請假/福利)</label>
                                <input v-model.number="form.other_deductions" type="number" class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- 預覽實發與狀態 -->
                    <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-emerald-800 dark:text-emerald-300 font-bold">實發金額 (Net Pay 預覽)：</span>
                            <p class="text-2xl font-black text-emerald-700 dark:text-emerald-300 font-mono mt-0.5">
                                ${{ previewNet.toLocaleString() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 block mb-1">狀態</label>
                                <select v-model="form.status" class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <option value="draft">草稿 (同仁不可見)</option>
                                    <option value="published">立即發布 (同仁可查閱)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">備註說明 (選填)</label>
                        <input
                            v-model="form.note"
                            type="text"
                            placeholder="例如：含 Q3 績效獎金發放"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            @click="showFormModal = false"
                            class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 rounded-lg transition"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition"
                        >
                            {{ form.processing ? '儲存中...' : (editingPayslip ? '儲存變更' : '建立薪資單') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- HR/Admin 一鍵批次發布 Modal -->
        <div
            v-if="showBatchModal"
            class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
        >
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                        <span>批次發布薪資單</span>
                    </h3>
                    <button @click="showBatchModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <p class="text-xs text-gray-600 dark:text-gray-300">
                    將指定週期內所有處於「草稿」狀態的薪資單轉為「已發布」，並同時發送 EIP 系統站內通知給對應同仁。
                </p>

                <form @submit.prevent="submitBatchPublish" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">目標發放週期 (YYYY-MM) *</label>
                        <input
                            v-model="batchForm.period"
                            type="text"
                            required
                            placeholder="例如 2026-10"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showBatchModal = false"
                            class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 rounded-lg transition"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="batchForm.processing"
                            class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition"
                        >
                            {{ batchForm.processing ? '發布中...' : '確認批次發布' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
