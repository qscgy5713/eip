<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    documents: Object,
    departments: Array,
    filters: Object,
    canManage: Boolean,
});

const categories = [
    { key: 'all', label: '全部文件' },
    { key: 'policy', label: '公司規章' },
    { key: 'template', label: '表單範本' },
    { key: 'tech', label: '技術規範' },
    { key: 'training', label: '教育訓練' },
];

const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || 'all');
const selectedDept = ref(props.filters.department_id || '');

const applyFilters = () => {
    router.get(route('documents.index'), {
        category: selectedCategory.value,
        search: search.value,
        department_id: selectedDept.value,
    }, { preserveState: true, preserveScroll: true });
};

const selectCategory = (catKey) => {
    selectedCategory.value = catKey;
    applyFilters();
};

// 檔案類型標籤與顏色
const getFileInfo = (mimeType, fileName = '') => {
    const ext = fileName.split('.').pop()?.toUpperCase() || 'FILE';
    if (mimeType?.includes('pdf') || ext === 'PDF') {
        return { tag: 'PDF', color: 'bg-rose-50 text-rose-600 border-rose-200' };
    }
    if (mimeType?.includes('word') || ['DOC', 'DOCX'].includes(ext)) {
        return { tag: 'DOC', color: 'bg-blue-50 text-blue-600 border-blue-200' };
    }
    if (mimeType?.includes('sheet') || ['XLS', 'XLSX', 'CSV'].includes(ext)) {
        return { tag: 'XLS', color: 'bg-emerald-50 text-emerald-600 border-emerald-200' };
    }
    if (mimeType?.includes('presentation') || ['PPT', 'PPTX'].includes(ext)) {
        return { tag: 'PPT', color: 'bg-amber-50 text-amber-600 border-amber-200' };
    }
    return { tag: ext.slice(0, 4), color: 'bg-gray-50 text-gray-600 border-gray-200' };
};

// 上傳新文件 Modal
const isUploadModalOpen = ref(false);
const uploadForm = useForm({
    title: '',
    category: 'policy',
    description: '',
    department_id: '',
    restricted_roles: [],
    version_label: 'v1.0',
    changelog: '初版文件上傳',
    file: null,
});

const openUploadModal = () => {
    uploadForm.reset();
    uploadForm.clearErrors();
    uploadForm.category = selectedCategory.value !== 'all' ? selectedCategory.value : 'policy';
    isUploadModalOpen.value = true;
};

const handleFileChange = (e) => {
    uploadForm.file = e.target.files[0];
};

const submitUpload = () => {
    uploadForm.post(route('documents.store'), {
        forceFormData: true,
        onSuccess: () => {
            isUploadModalOpen.value = false;
        },
    });
};

// 上傳新版本 Modal
const isNewVersionModalOpen = ref(false);
const targetDocForVersion = ref(null);
const newVersionForm = useForm({
    version_label: '',
    changelog: '',
    file: null,
});

const openNewVersionModal = (doc) => {
    targetDocForVersion.value = doc;
    newVersionForm.reset();
    newVersionForm.clearErrors();
    const currentNum = doc.current_version;
    newVersionForm.version_label = `v${currentNum + 1}.0`;
    isNewVersionModalOpen.value = true;
};

const handleNewVersionFileChange = (e) => {
    newVersionForm.file = e.target.files[0];
};

const submitNewVersion = () => {
    if (!targetDocForVersion.value) return;
    newVersionForm.post(route('documents.versions.upload', targetDocForVersion.value.id), {
        forceFormData: true,
        onSuccess: () => {
            isNewVersionModalOpen.value = false;
        },
    });
};

// 版本歷程 Modal
const isHistoryModalOpen = ref(false);
const historyDoc = ref(null);
const historyVersions = ref([]);
const loadingHistory = ref(false);

const openHistoryModal = async (doc) => {
    historyDoc.value = doc;
    isHistoryModalOpen.value = true;
    loadingHistory.value = true;
    try {
        const res = await fetch(route('documents.versions.list', doc.id));
        const data = await res.json();
        historyVersions.value = data.versions;
    } catch (e) {
        alert('載入歷史版本歷程失敗');
    } finally {
        loadingHistory.value = false;
    }
};

// 編輯文件 Modal
const isEditModalOpen = ref(false);
const targetDocForEdit = ref(null);
const editForm = useForm({
    title: '',
    category: 'policy',
    description: '',
    department_id: '',
    restricted_roles: [],
});

const openEditModal = (doc) => {
    targetDocForEdit.value = doc;
    editForm.reset();
    editForm.clearErrors();
    editForm.title = doc.title;
    editForm.category = doc.category;
    editForm.description = doc.description || '';
    editForm.department_id = doc.department_id || '';
    editForm.restricted_roles = doc.restricted_roles ? [...doc.restricted_roles] : [];
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    targetDocForEdit.value = null;
};

const submitEdit = () => {
    if (!targetDocForEdit.value) return;
    editForm.put(route('documents.update', targetDocForEdit.value.id), {
        onSuccess: () => {
            closeEditModal();
        },
    });
};

// 刪除文件
const deleteDoc = (docId) => {
    if (confirm('確定要刪除這份文件及其所有歷史版本嗎？此操作不可逆。')) {
        router.delete(route('documents.destroy', docId));
    }
};

// 線上預覽 Modal 狀態與方法
const isPreviewModalOpen = ref(false);
const previewDoc = ref(null);
const previewVersion = ref(null);
const previewUrl = ref('');

const openPreviewModal = (doc, version = null) => {
    previewDoc.value = doc;
    previewVersion.value = version || doc.latest_version;
    const versionId = version ? version.id : (doc.latest_version ? doc.latest_version.id : '');
    previewUrl.value = route('documents.preview', {
        document: doc.id,
        version: versionId || undefined,
    });
    isPreviewModalOpen.value = true;
};

const closePreviewModal = () => {
    isPreviewModalOpen.value = false;
    previewDoc.value = null;
    previewVersion.value = null;
    previewUrl.value = '';
};

const isPdf = (mimeType, fileName = '') => {
    return mimeType?.includes('pdf') || fileName?.toLowerCase().endsWith('.pdf');
};

const isImage = (mimeType, fileName = '') => {
    if (mimeType?.startsWith('image/')) return true;
    const ext = fileName?.split('.').pop()?.toLowerCase();
    return ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext);
};

const isText = (mimeType, fileName = '') => {
    if (mimeType?.includes('text') || mimeType?.includes('json')) return true;
    const ext = fileName?.split('.').pop()?.toLowerCase();
    return ['txt', 'md', 'json', 'csv', 'log'].includes(ext);
};
</script>

<template>
    <Head title="企業文件庫與檔案版本控制" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800">
                        企業知識文件庫
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        內部規章、表單範本、技術規格集中管理與多版本歷程回溯
                    </p>
                </div>
                <div>
                    <button
                        @click="openUploadModal"
                        class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg shadow-sm hover:bg-blue-700 transition flex items-center space-x-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span>上傳新文件</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 分類標籤頁與篩選列 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-4">
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 pb-3">
                        <button
                            v-for="cat in categories"
                            :key="cat.key"
                            @click="selectCategory(cat.key)"
                            :class="[
                                'px-4 py-2 text-xs font-medium rounded-lg transition flex items-center space-x-1.5',
                                selectedCategory === cat.key
                                    ? 'bg-blue-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:bg-gray-100'
                            ]"
                        >
                            <span>{{ cat.label }}</span>
                        </button>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <div class="relative flex-1 w-full">
                            <input
                                type="text"
                                v-model="search"
                                @keyup.enter="applyFilters"
                                placeholder="搜尋文件標題或說明內容 (按 Enter 搜尋)..."
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 pl-9 py-2"
                            />
                            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <select
                            v-model="selectedDept"
                            @change="applyFilters"
                            class="text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-2 w-full sm:w-44"
                        >
                            <option value="">全公司共用文件</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <button
                            @click="applyFilters"
                            class="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition shrink-0"
                        >
                            套用篩選
                        </button>
                    </div>
                </div>

                <!-- 文件列表 Grid -->
                <div v-if="documents.data && documents.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="doc in documents.data"
                        :key="doc.id"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition flex flex-col justify-between overflow-hidden group"
                    >
                        <div class="p-5 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center space-x-3">
                                    <div :class="['w-10 h-10 rounded-xl border flex items-center justify-center text-xs font-bold font-mono shrink-0', getFileInfo(doc.latest_version?.mime_type, doc.latest_version?.file_name).color]">
                                        {{ getFileInfo(doc.latest_version?.mime_type, doc.latest_version?.file_name).tag }}
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-sm text-gray-900 group-hover:text-blue-600 transition line-clamp-1">
                                            {{ doc.title }}
                                        </h3>
                                        <div class="flex items-center space-x-2 mt-0.5">
                                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 rounded">
                                                {{ doc.latest_version?.version_label || `v${doc.current_version}.0` }}
                                            </span>
                                            <span v-if="doc.department" class="text-[11px] text-gray-400">
                                                {{ doc.department.name }}
                                            </span>
                                            <span v-else class="text-[11px] text-emerald-600 font-medium">
                                                全公司共用
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <span v-if="doc.restricted_roles" class="text-[10px] px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded shrink-0 flex items-center" title="權限受限">
                                    <svg class="w-3 h-3 mr-1 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    主管限定
                                </span>
                            </div>

                            <p class="text-xs text-gray-500 line-clamp-2 min-h-[32px]">
                                {{ doc.description || '無特別說明備註。' }}
                            </p>

                            <!-- 文件中繼資訊 -->
                            <div class="pt-2 border-t border-gray-50 text-[11px] text-gray-400 flex items-center justify-between">
                                <span>大小：{{ doc.latest_version?.formatted_size || '未知' }}</span>
                                <span>下載 {{ doc.download_count }} 次</span>
                            </div>
                        </div>

                        <!-- 底部操作列 -->
                        <div class="bg-gray-50/70 px-5 py-3 border-t border-gray-100 flex items-center justify-between text-xs">
                            <div class="flex items-center space-x-1.5">
                                <button
                                    type="button"
                                    @click="openPreviewModal(doc)"
                                    class="px-2.5 py-1.5 font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-600 hover:text-white rounded-lg transition flex items-center space-x-1"
                                    title="線上預覽文件"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>預覽</span>
                                </button>
                                <a
                                    :href="route('documents.download', doc.id)"
                                    class="px-2.5 py-1.5 font-semibold text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white rounded-lg transition flex items-center space-x-1"
                                    title="下載最新版本"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    <span>下載</span>
                                </a>
                                <button
                                    @click="openHistoryModal(doc)"
                                    class="text-gray-500 hover:text-gray-800 px-1.5 py-1.5"
                                    title="查看歷史修訂版本"
                                >
                                    歷程 ({{ doc.versions_count }})
                                </button>
                            </div>

                            <div class="flex items-center space-x-2">
                                <button
                                    v-if="canManage || doc.uploader_id === $page.props.auth.user.id"
                                    @click="openNewVersionModal(doc)"
                                    class="text-gray-500 hover:text-blue-600"
                                    title="發布新版本"
                                >
                                    +新版
                                </button>
                                <button
                                    v-if="canManage || doc.uploader_id === $page.props.auth.user.id"
                                    @click="openEditModal(doc)"
                                    class="text-gray-500 hover:text-amber-600"
                                    title="編輯文件設定"
                                >
                                    編輯
                                </button>
                                <button
                                    v-if="$page.props.auth.user.role === 'admin' || doc.uploader_id === $page.props.auth.user.id"
                                    @click="deleteDoc(doc.id)"
                                    class="text-gray-400 hover:text-rose-600"
                                    title="刪除文件"
                                >
                                    刪除
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 空狀態提示 -->
                <div v-else class="bg-white rounded-xl border border-gray-100 p-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-400 mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    </div>
                    <h3 class="font-bold text-gray-700 text-base">目前尚無符合條件的文件</h3>
                    <p class="text-xs text-gray-400">您可以切換分類標籤，或點擊右上角「上傳新文件」建立第一份文件。</p>
                </div>
            </div>
        </div>

        <!-- 上傳新文件 Modal -->
        <div v-if="isUploadModalOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">上傳新文件至企業文件庫</h3>
                    <button @click="isUploadModalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitUpload" class="space-y-4 text-sm">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">文件標題</label>
                        <input
                            type="text"
                            v-model="uploadForm.title"
                            placeholder="如：2026 年度員工出勤及加班管理要點"
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                            required
                        />
                        <p v-if="uploadForm.errors.title" class="text-xs text-rose-500 mt-1">{{ uploadForm.errors.title }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">文件分類</label>
                            <select
                                v-model="uploadForm.category"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                required
                            >
                                <option value="policy">公司規章制度</option>
                                <option value="template">行政表單範本</option>
                                <option value="tech">技術規格書</option>
                                <option value="training">教育訓練教材</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">所屬部門</label>
                            <select
                                v-model="uploadForm.department_id"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">全公司共用</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">版本號標記</label>
                            <input
                                type="text"
                                v-model="uploadForm.version_label"
                                placeholder="如：v1.0"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">機密權限</label>
                            <div class="mt-2 flex items-center space-x-2">
                                <label class="flex items-center space-x-1.5 text-xs text-gray-700">
                                    <input
                                        type="checkbox"
                                        :checked="uploadForm.restricted_roles.length > 0"
                                        @change="uploadForm.restricted_roles = $event.target.checked ? ['admin', 'manager'] : []"
                                        class="rounded text-blue-600 focus:ring-blue-500"
                                    />
                                    <span>僅主管以上可見</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">文件摘要說明 (選填)</label>
                        <textarea
                            v-model="uploadForm.description"
                            rows="2"
                            placeholder="簡述此份文件的主要內容與適用範圍..."
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">選取檔案 (支援 PDF, Word, Excel, PPT, 圖片，最大 50MB)</label>
                        <input
                            type="file"
                            @change="handleFileChange"
                            class="w-full text-xs border border-gray-300 rounded-lg p-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            required
                        />
                        <p v-if="uploadForm.errors.file" class="text-xs text-rose-500 mt-1">{{ uploadForm.errors.file }}</p>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3 border-t">
                        <button
                            type="button"
                            @click="isUploadModalOpen = false"
                            class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="uploadForm.processing"
                            class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm disabled:opacity-50"
                        >
                            確認上傳
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 發布新版本 Modal -->
        <div v-if="isNewVersionModalOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-base font-bold text-gray-900">
                        發布新修訂版本：{{ targetDocForVersion?.title }}
                    </h3>
                    <button @click="isNewVersionModalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitNewVersion" class="space-y-4 text-sm">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">新版本標籤</label>
                        <input
                            type="text"
                            v-model="newVersionForm.version_label"
                            placeholder="如：v1.1, v2.0"
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                            required
                        />
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">修訂紀錄說明</label>
                        <textarea
                            v-model="newVersionForm.changelog"
                            rows="2"
                            placeholder="如：依據最新法令修正第 3 條津貼計算公式..."
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">上傳新檔案</label>
                        <input
                            type="file"
                            @change="handleNewVersionFileChange"
                            class="w-full text-xs border border-gray-300 rounded-lg p-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            required
                        />
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3 border-t">
                        <button
                            type="button"
                            @click="isNewVersionModalOpen = false"
                            class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="newVersionForm.processing"
                            class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm disabled:opacity-50"
                        >
                            發布此版本
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 歷史版本歷程 Modal -->
        <div v-if="isHistoryModalOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">
                            版本歷程回溯：{{ historyDoc?.title }}
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">點擊各版本下載圖示即可下載當時的檔案版本</p>
                    </div>
                    <button @click="isHistoryModalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <div v-if="loadingHistory" class="py-12 text-center text-xs text-gray-400">載入歷史版本中...</div>
                <div v-else class="max-h-96 overflow-y-auto space-y-3">
                    <div
                        v-for="ver in historyVersions"
                        :key="ver.id"
                        class="p-4 rounded-xl border border-gray-100 bg-gray-50/50 flex items-center justify-between"
                    >
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-800 font-mono font-bold text-xs rounded">
                                    {{ ver.version_label }}
                                </span>
                                <span class="text-xs font-medium text-gray-900">{{ ver.file_name }}</span>
                                <span class="text-[11px] text-gray-400">({{ ver.formatted_size }})</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1">變更紀錄：{{ ver.changelog || '初版建立' }}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">
                                上傳同仁：{{ ver.uploader?.name }} · {{ new Date(ver.created_at).toLocaleString() }}
                            </p>
                        </div>

                        <div class="flex items-center space-x-2 shrink-0">
                            <button
                                type="button"
                                @click="openPreviewModal(historyDoc, ver)"
                                class="px-2.5 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-600 hover:text-white rounded-lg transition"
                                title="預覽此歷史版本"
                            >
                                預覽
                            </button>
                            <a
                                :href="route('documents.download', { document: historyDoc.id, version: ver.id })"
                                class="px-2.5 py-1.5 text-xs font-semibold text-blue-600 bg-white border border-blue-200 hover:bg-blue-50 rounded-lg transition"
                                title="下載此歷史版本"
                            >
                                下載此版
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 線上預覽 Modal -->
        <div v-if="isPreviewModalOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6 overflow-hidden">
            <div class="bg-white rounded-2xl max-w-5xl w-full h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-gray-100">
                <!-- 預覽視窗頂部標題列 -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between shrink-0">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div :class="['w-8 h-8 rounded-lg border flex items-center justify-center text-xs font-bold font-mono shrink-0', getFileInfo(previewVersion?.mime_type, previewVersion?.file_name).color]">
                            {{ getFileInfo(previewVersion?.mime_type, previewVersion?.file_name).tag }}
                        </div>
                        <div class="truncate">
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm font-bold text-gray-900 truncate" :title="previewDoc?.title">
                                    {{ previewDoc?.title }}
                                </h3>
                                <span class="px-2 py-0.5 text-[10px] font-mono font-bold bg-blue-100 text-blue-700 rounded">
                                    {{ previewVersion?.version_label }}
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-400 truncate">
                                檔案名稱：{{ previewVersion?.file_name }} ({{ previewVersion?.formatted_size }})
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 shrink-0">
                        <a
                            :href="previewUrl"
                            target="_blank"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                            title="開新分頁全螢幕檢視"
                        >
                            <svg class="w-3.5 h-3.5 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            <span>新分頁開啟</span>
                        </a>
                        <a
                            :href="route('documents.download', { document: previewDoc?.id, version: previewVersion?.id })"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition"
                            title="下載此檔案"
                        >
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>下載檔案</span>
                        </a>
                        <button
                            type="button"
                            @click="closePreviewModal"
                            class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-200 transition"
                            title="關閉預覽"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <!-- 預覽主體內容區 -->
                <div class="flex-1 bg-gray-100 p-2 sm:p-4 overflow-auto flex items-center justify-center">
                    <!-- PDF 內嵌預覽 -->
                    <iframe
                        v-if="isPdf(previewVersion?.mime_type, previewVersion?.file_name)"
                        :src="previewUrl"
                        class="w-full h-full border-0 rounded-lg bg-white shadow-sm"
                    ></iframe>

                    <!-- 圖片預覽 -->
                    <div
                        v-else-if="isImage(previewVersion?.mime_type, previewVersion?.file_name)"
                        class="max-w-full max-h-full flex items-center justify-center p-4 bg-white rounded-lg shadow-sm"
                    >
                        <img
                            :src="previewUrl"
                            :alt="previewDoc?.title"
                            class="max-w-full max-h-[75vh] object-contain rounded"
                        />
                    </div>

                    <!-- 純文字 / Markdown / JSON 檔案 -->
                    <iframe
                        v-else-if="isText(previewVersion?.mime_type, previewVersion?.file_name)"
                        :src="previewUrl"
                        class="w-full h-full border rounded-lg bg-white shadow-sm p-2 font-mono text-xs"
                    ></iframe>

                    <!-- 其他不支援原生預覽的格式 (如 Word, Excel, PPT, ZIP) -->
                    <div
                        v-else
                        class="max-w-md w-full bg-white p-8 rounded-xl shadow-sm border border-gray-200 text-center space-y-4"
                    >
                        <div :class="['w-16 h-16 mx-auto rounded-2xl border flex items-center justify-center text-lg font-bold font-mono', getFileInfo(previewVersion?.mime_type, previewVersion?.file_name).color]">
                            {{ getFileInfo(previewVersion?.mime_type, previewVersion?.file_name).tag }}
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 text-base">{{ previewVersion?.file_name }}</h4>
                            <p class="text-xs text-gray-500 mt-1">此格式（Office 文件或壓縮檔）需由本地端對應應用程式檢視</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">檔案大小：{{ previewVersion?.formatted_size }}</p>
                        </div>
                        <div class="pt-2 flex justify-center space-x-3">
                            <a
                                :href="route('documents.download', { document: previewDoc?.id, version: previewVersion?.id })"
                                class="px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition flex items-center space-x-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span>下載檔案以開啟</span>
                            </a>
                            <a
                                :href="previewUrl"
                                target="_blank"
                                class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                            >
                                嘗試於新分頁開啟
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 編輯文件設定 Modal -->
        <div v-if="isEditModalOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">編輯文件屬性與存取權限</h3>
                    <button @click="closeEditModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">文件標題 *</label>
                        <input
                            type="text"
                            v-model="editForm.title"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-blue-500 focus:border-blue-500"
                            required
                        />
                        <p v-if="editForm.errors.title" class="text-rose-500 mt-1">{{ editForm.errors.title }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">所屬分類 *</label>
                            <select
                                v-model="editForm.category"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                                <option value="policy">公司規章</option>
                                <option value="template">表單範本</option>
                                <option value="tech">技術規範</option>
                                <option value="training">教育訓練</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">指定歸屬部門 (選填)</label>
                            <select
                                v-model="editForm.department_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">全公司共用文件</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">文件說明備註</label>
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-blue-500 focus:border-blue-500"
                        ></textarea>
                    </div>

                    <!-- 角色存取限制 (密件保護) -->
                    <div class="bg-amber-50/50 p-3 rounded-lg border border-amber-200/60">
                        <label class="block font-medium text-amber-900 mb-1">密件存取限制 (僅允許特定角色調閱)</label>
                        <p class="text-[11px] text-amber-700 mb-2">未勾選代表全體在職同仁均可公開查閱下載</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center space-x-2 text-gray-700 cursor-pointer">
                                <input type="checkbox" value="manager" v-model="editForm.restricted_roles" class="rounded text-amber-600 focus:ring-amber-500" />
                                <span>主管級 (Manager)</span>
                            </label>
                            <label class="flex items-center space-x-2 text-gray-700 cursor-pointer">
                                <input type="checkbox" value="hr" v-model="editForm.restricted_roles" class="rounded text-amber-600 focus:ring-amber-500" />
                                <span>人資行政 (HR)</span>
                            </label>
                            <label class="flex items-center space-x-2 text-gray-700 cursor-pointer">
                                <input type="checkbox" value="admin" v-model="editForm.restricted_roles" class="rounded text-amber-600 focus:ring-amber-500" />
                                <span>系統管理員 (Admin)</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3 border-t">
                        <button
                            type="button"
                            @click="closeEditModal"
                            class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm disabled:opacity-50"
                        >
                            {{ editForm.processing ? '儲存中...' : '儲存變更' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
