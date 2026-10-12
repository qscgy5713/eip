<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    posts: Object,
    wallet: Object,
    stars: Array,
    recipients: Array,
    coreValues: Object,
    filters: Object,
});

const tabs = [
    { key: 'all', label: '全體文化牆' },
    { key: 'received', label: '我收到的感謝' },
    { key: 'sent', label: '我送出的感謝' },
];

const valueColors = {
    teamwork: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
    excellence: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
    helpfulness: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
    innovation: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800',
    customer_first: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
};

const go = (params) => {
    router.get(route('recognition.index'), { ...props.filters, ...params }, { preserveState: true, replace: true });
};

// 贈送感謝幣 Modal
const showModal = ref(false);
const recipientSearch = ref('');
const form = useForm({
    recipient_id: null,
    points: 10,
    core_value: 'teamwork',
    message: '',
    is_anonymous: false,
});

const filteredRecipients = computed(() => {
    const q = recipientSearch.value.trim().toLowerCase();
    if (!q) return props.recipients.slice(0, 50);
    return props.recipients
        .filter((r) => r.name.toLowerCase().includes(q) || (r.department || '').toLowerCase().includes(q))
        .slice(0, 50);
});

const selectedRecipient = computed(() => props.recipients.find((r) => r.id === form.recipient_id));

const openModal = () => {
    form.reset();
    form.clearErrors();
    recipientSearch.value = '';
    showModal.value = true;
};

const submit = () => {
    form.post(route('recognition.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};

const toggleLike = (post) => {
    router.post(route('recognition.like', post.id), {}, { preserveScroll: true });
};

const removePost = async (post) => {
    if (await confirmDialog('確定要刪除這則感謝貼文嗎？已贈送的感謝幣不會追回。')) {
        router.delete(route('recognition.destroy', post.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="企業感謝幣文化牆" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        企業感謝幣文化牆
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        用一枚感謝幣，讓同仁的付出被看見；收到的感謝幣即為福利點數，可至福利商城兌換。
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('welfare.index')"
                        class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg transition shadow-sm"
                    >
                        前往福利商城
                    </Link>
                    <button
                        type="button"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                        @click="openModal"
                    >
                        贈送感謝幣
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div
                v-if="$page.props.flash?.success"
                class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 shadow-sm text-sm"
            >
                {{ $page.props.flash.success }}
            </div>

            <!-- 錢包指標 -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
                    <p class="text-xs text-gray-500 dark:text-gray-400">本月可贈送感謝幣額度</p>
                    <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-0.5 font-mono">
                        {{ wallet.recognition_quota }}
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">/ {{ wallet.monthly_quota }} 枚，每月 1 日重置</span>
                    </p>
                    <div class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                        <div
                            class="h-full bg-indigo-500"
                            :style="{ width: Math.min(100, (wallet.recognition_quota / wallet.monthly_quota) * 100) + '%' }"
                        ></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
                    <p class="text-xs text-gray-500 dark:text-gray-400">我的福利點數</p>
                    <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5 font-mono">
                        {{ wallet.reward_points }} <span class="text-xs font-normal text-gray-500 dark:text-gray-400">點</span>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- 文化牆 -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex space-x-2">
                            <button
                                v-for="t in tabs"
                                :key="t.key"
                                type="button"
                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition"
                                :class="filters.tab === t.key
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                @click="go({ tab: t.key, page: 1 })"
                            >
                                {{ t.label }}
                            </button>
                        </div>
                        <select
                            :value="filters.core_value"
                            class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            @change="go({ core_value: $event.target.value, page: 1 })"
                        >
                            <option value="">全部核心價值</option>
                            <option v-for="(label, key) in coreValues" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>

                    <div
                        v-for="post in posts.data"
                        :key="post.id"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="text-sm text-gray-800 dark:text-gray-200">
                                <span class="font-bold">{{ post.sender ? post.sender.name : '匿名同仁' }}</span>
                                <span class="text-gray-400 mx-1">感謝</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ post.recipient?.name }}</span>
                                <span v-if="post.recipient?.department" class="text-[11px] text-gray-400 ml-1">
                                    {{ post.recipient.department.name }}
                                </span>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 text-xs font-black rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800 font-mono">
                                +{{ post.points }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line break-words">{{ post.message }}</p>
                        <div class="mt-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    class="px-2 py-0.5 text-[11px] font-bold rounded border"
                                    :class="valueColors[post.core_value]"
                                >
                                    {{ post.core_value_label }}
                                </span>
                                <span class="text-[11px] text-gray-400">{{ post.created_at?.slice(0, 16).replace('T', ' ') }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="text-xs font-semibold transition"
                                    :class="post.liked_by_me ? 'text-rose-600' : 'text-gray-400 hover:text-rose-500'"
                                    @click="toggleLike(post)"
                                >
                                    {{ post.liked_by_me ? '已讚' : '讚' }} · {{ post.likes_count }}
                                </button>
                                <button
                                    v-if="post.can_delete"
                                    type="button"
                                    class="text-xs text-gray-400 hover:text-red-600 transition"
                                    @click="removePost(post)"
                                >
                                    刪除
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="posts.data.length === 0"
                        class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center text-sm text-gray-400"
                    >
                        目前還沒有感謝貼文，成為第一個說謝謝的人吧！
                    </div>

                    <!-- 分頁 -->
                    <div v-if="posts.links && posts.last_page > 1" class="flex flex-wrap gap-1 justify-center">
                        <template v-for="(link, i) in posts.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="px-3 py-1 text-xs rounded border"
                                :class="link.active
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700'"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>

                <!-- 本月感謝之星 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 h-fit">
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base pb-3 border-b border-gray-100 dark:border-gray-700">
                        本月感謝之星
                    </h3>
                    <ol class="mt-3 space-y-3">
                        <li v-for="(star, idx) in stars" :key="star.user_id" class="flex items-center gap-3">
                            <span
                                class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-xs font-black"
                                :class="idx === 0 ? 'bg-amber-400 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300'"
                            >
                                {{ idx + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ star.name }}</p>
                                <p class="text-[11px] text-gray-400 truncate">{{ star.department || '總部' }} · 收到 {{ star.thanks_count }} 則感謝</p>
                            </div>
                            <span class="text-sm font-black text-amber-600 dark:text-amber-400 font-mono">{{ star.total_points }}</span>
                        </li>
                    </ol>
                    <p v-if="stars.length === 0" class="py-6 text-center text-xs text-gray-400">本月尚無榜單</p>
                </div>
            </div>
        </div>

        <!-- 贈送感謝幣 Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">贈送感謝幣</h3>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">感謝對象</label>
                        <div v-if="selectedRecipient" class="flex items-center justify-between px-3 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-sm">
                            <span class="font-bold text-indigo-700 dark:text-indigo-300">
                                {{ selectedRecipient.name }}
                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ selectedRecipient.department }}</span>
                            </span>
                            <button type="button" class="text-xs text-gray-500 hover:text-gray-800 dark:text-gray-400" @click="form.recipient_id = null">更換</button>
                        </div>
                        <template v-else>
                            <input
                                v-model="recipientSearch"
                                type="text"
                                placeholder="輸入姓名或部門搜尋同仁"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <ul class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                                <li
                                    v-for="r in filteredRecipients"
                                    :key="r.id"
                                    class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200"
                                    @click="form.recipient_id = r.id"
                                >
                                    {{ r.name }} <span class="text-xs text-gray-400">{{ r.department }}</span>
                                </li>
                                <li v-if="filteredRecipients.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">查無符合的在職同仁</li>
                            </ul>
                        </template>
                        <p v-if="form.errors.recipient_id" class="mt-1 text-xs text-red-600">{{ form.errors.recipient_id }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">核心價值</label>
                            <select v-model="form.core_value" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <option v-for="(label, key) in coreValues" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                感謝幣數量 (1~50，剩餘 {{ wallet.recognition_quota }})
                            </label>
                            <input
                                v-model.number="form.points"
                                type="number"
                                min="1"
                                max="50"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                            <p v-if="form.errors.points" class="mt-1 text-xs text-red-600">{{ form.errors.points }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">感謝的話</label>
                        <textarea
                            v-model="form.message"
                            rows="3"
                            maxlength="500"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            placeholder="說說對方做了什麼讓你想感謝他..."
                        ></textarea>
                        <p v-if="form.errors.message" class="mt-1 text-xs text-red-600">{{ form.errors.message }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                        <input v-model="form.is_anonymous" type="checkbox" class="rounded border-gray-300 text-indigo-600 dark:border-gray-700" />
                        匿名送出（文化牆不顯示我的名字，對象的通知也不會揭露）
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200"
                            @click="showModal = false"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || !form.recipient_id || !form.message"
                            class="px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50"
                        >
                            送出感謝
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
