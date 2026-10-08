<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    announcement: Object,
    readers: Array,
    canManage: Boolean,
});

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const handleDelete = () => {
    if (confirm(`確定要刪除公告「${props.announcement.title}」嗎？此動作將連同清理所有附件。`)) {
        router.delete(route('announcements.destroy', props.announcement.id));
    }
};

const priorityBadge = (priority) => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-800 border-red-200';
        case 'high': return 'bg-amber-100 text-amber-800 border-amber-200';
        default: return 'bg-blue-100 text-blue-800 border-blue-200';
    }
};
</script>

<template>
    <Head :title="announcement.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link :href="route('announcements.index')" class="text-sm text-blue-600 hover:underline">&larr; 返回公告列表</Link>
                    <span class="text-gray-300">/</span>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 truncate">公告詳情</h2>
                </div>
                <div v-if="canManage" class="flex items-center space-x-2">
                    <button
                        @click="handleDelete"
                        type="button"
                        class="px-3 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100 transition flex items-center space-x-1"
                    >
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        刪除公告
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">
                <!-- 提示/成功訊息 -->
                <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center text-emerald-800 shadow-sm text-sm">
                    <svg class="w-5 h-5 text-emerald-600 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <!-- 公告正文卡片 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 space-y-6">
                    <div class="border-b border-gray-100 pb-5 space-y-3">
                        <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                            <span v-if="announcement.is_pinned" class="px-2.5 py-0.5 text-xs font-bold bg-rose-100 text-rose-700 border border-rose-200 rounded-md">置頂</span>
                            <span :class="['px-2.5 py-0.5 text-xs font-semibold border rounded-md', priorityBadge(announcement.priority)]">
                                {{ announcement.priority === 'urgent' ? '緊急公告' : (announcement.priority === 'high' ? '重要公告' : '一般通知') }}
                            </span>
                            <span v-if="announcement.status === 'draft'" class="px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-md">
                                暫存草稿
                            </span>
                        </div>
                        <h1 class="text-2xl font-extrabold text-gray-900 leading-snug">{{ announcement.title }}</h1>
                        <div class="flex items-center space-x-6 text-sm text-gray-500 pt-1">
                            <span>發布同仁：<strong class="text-gray-800">{{ announcement.author?.name }}</strong></span>
                            <span>發布日期：{{ announcement.published_at ? new Date(announcement.published_at).toLocaleString() : '尚未公開' }}</span>
                            <span>總已讀人數：<strong class="text-blue-600">{{ announcement.reads_count }} 人</strong></span>
                        </div>
                    </div>

                    <div class="prose max-w-none text-gray-700 leading-relaxed whitespace-pre-line text-base">
                        {{ announcement.content }}
                    </div>

                    <!-- 官方檢附檔案與附件下載清單 -->
                    <div v-if="announcement.attachments && announcement.attachments.length > 0" class="pt-6 border-t border-gray-100 space-y-3">
                        <h3 class="text-sm font-bold text-gray-900 flex items-center space-x-2">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            <span>官方檢附文件 / 附件檔案 ({{ announcement.attachments.length }})</span>
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div
                                v-for="(file, idx) in announcement.attachments"
                                :key="idx"
                                class="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/70 hover:bg-white hover:border-blue-300 transition group"
                            >
                                <div class="flex items-center space-x-3 min-w-0 mr-3">
                                    <div class="p-2.5 bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white rounded-lg transition shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="truncate">
                                        <p class="text-sm font-bold text-gray-800 truncate group-hover:text-blue-600 transition" :title="file.name">{{ file.name }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ formatSize(file.size) }}</p>
                                    </div>
                                </div>
                                <a
                                    :href="route('announcements.attachments.download', [announcement.id, idx])"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition shrink-0 border border-blue-100"
                                    download
                                >
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    下載
                                </a>
                            </div>
                        </div>
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
