<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    announcements: Object,
    category: String,
});

const filterCategory = (cat) => {
    router.get(route('announcements.index'), { category: cat }, { preserveState: true });
};

const categories = [
    { key: 'all', label: '全部公告' },
    { key: 'company', label: '公司重大' },
    { key: 'activity', label: '全員活動' },
    { key: 'general', label: '一般通知' },
];

const priorityBadge = (priority) => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-800 border-red-200';
        case 'high': return 'bg-amber-100 text-amber-800 border-amber-200';
        default: return 'bg-blue-100 text-blue-800 border-blue-200';
    }
};
</script>

<template>
    <Head title="企業公告中心" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-gray-800">企業公告中心</h2>
                <span class="text-sm text-gray-500">掌握公司重要決策與最新活動訊息</span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 分類切換標籤 -->
                <div class="flex space-x-2 border-b border-gray-200 pb-3">
                    <button
                        v-for="cat in categories"
                        :key="cat.key"
                        @click="filterCategory(cat.key)"
                        :class="[
                            'px-4 py-2 text-sm font-medium rounded-lg transition',
                            category === cat.key ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
                        ]"
                    >
                        {{ cat.label }}
                    </button>
                </div>

                <!-- 公告列表清單 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                    <div
                        v-for="item in announcements.data"
                        :key="item.id"
                        class="p-5 hover:bg-gray-50/80 transition flex items-start justify-between gap-4"
                    >
                        <div class="space-y-1.5 flex-1">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <span v-if="item.is_pinned" class="px-2 py-0.5 text-xs font-semibold bg-rose-50 text-rose-600 border border-rose-200 rounded">置頂</span>
                                <span :class="['px-2 py-0.5 text-xs font-medium border rounded', priorityBadge(item.priority)]">
                                    {{ item.priority === 'urgent' ? '緊急' : (item.priority === 'high' ? '重要' : '一般') }}
                                </span>
                                <Link :href="route('announcements.show', item.id)" class="text-lg font-bold text-gray-900 hover:text-blue-600">
                                    {{ item.title }}
                                </Link>
                            </div>
                            <p class="text-sm text-gray-500 line-clamp-2">{{ item.content }}</p>
                            <div class="flex items-center space-x-4 text-xs text-gray-400 pt-1">
                                <span>發布人：{{ item.author?.name }}</span>
                                <span>時間：{{ new Date(item.published_at).toLocaleString() }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col items-end justify-between self-stretch shrink-0">
                            <span v-if="!item.is_read" class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700">未讀</span>
                            <span v-else class="text-xs text-gray-400">已讀</span>
                            <Link :href="route('announcements.show', item.id)" class="text-sm font-medium text-blue-600 hover:underline mt-2">
                                查看內文 &rarr;
                            </Link>
                        </div>
                    </div>

                    <div v-if="announcements.data.length === 0" class="p-12 text-center text-gray-400">此分類目前無公告</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
