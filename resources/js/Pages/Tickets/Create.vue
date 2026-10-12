<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    title: '',
    category: 'it_hardware',
    priority: 'normal',
    location: '',
    description: '',
    attachments: [],
});

const fileInput = ref(null);
const fileList = ref([]);

const handleFiles = (e) => {
    const files = Array.from(e.target.files);
    form.attachments = files;
    fileList.value = files.map(f => ({ name: f.name, size: (f.size / 1024).toFixed(1) + ' KB' }));
};

const removeFile = (idx) => {
    const dt = new DataTransfer();
    const files = Array.from(fileInput.value.files);
    files.splice(idx, 1);
    files.forEach(f => dt.items.add(f));
    fileInput.value.files = dt.files;
    form.attachments = Array.from(dt.files);
    fileList.value = form.attachments.map(f => ({ name: f.name, size: (f.size / 1024).toFixed(1) + ' KB' }));
};

const submit = () => {
    form.post(route('tickets.store'));
};
</script>

<template>
    <Head title="提報叫修服務工單" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('tickets.index')" class="p-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-500 transition dark:text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>提報服務叫修工單</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        填寫設備或設施故障細節，工程師將即時受理派工
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 px-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-xs">
                    <form @submit.prevent="submit" class="space-y-5 text-xs">
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                問題主旨與故障簡述 *
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                required
                                placeholder="例：辦公室 Wi-Fi 頻繁斷線、筆記型電腦無法開機、7F 會議室投影機訊號異常"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-red-500 focus:border-red-500"
                            />
                            <p v-if="form.errors.title" class="text-red-600 mt-1 font-semibold">{{ form.errors.title }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    問題分類 *
                                </label>
                                <select
                                    v-model="form.category"
                                    required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-red-500 focus:border-red-500"
                                >
                                    <option value="it_hardware">電腦與硬體周邊 (螢幕、鍵盤、筆電)</option>
                                    <option value="it_software">軟體安裝與帳號權限 (VPN、Email、系統)</option>
                                    <option value="it_network">網路連線與電話通訊 (Wi-Fi、分機、網路孔)</option>
                                    <option value="facility">水電空調與環境設施 (電燈、冷氣、桌椅鎖)</option>
                                    <option value="stationery">行政庶務與印表機耗材 (碳粉匣、文具)</option>
                                    <option value="other">其他服務需求</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    緊急程度 / 優先級 *
                                </label>
                                <select
                                    v-model="form.priority"
                                    required
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-red-500 focus:border-red-500"
                                >
                                    <option value="low">低優先 (不影響正常工作進度)</option>
                                    <option value="normal">一般 (日常叫修，正常工作日排程)</option>
                                    <option value="high">高優先 (嚴重影響個人工作進行)</option>
                                    <option value="urgent">特急件 (全體或部門重大系統中斷、緊急安全事件)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                故障具體地點 / 座位編號
                            </label>
                            <input
                                v-model="form.location"
                                type="text"
                                placeholder="例：8F 研發部座位 A-12、5F 大會議室、茶水間旁影印機"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-red-500 focus:border-red-500"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                詳細問題描述與發生狀況 *
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="4"
                                required
                                placeholder="請具體說明：何時開始發生？是否有出現錯誤代碼？已經嘗試過哪些重開機或排除步驟？以便工程師準備工具即時排除。"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs focus:ring-red-500 focus:border-red-500"
                            ></textarea>
                            <p v-if="form.errors.description" class="text-red-600 mt-1 font-semibold">{{ form.errors.description }}</p>
                        </div>

                        <!-- 故障截圖或照片上傳 -->
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                上傳故障照片或畫面截圖 (選填，最多 10MB)
                            </label>
                            <div class="p-4 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl hover:border-red-400 dark:hover:border-red-500 transition text-center">
                                <input
                                    ref="fileInput"
                                    type="file"
                                    multiple
                                    accept="image/*,.pdf,.zip"
                                    @change="handleFiles"
                                    class="hidden"
                                    id="ticket-file-input"
                                />
                                <label for="ticket-file-input" class="cursor-pointer block">
                                    <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    <p class="font-bold text-red-600 dark:text-red-400 hover:underline">點擊選取檔案或截圖照片</p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">支援 PNG, JPG, GIF, PDF，單檔上限 10MB</p>
                                </label>
                            </div>

                            <div v-if="fileList.length > 0" class="mt-3 space-y-1.5">
                                <div
                                    v-for="(f, idx) in fileList"
                                    :key="idx"
                                    class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700/60 text-xs"
                                >
                                    <span class="truncate text-gray-700 dark:text-gray-300 font-medium">{{ f.name }} ({{ f.size }})</span>
                                    <button
                                        type="button"
                                        @click="removeFile(idx)"
                                        class="text-red-600 hover:text-red-800 text-xs font-bold"
                                    >
                                        移除
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <Link
                                :href="route('tickets.index')"
                                class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition"
                            >
                                取消
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition active:scale-95 disabled:opacity-50"
                            >
                                確認送出工單
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
