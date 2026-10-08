<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    poll: Object,
    options: Array,
    has_voted: Boolean,
    my_voted_option_ids: Array,
    total_votes: Number,
    voters_count: Number,
    can_manage: Boolean,
});

// 選取的投票選項
const selectedOptions = ref(props.my_voted_option_ids || []);

const toggleOption = (optionId) => {
    if (props.has_voted || props.poll.is_closed) return;

    if (props.poll.is_multiple_choice) {
        const index = selectedOptions.value.indexOf(optionId);
        if (index > -1) {
            selectedOptions.value.splice(index, 1);
        } else {
            selectedOptions.value.push(optionId);
        }
    } else {
        selectedOptions.value = [optionId];
    }
};

// 提交投票表單
const voteForm = useForm({
    option_ids: [],
});

const submitVote = () => {
    if (selectedOptions.value.length === 0) return;

    voteForm.option_ids = [...selectedOptions.value];
    voteForm.post(route('polls.vote', props.poll.id), {
        preserveScroll: true,
    });
};

// 關閉與刪除確認 Modal
const showCloseConfirmModal = ref(false);
const showDeleteConfirmModal = ref(false);

const handleClosePoll = () => {
    router.post(route('polls.close', props.poll.id), {}, {
        onSuccess: () => {
            showCloseConfirmModal.value = false;
        },
    });
};

const handleDeletePoll = () => {
    router.delete(route('polls.destroy', props.poll.id), {
        onSuccess: () => {
            showDeleteConfirmModal.value = false;
        },
    });
};

// 計算最高得票數
const maxVotesCount = computed(() => {
    if (!props.options || props.options.length === 0) return 0;
    return Math.max(...props.options.map(o => o.votes_count));
});

// 展開記名投票同仁列表
const expandedVoters = ref({});
const toggleVotersList = (optionId) => {
    expandedVoters.value[optionId] = !expandedVoters.value[optionId];
};
</script>

<template>
    <Head :title="`投票：${poll.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('polls.index')"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                        title="返回投票清單"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h2 class="text-xl font-bold leading-tight text-gray-800">
                            {{ poll.title }}
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            發起人：{{ poll.creator?.name }} · {{ poll.created_at }}
                        </p>
                    </div>
                </div>

                <div v-if="can_manage" class="flex items-center gap-2">
                    <button
                        v-if="!poll.is_closed"
                        @click="showCloseConfirmModal = true"
                        class="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg transition-colors"
                    >
                        提前結束投票
                    </button>
                    <button
                        @click="showDeleteConfirmModal = true"
                        class="px-3 py-1.5 text-xs font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition-colors"
                    >
                        刪除投票
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- 狀態橫幅與基本資訊 -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-gray-100">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="!poll.is_closed"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                進行中
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200"
                            >
                                已截止
                            </span>

                            <span
                                :class="poll.is_anonymous ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-blue-50 text-blue-700 border-blue-200'"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                            >
                                {{ poll.is_anonymous ? '匿名投票（徹底隱密）' : '具名投票（留名）' }}
                            </span>

                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                                {{ poll.is_multiple_choice ? '複選題' : '單選題' }}
                            </span>
                        </div>

                        <div class="text-xs text-gray-500">
                            <span v-if="poll.ends_at">
                                截止期限：<strong class="text-gray-700">{{ poll.ends_at }}</strong>
                            </span>
                            <span v-else>
                                截止期限：由發起人手動關閉
                            </span>
                        </div>
                    </div>

                    <div v-if="poll.description" class="mt-4 text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                        {{ poll.description }}
                    </div>

                    <!-- 統計概覽數據列 -->
                    <div class="mt-6 pt-4 border-t border-gray-100 grid grid-cols-2 sm:grid-cols-3 gap-4 text-center">
                        <div class="bg-gray-50/80 p-3 rounded-lg border border-gray-100">
                            <div class="text-xs text-gray-500">累計參與同仁</div>
                            <div class="text-xl font-bold text-gray-900 mt-0.5">{{ voters_count }} <span class="text-xs font-normal text-gray-500">人</span></div>
                        </div>
                        <div class="bg-gray-50/80 p-3 rounded-lg border border-gray-100">
                            <div class="text-xs text-gray-500">總投出票數</div>
                            <div class="text-xl font-bold text-indigo-600 mt-0.5">{{ total_votes }} <span class="text-xs font-normal text-gray-500">票</span></div>
                        </div>
                        <div class="col-span-2 sm:col-span-1 bg-gray-50/80 p-3 rounded-lg border border-gray-100 flex flex-col justify-center">
                            <div class="text-xs text-gray-500">我的狀態</div>
                            <div class="text-sm font-bold mt-0.5">
                                <span v-if="has_voted" class="text-emerald-600 flex items-center justify-center gap-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    已完成投票
                                </span>
                                <span v-else-if="!poll.is_closed" class="text-amber-600">
                                    尚未參與
                                </span>
                                <span v-else class="text-gray-400">
                                    已截止未參與
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 核心互動區：投票介面 或 即時結果長條圖 -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
                    <!-- 1. 進行中 且 尚未投票：展示投票選取面板 -->
                    <div v-if="!has_voted && !poll.is_closed">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                請投下您寶貴的一票
                            </h3>
                            <span class="text-xs text-gray-500">
                                {{ poll.is_multiple_choice ? '（可勾選多個選項）' : '（單選，僅能選一項）' }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="option in options"
                                :key="option.id"
                                @click="toggleOption(option.id)"
                                :class="[
                                    selectedOptions.includes(option.id)
                                        ? 'border-indigo-600 bg-indigo-50/60 ring-2 ring-indigo-500/20'
                                        : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/50',
                                    'p-4 rounded-xl border cursor-pointer transition-all flex items-center justify-between group'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        :class="[
                                            selectedOptions.includes(option.id)
                                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                                : 'border-gray-300 bg-white group-hover:border-gray-400',
                                            poll.is_multiple_choice ? 'rounded-md' : 'rounded-full',
                                            'w-5 h-5 border flex items-center justify-center transition-colors'
                                        ]"
                                    >
                                        <svg v-if="selectedOptions.includes(option.id)" class="w-3.5 h-3.5 stroke-current stroke-2" fill="none" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-800">
                                        {{ option.option_text }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="voteForm.errors.vote" class="mt-3 text-sm text-rose-600 font-medium">
                            {{ voteForm.errors.vote }}
                        </div>

                        <div class="mt-8 pt-4 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs text-gray-500">
                                已選取 <strong class="text-indigo-600 font-semibold">{{ selectedOptions.length }}</strong> 項
                            </span>
                            <PrimaryButton
                                @click="submitVote"
                                :disabled="selectedOptions.length === 0 || voteForm.processing"
                            >
                                確認送出選票
                            </PrimaryButton>
                        </div>
                    </div>

                    <!-- 2. 已投票 或 已截止：展示即時統計圖表 -->
                    <div v-else>
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    即時得票統計分析
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ has_voted ? '您已完成投票，以下為全員即時票選分佈' : '此活動已結束，以下為最終票選結果' }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div
                                v-for="option in options"
                                :key="option.id"
                                class="p-4 rounded-xl border border-gray-100 bg-gray-50/50 hover:bg-gray-50 transition-colors"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900">
                                            {{ option.option_text }}
                                        </span>
                                        <!-- 最高票徽章 -->
                                        <span
                                            v-if="maxVotesCount > 0 && option.votes_count === maxVotesCount"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300"
                                        >
                                            👑 最高票
                                        </span>
                                        <!-- 我的選票標籤 -->
                                        <span
                                            v-if="my_voted_option_ids && my_voted_option_ids.includes(option.id)"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"
                                        >
                                            ✓ 我的選擇
                                        </span>
                                    </div>
                                    <div class="text-sm font-bold text-gray-700 flex items-center gap-2">
                                        <span class="text-indigo-600">{{ option.votes_count }} 票</span>
                                        <span class="text-gray-400">({{ option.percentage }}%)</span>
                                    </div>
                                </div>

                                <!-- 長條圖進度條 -->
                                <div class="w-full bg-gray-200 rounded-full h-3.5 overflow-hidden">
                                    <div
                                        :class="[
                                            maxVotesCount > 0 && option.votes_count === maxVotesCount
                                                ? 'bg-gradient-to-r from-amber-400 to-indigo-600'
                                                : 'bg-indigo-500',
                                            'h-3.5 rounded-full transition-all duration-500 ease-out'
                                        ]"
                                        :style="{ width: `${option.percentage}%` }"
                                    ></div>
                                </div>

                                <!-- 若具名投票且具管理權限，可展開查看投票人明細 -->
                                <div v-if="!poll.is_anonymous && can_manage && option.voters && option.voters.length > 0" class="mt-3 pt-2 border-t border-gray-200/60">
                                    <button
                                        @click="toggleVotersList(option.id)"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1"
                                    >
                                        <span>{{ expandedVoters[option.id] ? '收合名單' : `查看投票名冊 (${option.voters.length}人)` }}</span>
                                        <svg
                                            :class="expandedVoters[option.id] ? 'rotate-180' : ''"
                                            class="w-3.5 h-3.5 transition-transform"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>

                                    <div v-if="expandedVoters[option.id]" class="mt-2 flex flex-wrap gap-1.5">
                                        <span
                                            v-for="voter in option.voters"
                                            :key="voter.id"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-white text-gray-700 border border-gray-200 shadow-2xs"
                                        >
                                            {{ voter.name }} ({{ voter.employee_no }})
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 提前結束投票確認 Modal -->
        <Modal :show="showCloseConfirmModal" @close="showCloseConfirmModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900">確定要提前結束此投票嗎？</h3>
                <p class="text-sm text-gray-500 mt-2">
                    結束後將無法再接收同仁的新選票，但所有人仍可繼續查看最終統計結果。
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCloseConfirmModal = false">取消</SecondaryButton>
                    <PrimaryButton @click="handleClosePoll">確認結束</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- 刪除投票確認 Modal -->
        <Modal :show="showDeleteConfirmModal" @close="showDeleteConfirmModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900">確定要永久刪除此投票活動嗎？</h3>
                <p class="text-sm text-gray-500 mt-2">
                    此動作將刪除包含所有選項與已投票資料，且無法復原。
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showDeleteConfirmModal = false">取消</SecondaryButton>
                    <DangerButton @click="handleDeletePoll">確認刪除</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
