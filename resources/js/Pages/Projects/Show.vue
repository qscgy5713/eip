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
    project: Object,
    tasksByStatus: Object,
    progressPercent: Number,
    availableUsers: Array,
    canManage: Boolean,
});

const showTaskCreateModal = ref(false);
const showTaskEditModal = ref(false);
const editingTask = ref(null);
const currentColumnForNewTask = ref('todo');

const taskForm = useForm({
    title: '',
    description: '',
    priority: 'medium',
    status: 'todo',
    assignee_id: '',
    due_date: '',
    estimated_hours: '',
});

const taskEditForm = useForm({
    title: '',
    description: '',
    priority: 'medium',
    assignee_id: '',
    due_date: '',
    estimated_hours: '',
    actual_hours: '',
});

const openCreateTaskModal = (columnKey = 'todo') => {
    taskForm.reset();
    taskForm.status = columnKey;
    taskForm.priority = 'medium';
    currentColumnForNewTask.value = columnKey;
    showTaskCreateModal.value = true;
};

const submitCreateTask = () => {
    taskForm.post(route('tasks.store', props.project.id), {
        onSuccess: () => {
            showTaskCreateModal.value = false;
            taskForm.reset();
        },
    });
};

const openEditTaskModal = (task) => {
    editingTask.value = task;
    taskEditForm.title = task.title;
    taskEditForm.description = task.description || '';
    taskEditForm.priority = task.priority;
    taskEditForm.assignee_id = task.assignee_id || '';
    taskEditForm.due_date = task.due_date ? task.due_date.substring(0, 10) : '';
    taskEditForm.estimated_hours = task.estimated_hours || '';
    taskEditForm.actual_hours = task.actual_hours || '';
    showTaskEditModal.value = true;
};

const submitEditTask = () => {
    if (!editingTask.value) return;
    taskEditForm.put(route('tasks.update', editingTask.value.id), {
        onSuccess: () => {
            showTaskEditModal.value = false;
            editingTask.value = null;
        },
    });
};

const updateStatus = (task, newStatus) => {
    router.patch(route('tasks.updateStatus', task.id), {
        status: newStatus,
    }, { preserveScroll: true });
};

const deleteTask = (task) => {
    if (confirm(`確定要刪除任務「${task.title}」嗎？`)) {
        router.delete(route('tasks.destroy', task.id), { preserveScroll: true });
    }
};

const columns = [
    { key: 'todo', label: '待處理', color: 'border-slate-400 text-slate-700 dark:text-slate-300', bg: 'bg-slate-50/80 dark:bg-slate-900/40' },
    { key: 'in_progress', label: '進行中', color: 'border-blue-500 text-blue-700 dark:text-blue-300', bg: 'bg-blue-50/50 dark:bg-blue-950/20' },
    { key: 'review', label: '待審查', color: 'border-amber-500 text-amber-700 dark:text-amber-300', bg: 'bg-amber-50/50 dark:bg-amber-950/20' },
    { key: 'done', label: '已完成', color: 'border-emerald-500 text-emerald-700 dark:text-emerald-300', bg: 'bg-emerald-50/50 dark:bg-emerald-950/20' },
];

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

const isOverdue = (task) => {
    if (task.status === 'done' || !task.due_date) return false;
    const due = task.due_date.substring(0, 10);
    const today = new Date().toLocaleDateString('en-CA');
    return due < today;
};

const showProjectEditModal = ref(false);
const projectEditForm = useForm({
    name: props.project.name,
    description: props.project.description || '',
    priority: props.project.priority,
    status: props.project.status,
    start_date: props.project.start_date ? props.project.start_date.substring(0, 10) : '',
    due_date: props.project.due_date ? props.project.due_date.substring(0, 10) : '',
    member_ids: props.project.members ? props.project.members.map(m => m.id) : [],
});

const openProjectEditModal = () => {
    projectEditForm.name = props.project.name;
    projectEditForm.description = props.project.description || '';
    projectEditForm.priority = props.project.priority;
    projectEditForm.status = props.project.status;
    projectEditForm.start_date = props.project.start_date ? props.project.start_date.substring(0, 10) : '';
    projectEditForm.due_date = props.project.due_date ? props.project.due_date.substring(0, 10) : '';
    projectEditForm.member_ids = props.project.members ? props.project.members.map(m => m.id) : [];
    showProjectEditModal.value = true;
};

const submitEditProject = () => {
    projectEditForm.put(route('projects.update', props.project.id), {
        onSuccess: () => {
            showProjectEditModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`專案看板 - ${project.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <Link :href="route('projects.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                            &larr; 返回專案清單
                        </Link>
                        <span class="text-xs text-gray-400">/</span>
                        <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            {{ project.code }}
                        </span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span>{{ project.name }}</span>
                    </h2>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="canManage"
                        @click="openProjectEditModal"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-lg text-xs font-semibold shadow-xs transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        <span>專案設定</span>
                    </button>
                    <button
                        @click="openCreateTaskModal('todo')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>新增任務</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 px-4">
                <!-- 專案概況列 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-5 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="space-y-1.5 flex-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                            {{ project.description || '專案持續推進中，可於下方四欄看板新增與流轉任務卡片。' }}
                        </p>
                        <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400 pt-1">
                            <div>
                                <span class="font-medium text-gray-700 dark:text-gray-300">主導主管：</span>
                                <span>{{ project.owner?.name }} ({{ project.owner?.job_title || '主管' }})</span>
                            </div>
                            <div v-if="project.department">
                                <span class="font-medium text-gray-700 dark:text-gray-300">歸屬部門：</span>
                                <span>{{ project.department.name }}</span>
                            </div>
                            <div v-if="project.due_date">
                                <span class="font-medium text-gray-700 dark:text-gray-300">預計結案：</span>
                                <span class="font-mono">{{ new Date(project.due_date).toLocaleDateString('sv-SE') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 進度條 -->
                    <div class="w-full lg:w-72 bg-gray-50 dark:bg-gray-900/40 p-3 rounded-lg border border-gray-100 dark:border-gray-700/60 shrink-0">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="text-gray-500 dark:text-gray-400 font-medium">總體完成度</span>
                            <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ progressPercent }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                            <div class="bg-indigo-600 h-full rounded-full transition-all duration-300" :style="{ width: `${progressPercent}%` }"></div>
                        </div>
                    </div>
                </div>

                <!-- 任務看板 (Kanban 4 欄) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
                    <div
                        v-for="col in columns"
                        :key="col.key"
                        class="rounded-xl border border-gray-200/80 dark:border-gray-700/80 flex flex-col min-h-[500px]"
                        :class="col.bg"
                    >
                        <!-- 欄位標題列 -->
                        <div class="p-3 border-b border-gray-200/60 dark:border-gray-700 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full border-2" :class="col.color"></span>
                                <h3 class="font-bold text-xs text-gray-800 dark:text-gray-200">{{ col.label }}</h3>
                                <span class="px-1.5 py-0.2 text-[10px] font-mono font-bold rounded-full bg-gray-200/80 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                    {{ tasksByStatus[col.key]?.length || 0 }}
                                </span>
                            </div>
                            <button
                                @click="openCreateTaskModal(col.key)"
                                class="p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-white/60 dark:hover:bg-gray-800 transition"
                                title="在此欄新增任務"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </button>
                        </div>

                        <!-- 任務卡片列表 -->
                        <div class="p-3 space-y-3 flex-1 overflow-y-auto max-h-[70vh]">
                            <div
                                v-for="task in tasksByStatus[col.key]"
                                :key="task.id"
                                class="bg-white dark:bg-gray-800 rounded-lg p-3.5 border border-gray-100 dark:border-gray-700 shadow-2xs hover:shadow-sm transition flex flex-col justify-between group"
                            >
                                <div>
                                    <!-- 優先級與工時標籤 -->
                                    <div class="flex items-center justify-between gap-1.5 mb-2">
                                        <span :class="['px-1.5 py-0.2 text-[10px] font-semibold border rounded', priorityBadge(task.priority)]">
                                            {{ priorityLabel(task.priority) }}
                                        </span>
                                        <span v-if="task.estimated_hours" class="text-[10px] text-gray-400 font-mono">
                                            {{ task.estimated_hours }}h
                                        </span>
                                    </div>

                                    <!-- 任務標題 -->
                                    <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100 leading-snug">
                                        {{ task.title }}
                                    </h4>

                                    <!-- 任務描述 -->
                                    <p v-if="task.description" class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                        {{ task.description }}
                                    </p>
                                </div>

                                <div class="mt-3 pt-2.5 border-t border-gray-100 dark:border-gray-700/60">
                                    <!-- 負責人與截止日 -->
                                    <div class="flex items-center justify-between text-[11px]">
                                        <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                                            <span class="w-5 h-5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-[9px] border border-indigo-200 dark:border-indigo-800">
                                                {{ task.assignee?.name?.substring(0, 1) || '?' }}
                                            </span>
                                            <span class="truncate max-w-[80px]">{{ task.assignee?.name || '未指派' }}</span>
                                        </div>

                                        <div v-if="task.due_date" class="text-[10px] font-mono">
                                            <span v-if="isOverdue(task)" class="px-1 py-0.5 rounded bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 font-bold">
                                                逾期 {{ new Date(task.due_date).toLocaleDateString([], { month: '2-digit', day: '2-digit' }) }}
                                            </span>
                                            <span v-else class="text-gray-400">
                                                {{ new Date(task.due_date).toLocaleDateString([], { month: '2-digit', day: '2-digit' }) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- 快速狀態流轉工具列 -->
                                    <div class="mt-2.5 pt-2 border-t border-gray-50 dark:border-gray-700/40 flex items-center justify-between text-[10px]">
                                        <!-- 回退按鈕 -->
                                        <div>
                                            <button
                                                v-if="col.key === 'in_progress'"
                                                @click="updateStatus(task, 'todo')"
                                                class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition"
                                                title="退回待處理"
                                            >
                                                &larr; 待辦
                                            </button>
                                            <button
                                                v-if="col.key === 'review'"
                                                @click="updateStatus(task, 'in_progress')"
                                                class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition"
                                                title="退回進行中"
                                            >
                                                &larr; 進行
                                            </button>
                                        </div>

                                        <!-- 編輯與刪除 -->
                                        <div class="flex items-center gap-1.5">
                                            <button
                                                @click="openEditTaskModal(task)"
                                                class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                                title="編輯任務"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                            <button
                                                @click="deleteTask(task)"
                                                class="text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition"
                                                title="刪除任務"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>

                                        <!-- 前進按鈕 -->
                                        <div>
                                            <button
                                                v-if="col.key === 'todo'"
                                                @click="updateStatus(task, 'in_progress')"
                                                class="px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-semibold hover:bg-blue-100 transition"
                                                title="開始進行"
                                            >
                                                進行 &rarr;
                                            </button>
                                            <button
                                                v-if="col.key === 'in_progress'"
                                                @click="updateStatus(task, 'review')"
                                                class="px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 font-semibold hover:bg-amber-100 transition"
                                                title="送審查"
                                            >
                                                審查 &rarr;
                                            </button>
                                            <button
                                                v-if="col.key === 'review'"
                                                @click="updateStatus(task, 'done')"
                                                class="px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 font-semibold hover:bg-emerald-100 transition"
                                                title="驗收完成"
                                            >
                                                完成 ✓
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 欄位空卡片提示 -->
                            <div v-if="!tasksByStatus[col.key] || tasksByStatus[col.key].length === 0" class="py-10 text-center text-xs text-gray-400 dark:text-gray-500">
                                無任務
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 新增任務彈窗 Modal -->
        <Modal :show="showTaskCreateModal" @close="showTaskCreateModal = false" maxWidth="md">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">新增任務卡片</h3>
                    <button @click="showTaskCreateModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <form @submit.prevent="submitCreateTask" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="task_title" value="任務標題 *" />
                        <TextInput id="task_title" v-model="taskForm.title" type="text" class="mt-1 w-full text-xs" placeholder="輸入具體任務摘要" required />
                        <InputError :message="taskForm.errors.title" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="task_description" value="任務說明與驗收要點" />
                        <textarea
                            id="task_description"
                            v-model="taskForm.description"
                            rows="3"
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="詳細執行步驟與預期產出..."
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="task_priority" value="優先級 *" />
                            <select id="task_priority" v-model="taskForm.priority" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="urgent">緊急 (Urgent)</option>
                                <option value="high">高 (High)</option>
                                <option value="medium">中 (Medium)</option>
                                <option value="low">低 (Low)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="task_status" value="初始欄位狀態 *" />
                            <select id="task_status" v-model="taskForm.status" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="todo">待處理 (Todo)</option>
                                <option value="in_progress">進行中 (In Progress)</option>
                                <option value="review">待審查 (Review)</option>
                                <option value="done">已完成 (Done)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="task_assignee" value="指派負責同仁" />
                        <select id="task_assignee" v-model="taskForm.assignee_id" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- 尚未指派 --</option>
                            <option v-for="u in availableUsers" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.job_title || u.email }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="task_due_date" value="預計截止日" />
                            <TextInput id="task_due_date" v-model="taskForm.due_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                        <div>
                            <InputLabel for="task_estimated_hours" value="預估工時 (小時)" />
                            <TextInput id="task_estimated_hours" v-model="taskForm.estimated_hours" type="number" step="0.5" class="mt-1 w-full text-xs" placeholder="如 4.0" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end space-x-2">
                        <SecondaryButton @click="showTaskCreateModal = false">取消</SecondaryButton>
                        <PrimaryButton :disabled="taskForm.processing">新增任務</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 編輯任務彈窗 Modal -->
        <Modal :show="showTaskEditModal" @close="showTaskEditModal = false" maxWidth="md">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">編輯任務詳情</h3>
                    <button @click="showTaskEditModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <form @submit.prevent="submitEditTask" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="edit_task_title" value="任務標題 *" />
                        <TextInput id="edit_task_title" v-model="taskEditForm.title" type="text" class="mt-1 w-full text-xs" required />
                    </div>

                    <div>
                        <InputLabel for="edit_task_description" value="任務說明" />
                        <textarea
                            id="edit_task_description"
                            v-model="taskEditForm.description"
                            rows="3"
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="edit_task_priority" value="優先級 *" />
                            <select id="edit_task_priority" v-model="taskEditForm.priority" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="urgent">緊急 (Urgent)</option>
                                <option value="high">高 (High)</option>
                                <option value="medium">中 (Medium)</option>
                                <option value="low">低 (Low)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="edit_task_assignee" value="指派負責同仁" />
                            <select id="edit_task_assignee" v-model="taskEditForm.assignee_id" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- 尚未指派 --</option>
                                <option v-for="u in availableUsers" :key="u.id" :value="u.id">
                                    {{ u.name }} ({{ u.job_title || u.email }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <InputLabel for="edit_task_due_date" value="截止日期" />
                            <TextInput id="edit_task_due_date" v-model="taskEditForm.due_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                        <div>
                            <InputLabel for="edit_task_est_hours" value="預估工時" />
                            <TextInput id="edit_task_est_hours" v-model="taskEditForm.estimated_hours" type="number" step="0.5" class="mt-1 w-full text-xs" />
                        </div>
                        <div>
                            <InputLabel for="edit_task_act_hours" value="實際工時" />
                            <TextInput id="edit_task_act_hours" v-model="taskEditForm.actual_hours" type="number" step="0.5" class="mt-1 w-full text-xs" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end space-x-2">
                        <SecondaryButton @click="showTaskEditModal = false">取消</SecondaryButton>
                        <PrimaryButton :disabled="taskEditForm.processing">儲存變更</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 編輯專案設定 Modal -->
        <Modal :show="showProjectEditModal" @close="showProjectEditModal = false" maxWidth="lg">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">編輯專案設定</h3>
                    <button @click="showProjectEditModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <form @submit.prevent="submitEditProject" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="p_edit_status" value="專案狀態 *" />
                            <select id="p_edit_status" v-model="projectEditForm.status" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="active">進行中 (Active)</option>
                                <option value="on_hold">暫停 (On Hold)</option>
                                <option value="completed">已完成 (Completed)</option>
                                <option value="archived">封存 (Archived)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="p_edit_priority" value="優先級 *" />
                            <select id="p_edit_priority" v-model="projectEditForm.priority" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="urgent">緊急 (Urgent)</option>
                                <option value="high">高 (High)</option>
                                <option value="medium">中 (Medium)</option>
                                <option value="low">低 (Low)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="p_edit_name" value="專案名稱 *" />
                        <TextInput id="p_edit_name" v-model="projectEditForm.name" type="text" class="mt-1 w-full text-xs" required />
                    </div>

                    <div>
                        <InputLabel for="p_edit_description" value="專案簡述" />
                        <textarea
                            id="p_edit_description"
                            v-model="projectEditForm.description"
                            rows="3"
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="p_edit_start_date" value="開始日期" />
                            <TextInput id="p_edit_start_date" v-model="projectEditForm.start_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                        <div>
                            <InputLabel for="p_edit_due_date" value="預計截止日" />
                            <TextInput id="p_edit_due_date" v-model="projectEditForm.due_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="專案成員名單" />
                        <div class="mt-2 max-h-36 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-2.5 space-y-1.5 bg-gray-50/50 dark:bg-gray-900/50">
                            <label
                                v-for="u in availableUsers"
                                :key="u.id"
                                class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 p-1 rounded cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :value="u.id"
                                    v-model="projectEditForm.member_ids"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700"
                                />
                                <span>{{ u.name }} <span class="text-gray-400">({{ u.job_title || u.email }})</span></span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end space-x-2">
                        <SecondaryButton @click="showProjectEditModal = false">取消</SecondaryButton>
                        <PrimaryButton :disabled="projectEditForm.processing">儲存變更</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
