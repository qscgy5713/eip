<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    payslip: Object,
    isHrAdmin: Boolean,
});

const privacyShield = ref(false);

const formatCurrency = (amount) => {
    if (privacyShield.value) return '••••••';
    return '$' + Number(amount || 0).toLocaleString();
};

const handlePrint = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${payslip.period_label || payslip.period} 薪資單明細 - ${payslip.user?.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="print:hidden flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('payslips.index')"
                        class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-gray-700 transition"
                        title="返回薪資存摺"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    </Link>
                    <div>
                        <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                            <span>{{ payslip.period_label || payslip.period }} 機密薪資條</span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-xs font-bold',
                                    payslip.status === 'published'
                                        ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                                        : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'
                                ]"
                            >
                                {{ payslip.status_label || (payslip.status === 'published' ? '已發布' : '草稿') }}
                            </span>
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            同仁：{{ payslip.user?.name }} ({{ payslip.user?.email }})
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- 防窺模式按鈕 -->
                    <button
                        @click="privacyShield = !privacyShield"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:bg-gray-50 shadow-2xs transition"
                    >
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>{{ privacyShield ? '顯示金額' : '防窺遮罩' }}</span>
                    </button>

                    <!-- A4 正式列印 / 存為 PDF 按鈕 -->
                    <button
                        @click="handlePrint"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>列印 / 儲存 PDF</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6 px-4">

                <!-- 正式薪資單 A4 憑單容器 -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 sm:p-12 print:p-0 print:border-none print:shadow-none print:bg-white text-gray-800 dark:text-gray-200 print:text-black">

                    <!-- 表頭：公司抬頭與單據資訊 -->
                    <div class="border-b-2 border-gray-900 dark:border-gray-100 pb-6 mb-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white print:text-black">
                                    台灣企業雲端資訊股份有限公司
                                </h1>
                                <p class="text-sm font-bold text-gray-600 dark:text-gray-300 print:text-gray-700 mt-1">
                                    員工薪資明細單 (PAYSLIP VOUCHER)
                                </p>
                            </div>
                            <div class="text-left sm:text-right text-xs text-gray-500 dark:text-gray-400 print:text-gray-600 space-y-0.5">
                                <p>單據編號：<span class="font-mono font-bold text-gray-800 dark:text-gray-200 print:text-black">PS-{{ payslip.period.replace('-', '') }}-{{ String(payslip.id).padStart(5, '0') }}</span></p>
                                <p>發薪週期：<span class="font-bold text-emerald-600 dark:text-emerald-400 print:text-black">{{ payslip.period_label || payslip.period }}</span></p>
                                <p>發薪日期：<span class="font-mono">{{ payslip.payment_date ? payslip.payment_date.substring(0, 10) : '次月 5 日' }}</span></p>
                            </div>
                        </div>

                        <!-- 員工個人身分摘要列 -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 text-xs">
                            <div>
                                <span class="text-gray-400 block">員工姓名</span>
                                <span class="font-bold text-gray-900 dark:text-white print:text-black text-sm">{{ payslip.user?.name }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">所屬部門</span>
                                <span class="font-bold text-gray-900 dark:text-white print:text-black">{{ payslip.user?.department?.name || '研發資訊部' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">職務職稱</span>
                                <span class="font-bold text-gray-900 dark:text-white print:text-black">{{ payslip.user?.job_title || '全端資深工程師' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">員工編號 / 帳號</span>
                                <span class="font-mono text-gray-900 dark:text-white print:text-black">{{ payslip.user?.employee_id || payslip.user?.id }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 薪資明細：兩欄對帳 (應發項目 VS 應扣項目) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 my-8">

                        <!-- 左欄：應發項目 (Earnings) -->
                        <div class="rounded-xl border border-indigo-100 dark:border-indigo-900/60 overflow-hidden">
                            <div class="bg-indigo-50 dark:bg-indigo-950/40 px-4 py-2.5 border-b border-indigo-100 dark:border-indigo-900/60 flex justify-between items-center">
                                <span class="font-bold text-xs text-indigo-800 dark:text-indigo-300">應發薪資項目 (EARNINGS)</span>
                                <span class="text-[11px] text-indigo-600 dark:text-indigo-400 font-mono">NTD</span>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-gray-700 text-xs">
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">基本底薪 / 本薪</span>
                                    <span class="font-mono font-semibold">{{ formatCurrency(payslip.base_salary) }}</span>
                                </div>
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">職務專業加給</span>
                                    <span class="font-mono font-semibold">{{ formatCurrency(payslip.allowance) }}</span>
                                </div>
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">績效考核獎金</span>
                                    <span class="font-mono font-semibold">{{ formatCurrency(payslip.bonus) }}</span>
                                </div>
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">平日/假日加班費</span>
                                    <span class="font-mono font-semibold">{{ formatCurrency(payslip.overtime_pay) }}</span>
                                </div>
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">其他應發 / 補助津貼</span>
                                    <span class="font-mono font-semibold">{{ formatCurrency(payslip.other_additions) }}</span>
                                </div>
                            </div>
                            <div class="bg-indigo-50/60 dark:bg-indigo-950/20 px-4 py-3 border-t border-indigo-100 dark:border-indigo-900/60 flex justify-between items-center font-bold text-xs text-indigo-900 dark:text-indigo-200">
                                <span>應發總額 (Gross Earnings)</span>
                                <span class="font-mono text-sm text-indigo-600 dark:text-indigo-400">{{ formatCurrency(payslip.gross_pay) }}</span>
                            </div>
                        </div>

                        <!-- 右欄：應扣項目 (Deductions) -->
                        <div class="rounded-xl border border-red-100 dark:border-red-900/60 overflow-hidden">
                            <div class="bg-red-50 dark:bg-red-950/40 px-4 py-2.5 border-b border-red-100 dark:border-red-900/60 flex justify-between items-center">
                                <span class="font-bold text-xs text-red-800 dark:text-red-300">法定扣繳項目 (DEDUCTIONS)</span>
                                <span class="text-[11px] text-red-600 dark:text-red-400 font-mono">NTD</span>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-gray-700 text-xs">
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">勞工保險自付額</span>
                                    <span class="font-mono font-semibold text-red-600 dark:text-red-400">-{{ formatCurrency(payslip.labor_insurance) }}</span>
                                </div>
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">全民健保自付額</span>
                                    <span class="font-mono font-semibold text-red-600 dark:text-red-400">-{{ formatCurrency(payslip.health_insurance) }}</span>
                                </div>
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">勞工退休金自願提繳</span>
                                    <span class="font-mono font-semibold text-red-600 dark:text-red-400">-{{ formatCurrency(payslip.pension_self) }}</span>
                                </div>
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">代扣綜合所得稅額</span>
                                    <span class="font-mono font-semibold text-red-600 dark:text-red-400">-{{ formatCurrency(payslip.tax_withholding) }}</span>
                                </div>
                                <div class="px-4 py-3 flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">其他扣款 / 請假扣薪</span>
                                    <span class="font-mono font-semibold text-red-600 dark:text-red-400">-{{ formatCurrency(payslip.other_deductions) }}</span>
                                </div>
                            </div>
                            <div class="bg-red-50/60 dark:bg-red-950/20 px-4 py-3 border-t border-red-100 dark:border-red-900/60 flex justify-between items-center font-bold text-xs text-red-900 dark:text-red-200">
                                <span>扣繳總計 (Total Deductions)</span>
                                <span class="font-mono text-sm text-red-600 dark:text-red-400">-{{ formatCurrency(payslip.total_deductions) }}</span>
                            </div>
                        </div>

                    </div>

                    <!-- 實發總計結算 (Net Pay Highlight) -->
                    <div class="rounded-xl bg-gradient-to-r from-emerald-900 to-indigo-900 text-white p-6 my-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm print:bg-none print:border-2 print:border-black print:text-black">
                        <div>
                            <span class="text-xs font-bold text-emerald-300 print:text-black">實際發放金額 (NET PAYABLE)</span>
                            <p class="text-xs text-emerald-100 print:text-gray-600 mt-0.5">應發金額減除各項法定扣繳後之轉帳淨額</p>
                        </div>
                        <div class="text-right">
                            <span class="text-3xl font-black font-mono text-emerald-300 print:text-black">
                                {{ formatCurrency(payslip.net_pay) }}
                            </span>
                            <span class="text-xs text-emerald-200 print:text-black ml-1">TWD</span>
                        </div>
                    </div>

                    <!-- 備註說明區塊 -->
                    <div v-if="payslip.note" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-700 text-xs my-4">
                        <span class="font-bold text-gray-700 dark:text-gray-300">人事備註說明：</span>
                        <p class="text-gray-600 dark:text-gray-400 mt-1 whitespace-pre-wrap">{{ payslip.note }}</p>
                    </div>

                    <!-- 機密提示與簽章戳記 (正式列印視圖) -->
                    <div class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs text-gray-500">
                        <div>
                            <p class="font-bold text-gray-700 dark:text-gray-300 print:text-black">製表人 / 人資經辦</p>
                            <p class="mt-4 font-serif text-gray-400 italic">EIP 系統電子簽核核定</p>
                        </div>
                        <div>
                            <p class="font-bold text-gray-700 dark:text-gray-300 print:text-black">財務覆核</p>
                            <p class="mt-4 font-serif text-gray-400 italic">財務部線上覆核章</p>
                        </div>
                        <div>
                            <p class="font-bold text-gray-700 dark:text-gray-300 print:text-black">同仁收執</p>
                            <p class="mt-4 font-serif text-gray-400 italic">電子存摺自動派發交付</p>
                        </div>
                    </div>

                    <div class="mt-6 text-center text-[11px] text-gray-400 print:text-gray-500">
                        ※ 本薪資明細單屬高度個人隱私機密文件，請妥善保管。如有明細疑問，請於發薪日起七日內向人資單位提出複查。
                    </div>

                </div>

            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    /* 列印時隱藏頂部與底部的導覽元素 */
    nav, header, footer, .print\:hidden {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
    }
}
</style>
