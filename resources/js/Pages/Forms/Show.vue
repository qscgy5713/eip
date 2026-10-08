<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    formRequest: Object,
    canApprove: Boolean,
});

const approvalForm = useForm({
    status: 'approved',
    comment: '',
});

const handleAction = (status) => {
    approvalForm.status = status;
    approvalForm.post(route('forms.action', props.formRequest.id));
};

const statusBadge = (status) => {
    switch (status) {
        case 'approved': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'rejected': return 'bg-rose-100 text-rose-800 border-rose-200';
        default: return 'bg-amber-100 text-amber-800 border-amber-200';
    }
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};
</script>

<template>
    <Head :title="formRequest.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-3">
                <Link :href="route('forms.index')" class="text-sm text-blue-600 hover:underline">&larr; 返回簽核中心</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-bold leading-tight text-gray-800 truncate">申請單明細</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <!-- 單據主要內容 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 space-y-6">
                    <div class="flex items-start justify-between border-b border-gray-100 pb-5">
                        <div>
                            <span class="text-xs font-mono font-semibold text-gray-400 block mb-1">{{ formRequest.request_no }}</span>
                            <h1 class="text-2xl font-extrabold text-gray-900">{{ formRequest.title }}</h1>
                            <div class="flex items-center space-x-4 text-xs text-gray-500 mt-2">
                                <span>申請人：<strong class="text-gray-800">{{ formRequest.user?.name }}</strong></span>
                                <span>所屬部門：{{ formRequest.user?.department?.name || '無' }}</span>
                                <span>送出時間：{{ new Date(formRequest.created_at).toLocaleString() }}</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2.5 shrink-0">
                            <a
                                :href="route('forms.print', formRequest.id)"
                                target="_blank"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition"
                                title="開新分頁列印或另存為 PDF"
                            >
                                <svg class="w-3.5 h-3.5 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                列印存證 / PDF
                            </a>
                            <span :class="['px-3 py-1.5 text-xs font-bold rounded-lg border', statusBadge(formRequest.status)]">
                                {{ formRequest.status === 'approved' ? '已核准' : (formRequest.status === 'rejected' ? '已駁回' : '審批中') }}
                            </span>
                        </div>
                    </div>

                    <!-- 動態欄位展示 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50/70 p-5 rounded-lg border border-gray-100">
                        <div v-for="(val, key) in formRequest.data" :key="key" class="space-y-0.5">
                            <p class="text-xs font-semibold text-gray-400 capitalize">{{ key }}</p>
                            <p class="text-sm font-medium text-gray-900 whitespace-pre-line">{{ val }}</p>
                        </div>
                    </div>

                    <!-- 檢附證明文件與附件清單 -->
                    <div v-if="formRequest.attachments && formRequest.attachments.length > 0" class="pt-4 border-t border-gray-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-800 flex items-center space-x-1.5">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span>檢附證明文件 / 附件 ({{ formRequest.attachments.length }})</span>
                            </h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div
                                v-for="(file, idx) in formRequest.attachments"
                                :key="idx"
                                class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-gray-50/60 hover:bg-white hover:border-blue-300 transition"
                            >
                                <div class="flex items-center space-x-3 min-w-0 mr-3">
                                    <div class="p-2 bg-blue-50 text-blue-600 rounded-lg flex-shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="truncate">
                                        <p class="text-sm font-semibold text-gray-800 truncate" :title="file.name">{{ file.name }}</p>
                                        <p class="text-xs text-gray-400">{{ formatSize(file.size) }}</p>
                                    </div>
                                </div>
                                <a
                                    :href="route('forms.attachments.download', [formRequest.id, idx])"
                                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-md transition shrink-0"
                                    download
                                >
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    下載
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 主管審批操作區塊 (僅當使用者為當前審批主管且單據審批中) -->
                <div v-if="canApprove && formRequest.status === 'pending'" class="bg-amber-50/80 border border-amber-200 rounded-xl p-6 space-y-4">
                    <h3 class="font-bold text-amber-900 text-base flex items-center space-x-2">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        <span>簽核審批動作</span>
                    </h3>
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-600">審核意見 / 備註</label>
                        <textarea
                            v-model="approvalForm.comment"
                            rows="2"
                            placeholder="請輸入審核意見（可選）..."
                            class="w-full text-sm rounded-lg border-amber-200 focus:border-amber-500 focus:ring-amber-500 bg-white"
                        ></textarea>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button
                            type="button"
                            @click="handleAction('approved')"
                            :disabled="approvalForm.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold text-sm rounded-lg shadow-sm transition flex items-center space-x-1.5"
                        >
                            <svg v-if="approvalForm.processing && approvalForm.status === 'approved'" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>同意核准</span>
                        </button>
                        <button
                            type="button"
                            @click="handleAction('rejected')"
                            :disabled="approvalForm.processing"
                            class="px-5 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white font-bold text-sm rounded-lg shadow-sm transition flex items-center space-x-1.5"
                        >
                            <svg v-if="approvalForm.processing && approvalForm.status === 'rejected'" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>退回駁回</span>
                        </button>
                    </div>
                </div>

                <!-- 簽核歷程軌跡 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <h3 class="font-bold text-gray-900 text-base">簽核進度與歷程軌跡</h3>
                    <div class="space-y-4 border-l-2 border-gray-200 ml-3 pl-4">
                        <!-- 申請送出節點 -->
                        <div class="relative">
                            <span class="absolute -left-[23px] top-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-white"></span>
                            <p class="text-sm font-semibold text-gray-900">單據送出</p>
                            <p class="text-xs text-gray-400">{{ formRequest.user?.name }} · {{ new Date(formRequest.created_at).toLocaleString() }}</p>
                        </div>

                        <!-- 各審核關卡 -->
                        <div v-for="rec in formRequest.approval_records" :key="rec.id" class="relative">
                            <span
                                :class="[
                                    'absolute -left-[23px] top-1 w-3 h-3 rounded-full border-2 border-white',
                                    rec.status === 'approved' ? 'bg-emerald-500' : (rec.status === 'rejected' ? 'bg-rose-500' : 'bg-amber-400')
                                ]"
                            ></span>
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <p class="text-sm font-semibold text-gray-900">
                                    關卡 {{ rec.step }}：
                                    <template v-if="rec.delegated_from">
                                        代理人代簽（{{ rec.approver?.name }}，原主管：{{ rec.delegated_from?.name }}）
                                    </template>
                                    <template v-else>
                                        主管審核（{{ rec.approver?.name }}）
                                    </template>
                                </p>
                                <span :class="['px-2 py-0.5 text-xs rounded', statusBadge(rec.status)]">
                                    {{ rec.status === 'approved' ? '核准' : (rec.status === 'rejected' ? '駁回' : '待審批') }}
                                </span>
                                <span v-if="rec.delegated_from" class="px-2 py-0.5 text-[10px] font-bold rounded bg-purple-100 text-purple-700">
                                    職務代理代簽
                                </span>
                            </div>
                            <p v-if="rec.comment" class="text-xs text-gray-600 mt-1 bg-gray-50 p-2 rounded border border-gray-100">意見：{{ rec.comment }}</p>
                            <p v-if="rec.actioned_at" class="text-xs text-gray-400 mt-0.5">{{ new Date(rec.actioned_at).toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
