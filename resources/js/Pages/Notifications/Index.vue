<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    notifications: Object,
    unreadCount: Number,
});

const markAllAsRead = () => {
    router.post(route('notifications.readAll'));
};

const markAsRead = (id, redirectTo = null) => {
    router.post(route('notifications.read', id), {
        redirect_to: redirectTo,
    });
};

const deleteNotification = (id) => {
    if (confirm('確定要刪除此則通知嗎？')) {
        router.delete(route('notifications.destroy', id));
    }
};

const getTypeBadge = (type) => {
    switch (type) {
        case 'form_approval':
            return { label: '表單簽核', bg: 'bg-indigo-50 text-indigo-700 border-indigo-200' };
        case 'announcement':
            return { label: '重要公告', bg: 'bg-amber-50 text-amber-700 border-amber-200' };
        case 'meeting_room':
            return { label: '會議預約', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'document':
            return { label: '文件修訂', bg: 'bg-blue-50 text-blue-700 border-blue-200' };
        default:
            return { label: '系統通知', bg: 'bg-gray-100 text-gray-700 border-gray-200' };
    }
};
</script>

<template>
    <Head title="通知中心" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900">
                        🔔 個人通知中心
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        掌握待簽核單據、審查結果、會議室借用確認與重要內部公佈資訊
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm transition"
                    >
                        <svg class="w-4 h-4 mr-1.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        全部標記為已讀 ({{ unreadCount }})
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <!-- 通知列表容器 -->
                <div class="bg-white shadow-sm sm:rounded-xl border border-gray-200 overflow-hidden">
                    <div v-if="notifications.data.length === 0" class="py-16 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <div class="text-base font-medium text-gray-700">目前沒有任何通知</div>
                        <p class="text-sm text-gray-400 mt-1">有新的簽核待辦或公佈事項時會即時顯示在此處</p>
                    </div>

                    <ul v-else class="divide-y divide-gray-100">
                        <li
                            v-for="item in notifications.data"
                            :key="item.id"
                            :class="[
                                'p-5 transition hover:bg-gray-50 flex items-start justify-between gap-4',
                                !item.read_at ? 'bg-indigo-50/30' : ''
                            ]"
                        >
                            <div class="flex items-start gap-4 flex-1 min-w-0">
                                <!-- 未讀指示圓點 -->
                                <div class="mt-1 flex-shrink-0">
                                    <span
                                        v-if="!item.read_at"
                                        class="inline-block w-2.5 h-2.5 rounded-full bg-indigo-600 ring-4 ring-indigo-100"
                                    ></span>
                                    <span
                                        v-else
                                        class="inline-block w-2.5 h-2.5 rounded-full bg-gray-300"
                                    ></span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border',
                                                getTypeBadge(item.type).bg
                                            ]"
                                        >
                                            {{ getTypeBadge(item.type).label }}
                                        </span>
                                        <h3
                                            :class="[
                                                'text-base leading-snug truncate',
                                                !item.read_at ? 'font-bold text-gray-900' : 'font-medium text-gray-700'
                                            ]"
                                        >
                                            {{ item.title }}
                                        </h3>
                                        <span class="text-xs text-gray-400 ml-auto whitespace-nowrap">
                                            {{ item.created_at }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-gray-600 line-clamp-2 mt-1">
                                        {{ item.message }}
                                    </p>

                                    <!-- 底部操作列 -->
                                    <div class="mt-3 flex items-center gap-4 text-xs">
                                        <span class="text-gray-400">來自：{{ item.sender_name }}</span>

                                        <button
                                            v-if="item.action_url"
                                            @click="markAsRead(item.id, item.action_url)"
                                            class="font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
                                        >
                                            查看詳情
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>

                                        <button
                                            v-if="!item.read_at"
                                            @click="markAsRead(item.id)"
                                            class="text-gray-500 hover:text-gray-700"
                                        >
                                            標記為已讀
                                        </button>

                                        <button
                                            @click="deleteNotification(item.id)"
                                            class="text-red-400 hover:text-red-600 ml-auto"
                                        >
                                            刪除
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <!-- 分頁導覽 -->
                    <div
                        v-if="notifications.links && notifications.links.length > 3"
                        class="px-6 py-4 border-t border-gray-100 flex items-center justify-between"
                    >
                        <div class="text-sm text-gray-500">
                            顯示第 {{ notifications.from || 0 }} 至 {{ notifications.to || 0 }} 筆，共 {{ notifications.total }} 則通知
                        </div>
                        <div class="flex gap-1">
                            <template v-for="(link, i) in notifications.links" :key="i">
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
