<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    ticket: Object,
    replies: Array,
    canManage: Boolean,
    staffList: Array,
});

// 指派人員表單
const assignForm = useForm({
    assigned_to: props.ticket.assigned_to || '',
});

const submitAssign = () => {
    assignForm.patch(route('tickets.assign', props.ticket.id), {
        preserveScroll: true,
    });
};

// 狀態更新
const updateStatus = (status) => {
    router.patch(route('tickets.update-status', props.ticket.id), { status }, {
        preserveScroll: true,
    });
};

// 回覆表單
const replyForm = useForm({
    message: '',
    is_internal: false,
    attachments: [],
});

const fileInput = ref(null);
const fileList = ref([]);

const handleFiles = (e) => {
    const files = Array.from(e.target.files);
    replyForm.attachments = files;
    fileList.value = files.map(f => ({ name: f.name, size: (f.size / 1024).toFixed(1) + ' KB' }));
};

const submitReply = () => {
    replyForm.post(route('tickets.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            fileList.value = [];
        },
    });
};

// 評分表單
const ratingForm = useForm({
    satisfaction_rating: 5,
    satisfaction_feedback: '',
});

const submitRate = () => {
    ratingForm.post(route('tickets.rate', props.ticket.id), {
        preserveScroll: true,
    });
};

const getPriorityBadge = (p) => {
    switch (p) {
        case 'urgent': return { label: '特急件', class: 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300 dark:border-rose-800 animate-pulse' };
        case 'high': return { label: '高優先', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800' };
        case 'normal': return { label: '一般', class: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800' };
        default: return { label: '低優先', class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700' };
    }
};

const getStatusBadge = (s) => {
    switch (s) {
        case 'open': return { label: '待處理', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' };
        case 'in_progress': return { label: '處理中', class: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300' };
        case 'pending_user': return { label: '待同仁確認', class: 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300' };
        case 'resolved': return { label: '已完修', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' };
        case 'closed': return { label: '已結案', class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' };
        default: return { label: s, class: 'bg-gray-100 text-gray-700' };
    }
};
</script>

<template>
    <Head :title="`${ticket.ticket_number} - 工單詳情`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('tickets.index')" class="p-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-500 transition dark:text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-lg bg-gray-900 text-white dark:bg-gray-700 font-mono text-xs font-bold">
                                {{ ticket.ticket_number }}
                            </span>
                            <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100">
                                {{ ticket.title }}
                            </h2>
                            <span :class="['px-2.5 py-0.5 text-xs font-bold rounded-md', getStatusBadge(ticket.status).class]">
                                {{ ticket.status_label }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-3">
                            <span>提單同仁：<strong class="text-gray-800 dark:text-gray-200">{{ ticket.user?.name }}</strong> ({{ ticket.user?.department?.name || '公司' }})</span>
                            <span>· 建立時間：{{ new Date(ticket.created_at).toLocaleString() }}</span>
                            <span v-if="ticket.resolution_duration_hours" class="text-emerald-600 font-semibold">
                                · 完修耗時：{{ ticket.resolution_duration_hours }} 小時
                            </span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span :class="['px-3 py-1 text-xs font-bold rounded-lg border', getPriorityBadge(ticket.priority).class]">
                        {{ ticket.priority_label }}
                    </span>
                    <span class="px-3 py-1 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        {{ ticket.category_label }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6 px-4">
                <!-- 視覺化進度管線 Stepper -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 shadow-xs">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center text-xs">
                        <div :class="['p-3 rounded-xl border transition', ticket.status === 'open' ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-300 font-bold text-amber-800' : 'bg-gray-50 dark:bg-gray-700/50 text-gray-400']">
                            <p class="text-base mb-1">1</p>
                            <p>待工程師受理</p>
                        </div>
                        <div :class="['p-3 rounded-xl border transition', ['in_progress', 'pending_user'].includes(ticket.status) ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 font-bold text-blue-800' : 'bg-gray-50 dark:bg-gray-700/50 text-gray-400']">
                            <p class="text-base mb-1">2</p>
                            <p>{{ ticket.status === 'pending_user' ? '待同仁確認中' : '工程師處理中' }}</p>
                        </div>
                        <div :class="['p-3 rounded-xl border transition', ticket.status === 'resolved' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 font-bold text-emerald-800' : 'bg-gray-50 dark:bg-gray-700/50 text-gray-400']">
                            <p class="text-base mb-1">3</p>
                            <p>已完修 (待同仁驗收)</p>
                        </div>
                        <div :class="['p-3 rounded-xl border transition', ticket.status === 'closed' ? 'bg-gray-100 dark:bg-gray-900 border-gray-400 font-bold text-gray-800 dark:text-gray-200' : 'bg-gray-50 dark:bg-gray-700/50 text-gray-400']">
                            <p class="text-base mb-1">4</p>
                            <p>已評分結案</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- 左欄：工單詳情與評分驗收卡片 -->
                    <div class="space-y-6">
                        <!-- 基本資料卡片 -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 shadow-xs space-y-4 text-xs">
                            <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm border-b border-gray-100 dark:border-gray-700 pb-2">
                                工單詳情資訊
                            </h3>

                            <div>
                                <p class="text-gray-400 font-medium">承辦工程師</p>
                                <div class="mt-1 flex items-center justify-between">
                                    <span v-if="ticket.assignee" class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                        <span>{{ ticket.assignee.name }}</span>
                                    </span>
                                    <span v-else class="text-amber-600 dark:text-amber-400 font-semibold">
                                        尚未指派 (待受理)
                                    </span>

                                    <!-- 管理員/專員指派 Modal/下拉 -->
                                    <div v-if="canManage" class="flex items-center gap-2">
                                        <select
                                            v-model="assignForm.assigned_to"
                                            @change="submitAssign"
                                            class="rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-xs py-1 px-2"
                                        >
                                            <option value="">指派人員...</option>
                                            <option v-for="staff in staffList" :key="staff.id" :value="staff.id">
                                                {{ staff.name }} ({{ staff.role }})
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div v-if="ticket.location">
                                <p class="text-gray-400 font-medium">故障地點 / 座位</p>
                                <p class="font-bold text-gray-800 dark:text-gray-200 mt-1">{{ ticket.location }}</p>
                            </div>

                            <div>
                                <p class="text-gray-400 font-medium">詳細問題描述</p>
                                <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-xl mt-1 text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">
                                    {{ ticket.description }}
                                </div>
                            </div>

                            <!-- 提單檢附照片附件 -->
                            <div v-if="ticket.attachments?.length > 0">
                                <p class="text-gray-400 font-medium mb-1.5">檢附附件檔案 ({{ ticket.attachments.length }})</p>
                                <div class="space-y-1.5">
                                    <a
                                        v-for="(att, idx) in ticket.attachments"
                                        :key="idx"
                                        :href="`/storage/${att.path}`"
                                        target="_blank"
                                        class="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-rose-50 text-gray-700 dark:text-gray-300 hover:text-rose-600 transition"
                                    >
                                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="truncate font-medium">{{ att.name }}</span>
                                    </a>
                                </div>
                            </div>

                            <!-- 工程師快捷管理狀態操作列 -->
                            <div v-if="canManage" class="pt-3 border-t border-gray-100 dark:border-gray-700 space-y-2">
                                <p class="text-gray-400 font-medium">工程師狀態操作</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-if="ticket.status === 'open'"
                                        @click="updateStatus('in_progress')"
                                        class="py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition text-center shadow-xs"
                                    >
                                        接單處理
                                    </button>
                                    <button
                                        v-if="ticket.status === 'in_progress'"
                                        @click="updateStatus('pending_user')"
                                        class="py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition text-center shadow-xs"
                                    >
                                        待同仁確認
                                    </button>
                                    <button
                                        v-if="['in_progress', 'pending_user'].includes(ticket.status)"
                                        @click="updateStatus('resolved')"
                                        class="py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition text-center shadow-xs col-span-2"
                                    >
                                        標記已完修 (通知驗收)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 滿意度結案卡片 (若完修待評分，或已結案展示評價) -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 shadow-xs text-xs">
                            <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm border-b border-gray-100 dark:border-gray-700 pb-2 flex items-center gap-1.5">
                                <span>服務滿意度評分與結案</span>
                            </h3>

                            <!-- 已結案顯示評分 -->
                            <div v-if="ticket.status === 'closed'" class="mt-3 text-center py-2">
                                <div class="text-2xl mb-1 text-amber-400">
                                    {{ '★'.repeat(ticket.satisfaction_rating) }}{{ '☆'.repeat(5 - ticket.satisfaction_rating) }}
                                </div>
                                <p class="font-bold text-gray-800 dark:text-gray-200">
                                    評價：{{ ticket.satisfaction_rating }} 顆星
                                </p>
                                <p v-if="ticket.satisfaction_feedback" class="mt-2 text-gray-500 italic bg-gray-50 dark:bg-gray-900/40 p-2.5 rounded-xl dark:text-gray-400">
                                    「{{ ticket.satisfaction_feedback }}」
                                </p>
                                <p class="text-[11px] text-gray-400 mt-2">
                                    結案時間：{{ new Date(ticket.closed_at).toLocaleString() }}
                                </p>
                            </div>

                            <!-- 完修狀態供提單同仁評分 -->
                            <div v-else-if="ticket.status === 'resolved'" class="mt-3 space-y-3">
                                <p class="text-emerald-700 dark:text-emerald-400 font-semibold">
                                     工程師已標記修復完成！請確認設備運作正常，並為本次服務給予評分：
                                </p>
                                <form @submit.prevent="submitRate" class="space-y-3">
                                    <div>
                                        <label class="block font-medium text-gray-600 dark:text-gray-400 mb-1">評分星級</label>
                                        <select
                                            v-model="ratingForm.satisfaction_rating"
                                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-xs"
                                        >
                                            <option :value="5">5 星 (非常滿意，神速修復)</option>
                                            <option :value="4">4 星 (滿意)</option>
                                            <option :value="3">3 星 (普通)</option>
                                            <option :value="2">2 星 (尚可)</option>
                                            <option :value="1">1 星 (不滿意)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block font-medium text-gray-600 dark:text-gray-400 mb-1">心得回饋 (選填)</label>
                                        <textarea
                                            v-model="ratingForm.satisfaction_feedback"
                                            rows="2"
                                            placeholder="感謝工程師的耐心協助或改善建議..."
                                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-xs"
                                        ></textarea>
                                    </div>

                                    <button
                                        type="submit"
                                        :disabled="ratingForm.processing"
                                        class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs transition"
                                    >
                                        確認滿意並正式結案
                                    </button>
                                </form>
                            </div>

                            <div v-else class="mt-3 text-center py-4 text-gray-400">
                                 工單處理完成後即可進行結案滿意度評分
                            </div>
                        </div>
                    </div>

                    <!-- 右欄：處理歷程與對話時間軸 -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-xs flex flex-col justify-between min-h-[500px]">
                            <div>
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm border-b border-gray-100 dark:border-gray-700 pb-3 flex items-center justify-between">
                                    <span>處理歷程與對話交流</span>
                                    <span class="text-xs font-normal text-gray-400">共 {{ replies.length }} 則溝通紀錄</span>
                                </h3>

                                <div class="mt-4 space-y-4">
                                    <div
                                        v-for="reply in replies"
                                        :key="reply.id"
                                        :class="[
                                            'p-4 rounded-2xl text-xs space-y-2 border transition',
                                            reply.is_internal ? 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/60' :
                                            reply.user_id === ticket.user_id ? 'bg-gray-50/80 dark:bg-gray-900/50 border-gray-100 dark:border-gray-700' :
                                            'bg-blue-50/70 dark:bg-blue-950/30 border-blue-100 dark:border-blue-900/60'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-gray-900 dark:text-gray-100">{{ reply.user?.name }}</span>
                                                <span v-if="reply.user?.role !== 'employee'" class="px-1.5 py-0.2 rounded text-[10px] bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 font-semibold">
                                                    {{ reply.user?.role === 'admin' ? '系統管理員' : '工程師' }}
                                                </span>
                                                <span v-if="reply.is_internal" class="px-1.5 py-0.2 rounded text-[10px] bg-amber-200 text-amber-900 font-bold">
                                                     內部備註 (同仁不可見)
                                                </span>
                                            </div>
                                            <span class="text-gray-400 text-[11px]">{{ new Date(reply.created_at).toLocaleString() }}</span>
                                        </div>

                                        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">
                                            {{ reply.message }}
                                        </p>

                                        <!-- 回覆附件清單 -->
                                        <div v-if="reply.attachments?.length > 0" class="pt-2 border-t border-gray-100 dark:border-gray-700/60 space-y-1">
                                            <a
                                                v-for="(att, aIdx) in reply.attachments"
                                                :key="aIdx"
                                                :href="`/storage/${att.path}`"
                                                target="_blank"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[11px] font-medium text-gray-700 dark:text-gray-300 hover:text-rose-600 mr-2"
                                            >
                                                <span>{{ att.name }}</span>
                                            </a>
                                        </div>
                                    </div>

                                    <div v-if="!replies || replies.length === 0" class="py-12 text-center text-xs text-gray-400">
                                        尚未有任何對話回覆紀錄，雙方可於下方輸入訊息即時溝通。
                                    </div>
                                </div>
                            </div>

                            <!-- 回覆發送框 (工單未結案時可用) -->
                            <div v-if="ticket.status !== 'closed'" class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <form @submit.prevent="submitReply" class="space-y-3 text-xs">
                                    <textarea
                                        v-model="replyForm.message"
                                        rows="3"
                                        required
                                        placeholder="輸入回覆訊息或維修進度說明..."
                                        class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-rose-500 focus:border-rose-500"
                                    ></textarea>

                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-4">
                                            <input
                                                ref="fileInput"
                                                type="file"
                                                multiple
                                                @change="handleFiles"
                                                class="hidden"
                                                id="reply-file-input"
                                            />
                                            <label for="reply-file-input" class="cursor-pointer text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1 dark:text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                <span>夾帶附件 ({{ fileList.length }})</span>
                                            </label>

                                            <!-- IT內部備註選取 -->
                                            <label v-if="canManage" class="flex items-center gap-1.5 cursor-pointer text-amber-700 dark:text-amber-400 font-semibold">
                                                <input
                                                    v-model="replyForm.is_internal"
                                                    type="checkbox"
                                                    class="rounded border-amber-300 text-amber-600 focus:ring-amber-500"
                                                />
                                                <span>內部備註 (僅 IT/管理員可見)</span>
                                            </label>
                                        </div>

                                        <button
                                            type="submit"
                                            :disabled="replyForm.processing"
                                            class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition active:scale-95"
                                        >
                                            送出回覆
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div v-else class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700 text-center text-xs text-gray-400">
                                 本工單已完成結案，對話溝通已關閉存檔。
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
