<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

defineProps({
    events: { type: Array, required: true },
});

const form = useForm({ password: '' });
const logoutOthers = () => form.post(route('profile.logout-other-devices'), { preserveScroll: true, onSuccess: () => form.reset() });

const labels = { success: '登入成功', failed: '密碼錯誤', two_factor_failed: '驗證碼錯誤' };
const fmt = (s) => (s ? s.replace('T', ' ').slice(0, 16) : '');
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">登入紀錄與裝置</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                最近 15 筆與您帳號有關的登入嘗試。看到不是您的紀錄,請立即更改密碼並登出其他裝置。
            </p>
        </header>

        <p v-if="!events.length" class="mt-4 text-sm text-gray-500">還沒有登入紀錄。</p>
        <ul v-else class="mt-4 divide-y divide-gray-100 text-sm dark:divide-gray-700">
            <li v-for="e in events" :key="e.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                <span class="font-medium" :class="e.event === 'success' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ labels[e.event] || e.event }}</span>
                <span class="text-gray-700 dark:text-gray-300">{{ e.device }}</span>
                <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ e.ip }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ fmt(e.created_at) }}</span>
            </li>
        </ul>

        <form class="mt-6 max-w-xl space-y-2 border-t border-gray-100 pt-5 dark:border-gray-700" @submit.prevent="logoutOthers">
            <InputLabel for="lo_pw" value="登出其他裝置(輸入目前密碼確認)" />
            <TextInput id="lo_pw" type="password" class="block w-full" v-model="form.password" autocomplete="current-password" required />
            <InputError :message="form.errors.password" />
            <SecondaryButton type="submit" :disabled="form.processing">登出其他所有裝置</SecondaryButton>
        </form>
    </section>
</template>
