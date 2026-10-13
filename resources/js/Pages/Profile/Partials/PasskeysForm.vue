<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Passkeys } from '@laravel/passkeys';

defineProps({
    passkeys: { type: Array, default: () => [] },
});

const name = ref('');
const busy = ref(false);
const message = ref('');
const supported = Passkeys.isSupported();

// 新增/移除前需要再確認一次密碼(伺服器回 423 時才出現輸入框)
const needPassword = ref(false);
const password = ref('');
let pending = null;

const isLocked = (e) => e?.response?.status === 423 || /confirmation required/i.test(e?.message || '');

const run = async (action) => {
    busy.value = true;
    message.value = '';
    try {
        await action();
        needPassword.value = false;
        pending = null;
    } catch (e) {
        if (isLocked(e)) {
            pending = action;
            needPassword.value = true;
            message.value = '為了安全,請先輸入一次目前的登入密碼。';
        } else {
            message.value = e?.name === 'NotAllowedError' ? '已取消。' : '操作失敗:' + (e?.response?.data?.message || e?.message || '請稍後再試');
        }
    } finally {
        busy.value = false;
    }
};

const confirmPassword = async () => {
    busy.value = true;
    try {
        await window.axios.post('/confirm-password', { password: password.value });
        password.value = '';
    } catch (e) {
        busy.value = false;
        message.value = '密碼不正確,請再試一次。';
        return;
    }
    busy.value = false;
    if (pending) await run(pending);
};

const addAction = async () => {
    await Passkeys.register({ name: name.value.trim() || '我的裝置' });
    name.value = '';
    router.reload({ only: ['passkeys'] });
    message.value = 'Passkey 已新增,下次可以在登入頁直接用指紋或臉部登入。';
};

const add = () => run(addAction);

const remove = (p) => {
    if (!confirm(`確定要移除「${p.name}」這組 Passkey 嗎?`)) return;
    run(async () => {
        await window.axios.delete(route('passkey.destroy', p.id));
        router.reload({ only: ['passkeys'] });
        message.value = 'Passkey 已移除。';
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Passkey(指紋 / 臉部登入)</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                用手機或電腦內建的指紋、臉部或螢幕鎖定直接登入,不用輸入密碼與驗證碼。Passkey 只存在您的裝置,無法被釣魚網站騙走。
                新增或移除時需要先再輸入一次密碼。
            </p>
        </header>

        <p v-if="!supported" class="mt-3 text-sm text-amber-700 dark:text-amber-300">這個瀏覽器不支援 Passkey。</p>
        <div v-else class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm text-gray-700 dark:text-gray-300">
                裝置名稱
                <input v-model="name" type="text" maxlength="60" placeholder="例如:我的 iPhone"
                    class="mt-1 block w-56 rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
            </label>
            <button type="button" :disabled="busy" @click="add"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                新增 Passkey
            </button>
        </div>
        <form v-if="needPassword" class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="confirmPassword">
            <label class="text-sm text-gray-700 dark:text-gray-300">
                目前的登入密碼
                <input v-model="password" type="password" autocomplete="current-password" required
                    class="mt-1 block w-56 rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
            </label>
            <button type="submit" :disabled="busy || !password"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-gray-200 dark:text-gray-900">
                確認密碼並繼續
            </button>
        </form>
        <p v-if="message" class="mt-3 text-sm text-gray-700 dark:text-gray-300" role="status">{{ message }}</p>

        <ul v-if="passkeys.length" class="mt-4 divide-y divide-gray-100 text-sm dark:divide-gray-700">
            <li v-for="p in passkeys" :key="p.id" class="flex items-center justify-between gap-3 py-2 text-gray-700 dark:text-gray-300">
                <span>{{ p.name }}
                    <span class="ms-2 text-xs text-gray-500">{{ p.last_used_at ? '最近使用 ' + p.last_used_at.slice(0, 10) : '尚未使用' }}</span>
                </span>
                <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(p)">移除</button>
            </li>
        </ul>
        <p v-else-if="supported" class="mt-3 text-sm text-gray-500">還沒有任何 Passkey。</p>
    </section>
</template>
