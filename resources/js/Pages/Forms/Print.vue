<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    formRequest: Object,
    printedBy: Object,
});

const printDocument = () => {
    window.print();
};

const closeWindow = () => {
    window.close();
};

// 格式化日期時間
const formatDateTime = (isoString) => {
    if (!isoString) return '-';
    return new Date(isoString).toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};
</script>

<template>
    <Head :title="`【列印存證】${formRequest.request_no} - ${formRequest.title}`" />

    <div class="min-h-screen bg-gray-100 print:bg-white text-gray-900 font-sans p-4 sm:p-8">
        <!-- 頂部浮動操作列 (列印時隱藏) -->
        <div class="max-w-4xl mx-auto mb-6 print:hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <span class="text-sm font-bold text-gray-800">公文單據正式列印與 PDF 存證檢視</span>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        @click="closeWindow"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                    >
                        關閉視窗
                    </button>
                    <button
                        @click="printDocument"
                        class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition flex items-center space-x-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>立即列印 / 另存為 PDF</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 正式公文存證主紙張 (A4 規格適配) -->
        <div class="max-w-4xl mx-auto bg-white border border-gray-300 print:border-none shadow-lg print:shadow-none p-8 sm:p-12 relative overflow-hidden">
            <!-- 頁首：公司與公文標頭 -->
            <div class="border-b-2 border-gray-900 pb-6 mb-6">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="px-2 py-0.5 text-xs font-bold bg-gray-900 text-white rounded">EIP SYSTEM</span>
                            <span class="text-xs text-gray-500 font-mono tracking-wider">OFFICIAL APPROVAL ARCHIVE</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                            企業電子簽核公文存證聯
                        </h1>
                        <p class="text-xs text-gray-500 mt-1">
                            本聯由企業資訊入口網系統自動產製，經由主管多級核准，具備正式電子稽核效力。
                        </p>
                    </div>

                    <!-- 單號資訊卡 -->
                    <div class="text-right font-mono">
                        <div class="text-xs text-gray-500 uppercase">公文編號 / REQUEST NO.</div>
                        <div class="text-lg sm:text-xl font-extrabold text-gray-900 tracking-wide mt-0.5">
                            {{ formRequest.request_no }}
                        </div>
                        <div class="text-[11px] text-gray-500 mt-1">
                            單據類別：<span class="font-bold text-gray-800">{{ formRequest.form?.name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 基本資訊表 (申請同仁與狀態) -->
            <div class="border border-gray-300 mb-6 text-xs">
                <div class="grid grid-cols-4 bg-gray-50 border-b border-gray-300 font-bold text-gray-700 py-2 px-3">
                    <span>申請同仁</span>
                    <span>所屬部門 / 職稱</span>
                    <span>申請時間</span>
                    <span>單據狀態</span>
                </div>
                <div class="grid grid-cols-4 py-2.5 px-3 items-center">
                    <span class="font-bold text-gray-900 text-sm">
                        {{ formRequest.user?.name }}
                        <span class="text-[11px] text-gray-500 font-normal block font-mono">工號: {{ formRequest.user?.employee_no || '-' }}</span>
                    </span>
                    <span class="text-gray-700">
                        {{ formRequest.user?.department?.name || '公司同仁' }}
                        <span class="text-gray-500 block">{{ formRequest.user?.job_title || '同仁' }}</span>
                    </span>
                    <span class="font-mono text-gray-600">
                        {{ formatDateTime(formRequest.created_at) }}
                    </span>
                    <div>
                        <span
                            :class="[
                                'inline-block px-2.5 py-1 text-xs font-bold rounded',
                                formRequest.status === 'approved' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' :
                                formRequest.status === 'rejected' ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-amber-100 text-amber-800 border border-amber-300'
                            ]"
                        >
                            {{ formRequest.status === 'approved' ? '審核通過 (APPROVED)' : (formRequest.status === 'rejected' ? '已駁回 (REJECTED)' : '簽核中 (PENDING)') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- 單據主旨與動態欄位內容 -->
            <div class="space-y-4 mb-8">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">公文申請主旨</label>
                    <div class="p-3 bg-gray-50 border border-gray-200 rounded font-bold text-sm text-gray-900">
                        {{ formRequest.title }}
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">表單明細內容清單</label>
                    <table class="w-full text-xs border border-gray-300 border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-300 text-left font-bold text-gray-700">
                                <th class="p-2.5 w-1/3 border-r border-gray-300">欄位名稱 (Field)</th>
                                <th class="p-2.5">填寫內容 (Content)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(val, key) in formRequest.data" :key="key">
                                <td class="p-2.5 font-semibold text-gray-600 bg-gray-50/50 border-r border-gray-300 capitalize">
                                    {{ key }}
                                </td>
                                <td class="p-2.5 font-medium text-gray-900 whitespace-pre-line">
                                    {{ val }}
                                </td>
                            </tr>
                            <tr v-if="!formRequest.data || Object.keys(formRequest.data).length === 0">
                                <td colspan="2" class="p-4 text-center text-gray-400">無特別自訂欄位資料</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 檢附證明文件與附件清單 (列印存證留痕) -->
            <div v-if="formRequest.attachments && formRequest.attachments.length > 0" class="mb-8">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">
                    檢附證明文件清單 (Attachments Archive)
                </label>
                <table class="w-full text-xs border border-gray-300 border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b border-gray-300 text-left font-bold text-gray-700">
                            <th class="p-2 border-r border-gray-300 text-center w-12">項次</th>
                            <th class="p-2 border-r border-gray-300">檔案名稱 (Filename)</th>
                            <th class="p-2 border-r border-gray-300 w-28 text-right">檔案大小</th>
                            <th class="p-2 w-48 font-mono">上傳存檔時間</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="(file, idx) in formRequest.attachments" :key="idx">
                            <td class="p-2 text-center font-bold font-mono border-r border-gray-300 bg-gray-50">
                                {{ idx + 1 }}
                            </td>
                            <td class="p-2 font-medium text-gray-900 border-r border-gray-300">
                                {{ file.name }}
                            </td>
                            <td class="p-2 text-right font-mono text-gray-600 border-r border-gray-300">
                                {{ formatSize(file.size) }}
                            </td>
                            <td class="p-2 font-mono text-gray-500 text-[11px]">
                                {{ formatDateTime(file.uploaded_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 簽核審查軌跡紀錄表 -->
            <div class="mb-12">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">
                    主管審核簽署歷程與審查意見 (Approval Trail)
                </label>
                <table class="w-full text-xs border border-gray-300 border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b border-gray-300 text-left font-bold text-gray-700">
                            <th class="p-2 border-r border-gray-300 text-center w-12">關卡</th>
                            <th class="p-2 border-r border-gray-300 w-36">審核人員</th>
                            <th class="p-2 border-r border-gray-300 w-24">審核結果</th>
                            <th class="p-2 border-r border-gray-300 w-40">審定時間</th>
                            <th class="p-2">審查意見 / 附言</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="record in formRequest.approval_records" :key="record.id">
                            <td class="p-2 text-center font-bold font-mono border-r border-gray-300 bg-gray-50">
                                {{ record.step }}
                            </td>
                            <td class="p-2 border-r border-gray-300">
                                <div class="font-bold text-gray-900">{{ record.approver?.name || '系統主管' }}</div>
                                <div v-if="record.delegated_from" class="text-[10px] text-purple-700 font-semibold">
                                    (由 {{ record.delegated_from?.name }} 授權代理簽核)
                                </div>
                            </td>
                            <td class="p-2 border-r border-gray-300 font-bold">
                                <span v-if="record.status === 'approved'" class="text-emerald-700">核准通過</span>
                                <span v-else-if="record.status === 'rejected'" class="text-rose-700">審批駁回</span>
                                <span v-else class="text-amber-600">等待審核</span>
                            </td>
                            <td class="p-2 border-r border-gray-300 font-mono text-gray-600">
                                {{ record.actioned_at ? formatDateTime(record.actioned_at) : '-' }}
                            </td>
                            <td class="p-2 text-gray-700">
                                {{ record.comment || '無附言說明' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 電子核准防偽印章 (若已核准時印出) -->
            <div
                v-if="formRequest.status === 'approved'"
                class="absolute right-12 bottom-20 w-32 h-32 rounded-full border-4 border-rose-600 border-dashed text-rose-600 flex flex-col items-center justify-center -rotate-12 select-none opacity-85 pointer-events-none"
            >
                <div class="text-[10px] font-bold tracking-widest uppercase">EIP PORTAL</div>
                <div class="text-base font-extrabold tracking-wider my-0.5 border-y border-rose-500 py-0.5 px-2">
                    電子核准章
                </div>
                <div class="text-[9px] font-mono font-bold tracking-tight">
                    {{ formatDateTime(formRequest.updated_at).slice(0, 10) }}
                </div>
            </div>

            <!-- 頁尾：資安防偽與列印簽署註記 -->
            <div class="border-t border-gray-300 pt-4 text-[10px] text-gray-500 flex flex-col sm:flex-row items-center justify-between gap-2 font-mono">
                <div>
                    調閱人員：{{ printedBy.name }} ({{ printedBy.department }}) · 列印時間：{{ printedBy.printed_at }}
                </div>
                <div>
                    SECURITY SEAL: {{ formRequest.request_no }}-HASH-SECURED
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4;
        margin: 15mm;
    }
    body {
        background: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
