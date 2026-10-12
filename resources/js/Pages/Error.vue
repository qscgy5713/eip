<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ status: Number });

const messages = {
    403: { title: '沒有權限', body: '你的帳號無法存取這個頁面,如需使用請聯絡系統管理員。' },
    404: { title: '找不到頁面', body: '這個網址不存在,或內容已被移除。' },
    419: { title: '頁面已逾時', body: '停留時間過長,請重新整理頁面後再試一次。' },
    500: { title: '系統發生錯誤', body: '我們已記錄這次問題,請稍後再試。' },
    503: { title: '系統維護中', body: '服務暫時無法使用,請稍後再試。' },
};

const goBack = () => window.history.back();

const info = computed(() => messages[props.status] || messages[500]);
</script>

<template>
    <Head :title="info.title" />
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 dark:bg-gray-950">
        <div class="w-full max-w-md text-center">
            <p class="text-6xl font-bold tracking-tight text-indigo-600 dark:text-indigo-400">{{ status }}</p>
            <h1 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ info.title }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ info.body }}</p>
            <div class="mt-8 flex justify-center gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    @click="goBack"
                >
                    回上一頁
                </button>
                <Link
                    :href="route('dashboard')"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    回到儀表板
                </Link>
            </div>
        </div>
    </div>
</template>
