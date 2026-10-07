<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    form: Object,
});

const initialData = {};
if (props.form.fields_schema) {
    props.form.fields_schema.forEach(field => {
        initialData[field.key] = field.type === 'select' && field.options ? field.options[0] : '';
    });
}

const formState = useForm({
    title: `${props.form.name}申請`,
    data: initialData,
});

const submit = () => {
    formState.post(route('forms.store', props.form.id));
};
</script>

<template>
    <Head :title="`填寫${form.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-3">
                <Link :href="route('forms.index')" class="text-sm text-blue-600 hover:underline">&larr; 返回簽核中心</Link>
                <span class="text-gray-300">/</span>
                <h2 class="text-xl font-bold leading-tight text-gray-800">發起申請：{{ form.name }}</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                    <div class="mb-6 pb-4 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900">{{ form.name }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ form.description }}</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">申請主旨 / 標題</label>
                            <input
                                v-model="formState.title"
                                type="text"
                                required
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                            />
                        </div>

                        <!-- 動態欄位 -->
                        <div v-for="field in form.fields_schema" :key="field.key" class="space-y-1">
                            <label class="block text-sm font-semibold text-gray-700">{{ field.label }}</label>

                            <select
                                v-if="field.type === 'select'"
                                v-model="formState.data[field.key]"
                                required
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                            >
                                <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                v-model="formState.data[field.key]"
                                required
                                rows="3"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                            ></textarea>

                            <input
                                v-else
                                :type="field.type || 'text'"
                                v-model="formState.data[field.key]"
                                required
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                            />
                        </div>

                        <div class="pt-4 border-t border-gray-100 flex justify-end space-x-3">
                            <Link :href="route('forms.index')" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                取消
                            </Link>
                            <button type="submit" :disabled="formState.processing" class="px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-bold shadow-sm hover:bg-blue-700 disabled:opacity-50">
                                確認送出申請
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
