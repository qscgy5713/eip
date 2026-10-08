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
    attachments: [],
});

const handleFileChange = (e) => {
    const files = Array.from(e.target.files);
    formState.attachments = [...formState.attachments, ...files];
    e.target.value = '';
};

const removeAttachment = (index) => {
    formState.attachments.splice(index, 1);
};

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const submit = () => {
    formState.post(route('forms.store', props.form.id), {
        forceFormData: true,
        preserveScroll: true,
    });
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
                        <!-- 表單錯誤提示區塊 -->
                        <div v-if="formState.hasErrors" class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-sm">
                            <div class="font-bold flex items-center space-x-1.5 mb-1">
                                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>表單內容有誤，請檢查並修正以下欄位：</span>
                            </div>
                            <ul class="list-disc list-inside text-xs space-y-0.5 pl-1">
                                <li v-for="(errMsg, errKey) in formState.errors" :key="errKey">
                                    {{ errMsg }}
                                </li>
                            </ul>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">申請主旨 / 標題 <span class="text-rose-500">*</span></label>
                            <input
                                v-model="formState.title"
                                type="text"
                                required
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                            />
                            <p v-if="formState.errors.title" class="text-xs text-rose-600 mt-1">{{ formState.errors.title }}</p>
                        </div>

                        <!-- 動態欄位 -->
                        <div v-for="field in form.fields_schema" :key="field.key" class="space-y-1">
                            <label class="block text-sm font-semibold text-gray-700">{{ field.label }}</label>

                            <select
                                v-if="field.type === 'select'"
                                v-model="formState.data[field.key]"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                            >
                                <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                v-model="formState.data[field.key]"
                                rows="3"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                            ></textarea>

                            <input
                                v-else
                                :type="field.type || 'text'"
                                v-model="formState.data[field.key]"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                            />

                            <p v-if="formState.errors['data.' + field.key]" class="text-xs text-rose-600 mt-1">{{ formState.errors['data.' + field.key] }}</p>
                        </div>

                        <!-- 證明文件與附件上傳 -->
                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                檢附證明文件 / 附件 (選填)
                            </label>
                            <p class="text-xs text-gray-500 mb-3">
                                如請假證明（就醫證明、訃聞、公文）、單據收據或申請相關說明文件。支援 PDF、JPG、PNG、Word、Excel、ZIP，單檔最大 10MB。
                            </p>

                            <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-400 transition-colors bg-gray-50/50">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-10 w-10 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600 justify-center">
                                        <label for="file-upload" class="relative cursor-pointer rounded-md font-semibold text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                            <span>點擊選擇檔案</span>
                                            <input id="file-upload" name="file-upload" type="file" multiple class="sr-only" @change="handleFileChange" />
                                        </label>
                                        <p class="pl-1 text-gray-500">或拖曳檔案至此</p>
                                    </div>
                                    <p class="text-xs text-gray-400">可選取多個檔案</p>
                                </div>
                            </div>

                            <!-- 已選擇附件清單 -->
                            <div v-if="formState.attachments.length > 0" class="mt-4 space-y-2">
                                <div class="text-xs font-semibold text-gray-500">已準備上傳的檔案 ({{ formState.attachments.length }})：</div>
                                <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                                    <li v-for="(file, idx) in formState.attachments" :key="idx" class="flex items-center justify-between py-2.5 px-4 text-sm">
                                        <div class="flex items-center space-x-3 truncate">
                                            <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <span class="font-medium text-gray-700 truncate">{{ file.name }}</span>
                                            <span class="text-xs text-gray-400">({{ formatSize(file.size) }})</span>
                                        </div>
                                        <button
                                            type="button"
                                            @click="removeAttachment(idx)"
                                            class="text-xs font-medium text-red-600 hover:text-red-800 ml-4 flex-shrink-0"
                                        >
                                            移除
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="formState.errors.attachments" class="text-xs text-rose-600 mt-2">
                                {{ formState.errors.attachments }}
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100 flex justify-end space-x-3">
                            <Link :href="route('forms.index')" class="px-5 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                取消
                            </Link>
                            <button
                                type="submit"
                                :disabled="formState.processing"
                                class="inline-flex items-center px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-bold shadow-sm hover:bg-blue-700 disabled:opacity-50 transition"
                            >
                                <svg v-if="formState.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ formState.processing ? '正在送出申請...' : '確認送出申請' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
