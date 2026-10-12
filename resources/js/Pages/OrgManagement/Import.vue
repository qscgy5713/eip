<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    maxRows: { type: Number, required: true },
    result: { type: Object, default: null },
});

const form = useForm({ file: null, mode: 'preview' });
const fileName = ref('');

const roleLabels = { admin: '系統管理員', manager: '部門主管', hr: '人資', employee: '一般員工' };

const onFile = (e) => {
    const f = e.target.files[0] ?? null;
    form.file = f;
    fileName.value = f ? f.name : '';
};

const send = (mode) => {
    form.mode = mode;
    form.post(route('org-management.import.store'), { preserveScroll: true, forceFormData: true });
};

const canCommit = computed(() => props.result && !props.result.committed && props.result.invalid === 0 && props.result.total > 0);
</script>

<template>
    <Head title="批次匯入同仁" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">批次匯入同仁帳號</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">以 CSV 一次建立多位同仁,匯入前會先逐列檢查,全部通過才會寫入。</p>
                </div>
                <Link :href="route('org-management.index')" class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">返回組織管理</Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- 步驟說明與範本 -->
                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                        <li>下載範本,依欄位填寫(Excel 另存為 CSV 即可,UTF-8 或 Big5 皆可辨識)。</li>
                        <li>上傳後先按「預覽檢查」,畫面會標出每一列的問題。</li>
                        <li>全部無誤後按「確認匯入」。每次最多 {{ maxRows }} 筆。</li>
                    </ol>
                    <ul class="mt-3 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <li>必填:姓名、Email。部門請填「部門代碼」(組織管理頁可查);角色可填:一般員工、部門主管、人資、系統管理員,留空視為一般員工。</li>
                        <li>初始密碼留空時,系統會自動產生並在匯入完成後顯示一次。同仁首次登入都必須先更換密碼。</li>
                    </ul>
                    <a :href="route('org-management.import.template')" class="mt-4 inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">下載範本 (CSV)</a>
                </div>

                <!-- 上傳 -->
                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="import_file">選擇 CSV 檔案</label>
                    <input id="import_file" type="file" accept=".csv,.txt,text/csv" @change="onFile"
                        class="mt-2 block w-full max-w-md text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 dark:text-gray-300 dark:file:bg-indigo-900/30 dark:file:text-indigo-300" />
                    <InputError class="mt-2" :message="form.errors.file" />

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" :disabled="!form.file || form.processing" @click="send('preview')"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-40">預覽檢查</button>
                        <button type="button" :disabled="!canCommit || !form.file || form.processing" @click="send('commit')"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-40">確認匯入</button>
                    </div>
                    <p v-if="result && !result.committed && result.invalid > 0" class="mt-2 text-xs text-red-600 dark:text-red-400">有 {{ result.invalid }} 列需要修正,請改好檔案後重新上傳並預覽。</p>
                </div>

                <!-- 匯入完成 -->
                <div v-if="result?.committed" class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-700/50 dark:bg-emerald-900/20">
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">已匯入 {{ result.total }} 位同仁。</p>
                    <div v-if="result.generated?.length" class="mt-3">
                        <p class="text-xs text-emerald-800 dark:text-emerald-200">以下為系統自動產生的初始密碼,只會顯示這一次,請立即轉交給同仁:</p>
                        <table class="mt-2 w-full text-left text-xs">
                            <thead class="text-emerald-900 dark:text-emerald-100"><tr><th class="py-1 pr-3">姓名</th><th class="py-1 pr-3">Email</th><th class="py-1">初始密碼</th></tr></thead>
                            <tbody class="text-gray-900 dark:text-gray-100">
                                <tr v-for="g in result.generated" :key="g.email" class="border-t border-emerald-200 dark:border-emerald-700/50">
                                    <td class="py-1 pr-3">{{ g.name }}</td><td class="py-1 pr-3">{{ g.email }}</td><td class="select-all py-1 font-mono">{{ g.password }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 逐列檢查結果 -->
                <div v-if="result" class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                    <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 px-5 py-3 text-sm dark:border-gray-700">
                        <span class="text-gray-700 dark:text-gray-300">共 {{ result.total }} 列</span>
                        <span class="text-emerald-600 dark:text-emerald-400">通過 {{ result.valid }}</span>
                        <span :class="result.invalid ? 'text-red-600 dark:text-red-400' : 'text-gray-400'">有問題 {{ result.invalid }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr><th class="px-4 py-2">行</th><th class="px-4 py-2">姓名</th><th class="px-4 py-2">Email</th><th class="px-4 py-2">員編</th><th class="px-4 py-2">部門</th><th class="px-4 py-2">角色</th><th class="px-4 py-2">檢查結果</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="r in result.rows" :key="r.line" :class="r.errors.length ? 'bg-red-50 dark:bg-red-900/10' : ''">
                                    <td class="px-4 py-2 text-gray-500">{{ r.line }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ r.name }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ r.email }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ r.employee_no || '—' }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ r.department_code || '—' }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ roleLabels[r.role] || '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span v-if="!r.errors.length" class="text-emerald-600 dark:text-emerald-400">{{ result.committed ? '已匯入' : '通過' }}</span>
                                        <ul v-else class="list-disc pl-4 text-red-600 dark:text-red-400"><li v-for="(e, i) in r.errors" :key="i">{{ e }}</li></ul>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
