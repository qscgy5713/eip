<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    wallet: Object,
    rewards: Array,
    myRedemptions: Array,
    manageQueue: Array,
    categories: Object,
    canManage: Boolean,
    filters: Object,
});

const tab = ref(props.filters.tab || 'shop');

const statusStyle = {
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    fulfilled: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    cancelled: 'bg-gray-100 text-gray-500 border-gray-200',
};

const redeem = async (reward) => {
    if (await confirmDialog(`確定以 ${reward.points_cost} 點兌換《${reward.name}》嗎？`)) {
        router.post(route('welfare.redeem', reward.id), {}, { preserveScroll: true });
    }
};

const cancelRedemption = async (r) => {
    if (await confirmDialog(`確定取消兌換單 ${r.redemption_code} 並退還 ${r.points_spent} 點嗎？`)) {
        router.post(route('welfare.cancel', r.id), {}, { preserveScroll: true });
    }
};

const fulfill = (r) => {
    router.post(route('welfare.fulfill', r.id), {}, { preserveScroll: true });
};

const toggleReward = (reward) => {
    router.patch(route('welfare.rewards.toggle', reward.id), {}, { preserveScroll: true });
};

// 上架商品 Modal
const showModal = ref(false);
const form = useForm({
    name: '',
    description: '',
    points_cost: 50,
    stock: -1,
    category: 'gift_card',
});

const submitReward = () => {
    form.post(route('welfare.rewards.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="福利點數商城" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">福利點數商城</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        以同仁互贈所累積的福利點數，兌換咖啡券、禮券、休閒票券與榮譽休假。
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('recognition.index')"
                        class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg transition shadow-sm"
                    >
                        前往感謝幣文化牆
                    </Link>
                    <button
                        v-if="canManage"
                        type="button"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                        @click="showModal = true"
                    >
                        上架新商品
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-100 dark:border-emerald-700/50 shadow-sm text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.errors?.error" class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 dark:bg-red-900/20 dark:text-red-100 dark:border-red-700/50 shadow-sm text-sm">
                {{ $page.props.errors.error }}
            </div>

            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
                <p class="text-xs text-gray-500 dark:text-gray-400">我的福利點數存摺</p>
                <p class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-0.5 font-mono">
                    {{ wallet.reward_points }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">點</span>
                </p>
            </div>

            <div class="flex space-x-2">
                <button
                    v-for="t in [
                        { key: 'shop', label: '福利商城' },
                        { key: 'mine', label: '我的兌換紀錄' },
                        ...(canManage ? [{ key: 'manage', label: '兌換核銷管理' }] : []),
                    ]"
                    :key="t.key"
                    type="button"
                    class="px-3 py-1.5 text-xs font-bold rounded-lg transition"
                    :class="tab === t.key
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                    @click="tab = t.key"
                >
                    {{ t.label }}
                </button>
            </div>

            <!-- 商城 -->
            <div v-if="tab === 'shop'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="reward in rewards"
                    :key="reward.id"
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5 flex flex-col justify-between"
                    :class="{ 'opacity-60': !reward.is_active }"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                {{ reward.category_label }}
                            </span>
                            <span v-if="!reward.is_active" class="text-[11px] font-bold text-gray-500 dark:text-gray-400">已下架</span>
                            <span v-else-if="reward.is_sold_out" class="text-[11px] font-bold text-red-600">已兌換完畢</span>
                            <span v-else-if="reward.stock > 0" class="text-[11px] text-gray-400">剩餘 {{ reward.stock }}</span>
                        </div>
                        <h3 class="mt-3 font-bold text-gray-900 dark:text-gray-100">{{ reward.name }}</h3>
                        <p v-if="reward.description" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ reward.description }}</p>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-lg font-black text-amber-600 dark:text-amber-400 font-mono">{{ reward.points_cost }} 點</span>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="canManage"
                                type="button"
                                class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-400"
                                @click="toggleReward(reward)"
                            >
                                {{ reward.is_active ? '下架' : '上架' }}
                            </button>
                            <button
                                type="button"
                                :disabled="!reward.is_active || reward.is_sold_out || wallet.reward_points < reward.points_cost"
                                class="px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-40 disabled:cursor-not-allowed"
                                @click="redeem(reward)"
                            >
                                {{ wallet.reward_points < reward.points_cost ? '點數不足' : '立即兌換' }}
                            </button>
                        </div>
                    </div>
                </div>
                <div
                    v-if="rewards.length === 0"
                    class="col-span-full rounded-xl border border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-12 text-center"
                >
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">商城目前沒有商品</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ canManage ? '點右上角「上架新商品」，為同仁準備第一份福利。' : '人資上架商品後，就能在這裡用福利點數兌換。' }}
                    </p>
                </div>
            </div>

            <!-- 我的兌換紀錄 -->
            <div v-if="tab === 'mine'" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                <div v-for="r in myRedemptions" :key="r.id" class="p-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ r.reward?.name }}</p>
                        <p class="text-[11px] text-gray-400 font-mono">{{ r.redemption_code }} · {{ r.points_spent }} 點</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="px-2 py-0.5 text-[11px] font-bold rounded border" :class="statusStyle[r.status]">{{ r.status_label }}</span>
                        <button v-if="r.status === 'pending'" type="button" class="text-xs text-gray-500 hover:text-red-600 dark:text-gray-400" @click="cancelRedemption(r)">
                            取消兌換
                        </button>
                    </div>
                </div>
                <div v-if="myRedemptions.length === 0" class="p-10 text-center text-sm text-gray-400">尚無兌換紀錄</div>
            </div>

            <!-- 核銷管理 -->
            <div v-if="tab === 'manage' && canManage" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                <div v-for="r in manageQueue" :key="r.id" class="p-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">
                            {{ r.user?.name }}
                            <span class="font-normal text-gray-400 text-xs">{{ r.user?.department?.name }}</span>
                            兌換 {{ r.reward?.name }}
                        </p>
                        <p class="text-[11px] text-gray-400 font-mono">{{ r.redemption_code }} · {{ r.points_spent }} 點</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="px-2 py-0.5 text-[11px] font-bold rounded border" :class="statusStyle[r.status]">{{ r.status_label }}</span>
                        <template v-if="r.status === 'pending'">
                            <button type="button" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white" @click="fulfill(r)">
                                核銷發放
                            </button>
                            <button type="button" class="text-xs text-gray-500 hover:text-red-600 dark:text-gray-400" @click="cancelRedemption(r)">取消退點</button>
                        </template>
                    </div>
                </div>
                <div v-if="manageQueue.length === 0" class="p-10 text-center text-sm text-gray-400">目前沒有兌換單</div>
            </div>
        </div>

        <!-- 上架 Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">上架福利商品</h3>
                <form class="mt-4 space-y-3" @submit.prevent="submitReward">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">商品名稱</label>
                        <input v-model="form.name" type="text" maxlength="150" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">說明</label>
                        <textarea v-model="form.description" rows="2" maxlength="1000" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"></textarea>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">分類</label>
                            <select v-model="form.category" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">所需點數</label>
                            <input v-model.number="form.points_cost" type="number" min="1" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" />
                            <p v-if="form.errors.points_cost" class="mt-1 text-xs text-red-600">{{ form.errors.points_cost }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">庫存 (-1 無限)</label>
                            <input v-model.number="form.stock" type="number" min="-1" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" />
                            <p v-if="form.errors.stock" class="mt-1 text-xs text-red-600">{{ form.errors.stock }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200" @click="showModal = false">取消</button>
                        <button type="submit" :disabled="form.processing || !form.name" class="px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50">上架</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
