<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    actionTypes: Object,
    totalLogs: Number,
});

const search = ref(props.filters.search || '');
const selectedAction = ref(props.filters.action || '');

const handleFilter = () => {
    router.get(route('audit-logs.index'), {
        search: search.value || undefined,
        action: selectedAction.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilter = () => {
    search.value = '';
    selectedAction.value = '';
    handleFilter();
};

const formatActionBadge = (action) => {
    if (action.includes('clock')) {
        return { label: '考勤打卡', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
    if (action.includes('form') || action.includes('request')) {
        return { label: '表單簽核', bg: 'bg-indigo-50 text-indigo-700 border-indigo-200' };
    }
    if (action.includes('document')) {
        return { label: '知識文件', bg: 'bg-blue-50 text-blue-700 border-blue-200' };
    }
    if (action.includes('room') || action.includes('booking')) {
        return { label: '會議借用', bg: 'bg-purple-50 text-purple-700 border-purple-200' };
    }
    return { label: action, bg: 'bg-gray-100 text-gray-700 border-gray-200' };
};
</script>

<template>
    <Head title="系統審計稽核日誌" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        系統審計稽核日誌 (Audit Trail)
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        完整留存全體同仁之表單簽核、會議室調度、考勤打卡與機密文件調閱等關鍵操作軌跡
                    </p>
                </div>
                <div class="text-sm text-gray-600 bg-white px-3 py-1.5 rounded-lg border border-gray-200 shadow-sm">
                    累計日誌記錄：<span class="font-bold text-indigo-600">{{ totalLogs }}</span> 筆
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 篩選器與搜尋工具列 -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                        <div class="sm:col-span-5">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">關鍵字檢索 (同仁姓名、Email、IP、操作描述)</label>
                            <div class="relative">
                                <input
                                    v-model="search"
                                    @keyup.enter="handleFilter"
                                    type="text"
                                    placeholder="搜尋同仁、IP 或操作備註..."
                                    class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 pl-9"
                                />
                                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>

                        <div class="sm:col-span-4">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">動作類別篩選</label>
                            <select
                                v-model="selectedAction"
                                @change="handleFilter"
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">全部動作類別</option>
                                <option v-for="(count, action) in actionTypes" :key="action" :value="action">
                                    {{ action }} ({{ count }})
                                </option>
                            </select>
                        </div>

                        <div class="sm:col-span-3 flex items-end gap-2">
                            <button
                                @click="handleFilter"
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg shadow-sm transition"
                            >
                                篩選查詢
                            </button>
                            <button
                                v-if="search || selectedAction"
                                @click="resetFilter"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-3 rounded-lg transition"
                            >
                                重設
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 審計日誌表格 -->
                <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 font-semibold text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-5 py-3">時間戳記</th>
                                    <th class="px-5 py-3">操作人員</th>
                                    <th class="px-5 py-3">動作類型</th>
                                    <th class="px-5 py-3">操作詳細描述</th>
                                    <th class="px-5 py-3">來源 IP / 裝置</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                        查無相符之審計稽核日誌
                                    </td>
                                </tr>
                                <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 transition">
                                    <td class="px-5 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                                        {{ log.created_at }}
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <div v-if="log.user" class="flex flex-col">
                                            <span class="font-medium text-gray-900">{{ log.user.name }}</span>
                                            <span class="text-xs text-gray-500">{{ log.user.email }} ({{ log.user.role }})</span>
                                        </div>
                                        <div v-else class="text-gray-400 text-xs italic">
                                            系統自動排程
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border',
                                                formatActionBadge(log.action).bg
                                            ]"
                                        >
                                            {{ formatActionBadge(log.action).label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="text-gray-800 font-medium leading-relaxed">
                                            {{ log.description }}
                                        </div>
                                        <div v-if="log.details" class="mt-1 text-xs text-gray-400 font-mono bg-gray-50 p-1.5 rounded inline-block max-w-lg truncate">
                                            {{ JSON.stringify(log.details) }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                            {{ log.ip_address || '127.0.0.1' }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- 分頁導覽 -->
                    <div
                        v-if="logs.links && logs.links.length > 3"
                        class="px-6 py-4 border-t border-gray-100 flex items-center justify-between"
                    >
                        <div class="text-sm text-gray-500">
                            顯示第 {{ logs.from || 0 }} 至 {{ logs.to || 0 }} 筆，共 {{ logs.total }} 筆日誌
                        </div>
                        <div class="flex gap-1">
                            <template v-for="(link, i) in logs.links" :key="i">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'px-3 py-1.5 text-xs rounded-md border font-medium transition',
                                        link.active
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'
                                    ]"
                                />
                                <span
                                    v-else
                                    v-html="link.label"
                                    class="px-3 py-1.5 text-xs rounded-md border border-gray-100 text-gray-300"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
