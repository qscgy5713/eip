<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    announcements: Object,
    category: {
        type: String,
        default: 'all',
    },
    status: {
        type: String,
        default: 'published',
    },
    canManage: {
        type: Boolean,
        default: false,
    },
});

const filterCategory = (cat) => {
    router.get(
        route('announcements.index'),
        { category: cat, status: props.status },
        { preserveState: true }
    );
};

const filterStatus = (st) => {
    router.get(
        route('announcements.index'),
        { category: props.category, status: st },
        { preserveState: true }
    );
};

const categories = [
    { key: 'all', label: '全部公告' },
    { key: 'company', label: '公司重大' },
    { key: 'activity', label: '全員活動' },
    { key: 'general', label: '一般通知' },
];

const priorityBadge = (priority) => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-800 border-red-200';
        case 'high': return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'low': return 'bg-slate-100 text-slate-700 border-slate-200';
        default: return 'bg-blue-100 text-blue-800 border-blue-200';
    }
};

const categoryName = (cat) => {
    switch (cat) {
        case 'company': return '公司重大';
        case 'activity': return '全員活動';
        default: return '一般通知';
    }
};

// 建立公告 Modal 狀態與表單
const isCreateModalOpen = ref(false);
const fileInputRef = ref(null);
const selectedFiles = ref([]);

const form = useForm({
    title: '',
    category: 'company',
    priority: 'normal',
    content: '',
    is_pinned: false,
    status: 'published',
    attachments: [],
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    selectedFiles.value = [];
    isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    form.reset();
    form.clearErrors();
    selectedFiles.value = [];
};

const handleFileChange = (e) => {
    const files = Array.from(e.target.files || []);
    selectedFiles.value = [...selectedFiles.value, ...files];
    form.attachments = selectedFiles.value;
};

const removeSelectedFile = (index) => {
    selectedFiles.value.splice(index, 1);
    form.attachments = selectedFiles.value;
};

const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const submitAnnouncement = () => {
    form.post(route('announcements.store'), {
        forceFormData: true,
        onSuccess: () => {
            closeCreateModal();
        },
    });
};
</script>

<template>
    <Head title="企業公告中心" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800">企業公告中心</h2>
                    <p class="text-sm text-gray-500 mt-1">掌握公司重要決策、最新活動規章與下載官方附件</p>
                </div>
                <div v-if="canManage" class="flex items-center space-x-3">
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        發布企業公告
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 篩選列：分類與發布狀態 -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-200 pb-4">
                    <!-- 分類切換標籤 -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="cat in categories"
                            :key="cat.key"
                            @click="filterCategory(cat.key)"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg transition',
                                category === cat.key ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
                            ]"
                        >
                            {{ cat.label }}
                        </button>
                    </div>

                    <!-- 管理者專屬狀態切換 (發布中 / 草稿 / 全部) -->
                    <div v-if="canManage" class="flex items-center space-x-1.5 bg-gray-100 p-1 rounded-lg self-start sm:self-auto text-xs font-medium">
                        <button
                            type="button"
                            @click="filterStatus('published')"
                            :class="['px-3 py-1.5 rounded-md transition', status === 'published' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-600 hover:text-gray-900']"
                        >
                            已發布
                        </button>
                        <button
                            type="button"
                            @click="filterStatus('draft')"
                            :class="['px-3 py-1.5 rounded-md transition', status === 'draft' ? 'bg-white text-amber-700 shadow-xs font-semibold' : 'text-gray-600 hover:text-gray-900']"
                        >
                            草稿中
                        </button>
                        <button
                            type="button"
                            @click="filterStatus('all')"
                            :class="['px-3 py-1.5 rounded-md transition', status === 'all' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-600 hover:text-gray-900']"
                        >
                            全狀態
                        </button>
                    </div>
                </div>

                <!-- 公告列表清單 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                    <div
                        v-for="item in announcements.data"
                        :key="item.id"
                        class="p-5 hover:bg-gray-50/80 transition flex items-start justify-between gap-4"
                    >
                        <div class="space-y-2 flex-1">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <span v-if="item.is_pinned" class="px-2 py-0.5 text-xs font-semibold bg-rose-50 text-rose-600 border border-rose-200 rounded">
                                    置頂
                                </span>
                                <span v-if="item.status === 'draft'" class="px-2 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 border border-amber-300 rounded">
                                    草稿
                                </span>
                                <span class="px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 rounded">
                                    {{ categoryName(item.category) }}
                                </span>
                                <span :class="['px-2 py-0.5 text-xs font-medium border rounded', priorityBadge(item.priority)]">
                                    {{ item.priority === 'urgent' ? '緊急' : (item.priority === 'high' ? '重要' : (item.priority === 'low' ? '低優先' : '一般')) }}
                                </span>
                                <Link :href="route('announcements.show', item.id)" class="text-lg font-bold text-gray-900 hover:text-blue-600">
                                    {{ item.title }}
                                </Link>
                            </div>

                            <p class="text-sm text-gray-500 line-clamp-2">{{ item.content }}</p>

                            <div class="flex items-center flex-wrap gap-x-4 gap-y-1 text-xs text-gray-400 pt-1">
                                <span>發布人：{{ item.author?.name || '系統' }}</span>
                                <span>發布時間：{{ new Date(item.published_at || item.created_at).toLocaleString() }}</span>
                                <span v-if="item.attachments && item.attachments.length > 0" class="inline-flex items-center text-blue-600 font-medium">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    {{ item.attachments.length }} 個檢附檔案
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col items-end justify-between self-stretch shrink-0">
                            <span v-if="!item.is_read" class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">未讀</span>
                            <span v-else class="text-xs text-gray-400">已讀</span>
                            <Link :href="route('announcements.show', item.id)" class="text-sm font-medium text-blue-600 hover:underline mt-2">
                                查看內文 &rarr;
                            </Link>
                        </div>
                    </div>

                    <div v-if="announcements.data.length === 0" class="p-12 text-center text-gray-400">
                        此分類或條件下目前無公告
                    </div>
                </div>

                <!-- 分頁按鈕 -->
                <div v-if="announcements.links && announcements.links.length > 3" class="flex justify-center space-x-1 pt-2">
                    <template v-for="(link, idx) in announcements.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 text-xs font-medium rounded-md transition',
                                link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'
                            ]"
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="px-3 py-1.5 text-xs font-medium rounded-md text-gray-400 border border-gray-100 bg-gray-50 cursor-not-allowed"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- 發布新公告 Modal -->
        <Modal :show="isCreateModalOpen" @close="closeCreateModal" maxWidth="2xl">
            <form @submit.prevent="submitAnnouncement" class="p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                    <h3 class="text-lg font-bold text-gray-900">發布企業內部公告</h3>
                    <button type="button" @click="closeCreateModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <!-- 標題 -->
                <div>
                    <InputLabel for="title" value="公告標題 *" />
                    <TextInput
                        id="title"
                        v-model="form.title"
                        type="text"
                        class="mt-1 block w-full text-sm"
                        placeholder="請輸入公告主旨（例：2026 年度員工健康檢查通知）"
                        required
                    />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <!-- 分類與優先級 -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="category" value="公告類別 *" />
                        <select
                            id="category"
                            v-model="form.category"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="company">公司重大</option>
                            <option value="activity">全員活動</option>
                            <option value="general">一般通知</option>
                        </select>
                        <InputError :message="form.errors.category" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="priority" value="緊急程度 *" />
                        <select
                            id="priority"
                            v-model="form.priority"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="low">低優先 (Low)</option>
                            <option value="normal">一般 (Normal)</option>
                            <option value="high">重要 (High)</option>
                            <option value="urgent">緊急 (Urgent - 全員通知)</option>
                        </select>
                        <InputError :message="form.errors.priority" class="mt-1" />
                    </div>
                </div>

                <!-- 內文 -->
                <div>
                    <InputLabel for="content" value="公告完整內文 *" />
                    <textarea
                        id="content"
                        v-model="form.content"
                        rows="6"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500"
                        placeholder="請詳述公告內容、時程規範與相關配合事項..."
                        required
                    ></textarea>
                    <InputError :message="form.errors.content" class="mt-1" />
                </div>

                <!-- 官方附件上傳 -->
                <div>
                    <InputLabel value="檢附官方文件/檔案 (選填，單檔上限 10MB)" />
                    <div class="mt-1 flex items-center justify-center px-4 pt-4 pb-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-400 transition bg-gray-50/50">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-8 w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-xs text-gray-600 justify-center">
                                <label class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                    <span>選擇檔案上傳</span>
                                    <input
                                        ref="fileInputRef"
                                        type="file"
                                        multiple
                                        class="sr-only"
                                        @change="handleFileChange"
                                    />
                                </label>
                                <span class="pl-1">或直接拖曳至此</span>
                            </div>
                            <p class="text-xs text-gray-500">支援 PDF、DOCX、XLSX、圖片、ZIP</p>
                        </div>
                    </div>

                    <!-- 已選取檔案清單 -->
                    <div v-if="selectedFiles.length > 0" class="mt-2.5 space-y-1.5">
                        <div
                            v-for="(f, i) in selectedFiles"
                            :key="i"
                            class="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-200 text-xs text-gray-700"
                        >
                            <div class="flex items-center space-x-2 truncate">
                                <span class="font-medium text-gray-900 truncate">{{ f.name }}</span>
                                <span class="text-gray-400 text-xs">({{ formatFileSize(f.size) }})</span>
                            </div>
                            <button
                                type="button"
                                @click="removeSelectedFile(i)"
                                class="text-red-500 hover:text-red-700 font-bold px-1"
                            >
                                移除
                            </button>
                        </div>
                    </div>
                    <InputError :message="form.errors.attachments" class="mt-1" />
                </div>

                <!-- 置頂與發布狀態選項 -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-gray-100">
                    <label class="flex items-center cursor-pointer">
                        <Checkbox v-model:checked="form.is_pinned" />
                        <span class="ms-2 text-sm text-gray-700 font-medium">置頂於公告列表最上方</span>
                    </label>

                    <div class="flex items-center space-x-4 text-sm">
                        <span class="text-gray-600 font-medium">發布狀態：</span>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" value="published" v-model="form.status" class="text-blue-600 focus:ring-blue-500" />
                            <span class="ms-1.5 text-gray-800">立即正式發布</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" value="draft" v-model="form.status" class="text-amber-600 focus:ring-amber-500" />
                            <span class="ms-1.5 text-gray-800">存為草稿</span>
                        </label>
                    </div>
                </div>

                <!-- 動作按鈕 -->
                <div class="flex justify-end space-x-3 pt-3 border-t border-gray-200">
                    <SecondaryButton type="button" @click="closeCreateModal">
                        取消
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700">
                        {{ form.processing ? '發布儲存中...' : (form.status === 'draft' ? '儲存草稿' : '正式發布公告') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
