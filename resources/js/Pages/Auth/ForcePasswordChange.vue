<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.force.update'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="更換初始密碼" />

        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-100">
            您的帳號由管理員建立或重設,為了帳號安全,請先設定一組只有您知道的新密碼,才能繼續使用系統。
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="current_password" value="目前的初始密碼" />
                <TextInput id="current_password" type="password" class="mt-1 block w-full" v-model="form.current_password" required autofocus autocomplete="current-password" />
                <InputError class="mt-2" :message="form.errors.current_password" />
            </div>

            <div>
                <InputLabel for="password" value="新密碼" />
                <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required autocomplete="new-password" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">至少 8 位,需含英文字母與數字。</p>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="確認新密碼" />
                <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-center justify-between">
                <Link :href="route('logout')" method="post" as="button" class="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400">登出</Link>
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">更換密碼並繼續</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
