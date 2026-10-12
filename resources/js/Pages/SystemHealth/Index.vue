<script setup>
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    checks: { type: Object, required: true },
    queue: { type: Object, required: true },
    mail: { type: Object, required: true },
    failedJobs: { type: Array, required: true },
});

const ago = (s) => (s === null ? '' : s < 90 ? `${s} 秒前` : s < 5400 ? `${Math.round(s / 60)} 分鐘前` : `${Math.round(s / 3600)} 小時前`);

const retry = (j) => router.post(route('system-health.retry', j.id), {}, { preserveScroll: true });
const retryAll = async () => {
    if (await confirmDialog(`確定要重送全部 ${props.queue.failed_total} 筆失敗任務嗎?`)) {
        router.post(route('system-health.retry-all'), {}, { preserveScroll: true });
    }
};
const forget = async (j) => {
    if (await confirmDialog('確定要刪除這筆失敗任務嗎?刪除後無法重送。')) {
        router.delete(route('system-health.forget', j.id), { preserveScroll: true });
    }
};
const refresh = () => router.reload({ preserveScroll: true });
</script>

<template>
    <Head title="系統健康" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">系統健康</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">背景服務、寄信佇列、備份與磁碟狀態。Email 寄不出去時,先看這一頁。</p>
                </div>
                <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-200" @click="refresh">重新整理</button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">資料庫</p>
                        <p class="mt-1 text-lg font-semibold" :class="checks.database.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ checks.database.ok ? '正常' : '異常' }}</p>
                        <p class="text-xs text-gray-400">{{ checks.database.detail }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">佇列 Worker(負責寄信)</p>
                        <p class="mt-1 text-lg font-semibold" :class="checks.queue.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ checks.queue.ok ? '運行中' : '沒有回應' }}</p>
                        <p class="text-xs text-gray-400">{{ checks.queue.last_seen ? `最後回報 ${ago(checks.queue.seconds_ago)}` : '從未回報,請確認 queue 容器已啟動' }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">排程器(每日提醒)</p>
                        <p class="mt-1 text-lg font-semibold" :class="checks.scheduler.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ checks.scheduler.ok ? '運行中' : '沒有回應' }}</p>
                        <p class="text-xs text-gray-400">{{ checks.scheduler.last_seen ? `最後回報 ${ago(checks.scheduler.seconds_ago)}` : '從未回報,請確認 scheduler 容器已啟動' }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">最近備份</p>
                        <p class="mt-1 text-lg font-semibold" :class="checks.backup.found && checks.backup.hours_ago <= 48 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                            {{ checks.backup.found ? `${checks.backup.hours_ago} 小時前` : '沒有備份' }}
                        </p>
                        <p class="text-xs text-gray-400">{{ checks.backup.found ? checks.backup.at : '請執行 make backup' }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">磁碟使用</p>
                        <p class="mt-1 text-lg font-semibold" :class="checks.disk.used_percent < 85 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ checks.disk.used_percent }}%</p>
                        <p class="text-xs text-gray-400">剩餘 {{ checks.disk.free_gb }} GB / 共 {{ checks.disk.total_gb }} GB</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">寄信設定</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ mail.mailer }}</p>
                        <p class="text-xs text-gray-400">{{ mail.host || '—' }} · {{ mail.from }}</p>
                        <p v-if="!mail.notifications_enabled" class="text-xs text-amber-600 dark:text-amber-400">Email 通知已全站關閉</p>
                        <p v-else-if="mail.mailer === 'log'" class="text-xs text-amber-600 dark:text-amber-400">目前只寫入日誌,不會真的寄出</p>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">佇列</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">連線 {{ queue.connection }} · 等待處理 {{ queue.pending ?? '—' }} 筆 · 失敗 {{ queue.failed_total }} 筆</p>
                        </div>
                        <button v-if="failedJobs.length" type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700" @click="retryAll">全部重送</button>
                    </div>

                    <p v-if="!failedJobs.length" class="mt-4 text-sm text-emerald-700 dark:text-emerald-400">沒有失敗的任務。</p>
                    <ul v-else class="mt-4 divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="j in failedJobs" :key="j.id" class="flex flex-wrap items-start justify-between gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ j.job }} <span class="ml-1 text-xs font-normal text-gray-400">{{ j.failed_at }}</span></p>
                                <p class="mt-1 break-words text-xs text-red-600 dark:text-red-400">{{ j.error }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-200" @click="retry(j)">重送</button>
                                <button type="button" class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 dark:border-red-800 dark:text-red-400" @click="forget(j)">刪除</button>
                            </div>
                        </li>
                    </ul>
                    <p v-if="queue.failed_total > failedJobs.length" class="mt-3 text-xs text-gray-500 dark:text-gray-400">僅顯示最近 {{ failedJobs.length }} 筆。</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
