<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    formRequest: Object,
    canApprove: Boolean,
    currentPendingRecord: Object,
    activeUsers: {
        type: Array,
        default: () => [],
    },
});

const approvalForm = useForm({
    status: 'approved',
    comment: '',
});

const handleAction = (status) => {
    approvalForm.status = status;
    approvalForm.post(route('forms.action', props.formRequest.id));
};

// 轉簽派審 Modal
const showTransferModal = ref(false);
const transferForm = useForm({
    target_user_id: '',
    reason: '',
});

const openTransferModal = () => {
    transferForm.reset();
    transferForm.clearErrors();
    showTransferModal.value = true;
};

const submitTransfer = () => {
    transferForm.post(route('forms.transfer', props.formRequest.id), {
        onSuccess: () => {
            showTransferModal.value = false;
            transferForm.reset();
        },
    });
};

// 會辦加簽 Modal
const showAddSignModal = ref(false);
const addSignForm = useForm({
    target_user_id: '',
    reason: '',
});

const openAddSignModal = () => {
    addSignForm.reset();
    addSignForm.clearErrors();
    showAddSignModal.value = true;
};

const submitAddSign = () => {
    addSignForm.post(route('forms.add-sign', props.formRequest.id), {
        onSuccess: () => {
            showAddSignModal.value = false;
            addSignForm.reset();
        },
    });
};

// 申請人撤回 Modal
const showWithdrawModal = ref(false);
const withdrawForm = useForm({
    reason: '',
});

const openWithdrawModal = () => {
    withdrawForm.reset();
    withdrawForm.clearErrors();
    showWithdrawModal.value = true;
};

const submitWithdraw = () => {
    withdrawForm.post(route('forms.withdraw', props.formRequest.id), {
        onSuccess: () => {
            showWithdrawModal.value = false;
            withdrawForm.reset();
        },
    });
};

// 主管退回修改 Modal
const showRevisionModal = ref(false);
const openRevisionModal = () => {
    showRevisionModal.value = true;
};

const submitRevision = () => {
    handleAction('revision_required');
    showRevisionModal.value = false;
};

// 申請人修改表單並重新提交 Modal
const showResubmitModal = ref(false);
const resubmitForm = useForm({
    data: { ...(props.formRequest.data || {}) },
    resubmit_note: '',
    attachments: [],
});

const openResubmitModal = () => {
    resubmitForm.data = { ...(props.formRequest.data || {}) };
    resubmitForm.resubmit_note = '';
    resubmitForm.attachments = [];
    resubmitForm.clearErrors();
    showResubmitModal.value = true;
};

const handleResubmitFileChange = (e) => {
    resubmitForm.attachments = Array.from(e.target.files);
};

const submitResubmit = () => {
    resubmitForm.post(route('forms.requests.resubmit', props.formRequest.id), {
        onSuccess: () => {
            showResubmitModal.value = false;
        },
    });
};

const statusBadge = (status) => {
    switch (status) {
        case 'approved': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'rejected': return 'bg-rose-100 text-rose-800 border-rose-200';
        case 'transferred': return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'withdrawn': return 'bg-gray-100 text-gray-700 border-gray-300';
        case 'revision_required': return 'bg-amber-100 text-amber-800 border-amber-300';
        default: return 'bg-blue-100 text-blue-800 border-blue-200';
    }
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

// 歷史版本修訂對照 (Diff Viewer)
const showDiffViewer = ref(false);
const activeDiffVersion = ref(null);

const getChangedFields = (rev) => {
    if (!rev) return [];
    const prev = rev.previous_data || {};
    const curr = rev.new_data || {};
    const allKeys = Array.from(new Set([...Object.keys(prev), ...Object.keys(curr)]));
    const changes = [];
    for (const key of allKeys) {
        const oldVal = prev[key] !== undefined && prev[key] !== null ? String(prev[key]) : '(未填寫)';
        const newVal = curr[key] !== undefined && curr[key] !== null ? String(curr[key]) : '(未填寫)';
        if (oldVal !== newVal) {
            changes.push({
                key,
                oldVal,
                newVal,
            });
        }
    }
    return changes;
};

const openDiffViewer = (rev = null) => {
    if (rev) {
        activeDiffVersion.value = rev;
    } else if (props.formRequest.revision_history && props.formRequest.revision_history.length > 0) {
        activeDiffVersion.value = props.formRequest.revision_history[props.formRequest.revision_history.length - 1];
    }
    showDiffViewer.value = true;
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
                <!-- 提示/成功訊息 -->
                <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center text-emerald-800 shadow-sm text-sm">
                    <svg class="w-5 h-5 text-emerald-600 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <!-- 單據已撤回提示橫幅 -->
                <div v-if="formRequest.status === 'withdrawn'" class="p-4 bg-gray-50 border border-gray-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-gray-700 shadow-sm text-sm">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>此申請單已由申請人撤回並作廢。相關凍結之休假額度已全數釋放恢復。</span>
                    </div>
                    <Link
                        v-if="formRequest.user_id === $page.props.auth.user.id"
                        :href="route('forms.create', { form: formRequest.form_id, copy_from: formRequest.id })"
                        class="text-xs font-semibold text-blue-600 underline hover:text-blue-800 shrink-0"
                    >
                        複製內容重新申請 &rarr;
                    </Link>
                </div>

                <!-- 單據已被退回修改提示橫幅 -->
                <div v-if="formRequest.status === 'revision_required'" class="p-4 sm:p-5 bg-amber-50 border border-amber-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-amber-900 shadow-sm text-sm">
                    <div class="flex items-start space-x-3">
                        <div class="p-2 bg-amber-100 rounded-lg text-amber-700 shrink-0 mt-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-amber-900">審核主管退回修改通知</h4>
                            <p class="text-xs text-amber-800 mt-0.5">
                                此申請單已被主管退回修改。請檢視簽核歷程中的退回審核意見，修正表單內容或補充證明檔案後重新提交審查。
                            </p>
                        </div>
                    </div>
                    <button
                        v-if="formRequest.user_id === $page.props.auth.user.id"
                        @click="openResubmitModal"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-sm transition shrink-0 flex items-center space-x-1.5 self-start sm:self-center"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>修改表單並重新提交</span>
                    </button>
                </div>

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
                        <div class="flex items-center space-x-2.5 shrink-0 flex-wrap gap-y-2">
                            <a
                                :href="route('forms.print', formRequest.id)"
                                target="_blank"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition"
                                title="開新分頁列印或另存為 PDF"
                            >
                                <svg class="w-3.5 h-3.5 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                列印存證 / PDF
                            </a>

                            <!-- 複製重新申請按鈕 (已撤回或已退件時同仁可一鍵複製) -->
                            <Link
                                v-if="formRequest.user_id === $page.props.auth.user.id && (formRequest.status === 'withdrawn' || formRequest.status === 'rejected')"
                                :href="route('forms.create', { form: formRequest.form_id, copy_from: formRequest.id })"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg shadow-sm hover:bg-blue-100 transition"
                                title="複製原單據內容發起新申請"
                            >
                                <svg class="w-3.5 h-3.5 mr-1 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                                複製重新申請
                            </Link>

                            <!-- 主動撤回按鈕 (審批中且為申請人本人或管理員) -->
                            <button
                                v-if="formRequest.status === 'pending' && ($page.props.auth.user.id === formRequest.user_id || $page.props.auth.user.role === 'admin')"
                                type="button"
                                @click="openWithdrawModal"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg shadow-sm hover:bg-rose-100 transition"
                                title="主動撤回此申請單據並釋放額度"
                            >
                                <svg class="w-3.5 h-3.5 mr-1 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                撤回申請
                            </button>

                            <span :class="['px-3 py-1.5 text-xs font-bold rounded-lg border', statusBadge(formRequest.status)]">
                                {{ formRequest.status === 'approved' ? '已核准' : (formRequest.status === 'rejected' ? '已駁回' : (formRequest.status === 'withdrawn' ? '已撤回' : '審批中')) }}
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

                    <!-- 歷史版本修訂比對提示條 (Version Revision Diff Banner) -->
                    <div
                        v-if="formRequest.revision_history && formRequest.revision_history.length > 0"
                        class="pt-4 border-t border-gray-100"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-amber-50/80 border border-amber-200 rounded-xl gap-3">
                            <div class="flex items-start sm:items-center space-x-3">
                                <div class="p-2 bg-amber-100 text-amber-700 rounded-lg shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-amber-900">
                                        此單據曾退回修改並已重新送審（累計 {{ formRequest.revision_history.length }} 次修訂）
                                    </h4>
                                    <p class="text-xs text-amber-700 mt-0.5">
                                        最新修訂於 {{ new Date(formRequest.revision_history[formRequest.revision_history.length - 1].resubmitted_at).toLocaleString() }}，可比對欄位異動差異與補充附件。
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="openDiffViewer()"
                                class="inline-flex items-center justify-center px-3.5 py-2 text-xs font-bold text-amber-800 bg-amber-200/80 hover:bg-amber-300 rounded-lg transition shrink-0 shadow-sm cursor-pointer"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                比對歷史修訂差異 (Diff)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 多層級簽核流程進度條 (Approval Pipeline Stepper) -->
                <div v-if="formRequest.workflow_snapshot && formRequest.workflow_snapshot.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <h3 class="font-bold text-gray-900 text-sm flex items-center space-x-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <span>簽核流程進度鏈（共 {{ formRequest.total_steps || formRequest.workflow_snapshot.length }} 關）</span>
                        </h3>
                        <span class="text-xs text-gray-400">當前進度：關卡 {{ formRequest.current_step }} / {{ formRequest.total_steps || formRequest.workflow_snapshot.length }}</span>
                    </div>

                    <!-- 橫向/堆疊 Stepper 節點 -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2">
                        <div
                            v-for="(st, idx) in formRequest.workflow_snapshot"
                            :key="idx"
                            :class="[
                                'relative p-4 rounded-xl border transition flex flex-col justify-between',
                                st.step < formRequest.current_step || (st.step === formRequest.current_step && formRequest.status === 'approved')
                                    ? 'bg-emerald-50/50 border-emerald-200'
                                    : (st.step === formRequest.current_step && formRequest.status === 'pending'
                                        ? 'bg-blue-50/70 border-blue-300 ring-2 ring-blue-100'
                                        : (st.step === formRequest.current_step && formRequest.status === 'rejected'
                                            ? 'bg-rose-50/50 border-rose-200'
                                            : 'bg-gray-50/60 border-gray-200 opacity-60'))
                            ]"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center space-x-2">
                                    <span
                                        :class="[
                                            'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0',
                                            st.step < formRequest.current_step || (st.step === formRequest.current_step && formRequest.status === 'approved')
                                                ? 'bg-emerald-600'
                                                : (st.step === formRequest.current_step && formRequest.status === 'pending'
                                                    ? 'bg-blue-600 animate-pulse'
                                                    : (st.step === formRequest.current_step && formRequest.status === 'revision_required'
                                                        ? 'bg-amber-600 animate-pulse'
                                                        : (st.step === formRequest.current_step && formRequest.status === 'rejected'
                                                            ? 'bg-rose-600'
                                                            : 'bg-gray-400')))
                                        ]"
                                    >
                                        {{ st.step }}
                                    </span>
                                    <span class="font-bold text-xs text-gray-900 truncate">{{ st.title }}</span>
                                </div>
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-[10px] font-bold rounded-full',
                                        st.step < formRequest.current_step || (st.step === formRequest.current_step && formRequest.status === 'approved')
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : (st.step === formRequest.current_step && formRequest.status === 'pending'
                                                ? 'bg-blue-100 text-blue-800'
                                                : (st.step === formRequest.current_step && formRequest.status === 'revision_required'
                                                    ? 'bg-amber-100 text-amber-800'
                                                    : (st.step === formRequest.current_step && formRequest.status === 'rejected'
                                                        ? 'bg-rose-100 text-rose-800'
                                                        : 'bg-gray-200 text-gray-600')))
                                    ]"
                                >
                                    {{ st.step < formRequest.current_step || (st.step === formRequest.current_step && formRequest.status === 'approved') ? '已核准' : (st.step === formRequest.current_step && formRequest.status === 'revision_required' ? '退回補件中' : (st.step === formRequest.current_step && formRequest.status === 'rejected' ? '已退件' : (st.step === formRequest.current_step ? '審核中' : '待流轉'))) }}
                                </span>
                            </div>

                            <div class="mt-3 text-xs space-y-1">
                                <p class="text-gray-600 flex items-center space-x-1">
                                    <span class="text-gray-400">審批人：</span>
                                    <strong class="text-gray-800">{{ st.approver_name }}</strong>
                                </p>
                                <p v-if="st.condition_desc" class="text-[11px] text-gray-400 leading-tight">
                                    {{ st.condition_desc }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 主管審批操作區塊 (僅當使用者為當前審批主管且單據審批中) -->
                <div v-if="canApprove && formRequest.status === 'pending'" class="bg-amber-50/80 border border-amber-200 rounded-xl p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-amber-900 text-base flex items-center space-x-2">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            <span>{{ currentPendingRecord?.is_add_sign ? '會辦加簽意見簽署' : '簽核審批動作' }}</span>
                        </h3>
                        <span v-if="currentPendingRecord" :class="['text-xs font-semibold px-2.5 py-1 rounded-lg', currentPendingRecord.is_add_sign ? 'bg-blue-100 text-blue-900' : 'bg-amber-200/70 text-amber-900']">
                            {{ currentPendingRecord.is_add_sign ? '受託會辦加簽' : (currentPendingRecord.step_title || ('關卡 ' + currentPendingRecord.step)) }}
                        </span>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-gray-600">
                            {{ currentPendingRecord?.is_add_sign ? '會辦附言意見' : '審核意見 / 備註' }}
                        </label>
                        <textarea
                            v-model="approvalForm.comment"
                            rows="2"
                            :placeholder="currentPendingRecord?.is_add_sign ? '請輸入會辦審查意見...' : '請輸入審核意見（可選）...'"
                            class="w-full text-sm rounded-lg border-amber-200 focus:border-amber-500 focus:ring-amber-500 bg-white"
                        ></textarea>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- 一般關卡核准或加簽同意 -->
                        <button
                            type="button"
                            @click="handleAction('approved')"
                            :disabled="approvalForm.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold text-sm rounded-lg shadow-sm transition flex items-center space-x-1.5"
                        >
                            <svg v-if="approvalForm.processing && approvalForm.status === 'approved'" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ currentPendingRecord?.is_add_sign ? '簽署會辦同意' : (formRequest.current_step < (formRequest.total_steps || 1) ? '同意並流轉至下一關' : '同意核准並結案') }}</span>
                        </button>

                        <!-- 一般關卡退回或加簽保留意見 -->
                        <button
                            type="button"
                            @click="handleAction('rejected')"
                            :disabled="approvalForm.processing"
                            class="px-5 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white font-bold text-sm rounded-lg shadow-sm transition flex items-center space-x-1.5"
                        >
                            <svg v-if="approvalForm.processing && approvalForm.status === 'rejected'" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ currentPendingRecord?.is_add_sign ? '簽署保留意見' : '退件駁回' }}</span>
                        </button>

                        <!-- 退回修改按鈕 (非加簽關卡時可使用) -->
                        <button
                            v-if="!currentPendingRecord?.is_add_sign"
                            type="button"
                            @click="openRevisionModal"
                            :disabled="approvalForm.processing"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white font-bold text-sm rounded-lg shadow-sm transition flex items-center space-x-1.5"
                            title="退回申請人修改並補件重送"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            <span>退回修改</span>
                        </button>

                        <!-- 協同加簽與轉簽按鈕 (非加簽關卡時可使用) -->
                        <template v-if="!currentPendingRecord?.is_add_sign">
                            <button
                                type="button"
                                @click="openAddSignModal"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-lg shadow-sm transition flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>會辦加簽</span>
                            </button>

                            <button
                                type="button"
                                @click="openTransferModal"
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold text-sm rounded-lg shadow-sm transition flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <span>轉簽派審</span>
                            </button>
                        </template>
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
                                    rec.status === 'approved' ? 'bg-emerald-500' : (rec.status === 'rejected' ? 'bg-rose-500' : (rec.status === 'returned' ? 'bg-amber-500' : (rec.status === 'transferred' ? 'bg-purple-500' : 'bg-blue-500')))
                                ]"
                            ></span>
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ rec.step_title ? rec.step_title : ('關卡 ' + rec.step) }}：
                                    <template v-if="rec.is_add_sign">
                                        會辦加簽（{{ rec.approver?.name }}，由主管 {{ rec.add_signed_by?.name || '主審' }} 發起）
                                    </template>
                                    <template v-else-if="rec.delegated_from">
                                        代理人代簽（{{ rec.approver?.name }}，原主管：{{ rec.delegated_from?.name }}）
                                    </template>
                                    <template v-else-if="rec.transferred_to">
                                        轉簽派審（{{ rec.approver?.name }} ➔ {{ rec.transferred_to?.name }}）
                                    </template>
                                    <template v-else>
                                        審核（{{ rec.approver?.name }}）
                                    </template>
                                </p>
                                <span :class="['px-2 py-0.5 text-xs rounded', statusBadge(rec.status)]">
                                    {{ rec.status === 'approved' ? '核准/簽畢' : (rec.status === 'rejected' ? '退件駁回' : (rec.status === 'returned' ? '已退回修改' : (rec.status === 'transferred' ? '已轉簽' : '待審批'))) }}
                                </span>
                                <span v-if="rec.is_add_sign" class="px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-700">
                                    會辦加簽
                                </span>
                                <span v-if="rec.delegated_from" class="px-2 py-0.5 text-[10px] font-bold rounded bg-purple-100 text-purple-700">
                                    職務代理代簽
                                </span>
                                <span v-if="rec.transferred_from" class="px-2 py-0.5 text-[10px] font-bold rounded bg-indigo-100 text-indigo-700">
                                    由 {{ rec.transferred_from?.name }} 轉簽
                                </span>
                            </div>
                            <p v-if="rec.comment" class="text-xs text-gray-600 mt-1 bg-gray-50 p-2 rounded border border-gray-100">意見：{{ rec.comment }}</p>
                            <p v-if="rec.actioned_at" class="text-xs text-gray-400 mt-0.5">{{ new Date(rec.actioned_at).toLocaleString() }}</p>
                        </div>
                    </div>
                </div>

                <!-- ================= 轉簽派審 Modal ================= -->
                <div v-if="showTransferModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="text-base font-bold text-gray-900">協同轉簽派審</h3>
                            <button @click="showTransferModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                        </div>

                        <p class="text-xs text-gray-500">
                            將目前關卡的審核決行權限全權轉派給指定的主管接手審查。
                        </p>

                        <form @submit.prevent="submitTransfer" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">受派審查主管 <span class="text-rose-500">*</span></label>
                                <select
                                    v-model="transferForm.target_user_id"
                                    required
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500"
                                >
                                    <option value="">— 請選擇受派主管 —</option>
                                    <option v-for="u in activeUsers" :key="u.id" :value="u.id">
                                        {{ u.name }} ({{ u.job_title || '主管' }} · {{ u.role }})
                                    </option>
                                </select>
                                <p v-if="transferForm.errors.target_user_id" class="text-2xs text-rose-600 mt-1">{{ transferForm.errors.target_user_id }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">轉簽派審事由說明 <span class="text-rose-500">*</span></label>
                                <textarea
                                    v-model="transferForm.reason"
                                    required
                                    rows="3"
                                    placeholder="請說明轉簽的原因（例如：涉及該處管轄業務，轉請決行）..."
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500"
                                ></textarea>
                                <p v-if="transferForm.errors.reason" class="text-2xs text-rose-600 mt-1">{{ transferForm.errors.reason }}</p>
                            </div>

                            <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="showTransferModal = false"
                                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium"
                                >
                                    取消
                                </button>
                                <button
                                    type="submit"
                                    :disabled="transferForm.processing"
                                    class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-bold shadow-sm"
                                >
                                    確認轉簽派送
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ================= 會辦加簽 Modal ================= -->
                <div v-if="showAddSignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="text-base font-bold text-gray-900">發起會辦加簽</h3>
                            <button @click="showAddSignModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                        </div>

                        <p class="text-xs text-gray-500">
                            邀請特定專業同仁或跨部門主管會審並簽署意見，會辦完成後流程將回流至您手續審。
                        </p>

                        <form @submit.prevent="submitAddSign" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">會辦加簽對象 <span class="text-rose-500">*</span></label>
                                <select
                                    v-model="addSignForm.target_user_id"
                                    required
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="">— 請選擇會辦對象 —</option>
                                    <option v-for="u in activeUsers" :key="u.id" :value="u.id">
                                        {{ u.name }} ({{ u.job_title || '同仁' }} · {{ u.role }})
                                    </option>
                                </select>
                                <p v-if="addSignForm.errors.target_user_id" class="text-2xs text-rose-600 mt-1">{{ addSignForm.errors.target_user_id }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">加簽會審事由說明 <span class="text-rose-500">*</span></label>
                                <textarea
                                    v-model="addSignForm.reason"
                                    required
                                    rows="3"
                                    placeholder="請說明需會辦說明的項目（例如：請 IT 評估伺服器規格、請會計覆核報銷單據）..."
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                ></textarea>
                                <p v-if="addSignForm.errors.reason" class="text-2xs text-rose-600 mt-1">{{ addSignForm.errors.reason }}</p>
                            </div>

                            <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="showAddSignModal = false"
                                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium"
                                >
                                    取消
                                </button>
                                <button
                                    type="submit"
                                    :disabled="addSignForm.processing"
                                    class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-sm"
                                >
                                    發送加簽邀請
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- ================= 撤回申請 Modal ================= -->
                <div v-if="showWithdrawModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div class="flex items-center space-x-3 text-rose-600 border-b border-gray-100 pb-3">
                            <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">確認撤回申請單</h3>
                                <p class="text-xs text-gray-500">撤回後單據立即作廢並終止簽核</p>
                            </div>
                        </div>

                        <p class="text-xs text-gray-600 leading-relaxed bg-amber-50 p-3 rounded-lg border border-amber-200">
                            提醒：撤回後此公文將標記為已作廢，主管待審批清單將自動移除此單據；若此申請包含休假，已凍結之請假額度將立即釋放恢復。
                        </p>

                        <form @submit.prevent="submitWithdraw" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">撤回原因說明（可選）</label>
                                <textarea
                                    v-model="withdrawForm.reason"
                                    rows="3"
                                    placeholder="例：行程變更取消、日期時間填寫有誤重新申請..."
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-rose-500 focus:ring-rose-500"
                                ></textarea>
                            </div>

                            <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="showWithdrawModal = false"
                                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium"
                                >
                                    取消
                                </button>
                                <button
                                    type="submit"
                                    :disabled="withdrawForm.processing"
                                    class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold shadow-sm disabled:opacity-50"
                                >
                                    {{ withdrawForm.processing ? '撤回中...' : '確定撤回單據' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ================= 主管退回修改 Modal ================= -->
                <div v-if="showRevisionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div class="flex items-center space-x-3 text-amber-600 border-b border-gray-100 pb-3">
                            <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">退回申請人修改補件</h3>
                                <p class="text-xs text-gray-500">申請人修改完成後可重新提交此關卡審查</p>
                            </div>
                        </div>

                        <p class="text-xs text-gray-600 leading-relaxed bg-amber-50/70 p-3 rounded-lg border border-amber-200">
                            退回修改不會直接終止或作廢單據。申請人將收到退回通知與指示，並可直接在此單據補齊資訊或重新上傳發票證明檔案後重新送審。
                        </p>

                        <form @submit.prevent="submitRevision" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">退回修改原因與補件指示 <span class="text-rose-500">*</span></label>
                                <textarea
                                    v-model="approvalForm.comment"
                                    required
                                    rows="3"
                                    placeholder="請具體告知申請同仁需修改之欄位或需補充之證明文件（例如：請補附具統編之統一發票電子檔並載明餐敘對象）..."
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
                                ></textarea>
                            </div>

                            <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="showRevisionModal = false"
                                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium"
                                >
                                    取消
                                </button>
                                <button
                                    type="submit"
                                    :disabled="approvalForm.processing || !approvalForm.comment.trim()"
                                    class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-sm disabled:opacity-50"
                                >
                                    {{ approvalForm.processing ? '處理中...' : '確認退回修改' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ================= 申請人修改表單並重新提交 Modal ================= -->
                <div v-if="showResubmitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                    <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 my-8">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div class="flex items-center space-x-2 text-amber-700">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <h3 class="text-base font-bold text-gray-900">修改表單內容並重新送審</h3>
                            </div>
                            <button @click="showResubmitModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                        </div>

                        <!-- 主管退回意見提醒 -->
                        <div v-if="formRequest.approval_records?.find(r => r.status === 'returned')" class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-900">
                            <span class="font-bold">主管退回意見：</span>
                            {{ formRequest.approval_records.find(r => r.status === 'returned')?.comment }}
                        </div>

                        <form @submit.prevent="submitResubmit" class="space-y-4">
                            <!-- 動態欄位編輯表單 -->
                            <div class="space-y-3 max-h-[45vh] overflow-y-auto pr-1">
                                <div v-for="(val, key) in resubmitForm.data" :key="key" class="space-y-1">
                                    <label class="block text-xs font-semibold text-gray-700 capitalize">{{ key }}</label>
                                    <input
                                        type="text"
                                        v-model="resubmitForm.data[key]"
                                        class="w-full text-xs rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
                                    />
                                </div>
                            </div>

                            <!-- 補充上傳證明文件 -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">補充檢附證明文件 / 附件檔案 (選填，單檔 10MB 內)</label>
                                <input
                                    type="file"
                                    multiple
                                    @change="handleResubmitFileChange"
                                    class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100"
                                />
                                <p v-if="resubmitForm.attachments.length > 0" class="text-2xs text-amber-700 mt-1">
                                    已選取 {{ resubmitForm.attachments.length }} 個新補充檔案
                                </p>
                            </div>

                            <!-- 申請人重新送審說明 -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">修改與補件說明備註 (選填)</label>
                                <textarea
                                    v-model="resubmitForm.resubmit_note"
                                    rows="2"
                                    placeholder="例：已更正金額、補充檢附蓋章報價單電子檔..."
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
                                ></textarea>
                            </div>

                            <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                                <button
                                    type="button"
                                    @click="showResubmitModal = false"
                                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium"
                                >
                                    取消
                                </button>
                                <button
                                    type="submit"
                                    :disabled="resubmitForm.processing"
                                    class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-sm disabled:opacity-50"
                                >
                                    {{ resubmitForm.processing ? '提交中...' : '確認重新提交審查' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 歷史版本修訂差異比對 Modal (Revision Diff Viewer Modal) -->
            <div
                v-if="showDiffViewer && activeDiffVersion"
                class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-gray-100 overflow-hidden transform transition-all">
                    <!-- Modal 標題 -->
                    <div class="px-6 py-4 bg-amber-500/10 border-b border-amber-200/50 flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span class="p-1.5 bg-amber-100 text-amber-700 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            </span>
                            <h3 class="text-base font-bold text-gray-900">
                                歷史版本修訂對照 (Diff Viewer)
                            </h3>
                        </div>
                        <button
                            type="button"
                            @click="showDiffViewer = false"
                            class="text-gray-400 hover:text-gray-600 transition p-1 rounded-lg"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                        <!-- 版本切換 Tabs (若有多個版本) -->
                        <div v-if="formRequest.revision_history && formRequest.revision_history.length > 1" class="flex items-center gap-2 border-b border-gray-100 pb-3">
                            <span class="text-xs font-semibold text-gray-500">切換版次：</span>
                            <button
                                v-for="(rev, idx) in formRequest.revision_history"
                                :key="idx"
                                type="button"
                                @click="activeDiffVersion = rev"
                                :class="[
                                    'px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer',
                                    activeDiffVersion.version === rev.version
                                        ? 'bg-amber-600 text-white shadow-xs'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                ]"
                            >
                                第 {{ rev.version }} 次修訂
                            </button>
                        </div>

                        <!-- 當前版本修訂資訊卡片 -->
                        <div class="p-4 rounded-xl bg-gray-50 border border-gray-100 space-y-2">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>修訂人：<strong class="text-gray-900">{{ activeDiffVersion.resubmitted_by?.name || '申請人' }}</strong></span>
                                <span>提交時間：<strong class="text-gray-900">{{ new Date(activeDiffVersion.resubmitted_at).toLocaleString() }}</strong></span>
                            </div>
                            <div v-if="activeDiffVersion.resubmit_note" class="text-xs text-gray-700 bg-white p-2.5 rounded-lg border border-gray-200">
                                <span class="font-bold text-amber-800">修訂附言：</span>
                                <span>{{ activeDiffVersion.resubmit_note }}</span>
                            </div>
                        </div>

                        <!-- 欄位差異對比清單 (Field Diff List) -->
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                欄位變更對比 (Changes)
                            </h4>

                            <div v-if="getChangedFields(activeDiffVersion).length === 0" class="p-4 text-center text-xs text-gray-500 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                表單欄位文字未變動（本次修訂僅補充上傳附件或說明備註）
                            </div>

                            <div
                                v-for="(chg, cIdx) in getChangedFields(activeDiffVersion)"
                                :key="cIdx"
                                class="border border-gray-200 rounded-xl overflow-hidden text-xs"
                            >
                                <div class="px-3.5 py-2 bg-gray-100 font-bold text-gray-700 capitalize border-b border-gray-200">
                                    {{ chg.key }}
                                </div>
                                <div class="p-3 space-y-2 font-mono">
                                    <div class="flex items-start gap-2 text-rose-700 bg-rose-50/70 p-2 rounded-lg">
                                        <span class="font-bold shrink-0 text-rose-500">- 原值:</span>
                                        <span class="line-through break-all whitespace-pre-wrap">{{ chg.oldVal }}</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-emerald-800 bg-emerald-50/70 p-2 rounded-lg">
                                        <span class="font-bold shrink-0 text-emerald-600">+ 新值:</span>
                                        <span class="font-semibold break-all whitespace-pre-wrap">{{ chg.newVal }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 本次補充上傳之附件檔案 (若有) -->
                        <div v-if="activeDiffVersion.new_attachments && activeDiffVersion.new_attachments.length > 0" class="space-y-2 pt-2 border-t border-gray-100">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                本次修訂新補充之附件檔案 ({{ activeDiffVersion.new_attachments.length }})
                            </h4>
                            <div class="space-y-1.5">
                                <div
                                    v-for="(att, aIdx) in activeDiffVersion.new_attachments"
                                    :key="aIdx"
                                    class="flex items-center justify-between p-2.5 bg-blue-50/50 border border-blue-100 rounded-lg text-xs"
                                >
                                    <span class="font-medium text-gray-800 truncate mr-2">{{ att.name }}</span>
                                    <span class="text-gray-400 shrink-0">{{ formatSize(att.size) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                        <button
                            type="button"
                            @click="showDiffViewer = false"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-lg text-xs font-bold transition cursor-pointer"
                        >
                            關閉對照視窗
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
