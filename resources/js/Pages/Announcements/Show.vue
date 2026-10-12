<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    announcement: Object,
    readers: Array,
    canManage: Boolean,
});

// 編輯公告 (附件僅能追加)
const showEdit = ref(false);
const editForm = useForm({
    title: props.announcement.title,
    content: props.announcement.content,
    category: props.announcement.category,
    priority: props.announcement.priority,
    status: props.announcement.status,
    is_pinned: !!props.announcement.is_pinned,
    attachments: [],
});

const openEdit = () => {
    editForm.defaults({
        title: props.announcement.title,
        content: props.announcement.content,
        category: props.announcement.category,
        priority: props.announcement.priority,
        status: props.announcement.status,
        is_pinned: !!props.announcement.is_pinned,
        attachments: [],
    }).reset();
    editForm.clearErrors();
    showEdit.value = true;
};

const submitEdit = () => {
    editForm
        .transform((d) => ({ ...d, is_pinned: d.is_pinned ? 1 : 0, _method: 'put' }))
        .post(route('announcements.update', props.announcement.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => (showEdit.value = false),
        });
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const handleDelete = async () => {
    if (await confirmDialog(`確定要刪除公告「${props.announcement.title}」嗎？此動作將連同清理所有附件。`)) {
        router.delete(route('announcements.destroy', props.announcement.id));
    }
};

const priorityBadge = (priority) => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-800 border-red-200';
        case 'high': return 'bg-amber-100 text-amber-800 border-amber-200';
        default: return 'bg-indigo-100 text-indigo-800 border-indigo-200';
    }
};
</script>

<template>
    <Head :title="announcement.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link :href="route('announcements.index')" class="text-sm text-indigo-600 hover:underline">&larr; 返回公告列表</Link>
                    <span class="text-gray-300">/</span>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 truncate dark:text-gray-100">公告詳情</h2>
                </div>
                <div v-if="canManage" class="flex items-center space-x-2">
                    <button
                        @click="openEdit"
                        type="button"
                        class="px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition"
                    >
                        編輯公告
                    </button>
                    <button
                        @click="handleDelete"
                        type="button"
                        class="px-3 py-1.5 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition flex items-center space-x-1"
                    >
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        刪除公告
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6 px-4">
                <!-- 提示/成功訊息 -->
                <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center text-emerald-800 shadow-sm text-sm">
                    <svg class="w-5 h-5 text-emerald-600 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <!-- 公告正文卡片 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 space-y-6 dark:bg-gray-800 dark:border-gray-700">
                    <div class="border-b border-gray-100 pb-5 space-y-3 dark:border-gray-700">
                        <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                            <span v-if="announcement.is_pinned" class="px-2.5 py-0.5 text-xs font-bold bg-red-100 text-red-700 border border-red-200 rounded-md">置頂</span>
                            <span :class="['px-2.5 py-0.5 text-xs font-semibold border rounded-md', priorityBadge(announcement.priority)]">
                                {{ announcement.priority === 'urgent' ? '緊急公告' : (announcement.priority === 'high' ? '重要公告' : '一般通知') }}
                            </span>
                            <span v-if="announcement.status === 'draft'" class="px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-md dark:bg-gray-700 dark:border-gray-700 dark:text-gray-300">
                                暫存草稿
                            </span>
                        </div>
                        <h1 class="text-2xl font-extrabold text-gray-900 leading-snug dark:text-gray-100">{{ announcement.title }}</h1>
                        <div class="flex items-center space-x-6 text-sm text-gray-500 pt-1 dark:text-gray-400">
                            <span>發布同仁：<strong class="text-gray-800 dark:text-gray-100">{{ announcement.author?.name }}</strong></span>
                            <span>發布日期：{{ announcement.published_at ? new Date(announcement.published_at).toLocaleString() : '尚未公開' }}</span>
                            <span>總已讀人數：<strong class="text-indigo-600">{{ announcement.reads_count }} 人</strong></span>
                        </div>
                    </div>

                    <div class="prose max-w-none text-gray-700 leading-relaxed whitespace-pre-line text-base dark:text-gray-300">
                        {{ announcement.content }}
                    </div>

                    <!-- 官方檢附檔案與附件下載清單 -->
                    <div v-if="announcement.attachments && announcement.attachments.length > 0" class="pt-6 border-t border-gray-100 space-y-3 dark:border-gray-700">
                        <h3 class="text-sm font-bold text-gray-900 flex items-center space-x-2 dark:text-gray-100">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            <span>官方檢附文件 / 附件檔案 ({{ announcement.attachments.length }})</span>
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div
                                v-for="(file, idx) in announcement.attachments"
                                :key="idx"
                                class="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/70 hover:bg-white hover:border-indigo-300 transition group dark:bg-gray-800/60 dark:border-gray-700"
                            >
                                <div class="flex items-center space-x-3 min-w-0 mr-3">
                                    <div class="p-2.5 bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white rounded-lg transition shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="truncate">
                                        <p class="text-sm font-bold text-gray-800 truncate group-hover:text-indigo-600 transition dark:text-gray-100" :title="file.name">{{ file.name }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ formatSize(file.size) }}</p>
                                    </div>
                                </div>
                                <a
                                    :href="route('announcements.attachments.download', [announcement.id, idx])"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition shrink-0 border border-indigo-100"
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
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 dark:bg-gray-800 dark:border-gray-700">
                    <h3 class="font-bold text-gray-800 text-sm mb-3 flex items-center space-x-2 dark:text-gray-100">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>已讀同仁追蹤 (共 {{ readers.length }} 人)</span>
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="(r, idx) in readers"
                            :key="idx"
                            class="inline-flex items-center space-x-1.5 px-3 py-1 bg-gray-50 border border-gray-200 rounded-full text-xs text-gray-600 dark:bg-gray-800/60 dark:border-gray-700 dark:text-gray-400"
                        >
                            <span class="font-medium text-gray-800 dark:text-gray-100">{{ r.name }}</span>
                            <span class="text-gray-400">({{ r.read_at }})</span>
                        </span>
                        <span v-if="readers.length === 0" class="text-xs text-gray-400">尚無同仁已讀</span>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showEdit" maxWidth="2xl" @close="showEdit = false">
            <form class="p-6 space-y-4 bg-white dark:bg-gray-800" @submit.prevent="submitEdit">
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">編輯公告</h3>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">標題</label>
                    <input v-model="editForm.title" type="text" required maxlength="255" class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                    <p v-if="editForm.errors.title" class="mt-1 text-xs text-red-600">{{ editForm.errors.title }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">分類</label>
                        <select v-model="editForm.category" class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            <option value="company">公司重大</option>
                            <option value="activity">全員活動</option>
                            <option value="administrative">行政通知</option>
                            <option value="general">一般通知</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">重要程度</label>
                        <select v-model="editForm.priority" class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            <option value="normal">一般</option>
                            <option value="high">重要</option>
                            <option value="urgent">緊急</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">狀態</label>
                        <select v-model="editForm.status" class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            <option value="published">已發布</option>
                            <option value="draft">草稿</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">內文</label>
                    <textarea v-model="editForm.content" rows="8" required class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"></textarea>
                    <p v-if="editForm.errors.content" class="mt-1 text-xs text-red-600">{{ editForm.errors.content }}</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">追加附件(既有附件會保留)</label>
                    <input type="file" multiple class="block w-full text-xs text-gray-600 dark:text-gray-300 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700" @change="editForm.attachments = Array.from($event.target.files)" />
                    <p v-if="editForm.errors.attachments || editForm['attachments.0']" class="mt-1 text-xs text-red-600">{{ editForm.errors.attachments || editForm.errors['attachments.0'] }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input v-model="editForm.is_pinned" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    置頂這則公告
                </label>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 transition" @click="showEdit = false">取消</button>
                    <button type="submit" :disabled="editForm.processing" class="px-5 py-2 rounded-xl bg-indigo-600 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 transition">儲存變更</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
