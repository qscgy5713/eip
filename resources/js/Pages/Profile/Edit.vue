<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import LoginActivityForm from './Partials/LoginActivityForm.vue';
import NotificationPreferencesForm from './Partials/NotificationPreferencesForm.vue';
import PushNotificationsForm from './Partials/PushNotificationsForm.vue';
import PasskeysForm from './Partials/PasskeysForm.vue';
import TwoFactorForm from './Partials/TwoFactorForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    twoFactor: {
        type: Object,
        required: true,
    },
    loginEvents: {
        type: Array,
        default: () => [],
    },
    mailNotifications: {
        type: Boolean,
        default: true,
    },
    mailNotificationsAvailable: {
        type: Boolean,
        default: true,
    },
    push: {
        type: Object,
        default: () => ({ publicKey: null, devices: [] }),
    },
    passkeys: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="個人帳號設定" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100"
            >
                個人帳號設定
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8 px-4">
                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <TwoFactorForm :two-factor="twoFactor" />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <PasskeysForm :passkeys="passkeys" />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <LoginActivityForm :events="loginEvents" />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <NotificationPreferencesForm :enabled="mailNotifications" :available="mailNotificationsAvailable" />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <PushNotificationsForm :push="push" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
