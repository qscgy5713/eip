<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    report: Object,
    canEdit: Boolean,
    canManage: Boolean,
});

const page = usePage();
const currentUser = page.props.auth.user;

const commentForm = useForm({
    comment: '',
});

const submitComment = () => {
    if (!commentForm.comment.trim()) return;
    commentForm.post(route('weekly-reports.comment', props.report.id), {
        onSuccess: () => {
            commentForm.reset();
        },
    });
};

const deleteReport = async () => {
    if (await confirmDialog(`確定要刪除第 ${props.report.year} 年第 ${props.report.week_number} 週的這份週報嗎？`)) {
        router.delete(route('weekly-reports.destroy', props.report.id));
    }
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'draft':
            return { text: '草稿暫存', class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' };
        case 'submitted':
            return { text: '已提交待點評', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' };
        case 'reviewed':
            return { text: '主管已點評', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' };
        default:
            return { text: status, class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' };
    }
};
</script>

<template>
    <Head :title="`${report.user?.name} 的週報 - ${report.year} 年第 ${report.week_number} 週`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-cyan-500 to-indigo-600 text-white font-black text-lg flex items-center justify-center shadow-md">
                        {{ report.user?.name.charAt(0) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                            <span>{{ report.user?.name }} 的工作週報</span>
                            <span :class="['text-xs px-2.5 py-0.5 rounded-full font-bold', getStatusBadge(report.status).class]">
                                {{ getStatusBadge(report.status).text }}
                            </span>
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ report.year }} 年 第 {{ report.week_number }} 週 ({{ String(report.start_date).slice(0, 10) }} ~ {{ String(report.end_date).slice(0, 10) }}) · {{ report.department?.name }} · {{ report.user?.job_title || '同仁' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <Link
                        v-if="canEdit"
                        :href="route('weekly-reports.edit', report.id)"
                        class="px-3.5 py-2 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
                    >
                         編輯週報
                    </Link>
                    <button
                        v-if="canEdit || currentUser.role === 'admin'"
                        type="button"
                        @click="deleteReport"
                        class="px-3.5 py-2 text-xs font-bold rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/50 dark:hover:bg-rose-900 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 transition"
                    >
                         刪除
                    </button>
                    <Link
                        :href="route('weekly-reports.index')"
                        class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                    >
                        返回列表
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 草稿橫幅提示 -->
                <div v-if="report.status === 'draft'" class="p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 rounded-xl flex items-center justify-between">
                    <div class="flex items-center space-x-2 text-xs text-amber-800 dark:text-amber-300 font-medium">
                        <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>此週報目前處於「草稿」狀態，尚未正式提交給主管審閱點評。</span>
                    </div>
                    <Link
                        v-if="canEdit"
                        :href="route('weekly-reports.edit', report.id)"
                        class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-xs"
                    >
                        立即編輯並提交
                    </Link>
                </div>

                <!-- 週報主體內容卡片 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="p-6 space-y-6">
                        
                        <!-- 1. 本週工作重點概述 -->
                        <div class="pb-5 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2 mb-2">
                                <span class="inline-flex p-1 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></span>
                                <span>本週工作重點概述 (Summary)</span>
                            </h3>
                            <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-xl text-xs sm:text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-line">
                                {{ report.summary }}
                            </div>
                        </div>

                        <!-- 2. 具體完成事項 -->
                        <div class="pb-5 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2 mb-2">
                                <span class="inline-flex p-1 rounded bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                <span>具體完成事項 (Achievements)</span>
                            </h3>
                            <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-xl text-xs sm:text-sm text-gray-800 dark:text-gray-200 leading-relaxed font-mono whitespace-pre-line">
                                {{ report.achievements }}
                            </div>
                        </div>

                        <!-- 3. 下週工作計畫 -->
                        <div class="pb-5 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2 mb-2">
                                <span class="inline-flex p-1 rounded bg-blue-50 dark:bg-blue-950/60 text-blue-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                <span>下週工作計畫 (Next Week Plans)</span>
                            </h3>
                            <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-xl text-xs sm:text-sm text-gray-800 dark:text-gray-200 leading-relaxed font-mono whitespace-pre-line">
                                {{ report.plans_next_week }}
                            </div>
                        </div>

                        <!-- 4. 困難與需跨部門支援 -->
                        <div v-if="report.challenges_or_support">
                            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 flex items-center gap-2 mb-2">
                                <span class="inline-flex p-1 rounded bg-amber-50 dark:bg-amber-950/60 text-amber-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 21h6m-3-18a6 6 0 00-3.6 10.8c.6.5 1.1 1.3 1.1 2.2h5c0-.9.5-1.7 1.1-2.2A6 6 0 0012 3z"/></svg></span>
                                <span>遭遇困難、阻礙或需跨部門支援 (Challenges & Support)</span>
                            </h3>
                            <div class="p-4 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/40 rounded-xl text-xs sm:text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-line">
                                {{ report.challenges_or_support }}
                            </div>
                        </div>

                        <!-- 底部中繼資訊 -->
                        <div class="pt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-gray-400 dark:text-gray-500 border-t border-gray-100 dark:border-gray-700">
                            <span>建立時間：{{ new Date(report.created_at).toLocaleString() }}</span>
                            <span v-if="report.submitted_at">正式提交時間：{{ new Date(report.submitted_at).toLocaleString() }}</span>
                            <span>最後更新：{{ new Date(report.updated_at).toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- 主管指導點評與反饋交流專區 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm p-6 space-y-6">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-cyan-500"></span>
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                 主管指導點評與交流反饋 ({{ report.comments?.length || 0 }})
                            </h3>
                        </div>
                    </div>

                    <!-- 點評留言時間軸列表 -->
                    <div class="space-y-4">
                        <div
                            v-for="comm in report.comments"
                            :key="comm.id"
                            class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-100 dark:border-gray-700/60 flex items-start space-x-3.5"
                        >
                            <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                {{ comm.user?.name.charAt(0) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-gray-900 dark:text-gray-100">{{ comm.user?.name }}</span>
                                        <span
                                            v-if="comm.user?.role === 'manager' || comm.user?.role === 'admin'"
                                            class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300"
                                        >
                                            指導主管
                                        </span>
                                    </div>
                                    <span class="text-[11px] text-gray-400">{{ new Date(comm.created_at).toLocaleString() }}</span>
                                </div>
                                <p class="text-xs text-gray-800 dark:text-gray-200 mt-2 leading-relaxed whitespace-pre-line">{{ comm.comment }}</p>
                            </div>
                        </div>

                        <div v-if="!report.comments || report.comments.length === 0" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                            目前尚無指導點評留言
                        </div>
                    </div>

                    <!-- 點評輸入表單 -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                        <form @submit.prevent="submitComment" class="space-y-3">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">
                                {{ canManage && report.user_id !== currentUser.id ? ' 撰寫主管指導點評 (送出後標記為已點評)' : ' 回覆留言' }}
                            </label>
                            <textarea
                                v-model="commentForm.comment"
                                rows="3"
                                required
                                :placeholder="canManage && report.user_id !== currentUser.id ? '給予同仁具體指導建議、讚賞肯定或協助調度資源...' : '輸入回覆意見...'"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-cyan-500 focus:border-cyan-500 p-3"
                            ></textarea>
                            <InputError :message="commentForm.errors.comment" />
                            <div class="flex justify-end">
                                <PrimaryButton
                                    type="submit"
                                    :disabled="commentForm.processing"
                                    class="text-xs bg-cyan-600 hover:bg-cyan-700"
                                >
                                    送出指導點評
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
