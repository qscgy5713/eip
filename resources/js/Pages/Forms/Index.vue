<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    availableForms: Array,
    myRequests: Object,
    pendingApprovals: Array,
    canManageForms: Boolean,
});

const statusBadge = (status) => {
    switch (status) {
        case 'approved': return 'bg-emerald-100 text-emerald-800';
        case 'rejected': return 'bg-rose-100 text-rose-800';
        default: return 'bg-amber-100 text-amber-800';
    }
};

// 自訂表單設計器 Modal
const isCreateFormModalOpen = ref(false);
const newForm = useForm({
    name: '',
    code: '',
    description: '',
    fields: [
        { label: '申請原因', key: 'reason', type: 'textarea', options_str: '' },
        { label: '預計執行日期', key: 'target_date', type: 'date', options_str: '' },
    ],
});

const openCreateFormModal = () => {
    newForm.reset();
    newForm.clearErrors();
    newForm.fields = [
        { label: '申請原因說明', key: 'reason', type: 'textarea', options_str: '' },
        { label: '生效/執行日期', key: 'effective_date', type: 'date', options_str: '' },
    ];
    isCreateFormModalOpen.value = true;
};

const addField = () => {
    const idx = newForm.fields.length + 1;
    newForm.fields.push({
        label: `自訂欄位 ${idx}`,
        key: `custom_field_${idx}`,
        type: 'text',
        options_str: '',
    });
};

const removeField = (index) => {
    if (newForm.fields.length <= 1) {
        alert('表單至少需保留一個自訂欄位');
        return;
    }
    newForm.fields.splice(index, 1);
};

const submitCustomForm = () => {
    // 整理 fields_schema
    const formattedSchema = newForm.fields.map(f => {
        const item = {
            key: f.key.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_') || 'field_' + Math.random().toString(36).substring(7),
            label: f.label.trim(),
            type: f.type,
        };
        if (f.type === 'select' && f.options_str) {
            item.options = f.options_str.split(/[,，\n]/).map(o => o.trim()).filter(Boolean);
        }
        return item;
    });

    router.post(route('forms.templates.store'), {
        name: newForm.name,
        code: newForm.code.trim().toUpperCase(),
        description: newForm.description,
        fields_schema: formattedSchema,
    }, {
        onSuccess: () => {
            isCreateFormModalOpen.value = false;
        },
    });
};

// 下架/刪除表單
const deleteForm = (formId, formName) => {
    if (confirm(`確定要下架/移除表單「${formName}」嗎？若該表單有過往申請紀錄將自動轉為停用保留。`)) {
        router.delete(route('forms.templates.destroy', formId));
    }
};
</script>

<template>
    <Head title="表單與簽核中心" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800">表單與線上簽核</h2>
                    <p class="text-xs text-gray-500 mt-1">標準化行政工作流、靈活自訂表單與透明審批歷程</p>
                </div>
                <div v-if="canManageForms">
                    <button
                        @click="openCreateFormModal"
                        class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg shadow-sm hover:bg-blue-700 transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>自訂新表單範本 (管理員/主管)</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                <!-- 可發起表單區塊 -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center space-x-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>發起新申請單 (共 {{ availableForms.length }} 種表單)</span>
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div
                            v-for="form in availableForms"
                            :key="form.id"
                            class="bg-white p-6 rounded-xl border border-gray-200/80 shadow-sm hover:shadow-md hover:border-blue-300 transition flex flex-col justify-between group"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-base font-bold text-gray-900 group-hover:text-blue-600 transition">{{ form.name }}</h4>
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2 py-0.5 text-xs font-mono font-semibold bg-gray-100 text-gray-600 rounded">{{ form.code }}</span>
                                        <button
                                            v-if="canManageForms"
                                            @click.stop="deleteForm(form.id, form.name)"
                                            class="text-gray-300 hover:text-rose-500 text-xs"
                                            title="下架此表單"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mb-4 min-h-[32px] line-clamp-2">{{ form.description || '標準行政申請表單。' }}</p>

                                <div class="mb-4 text-[11px] text-gray-400 flex flex-wrap gap-1">
                                    <span class="px-1.5 py-0.5 bg-gray-50 border border-gray-100 rounded" v-for="f in (form.fields_schema || []).slice(0, 3)" :key="f.key">
                                        {{ f.label }}
                                    </span>
                                    <span v-if="(form.fields_schema || []).length > 3" class="px-1.5 py-0.5 text-gray-400">
                                        +{{ (form.fields_schema || []).length - 3 }} 欄位
                                    </span>
                                </div>
                            </div>
                            <Link
                                :href="route('forms.create', form.id)"
                                class="w-full text-center py-2 px-4 bg-blue-50 text-blue-600 font-semibold text-xs rounded-lg hover:bg-blue-600 hover:text-white transition"
                            >
                                立即填寫申請 &rarr;
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 待我審核單據 (若有) -->
                <div v-if="pendingApprovals.length > 0" class="bg-amber-50/70 border border-amber-200 rounded-xl p-6">
                    <h3 class="text-base font-bold text-amber-900 mb-3 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>待您審批的單據 ({{ pendingApprovals.length }} 件)</span>
                    </h3>
                    <div class="divide-y divide-amber-100 bg-white rounded-lg border border-amber-200 overflow-hidden">
                        <div v-for="item in pendingApprovals" :key="item.id" class="p-4 flex items-center justify-between">
                            <div>
                                <p class="font-bold text-sm text-gray-900">{{ item.form_request?.title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    申請人：<span class="font-semibold text-gray-700">{{ item.form_request?.user?.name }}</span>
                                    · 類別：{{ item.form_request?.form?.name }}
                                    · 單號：{{ item.form_request?.request_no }}
                                </p>
                            </div>
                            <Link :href="route('forms.show', item.form_request_id)" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-sm">
                                進入審核
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 我的申請歷史清單 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">我的申請紀錄</h3>
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="req in myRequests.data"
                            :key="req.id"
                            class="py-4 first:pt-0 last:pb-0 flex items-center justify-between gap-4"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs font-mono font-medium text-gray-400">[{{ req.request_no }}]</span>
                                    <Link :href="route('forms.show', req.id)" class="font-bold text-sm text-gray-900 hover:text-blue-600">
                                        {{ req.title }}
                                    </Link>
                                </div>
                                <p class="text-xs text-gray-400">申請類別：{{ req.form?.name }} · 申請時間：{{ new Date(req.created_at).toLocaleString() }}</p>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span :class="['px-3 py-1 text-xs font-semibold rounded-full', statusBadge(req.status)]">
                                    {{ req.status === 'approved' ? '已核准' : (req.status === 'rejected' ? '已駁回' : '審批中') }}
                                </span>
                                <Link :href="route('forms.show', req.id)" class="text-xs font-medium text-blue-600 hover:underline">
                                    查看詳情 &rarr;
                                </Link>
                            </div>
                        </div>
                        <div v-if="myRequests.data.length === 0" class="py-8 text-center text-gray-400">尚無申請紀錄</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 自訂表單設計器 Modal -->
        <div v-if="isCreateFormModalOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl space-y-5 my-8">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">自訂新表單設計器</h3>
                        <p class="text-xs text-gray-500 mt-0.5">定義表單代碼與動態欄位結構，儲存後即刻供全員申請</p>
                    </div>
                    <button @click="isCreateFormModalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitCustomForm" class="space-y-4 text-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">表單名稱</label>
                            <input
                                type="text"
                                v-model="newForm.name"
                                placeholder="如：遠端居家辦公申請單"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">表單代碼 (大寫英文加底線)</label>
                            <input
                                type="text"
                                v-model="newForm.code"
                                placeholder="如：REMOTE_WORK"
                                class="w-full text-xs font-mono rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">表單用途說明 (選填)</label>
                        <textarea
                            v-model="newForm.description"
                            rows="2"
                            placeholder="如：提供同仁申請遠端辦公，每週以 2 日為限..."
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <!-- 動態欄位配置列表 -->
                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/50 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-xs text-gray-700 uppercase tracking-wider">自訂欄位清單 (JSON Schema)</h4>
                            <button
                                type="button"
                                @click="addField"
                                class="px-2.5 py-1 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition"
                            >
                                + 增加欄位
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(field, index) in newForm.fields"
                                :key="index"
                                class="p-3 bg-white rounded-lg border border-gray-200 shadow-sm space-y-2"
                            >
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                                    <div class="sm:col-span-5">
                                        <label class="block text-[11px] text-gray-500 mb-0.5">欄位標題 (Label)</label>
                                        <input
                                            type="text"
                                            v-model="field.label"
                                            placeholder="如：居家工作地址"
                                            class="w-full text-xs rounded border-gray-300 py-1"
                                            required
                                        />
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="block text-[11px] text-gray-500 mb-0.5">英文識別碼 (Key)</label>
                                        <input
                                            type="text"
                                            v-model="field.key"
                                            placeholder="如：location"
                                            class="w-full text-xs font-mono rounded border-gray-300 py-1"
                                            required
                                        />
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="block text-[11px] text-gray-500 mb-0.5">欄位類型</label>
                                        <select
                                            v-model="field.type"
                                            class="w-full text-xs rounded border-gray-300 py-1"
                                        >
                                            <option value="text">單行文字</option>
                                            <option value="textarea">多行文字</option>
                                            <option value="number">數值金額</option>
                                            <option value="date">日期選擇</option>
                                            <option value="select">下拉選單</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-1 text-right pt-3">
                                        <button
                                            type="button"
                                            @click="removeField(index)"
                                            class="text-rose-500 hover:text-rose-700 text-base font-bold"
                                            title="移除欄位"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>

                                <!-- 下拉選單選項輸入 -->
                                <div v-if="field.type === 'select'" class="pt-2 border-t border-gray-100">
                                    <label class="block text-[11px] text-blue-600 mb-0.5">下拉選單選項 (請以逗點分隔，如：住家, 咖啡廳, 其他)</label>
                                    <input
                                        type="text"
                                        v-model="field.options_str"
                                        placeholder="選項 A, 選項 B, 選項 C"
                                        class="w-full text-xs rounded border-blue-200 bg-blue-50/30 py-1"
                                        required
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3 border-t">
                        <button
                            type="button"
                            @click="isCreateFormModalOpen = false"
                            class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm"
                        >
                            確認建立表單
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
