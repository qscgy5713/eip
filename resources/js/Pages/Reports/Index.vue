<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    report: { type: Object, required: true },
    filters: { type: Object, required: true },
    departments: { type: Array, required: true },
    canPickDepartment: { type: Boolean, required: true },
});

const apply = (patch) => {
    const q = { month: props.filters.month, department_id: props.filters.department_id || undefined, ...patch };
    if (!q.department_id) delete q.department_id;
    router.get(route('reports.index'), q, { preserveScroll: true, preserveState: true });
};

const exportUrl = computed(() => route('reports.export', {
    month: props.filters.month,
    ...(props.filters.department_id ? { department_id: props.filters.department_id } : {}),
}));

const t = computed(() => props.report.totals);
const maxLeave = computed(() => Math.max(1, ...props.report.leave_by_type.map((x) => x.days)));
const maxExpense = computed(() => Math.max(1, ...props.report.expense_by_type.map((x) => x.amount)));
const money = (n) => Number(n || 0).toLocaleString('zh-TW');

const statusLabels = {
    pending: '簽核中', approved: '已核准', rejected: '已駁回', revision_required: '退回修改中', withdrawn: '已撤回',
};
const totalRequests = computed(() => Object.values(props.report.status_counts).reduce((a, b) => a + b, 0));
</script>

<template>
    <Head title="表單統計報表" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">表單統計報表</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">請假、加班、費用報銷與請購的月統計,只計算已核准的單據。</p>
                </div>
                <a :href="exportUrl" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">匯出本月報表 (CSV)</a>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- 篩選 -->
                <div class="flex flex-wrap items-end gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                    <div>
                        <label for="rpt_month" class="block text-xs font-medium text-gray-600 dark:text-gray-400">月份</label>
                        <input id="rpt_month" type="month" :value="filters.month" @change="apply({ month: $event.target.value })"
                            class="mt-1 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label for="rpt_dept" class="block text-xs font-medium text-gray-600 dark:text-gray-400">部門</label>
                        <select id="rpt_dept" :value="filters.department_id ?? ''" :disabled="!canPickDepartment" @change="apply({ department_id: $event.target.value })"
                            class="mt-1 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            <option v-if="canPickDepartment" value="">全公司</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <p v-if="!canPickDepartment" class="pb-2 text-xs text-gray-500 dark:text-gray-400">部門主管僅能查看所屬部門。</p>
                </div>

                <!-- 摘要 -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">請假天數</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ t.leave_days }}</p>
                        <p class="text-xs text-gray-400">{{ t.leave_count }} 張單</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">加班時數</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ t.overtime_hours }}</p>
                        <p class="text-xs text-gray-400">{{ t.overtime_count }} 張單</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">報銷金額 (TWD)</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ money(t.expense_amount) }}</p>
                        <p class="text-xs text-gray-400">{{ t.expense_count }} 張單</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">請購預算 (TWD)</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ money(t.purchase_amount) }}</p>
                        <p class="text-xs text-gray-400">{{ t.purchase_count }} 張單</p>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- 假別 -->
                    <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">各假別請假天數</h3>
                        <p v-if="!report.leave_by_type.length" class="mt-3 text-xs text-gray-500">本月沒有已核准的請假。</p>
                        <ul class="mt-3 space-y-3">
                            <li v-for="x in report.leave_by_type" :key="x.type">
                                <div class="flex justify-between text-xs text-gray-700 dark:text-gray-300"><span>{{ x.type }}({{ x.count }} 張)</span><span>{{ x.days }} 天</span></div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-gray-700"><div class="h-2 rounded-full bg-indigo-500" :style="{ width: (x.days / maxLeave * 100) + '%' }"></div></div>
                            </li>
                        </ul>
                    </div>

                    <!-- 報銷項目 -->
                    <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">各項目報銷金額</h3>
                        <p v-if="!report.expense_by_type.length" class="mt-3 text-xs text-gray-500">本月沒有已核准的報銷。</p>
                        <ul class="mt-3 space-y-3">
                            <li v-for="x in report.expense_by_type" :key="x.type">
                                <div class="flex justify-between text-xs text-gray-700 dark:text-gray-300"><span>{{ x.type }}({{ x.count }} 張)</span><span>{{ money(x.amount) }}</span></div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-gray-700"><div class="h-2 rounded-full bg-emerald-500" :style="{ width: (x.amount / maxExpense * 100) + '%' }"></div></div>
                            </li>
                        </ul>
                    </div>

                    <!-- 狀態 -->
                    <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">本月申請單處理狀態</h3>
                        <p class="mt-1 text-xs text-gray-500">共 {{ totalRequests }} 張(所有表單種類)</p>
                        <ul class="mt-3 space-y-2 text-xs text-gray-700 dark:text-gray-300">
                            <li v-for="(label, key) in statusLabels" :key="key" class="flex justify-between border-b border-gray-100 pb-1 dark:border-gray-700"><span>{{ label }}</span><span>{{ report.status_counts[key] }}</span></li>
                        </ul>
                    </div>
                </div>

                <!-- 個人明細 -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">個人明細</div>
                    <p v-if="!report.rows.length" class="px-5 py-6 text-sm text-gray-500">這個月份與範圍沒有已核准的請假、加班、報銷或請購單。</p>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr><th class="px-4 py-2">姓名</th><th class="px-4 py-2">部門</th><th class="px-4 py-2 text-right">請假天數</th><th class="px-4 py-2 text-right">加班時數</th><th class="px-4 py-2 text-right">報銷金額</th><th class="px-4 py-2 text-right">請購預算</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-800 dark:divide-gray-700 dark:text-gray-200">
                                <tr v-for="r in report.rows" :key="r.user_id">
                                    <td class="px-4 py-2">{{ r.name }}<span v-if="r.employee_no" class="ml-1 text-gray-400">{{ r.employee_no }}</span></td>
                                    <td class="px-4 py-2">{{ r.department || '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ r.leave_days || '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ r.overtime_hours || '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ r.expense_amount ? money(r.expense_amount) : '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ r.purchase_amount ? money(r.purchase_amount) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
