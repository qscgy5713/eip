<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    enabled: { type: Boolean, required: true },
    available: { type: Boolean, required: true },
});

const form = useForm({ mail_notifications: props.enabled });

const toggle = () => {
    form.mail_notifications = !form.mail_notifications;
    form.put(route('profile.notification-preferences'), { preserveScroll: true });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Email 通知</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                簽核結果、待審提醒、公告等系統通知,除了站內通知鈴噹,也會寄一份到您的 Email 信箱。
            </p>
        </header>

        <p v-if="!available" class="mt-3 text-sm text-amber-700 dark:text-amber-300">系統管理員目前已全站關閉 Email 通知,只會收到站內通知。</p>

        <button type="button" role="switch" :aria-checked="form.mail_notifications" :disabled="form.processing" @click="toggle"
            class="mt-4 inline-flex items-center gap-3 text-sm text-gray-800 dark:text-gray-200">
            <span class="relative inline-block h-6 w-11 rounded-full transition" :class="form.mail_notifications ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'">
                <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition" :class="form.mail_notifications ? 'translate-x-5' : ''"></span>
            </span>
            {{ form.mail_notifications ? '已開啟' : '已關閉' }}
        </button>
    </section>
</template>
