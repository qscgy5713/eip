<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    departments: {
        type: Array,
        required: true,
    },
    users: {
        type: Object,
        required: true,
    },
    activeUsers: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const currentTab = ref(props.filters.tab || 'departments');

// 搜尋與篩選狀態
const searchForm = ref({
    search: props.filters.search || '',
    department_id: props.filters.department_id || '',
    role: props.filters.role || '',
    status: props.filters.status || '',
});

const handleFilter = () => {
    router.get(route('org-management.index'), {
        tab: 'users',
        ...searchForm.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilter = () => {
    searchForm.value = {
        search: '',
        department_id: '',
        role: '',
        status: '',
    };
    handleFilter();
};

const switchTab = (tab) => {
    currentTab.value = tab;
    router.get(route('org-management.index'), {
        tab,
        ...(tab === 'users' ? searchForm.value : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// ================= 部門管理 Modal =================
const showDeptModal = ref(false);
const isEditingDept = ref(false);
const editingDeptId = ref(null);

const deptForm = useForm({
    name: '',
    code: '',
    parent_id: '',
    leader_id: '',
    sort_order: 0,
    is_active: true,
});

const openCreateDeptModal = () => {
    isEditingDept.value = false;
    editingDeptId.value = null;
    deptForm.reset();
    deptForm.clearErrors();
    deptForm.sort_order = (props.departments.length + 1) * 10;
    deptForm.is_active = true;
    showDeptModal.value = true;
};

const openEditDeptModal = (dept) => {
    isEditingDept.value = true;
    editingDeptId.value = dept.id;
    deptForm.clearErrors();
    deptForm.name = dept.name;
    deptForm.code = dept.code;
    deptForm.parent_id = dept.parent_id || '';
    deptForm.leader_id = dept.leader_id || '';
    deptForm.sort_order = dept.sort_order;
    deptForm.is_active = Boolean(dept.is_active);
    showDeptModal.value = true;
};

const submitDeptForm = () => {
    if (isEditingDept.value) {
        deptForm.put(route('org-management.departments.update', editingDeptId.value), {
            onSuccess: () => {
                showDeptModal.value = false;
                deptForm.reset();
            },
        });
    } else {
        deptForm.post(route('org-management.departments.store'), {
            onSuccess: () => {
                showDeptModal.value = false;
                deptForm.reset();
            },
        });
    }
};

const deleteDept = (dept) => {
    if (confirm(`確定要刪除部門「${dept.name}」(${dept.code}) 嗎？此操作將永久移除該部門資料。`)) {
        router.delete(route('org-management.departments.destroy', dept.id));
    }
};

// 建立階層樹狀陣列以利樹狀結構卡片展現
const departmentTree = computed(() => {
    const map = {};
    const roots = [];

    // 初始化節點
    props.departments.forEach((dept) => {
        map[dept.id] = { ...dept, childrenNodes: [] };
    });

    // 建立樹狀關聯
    props.departments.forEach((dept) => {
        if (dept.parent_id && map[dept.parent_id]) {
            map[dept.parent_id].childrenNodes.push(map[dept.id]);
        } else {
            roots.push(map[dept.id]);
        }
    });

    return roots;
});

// ================= 員工管理 Modal =================
const showUserModal = ref(false);
const isEditingUser = ref(false);
const editingUserId = ref(null);

const userForm = useForm({
    name: '',
    email: '',
    employee_no: '',
    department_id: '',
    job_title: '',
    role: 'employee',
    phone: '',
    password: '',
});

const openCreateUserModal = () => {
    isEditingUser.value = false;
    editingUserId.value = null;
    userForm.reset();
    userForm.clearErrors();
    userForm.role = 'employee';
    showUserModal.value = true;
};

const openEditUserModal = (user) => {
    isEditingUser.value = true;
    editingUserId.value = user.id;
    userForm.clearErrors();
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.employee_no = user.employee_no || '';
    userForm.department_id = user.department_id || '';
    userForm.job_title = user.job_title || '';
    userForm.role = user.role;
    userForm.phone = user.phone || '';
    userForm.password = '';
    showUserModal.value = true;
};

const submitUserForm = () => {
    if (isEditingUser.value) {
        userForm.put(route('org-management.users.update', editingUserId.value), {
            onSuccess: () => {
                showUserModal.value = false;
                userForm.reset();
            },
        });
    } else {
        userForm.post(route('org-management.users.store'), {
            onSuccess: () => {
                showUserModal.value = false;
                userForm.reset();
            },
        });
    }
};

// ================= 重設密碼 Modal =================
const showResetPwdModal = ref(false);
const targetUserForPwd = ref(null);

const pwdForm = useForm({
    password: '',
    password_confirmation: '',
});

const openResetPwdModal = (user) => {
    targetUserForPwd.value = user;
    pwdForm.reset();
    pwdForm.clearErrors();
    showResetPwdModal.value = true;
};

const submitResetPwd = () => {
    if (!targetUserForPwd.value) return;
    pwdForm.post(route('org-management.users.reset-password', targetUserForPwd.value.id), {
        onSuccess: () => {
            showResetPwdModal.value = false;
            pwdForm.reset();
        },
    });
};

// ================= 變更在職狀態 =================
const changeUserStatus = (user, newStatus) => {
    const statusText = newStatus === 'active' ? '在職正常' : (newStatus === 'suspended' ? '暫時停權' : '已離職');
    if (confirm(`確定要將同仁「${user.name}」的在職狀態變更為【${statusText}】嗎？`)) {
        router.post(route('org-management.users.status', user.id), {
            status: newStatus,
        }, {
            preserveScroll: true,
        });
    }
};

const roleBadgeClass = (role) => {
    switch (role) {
        case 'admin': return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'manager': return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'hr': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};

const roleLabel = (role) => {
    switch (role) {
        case 'admin': return '系統管理員';
        case 'manager': return '部門主管';
        case 'hr': return '人資主管';
        default: return '一般同仁';
    }
};

const statusBadgeClass = (status) => {
    switch (status) {
        case 'active': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'suspended': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'resigned': return 'bg-rose-50 text-rose-700 border-rose-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'active': return '在職';
        case 'suspended': return '停權';
        case 'resigned': return '離職';
        default: return status;
    }
};
</script>

<template>
    <Head title="組織架構與人員管理" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        組織架構與員工維護管理中心
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        維護公司部門樹狀階層體系、建立與管理同仁帳號、配置職務權限與在職狀態。
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        v-if="currentTab === 'departments'"
                        @click="openCreateDeptModal"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        新增組織部門
                    </button>
                    <button
                        v-else
                        @click="openCreateUserModal"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        新增同仁帳號
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- 提示/錯誤訊息 -->
                <div v-if="$page.props.errors?.error" class="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-center text-rose-800 text-sm shadow-sm">
                    <svg class="w-5 h-5 text-rose-600 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $page.props.errors.error }}</span>
                </div>

                <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center text-emerald-800 text-sm shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <!-- 頂部指標看板 -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500">組織部門總數</p>
                            <p class="text-2xl font-black text-gray-900 mt-1">{{ stats.total_departments }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500">公司全員總數</p>
                            <p class="text-2xl font-black text-gray-900 mt-1">{{ stats.total_users }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500">在職正常同仁</p>
                            <p class="text-2xl font-black text-emerald-600 mt-1">{{ stats.active_users }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500">停權或離職</p>
                            <p class="text-2xl font-black text-rose-600 mt-1">{{ stats.suspended_or_resigned }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        </div>
                    </div>
                </div>

                <!-- 頁籤切換選單 -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            @click="switchTab('departments')"
                            :class="[
                                'py-3 px-1 border-b-2 font-semibold text-sm transition flex items-center gap-2',
                                currentTab === 'departments'
                                    ? 'border-indigo-600 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            組織架構與部門階層 ({{ departments.length }})
                        </button>
                        <button
                            @click="switchTab('users')"
                            :class="[
                                'py-3 px-1 border-b-2 font-semibold text-sm transition flex items-center gap-2',
                                currentTab === 'users'
                                    ? 'border-indigo-600 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            員工名冊與帳號維護 ({{ stats.total_users }})
                        </button>
                    </nav>
                </div>

                <!-- ================= 頁籤 1: 部門組織架構 ================= -->
                <div v-if="currentTab === 'departments'" class="space-y-6">
                    <!-- 樹狀組織卡片階層檢視 -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">企業部門組織階層樹狀視圖</h3>
                                <p class="text-xs text-gray-500 mt-0.5">直觀呈現父子部門隸屬關係、負責主管與在職人數</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <!-- 頂層部門節點迴圈 -->
                            <div v-for="rootDept in departmentTree" :key="rootDept.id" class="border border-gray-200 rounded-xl overflow-hidden bg-slate-50/50">
                                <!-- 頂層部門卡片標題 -->
                                <div class="p-4 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                                            {{ rootDept.code }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-bold text-gray-900 text-base">{{ rootDept.name }}</h4>
                                                <span v-if="!rootDept.is_active" class="px-2 py-0.5 text-2xs bg-gray-100 text-gray-600 rounded">已停用</span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                主管：<span class="font-medium text-gray-700">{{ rootDept.leader?.name ? `${rootDept.leader.name} (${rootDept.leader.job_title || '主管'})` : '未指定' }}</span> ·
                                                在職同仁：<span class="font-semibold text-indigo-600">{{ rootDept.users_count }}</span> 人
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="openEditDeptModal(rootDept)"
                                            class="px-3 py-1.5 text-xs font-medium text-gray-700 hover:text-indigo-600 hover:bg-indigo-50 border border-gray-200 rounded-lg transition"
                                        >
                                            編輯
                                        </button>
                                        <button
                                            @click="deleteDept(rootDept)"
                                            class="px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 border border-rose-200 rounded-lg transition"
                                        >
                                            刪除
                                        </button>
                                    </div>
                                </div>

                                <!-- 子部門樹狀卡片清單 (若有) -->
                                <div v-if="rootDept.childrenNodes?.length > 0" class="p-4 space-y-3 bg-slate-50/70 border-t border-gray-100">
                                    <div
                                        v-for="subDept in rootDept.childrenNodes"
                                        :key="subDept.id"
                                        class="ml-4 sm:ml-8 p-3.5 bg-white rounded-lg border border-gray-200 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 relative before:absolute before:-left-4 before:top-1/2 before:w-4 before:h-px before:bg-gray-300"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs">
                                                {{ subDept.code }}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h5 class="font-bold text-gray-900 text-sm">{{ subDept.name }}</h5>
                                                    <span class="px-1.5 py-0.5 text-2xs bg-indigo-50 text-indigo-600 rounded">子部門</span>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    主管：<span class="text-gray-700">{{ subDept.leader?.name ? `${subDept.leader.name} (${subDept.leader.job_title || '主管'})` : '未指定' }}</span> ·
                                                    同仁：<span class="font-semibold text-indigo-600">{{ subDept.users_count }}</span> 人
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button
                                                @click="openEditDeptModal(subDept)"
                                                class="px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-indigo-600 border border-gray-200 rounded-md transition"
                                            >
                                                編輯
                                            </button>
                                            <button
                                                @click="deleteDept(subDept)"
                                                class="px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 border border-rose-200 rounded-md transition"
                                            >
                                                刪除
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 部門總表檢視 -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-900">部門設定總表</h3>
                            <span class="text-xs text-gray-400">共 {{ departments.length }} 個部門</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-gray-600">
                                <thead class="bg-gray-50 text-gray-700 uppercase font-bold border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3">部門名稱</th>
                                        <th class="px-4 py-3">代碼</th>
                                        <th class="px-4 py-3">上級部門</th>
                                        <th class="px-4 py-3">部門主管</th>
                                        <th class="px-4 py-3">在職員工</th>
                                        <th class="px-4 py-3">排序權重</th>
                                        <th class="px-4 py-3">狀態</th>
                                        <th class="px-4 py-3 text-right">管理操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="dept in departments" :key="dept.id" class="hover:bg-gray-50/80 transition">
                                        <td class="px-4 py-3 font-semibold text-gray-900">{{ dept.name }}</td>
                                        <td class="px-4 py-3 font-mono text-indigo-600 font-bold">{{ dept.code }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ dept.parent?.name || '— 頂層 —' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ dept.leader?.name || '未指定' }}</td>
                                        <td class="px-4 py-3 font-bold text-indigo-600">{{ dept.users_count }} 人</td>
                                        <td class="px-4 py-3 text-gray-500">{{ dept.sort_order }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-0.5 rounded-full text-2xs font-bold border', dept.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-600 border-gray-200']">
                                                {{ dept.is_active ? '啟用中' : '已停用' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right space-x-2">
                                            <button @click="openEditDeptModal(dept)" class="text-indigo-600 hover:underline font-medium">編輯</button>
                                            <button @click="deleteDept(dept)" class="text-rose-600 hover:underline font-medium">刪除</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================= 頁籤 2: 員工名冊與帳號維護 ================= -->
                <div v-else class="space-y-6">
                    <!-- 搜尋與過濾條件列 -->
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 w-full md:w-auto flex-1">
                            <div>
                                <input
                                    v-model="searchForm.search"
                                    type="text"
                                    placeholder="搜尋姓名/工號/Email/職稱..."
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    @keyup.enter="handleFilter"
                                />
                            </div>
                            <div>
                                <select
                                    v-model="searchForm.department_id"
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    @change="handleFilter"
                                >
                                    <option value="">全部部門</option>
                                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                                </select>
                            </div>
                            <div>
                                <select
                                    v-model="searchForm.role"
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    @change="handleFilter"
                                >
                                    <option value="">全部角色</option>
                                    <option value="admin">系統管理員 (Admin)</option>
                                    <option value="manager">部門主管 (Manager)</option>
                                    <option value="hr">人資主管 (HR)</option>
                                    <option value="employee">一般同仁 (Employee)</option>
                                </select>
                            </div>
                            <div>
                                <select
                                    v-model="searchForm.status"
                                    class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    @change="handleFilter"
                                >
                                    <option value="">全部狀態</option>
                                    <option value="active">在職正常</option>
                                    <option value="suspended">暫時停權</option>
                                    <option value="resigned">已離職</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                @click="handleFilter"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-xs transition"
                            >
                                篩選查詢
                            </button>
                            <button
                                @click="resetFilter"
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium transition"
                            >
                                重設
                            </button>
                        </div>
                    </div>

                    <!-- 員工名冊清單表格 -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-gray-600">
                                <thead class="bg-gray-50 text-gray-700 uppercase font-bold border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3">同仁姓名 / 工號</th>
                                        <th class="px-4 py-3">電子郵件</th>
                                        <th class="px-4 py-3">所屬部門 / 職稱</th>
                                        <th class="px-4 py-3">系統權限角色</th>
                                        <th class="px-4 py-3">聯絡電話</th>
                                        <th class="px-4 py-3">在職狀態</th>
                                        <th class="px-4 py-3 text-right">帳號操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50/80 transition">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs">
                                                    {{ user.name.charAt(0) }}
                                                </div>
                                                <div>
                                                    <p class="font-bold text-gray-900">{{ user.name }}</p>
                                                    <p class="text-2xs text-gray-400 font-mono">{{ user.employee_no || '無工號' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-gray-700">{{ user.email }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-gray-900">{{ user.department?.name || '— 未指派 —' }}</p>
                                            <p class="text-2xs text-gray-500">{{ user.job_title || '同仁' }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-0.5 rounded-full text-2xs font-bold border', roleBadgeClass(user.role)]">
                                                {{ roleLabel(user.role) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">{{ user.phone || '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-0.5 rounded-full text-2xs font-bold border', statusBadgeClass(user.status)]">
                                                {{ statusLabel(user.status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <button
                                                    @click="openEditUserModal(user)"
                                                    class="text-indigo-600 hover:text-indigo-800 font-medium"
                                                >
                                                    編輯
                                                </button>
                                                <span class="text-gray-300">|</span>
                                                <button
                                                    @click="openResetPwdModal(user)"
                                                    class="text-amber-600 hover:text-amber-800 font-medium"
                                                >
                                                    改密碼
                                                </button>
                                                <span class="text-gray-300">|</span>
                                                <button
                                                    v-if="user.status === 'active'"
                                                    @click="changeUserStatus(user, 'suspended')"
                                                    class="text-rose-600 hover:text-rose-800 font-medium"
                                                    title="停用該帳號"
                                                >
                                                    停權
                                                </button>
                                                <button
                                                    v-else
                                                    @click="changeUserStatus(user, 'active')"
                                                    class="text-emerald-600 hover:text-emerald-800 font-medium"
                                                    title="恢復在職狀態"
                                                >
                                                    復原
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="users.data.length === 0">
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">
                                            查無符合篩選條件的同仁資料
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分頁列 -->
                        <div v-if="users.links?.length > 3" class="p-4 border-t border-gray-100 flex items-center justify-between">
                            <p class="text-xs text-gray-500">
                                顯示第 {{ users.from || 0 }} 至 {{ users.to || 0 }} 筆，共 {{ users.total }} 筆
                            </p>
                            <div class="flex gap-1">
                                <Link
                                    v-for="(link, i) in users.links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    :class="[
                                        'px-2.5 py-1 text-xs rounded border transition',
                                        link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50',
                                        !link.url ? 'opacity-40 cursor-not-allowed' : ''
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= 新增/編輯部門 Modal ================= -->
        <div v-if="showDeptModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 animate-scale-in">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">
                        {{ isEditingDept ? '編輯組織部門資訊' : '新增企業組織部門' }}
                    </h3>
                    <button @click="showDeptModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <form @submit.prevent="submitDeptForm" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">部門名稱 <span class="text-rose-500">*</span></label>
                        <input
                            v-model="deptForm.name"
                            type="text"
                            required
                            placeholder="例如：產品研發部、市場行銷處"
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="deptForm.errors.name" class="text-2xs text-rose-600 mt-1">{{ deptForm.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">部門代碼 <span class="text-rose-500">*</span></label>
                            <input
                                v-model="deptForm.code"
                                type="text"
                                required
                                placeholder="例如：RD, MKT, SALES"
                                class="w-full text-xs font-mono uppercase rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-if="deptForm.errors.code" class="text-2xs text-rose-600 mt-1">{{ deptForm.errors.code }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">排序權重</label>
                            <input
                                v-model.number="deptForm.sort_order"
                                type="number"
                                min="0"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">上級主管部門 (選填)</label>
                        <select
                            v-model="deptForm.parent_id"
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">— 無 (設為最頂層部門) —</option>
                            <option
                                v-for="d in departments.filter(x => !isEditingDept || x.id !== editingDeptId)"
                                :key="d.id"
                                :value="d.id"
                            >
                                {{ d.name }} ({{ d.code }})
                            </option>
                        </select>
                        <p v-if="deptForm.errors.parent_id" class="text-2xs text-rose-600 mt-1">{{ deptForm.errors.parent_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">部門主管 / Leader (選填)</label>
                        <select
                            v-model="deptForm.leader_id"
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">— 暫不指派主管 —</option>
                            <option v-for="u in activeUsers" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.job_title || '同仁' }})
                            </option>
                        </select>
                        <p v-if="deptForm.errors.leader_id" class="text-2xs text-rose-600 mt-1">{{ deptForm.errors.leader_id }}</p>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            v-model="deptForm.is_active"
                            id="dept_is_active"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="dept_is_active" class="text-xs font-medium text-gray-700">啟用此部門</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="showDeptModal = false"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="deptForm.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm"
                        >
                            {{ isEditingDept ? '儲存變更' : '建立部門' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= 新增/編輯員工 Modal ================= -->
        <div v-if="showUserModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-5 animate-scale-in">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">
                        {{ isEditingUser ? '編輯同仁帳號資料' : '新增企業同仁帳號' }}
                    </h3>
                    <button @click="showUserModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <form @submit.prevent="submitUserForm" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">同仁姓名 <span class="text-rose-500">*</span></label>
                            <input
                                v-model="userForm.name"
                                type="text"
                                required
                                placeholder="例如：王小明"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-if="userForm.errors.name" class="text-2xs text-rose-600 mt-1">{{ userForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">員工編號 (工號)</label>
                            <input
                                v-model="userForm.employee_no"
                                type="text"
                                placeholder="例如：EMP-012"
                                class="w-full text-xs font-mono rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-if="userForm.errors.employee_no" class="text-2xs text-rose-600 mt-1">{{ userForm.errors.employee_no }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">電子郵件 (登入帳號) <span class="text-rose-500">*</span></label>
                        <input
                            v-model="userForm.email"
                            type="email"
                            required
                            placeholder="例如：alex@example.com"
                            class="w-full text-xs font-mono rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="userForm.errors.email" class="text-2xs text-rose-600 mt-1">{{ userForm.errors.email }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">所屬部門</label>
                            <select
                                v-model="userForm.department_id"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">— 暫不分配部門 —</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">職稱</label>
                            <input
                                v-model="userForm.job_title"
                                type="text"
                                placeholder="例如：資深全端工程師"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">系統權限角色 <span class="text-rose-500">*</span></label>
                            <select
                                v-model="userForm.role"
                                required
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="employee">一般同仁 (Employee)</option>
                                <option value="manager">部門主管 (Manager)</option>
                                <option value="hr">人資主管 (HR)</option>
                                <option value="admin">系統管理員 (Admin)</option>
                            </select>
                            <p v-if="userForm.errors.role" class="text-2xs text-rose-600 mt-1">{{ userForm.errors.role }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">聯絡電話</label>
                            <input
                                v-model="userForm.phone"
                                type="text"
                                placeholder="例如：0912-345-678"
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div v-if="!isEditingUser">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">初始登入密碼 <span class="text-rose-500">*</span></label>
                        <input
                            v-model="userForm.password"
                            type="password"
                            required
                            placeholder="至少 8 位字元"
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p class="text-2xs text-gray-400 mt-1">建立帳號後系統將自動為同仁初始化法定年度休假額度。</p>
                        <p v-if="userForm.errors.password" class="text-2xs text-rose-600 mt-1">{{ userForm.errors.password }}</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="showUserModal = false"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="userForm.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm"
                        >
                            {{ isEditingUser ? '儲存帳號' : '建立帳號' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= 重設密碼 Modal ================= -->
        <div v-if="showResetPwdModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4 animate-scale-in">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">重設同仁登入密碼</h3>
                    <button @click="showResetPwdModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                <p class="text-xs text-gray-600">
                    正在重設同仁「<strong class="text-gray-900">{{ targetUserForPwd?.name }}</strong>」的密碼：
                </p>

                <form @submit.prevent="submitResetPwd" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">新密碼 <span class="text-rose-500">*</span></label>
                        <input
                            v-model="pwdForm.password"
                            type="password"
                            required
                            placeholder="至少 8 位字元"
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="pwdForm.errors.password" class="text-2xs text-rose-600 mt-1">{{ pwdForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">確認新密碼 <span class="text-rose-500">*</span></label>
                        <input
                            v-model="pwdForm.password_confirmation"
                            type="password"
                            required
                            placeholder="再次輸入新密碼"
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="showResetPwdModal = false"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="pwdForm.processing"
                            class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-sm"
                        >
                            確認更新密碼
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
