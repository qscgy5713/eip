<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    projects: Object,
    departments: Array,
    users: Array,
    filters: Object,
    canManage: Boolean,
});

const currentStatus = ref(props.filters.status || 'active');
const searchQuery = ref(props.filters.search || '');
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingProject = ref(null);

const form = useForm({
    code: '',
    name: '',
    description: '',
    department_id: '',
    priority: 'medium',
    status: 'active',
    start_date: '',
    due_date: '',
    member_ids: [],
});

const editForm = useForm({
    name: '',
    description: '',
    department_id: '',
    priority: 'medium',
    status: 'active',
    start_date: '',
    due_date: '',
    member_ids: [],
});

const handleSearch = () => {
    router.get(
        route('projects.index'),
        {
            status: currentStatus.value,
            search: searchQuery.value,
        },
        { preserveState: true, replace: true }
    );
};

const filterByStatus = (status) => {
    currentStatus.value = status;
    router.get(
        route('projects.index'),
        {
            status: status,
            search: searchQuery.value,
        },
        { preserveState: true, replace: true }
    );
};

const openCreateModal = () => {
    form.reset();
    form.code = `PRJ-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 900) + 100)}`;
    form.priority = 'medium';
    form.status = 'active';
    form.member_ids = [];
    showCreateModal.value = true;
};

const submitCreate = () => {
    form.post(route('projects.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const openEditModal = (project) => {
    editingProject.value = project;
    editForm.name = project.name;
    editForm.description = project.description || '';
    editForm.department_id = project.department_id || '';
    editForm.priority = project.priority;
    editForm.status = project.status;
    editForm.start_date = project.start_date ? project.start_date.substring(0, 10) : '';
    editForm.due_date = project.due_date ? project.due_date.substring(0, 10) : '';
    editForm.member_ids = project.members ? project.members.map(m => m.id) : [];
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingProject.value) return;
    editForm.put(route('projects.update', editingProject.value.id), {
        onSuccess: () => {
            showEditModal.value = false;
            editingProject.value = null;
        },
    });
};

const deleteProject = () => {
    if (!editingProject.value) return;
    if (confirm(`確定要刪除專案「[${editingProject.value.code}] ${editingProject.value.name}」嗎？這將一併刪除轄下所有任務且無法復原！`)) {
        router.delete(route('projects.destroy', editingProject.value.id), {
            onSuccess: () => {
                showEditModal.value = false;
                editingProject.value = null;
            },
        });
    }
};

const priorityBadge = (priority) => {
    switch (priority) {
        case 'urgent': return 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
        case 'high': return 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        case 'medium': return 'bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        default: return 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700';
    }
};

const priorityLabel = (priority) => {
    switch (priority) {
        case 'urgent': return '緊急';
        case 'high': return '高';
        case 'medium': return '中';
        default: return '低';
    }
};

const statusBadge = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        case 'on_hold': return 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        case 'completed': return 'bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        default: return 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'active': return '進行中';
        case 'on_hold': return '暫停中';
        case 'completed': return '已完成';
        case 'archived': return '已封存';
        default: return status;
    }
};
</script>

<template>
    <Head title="專案管理與協同看板" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>專案管理與協同看板</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-semibold">Project Hub</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">跨部門專案追蹤、任務看板與甘特進度協同管理</p>
                </div>
                <div v-if="canManage">
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>發起新專案</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- 狀態篩選與搜尋列 -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <!-- 狀態 Tabs -->
                    <div class="flex items-center space-x-1.5 overflow-x-auto pb-1 md:pb-0">
                        <button
                            v-for="tab in [
                                { key: 'active', label: '進行中' },
                                { key: 'my', label: '我負責的' },
                                { key: 'completed', label: '已完成' },
                                { key: 'on_hold', label: '已暫停' },
                                { key: 'all', label: '全部專案' },
                            ]"
                            :key="tab.key"
                            @click="filterByStatus(tab.key)"
                            :class="[
                                'px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors',
                                currentStatus === tab.key
                                    ? 'bg-indigo-600 text-white shadow-xs'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- 搜尋框 -->
                    <div class="relative w-full md:w-72">
                        <input
                            type="text"
                            v-model="searchQuery"
                            @keyup.enter="handleSearch"
                            placeholder="搜尋專案代碼、名稱..."
                            class="w-full text-xs pl-8 pr-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <!-- 專案卡片網格 -->
                <div v-if="projects.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div
                        v-for="project in projects.data"
                        :key="project.id"
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between"
                    >
                        <div>
                            <!-- 頂部標籤 -->
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                    {{ project.code }}
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <span :class="['px-2 py-0.5 text-[10px] font-semibold border rounded-full', priorityBadge(project.priority)]">
                                        {{ priorityLabel(project.priority) }}
                                    </span>
                                    <span :class="['px-2 py-0.5 text-[10px] font-semibold border rounded-full', statusBadge(project.status)]">
                                        {{ statusLabel(project.status) }}
                                    </span>
                                </div>
                            </div>

                            <!-- 專案名稱與說明 -->
                            <Link :href="route('projects.show', project.id)" class="block group">
                                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition line-clamp-1">
                                    {{ project.name }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 line-clamp-2 min-h-[32px]">
                                    {{ project.description || '尚無詳細專案說明' }}
                                </p>
                            </Link>

                            <!-- 進度長條圖 -->
                            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60">
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                    <span>任務進度</span>
                                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ project.completed_tasks_count }} / {{ project.total_tasks_count }} 完成 ({{ project.progress_percent }}%)</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                                    <div
                                        class="bg-indigo-600 h-full rounded-full transition-all duration-300"
                                        :style="{ width: `${project.progress_percent}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- 底部成員與操作 -->
                        <div class="mt-5 pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <!-- 負責人 -->
                                <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300" :title="`負責人：${project.owner?.name}`">
                                    <span class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center font-bold text-[10px]">
                                        {{ project.owner?.name?.substring(0, 1) }}
                                    </span>
                                    <span class="font-medium text-xs">{{ project.owner?.name }}</span>
                                </div>

                                <!-- 成員數 -->
                                <span v-if="project.members?.length > 1" class="text-[11px] text-gray-400 dark:text-gray-500">
                                    +{{ project.members.length - 1 }} 人
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    v-if="canManage || project.owner_id === $page.props.auth.user.id"
                                    @click="openEditModal(project)"
                                    class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                                    title="編輯專案"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <Link
                                    :href="route('projects.show', project.id)"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 rounded-lg text-xs font-semibold transition"
                                >
                                    <span>進入看板</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 空狀態提示 -->
                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-12 text-center shadow-sm">
                    <div class="w-16 h-16 rounded-full bg-indigo-50 dark:bg-indigo-950/40 text-indigo-500 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h4 class="text-base font-bold text-gray-800 dark:text-gray-200">目前尚無符合條件的專案</h4>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 max-w-sm mx-auto">
                        您可以嘗試清除搜尋條件，或點選右上角「發起新專案」建立跨部門協同工作看板。
                    </p>
                </div>
            </div>
        </div>

        <!-- 建立專案彈窗 Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="lg">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">發起新專案</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="code" value="專案代碼 *" />
                            <TextInput id="code" v-model="form.code" type="text" class="mt-1 w-full text-xs font-mono" placeholder="如 PRJ-2026-001" required />
                            <InputError :message="form.errors.code" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="priority" value="優先級 *" />
                            <select id="priority" v-model="form.priority" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="urgent">緊急 (Urgent)</option>
                                <option value="high">高 (High)</option>
                                <option value="medium">中 (Medium)</option>
                                <option value="low">低 (Low)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="name" value="專案名稱 *" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 w-full text-xs" placeholder="輸入專案名稱" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="description" value="專案簡述" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="描述專案目標與工作範疇..."
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="start_date" value="開始日期" />
                            <TextInput id="start_date" v-model="form.start_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                        <div>
                            <InputLabel for="due_date" value="預計截止日" />
                            <TextInput id="due_date" v-model="form.due_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="邀請專案同仁成員" />
                        <div class="mt-2 max-h-36 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-2.5 space-y-1.5 bg-gray-50/50 dark:bg-gray-900/50">
                            <label
                                v-for="u in users"
                                :key="u.id"
                                class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 p-1 rounded cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :value="u.id"
                                    v-model="form.member_ids"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700"
                                />
                                <span>{{ u.name }} <span class="text-gray-400">({{ u.job_title || u.email }})</span></span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end space-x-2">
                        <SecondaryButton @click="showCreateModal = false">取消</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">建立專案</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 編輯專案彈窗 Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="lg">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">編輯專案設定</h3>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="edit_status" value="專案狀態 *" />
                            <select id="edit_status" v-model="editForm.status" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="active">進行中 (Active)</option>
                                <option value="on_hold">暫停 (On Hold)</option>
                                <option value="completed">已完成 (Completed)</option>
                                <option value="archived">封存 (Archived)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="edit_priority" value="優先級 *" />
                            <select id="edit_priority" v-model="editForm.priority" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="urgent">緊急 (Urgent)</option>
                                <option value="high">高 (High)</option>
                                <option value="medium">中 (Medium)</option>
                                <option value="low">低 (Low)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="edit_name" value="專案名稱 *" />
                        <TextInput id="edit_name" v-model="editForm.name" type="text" class="mt-1 w-full text-xs" required />
                    </div>

                    <div>
                        <InputLabel for="edit_description" value="專案簡述" />
                        <textarea
                            id="edit_description"
                            v-model="editForm.description"
                            rows="3"
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="edit_start_date" value="開始日期" />
                            <TextInput id="edit_start_date" v-model="editForm.start_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                        <div>
                            <InputLabel for="edit_due_date" value="預計截止日" />
                            <TextInput id="edit_due_date" v-model="editForm.due_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="專案成員名單" />
                        <div class="mt-2 max-h-36 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-2.5 space-y-1.5 bg-gray-50/50 dark:bg-gray-900/50">
                            <label
                                v-for="u in users"
                                :key="u.id"
                                class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 p-1 rounded cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :value="u.id"
                                    v-model="editForm.member_ids"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700"
                                />
                                <span>{{ u.name }} <span class="text-gray-400">({{ u.job_title || u.email }})</span></span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <button
                            v-if="canManage || editingProject?.owner_id === $page.props.auth.user.id"
                            type="button"
                            @click="deleteProject"
                            class="text-xs text-rose-600 hover:text-rose-700 dark:text-rose-400 font-semibold hover:underline"
                        >
                            刪除此專案
                        </button>
                        <div v-else></div>
                        <div class="flex space-x-2">
                            <SecondaryButton @click="showEditModal = false">取消</SecondaryButton>
                            <PrimaryButton :disabled="editForm.processing">儲存變更</PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
