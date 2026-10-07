<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    announcement: Object,
    readers: Array,
});
</script>

<template>
    <Head :title="announcement.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-3">
                <Link :href="route('announcements.index')" class="text-sm text-blue-600 hover:underline">&larr; 返回公告列表</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-bold leading-tight text-gray-800 truncate">公告詳情</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">
                <!-- 公告正文卡片 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 space-y-6">
                    <div class="border-b border-gray-100 pb-5 space-y-2">
                        <h1 class="text-2xl font-extrabold text-gray-900">{{ announcement.title }}</h1>
                        <div class="flex items-center space-x-6 text-sm text-gray-500">
                            <span>發布同仁：<strong class="text-gray-800">{{ announcement.author?.name }}</strong></span>
                            <span>發布日期：{{ new Date(announcement.published_at).toLocaleString() }}</span>
                            <span>總已讀人數：<strong class="text-blue-600">{{ announcement.reads_count }} 人</strong></span>
                        </div>
                    </div>

                    <div class="prose max-w-none text-gray-700 leading-relaxed whitespace-pre-line text-base">
                        {{ announcement.content }}
                    </div>
                </div>

                <!-- 主管/行政 查閱追蹤名單 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-800 text-sm mb-3 flex items-center space-x-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>已讀同仁追蹤 (共 {{ readers.length }} 人)</span>
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="(r, idx) in readers"
                            :key="idx"
                            class="inline-flex items-center space-x-1.5 px-3 py-1 bg-gray-50 border border-gray-200 rounded-full text-xs text-gray-600"
                        >
                            <span class="font-medium text-gray-800">{{ r.name }}</span>
                            <span class="text-gray-400">({{ r.read_at }})</span>
                        </span>
                        <span v-if="readers.length === 0" class="text-xs text-gray-400">尚無同仁已讀</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
