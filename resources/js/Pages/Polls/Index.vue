<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    polls: Object,
    filters: Object,
    can_create: Boolean,
});

const currentStatus = ref(props.filters.status || 'active');

const setFilter = (status) => {
    currentStatus.value = status;
    router.get(route('polls.index'), { status }, { preserveState: true, replace: true });
};

// 建立投票 Modal
const showCreateModal = ref(false);
const form = useForm({
    title: '',
    description: '',
    is_multiple_choice: false,
    is_anonymous: false,
    ends_at: '',
    options: ['', ''],
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    form.options = ['', ''];
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const addOption = () => {
    if (form.options.length < 20) {
        form.options.push('');
    }
};

const removeOption = (index) => {
    if (form.options.length > 2) {
        form.options.splice(index, 1);
    }
};

const submitCreate = () => {
    form.post(route('polls.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="企業同仁投票與意見調查" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 flex items-center gap-2">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        企業同仁投票與意見調查
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        參與福利委員會活動票選、部門聚餐調查與內部重要決策意見徵詢
                    </p>
                </div>
                <div v-if="can_create">
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-medium text-sm text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        發起投票活動
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- 篩選標籤頁籤 -->
                <div class="flex border-b border-gray-200 space-x-6 text-sm font-medium">
                    <button
                        @click="setFilter('active')"
                        :class="[
                            currentStatus === 'active'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'pb-4 border-b-2 transition-colors flex items-center gap-1.5'
                        ]"
                    >
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        進行中投票
                    </button>
                    <button
                        @click="setFilter('closed')"
                        :class="[
                            currentStatus === 'closed'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'pb-4 border-b-2 transition-colors'
                        ]"
                    >
                        已截止記錄
                    </button>
                    <button
                        @click="setFilter('my')"
                        :class="[
                            currentStatus === 'my'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'pb-4 border-b-2 transition-colors'
                        ]"
                    >
                        我發起的
                    </button>
                    <button
                        @click="setFilter('all')"
                        :class="[
                            currentStatus === 'all'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'pb-4 border-b-2 transition-colors'
                        ]"
                    >
                        全部列表
                    </button>
                </div>

                <!-- 投票卡片格線列表 -->
                <div v-if="polls.data && polls.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="poll in polls.data"
                        :key="poll.id"
                        class="bg-white rounded-xl border border-gray-200/80 shadow-sm hover:shadow-md transition-all flex flex-col justify-between overflow-hidden group"
                    >
                        <div class="p-6">
                            <!-- 狀態與屬性徽章 -->
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2">
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
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border"
                                    >
                                        {{ poll.is_anonymous ? '匿名投票' : '具名投票' }}
                                    </span>
                                </div>

                                <span class="text-xs text-gray-400">
                                    {{ poll.is_multiple_choice ? '複選' : '單選' }}
                                </span>
                            </div>

                            <!-- 標題與說明 -->
                            <h3 class="text-base font-bold text-gray-900 group-hover:text-indigo-600 transition-colors line-clamp-1">
                                <Link :href="route('polls.show', poll.id)">
                                    {{ poll.title }}
                                </Link>
                            </h3>
                            <p class="text-sm text-gray-500 mt-2 line-clamp-2 min-h-[40px]">
                                {{ poll.description || '無詳細說明' }}
                            </p>

                            <!-- 選項數與參與狀況 -->
                            <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                                <div>
                                    提供 <span class="font-semibold text-gray-700">{{ poll.options_count }}</span> 項選擇
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span><strong class="text-indigo-600 font-bold">{{ poll.voters_count }}</strong> 人已參與</span>
                                </div>
                            </div>
                        </div>

                        <!-- 底部行動列 -->
                        <div class="px-6 py-3.5 bg-gray-50/70 border-t border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="poll.has_voted"
                                    class="inline-flex items-center text-xs font-semibold text-emerald-600"
                                >
                                    <svg class="w-4 h-4 mr-1 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    您已投票
                                </span>
                                <span v-else-if="!poll.is_closed" class="text-xs text-amber-600 font-medium">
                                    尚未投票
                                </span>
                                <span v-else class="text-xs text-gray-400">
                                    未參與
                                </span>
                            </div>

                            <Link
                                :href="route('polls.show', poll.id)"
                                class="inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors"
                            >
                                {{ poll.has_voted || poll.is_closed ? '查看結果' : '立即投票' }}
                                <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 空狀態提示 -->
                <div v-else class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-900">目前沒有符合條件的投票活動</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ currentStatus === 'active' ? '目前尚無進行中的投票活動，稍後請再回來查看。' : '查無相應的投票歷史記錄。' }}
                    </p>
                    <div v-if="can_create && currentStatus === 'active'" class="mt-6">
                        <button
                            @click="openCreateModal"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"
                        >
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            現在發起第一場投票
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 發起投票活動 Modal -->
        <Modal :show="showCreateModal" @close="closeCreateModal" max-width="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <span class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </span>
                        發起企業投票活動
                    </h3>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="mt-5 space-y-5">
                    <div>
                        <InputLabel for="poll-title" value="投票主題 *" />
                        <TextInput
                            id="poll-title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="例如：2026 年度員工旅遊地點票選、福委會尾牙聚餐餐廳意向"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.title" />
                    </div>

                    <div>
                        <InputLabel for="poll-desc" value="活動說明（選填）" />
                        <textarea
                            id="poll-desc"
                            v-model="form.description"
                            rows="2"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                            placeholder="請填寫此投票的目的、備註或相關規則..."
                        ></textarea>
                        <InputError class="mt-1" :message="form.errors.description" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-3 p-4 bg-gray-50 rounded-lg border border-gray-100">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_multiple_choice"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700 font-medium">允許複選（可投多項）</span>
                            </label>

                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_anonymous"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700 font-medium">匿名投票（保密投票人名冊）</span>
                            </label>
                        </div>

                        <div>
                            <InputLabel for="poll-ends-at" value="截止時間（選填，留空則手動關閉）" />
                            <TextInput
                                id="poll-ends-at"
                                v-model="form.ends_at"
                                type="datetime-local"
                                class="mt-1 block w-full text-sm"
                            />
                            <InputError class="mt-1" :message="form.errors.ends_at" />
                        </div>
                    </div>

                    <!-- 選項列表管理 -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <InputLabel value="投票選項清單 (至少 2 項，最多 20 項) *" />
                            <button
                                type="button"
                                @click="addOption"
                                :disabled="form.options.length >= 20"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 disabled:opacity-40"
                            >
                                + 增加新選項
                            </button>
                        </div>

                        <div class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                            <div
                                v-for="(opt, idx) in form.options"
                                :key="idx"
                                class="flex items-center gap-2"
                            >
                                <span class="text-xs font-semibold text-gray-400 w-6 text-center">
                                    {{ idx + 1 }}.
                                </span>
                                <TextInput
                                    v-model="form.options[idx]"
                                    type="text"
                                    class="block w-full text-sm py-1.5"
                                    :placeholder="`選項 ${idx + 1} 內容...`"
                                    required
                                />
                                <button
                                    type="button"
                                    @click="removeOption(idx)"
                                    :disabled="form.options.length <= 2"
                                    class="p-1 text-gray-400 hover:text-rose-500 disabled:opacity-20 transition-colors"
                                    title="刪除此選項"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <InputError class="mt-1" :message="form.errors.options" />
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                        <SecondaryButton @click="closeCreateModal">取消</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">
                            確認發起投票
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
