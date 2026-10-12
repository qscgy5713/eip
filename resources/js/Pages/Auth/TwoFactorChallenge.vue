<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

const submit = () => {
    form.post(route('two-factor.challenge'), { onFinish: () => form.reset() });
};
const toggle = () => {
    useRecovery.value = !useRecovery.value;
    form.reset();
    form.clearErrors();
};
</script>

<template>
    <GuestLayout>
        <Head title="雙因素驗證" />

        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            <template v-if="!useRecovery">請輸入手機驗證器 App 顯示的 6 位數驗證碼。</template>
            <template v-else>請輸入其中一組尚未使用的復原碼(格式 xxxxx-xxxxx)。</template>
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <div v-if="!useRecovery">
                <InputLabel for="code" value="驗證碼" />
                <TextInput id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="8" class="mt-1 block w-full" v-model="form.code" required autofocus />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>
            <div v-else>
                <InputLabel for="recovery_code" value="復原碼" />
                <TextInput id="recovery_code" type="text" maxlength="20" class="mt-1 block w-full" v-model="form.recovery_code" required autofocus />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </div>

            <div class="flex items-center justify-between">
                <button type="button" class="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400" @click="toggle">
                    {{ useRecovery ? '改用驗證碼' : '改用復原碼' }}
                </button>
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">驗證並登入</PrimaryButton>
            </div>
            <Link :href="route('login')" class="block text-center text-xs text-gray-500 underline dark:text-gray-400">返回登入</Link>
        </form>
    </GuestLayout>
</template>
