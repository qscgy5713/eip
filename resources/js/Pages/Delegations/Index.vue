<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    myDelegations: Array,
    delegatedToMe: Array,
    availableDelegates: Array,
});

const isModalOpen = ref(false);

const todayStr = new Date().toISOString().slice(0, 10);
const nextWeekDate = new Date();
nextWeekDate.setDate(nextWeekDate.getDate() + 7);
const nextWeekStr = nextWeekDate.toISOString().slice(0, 10);

const form = useForm({
    delegate_id: '',
    start_date: todayStr,
    end_date: nextWeekStr,
    reason: '',
});

const openModal = () => {
    form.reset();
    form.clearErrors();
    form.start_date = todayStr;
    form.end_date = nextWeekStr;
    if (props.availableDelegates.length > 0) {
        form.delegate_id = props.availableDelegates[0].id;
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const submitDelegation = () => {
    form.post(route('delegations.store'), {
        onSuccess: () => {
            closeModal();
        },
    });
};

const toggleDelegation = (id) => {
    form.patch(route('delegations.toggle', id), {
        preserveScroll: true,
    });
};

const deleteDelegation = (id, name) => {
    if (confirm(`確定要刪除對「${name}」的職務代理設定嗎？`)) {
        form.delete(route('delegations.destroy', id), {
            preserveScroll: true,
        });
    }
};

const getStatusBadge = (delegation) => {
    if (!delegation.is_active) {
        return { text: '已停用', class: 'bg-gray-100 text-gray-600' };
    }
    const today = new Date().toISOString().slice(0, 10);
    if (delegation.end_date < today) {
        return { text: '已逾期', class: 'bg-amber-100 text-amber-700' };
    }
    if (delegation.start_date > today) {
        return { text: '尚未開始', class: 'bg-blue-100 text-blue-700' };
    }
    return { text: '生效代簽中', class: 'bg-emerald-100 text-emerald-800 font-bold' };
};
</script>

<template>
    <Head title="簽核職務代理人設定" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800">簽核職務代理人設定</h2>
                    <p class="text-xs text-gray-500 mt-1">設定出差休假時的簽核代簽人，確保行政表單審批流暢運作</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('forms.index')"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition"
                    >
                        &larr; 返回表單簽核
                    </Link>
                    <button
                        @click="openModal"
                        class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 transition flex items-center space-x-1.5"
                    >
                        <span>+ 設定新職務代理人</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                <!-- 狀態提示卡片 -->
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5 text-indigo-900 text-xs flex items-start space-x-3">
                    <svg class="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="space-y-1">
                        <p class="font-bold">職務代理機制說明：</p>
                        <p>1. 在設定的代理期間內，被指派的代理人將擁有調閱您待審單據與執行核准/駁回之授權。</p>
                        <p>2. 單據送出時系統將同步通知代理人，審核歷程將永久留存「由代理人 XXX 代簽」之法律與審計稽核軌跡。</p>
                        <p>3. 您可隨時提前終止或恢復代理關係，保護表單權限安全。</p>
                    </div>
                </div>

                <!-- 區塊 1: 我指派的職務代理人 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-gray-900 flex items-center space-x-2">
                            <span>我指派的代理人 ({{ myDelegations.length }})</span>
                        </h3>
                    </div>

                    <div v-if="myDelegations.length > 0" class="divide-y divide-gray-100">
                        <div
                            v-for="item in myDelegations"
                            :key="item.id"
                            class="py-4 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center space-x-3">
                                    <span class="font-bold text-sm text-gray-900">{{ item.delegate?.name }}</span>
                                    <span class="text-xs text-gray-500 font-mono">{{ item.delegate?.email }}</span>
                                    <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ item.delegate?.department?.name || '無部門' }}</span>
                                    <span :class="['px-2.5 py-0.5 text-xs rounded-full', getStatusBadge(item).class]">
                                        {{ getStatusBadge(item).text }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 flex items-center space-x-4">
                                    <span class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        代理期間：{{ item.start_date }} ~ {{ item.end_date }}
                                    </span>
                                    <span v-if="item.reason" class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                        事由：{{ item.reason }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <button
                                    @click="toggleDelegation(item.id)"
                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition"
                                    :class="item.is_active ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50'"
                                >
                                    {{ item.is_active ? '暫停代理' : '重新啟用' }}
                                </button>
                                <button
                                    @click="deleteDelegation(item.id, item.delegate?.name)"
                                    class="px-3 py-1.5 text-xs font-semibold text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-50 transition"
                                >
                                    刪除
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400 text-sm">
                        目前未設定任何職務代理人
                    </div>
                </div>

                <!-- 區塊 2: 別人指派我為代理人的記錄 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <h3 class="text-base font-bold text-gray-900 flex items-center space-x-2">
                        <span>指派我為代理人之主管 ({{ delegatedToMe.length }})</span>
                    </h3>

                    <div v-if="delegatedToMe.length > 0" class="divide-y divide-gray-100">
                        <div
                            v-for="item in delegatedToMe"
                            :key="item.id"
                            class="py-4 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center space-x-3">
                                    <span class="font-bold text-sm text-gray-900">{{ item.user?.name }}</span>
                                    <span class="text-xs text-gray-500 font-mono">{{ item.user?.email }}</span>
                                    <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ item.user?.department?.name || '無部門' }}</span>
                                    <span :class="['px-2.5 py-0.5 text-xs rounded-full', getStatusBadge(item).class]">
                                        {{ getStatusBadge(item).text }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 flex items-center space-x-4">
                                    <span class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        代理區間：{{ item.start_date }} ~ {{ item.end_date }}
                                    </span>
                                    <span v-if="item.reason" class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                        事由：{{ item.reason }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="getStatusBadge(item).text.includes('生效')">
                                <Link
                                    :href="route('forms.index')"
                                    class="px-3 py-1.5 text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg transition"
                                >
                                    前往簽核中心代簽 &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400 text-sm">
                        目前尚無同仁指定您為職務代理人
                    </div>
                </div>
            </div>
        </div>

        <!-- 新增代理人 Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-5 my-8">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">設定簽核職務代理人</h3>
                        <p class="text-xs text-gray-500 mt-0.5">指定同仁在特定日期區間內擁有您的待審表單代理簽核權限</p>
                    </div>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitDelegation" class="space-y-4 text-sm">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">指派代理同仁 *</label>
                        <select
                            v-model="form.delegate_id"
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option v-for="user in availableDelegates" :key="user.id" :value="user.id">
                                {{ user.name }} ({{ user.department?.name || '無部門' }} · {{ user.job_title || user.role }})
                            </option>
                        </select>
                        <p v-if="form.errors.delegate_id" class="text-xs text-rose-500 mt-1">{{ form.errors.delegate_id }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">代理開始日期 *</label>
                            <input
                                v-model="form.start_date"
                                type="date"
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            />
                            <p v-if="form.errors.start_date" class="text-xs text-rose-500 mt-1">{{ form.errors.start_date }}</p>
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">代理結束日期 *</label>
                            <input
                                v-model="form.end_date"
                                type="date"
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            />
                            <p v-if="form.errors.end_date" class="text-xs text-rose-500 mt-1">{{ form.errors.end_date }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">代理事由說明 (可選)</label>
                        <input
                            v-model="form.reason"
                            type="text"
                            placeholder="如：出差拜訪客戶、休假出國..."
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.reason" class="text-xs text-rose-500 mt-1">{{ form.errors.reason }}</p>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3 border-t">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm"
                        >
                            確定指定代理人
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
