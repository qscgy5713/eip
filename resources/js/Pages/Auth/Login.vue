<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Passkeys } from '@laravel/passkeys';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const passkeySupported = Passkeys.isSupported();
const passkeyBusy = ref(false);
const passkeyError = ref('');

const passkeyLogin = async () => {
    passkeyBusy.value = true;
    passkeyError.value = '';
    try {
        const res = await Passkeys.verify({ remember: form.remember });
        window.location.href = res.redirect || route('dashboard');
    } catch (e) {
        passkeyError.value = e?.name === 'NotAllowedError' ? '' : 'Passkey 登入失敗,請改用密碼登入,或確認這台裝置已新增過 Passkey。';
    } finally {
        passkeyBusy.value = false;
    }
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="登入" />

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="電子郵件" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="密碼" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400"
                        >記住我</span
                    >
                </label>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                >
                    忘記密碼？
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    登入
                </PrimaryButton>
            </div>
        </form>

        <div v-if="passkeySupported" class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
            <button type="button" :disabled="passkeyBusy" @click="passkeyLogin"
                class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                用 Passkey 登入(指紋 / 臉部)
            </button>
            <p v-if="passkeyError" class="mt-2 text-sm text-red-600" role="alert">{{ passkeyError }}</p>
        </div>
    </GuestLayout>
</template>
