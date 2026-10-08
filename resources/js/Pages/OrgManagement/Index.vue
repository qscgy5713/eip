<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrgTreeNode from '@/Components/OrgTreeNode.vue';
import SearchableUserSelect from '@/Components/SearchableUserSelect.vue';

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

const openCreateChildDeptModal = (parentDept) => {
    isEditingDept.value = false;
    editingDeptId.value = null;
    deptForm.reset();
    deptForm.clearErrors();
    deptForm.parent_id = parentDept.id;
    deptForm.sort_order = (props.departments.length + 1) * 10;
    deptForm.is_active = true;
    showDeptModal.value = true;
};

// ================= 視覺組織圖視圖控制與拖曳調整 =================
const orgViewMode = ref('chart'); // 'chart' (視覺樹狀圖) | 'list' (階層卡片總覽)
const zoomLevel = ref(100);

const zoomIn = () => {
    if (zoomLevel.value < 140) zoomLevel.value += 10;
};
const zoomOut = () => {
    if (zoomLevel.value > 60) zoomLevel.value -= 10;
};
const resetZoom = () => {
    zoomLevel.value = 100;
};

// 節點折疊收合管理
const collapsedDeptIds = ref(new Set());
const toggleCollapse = (id) => {
    if (collapsedDeptIds.value.has(id)) {
        collapsedDeptIds.value.delete(id);
    } else {
        collapsedDeptIds.value.add(id);
    }
};
const expandAll = () => {
    collapsedDeptIds.value.clear();
};
const collapseAll = () => {
    props.departments.forEach((d) => collapsedDeptIds.value.add(d.id));
};

// 拖曳狀態管理
const draggingDept = ref(null);
const dropTargetDept = ref(null);
const isOverRootDropZone = ref(false);

const getDescendantIds = (deptId) => {
    const result = [];
    const traverse = (pid) => {
        props.departments.forEach((d) => {
            if (d.parent_id === pid) {
                result.push(d.id);
                traverse(d.id);
            }
        });
    };
    traverse(deptId);
    return result;
};

const disabledDropIds = computed(() => {
    if (!draggingDept.value) return [];
    return [draggingDept.value.id, ...getDescendantIds(draggingDept.value.id)];
});

const handleNodeDragStart = (dept, event) => {
    draggingDept.value = dept;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(dept.id));
};

const handleNodeDragOver = (targetDept, event) => {
    if (!draggingDept.value) return;
    if (disabledDropIds.value.includes(targetDept.id)) return;
    event.preventDefault();
    dropTargetDept.value = targetDept;
};

const handleNodeDragLeave = (targetDept) => {
    if (dropTargetDept.value?.id === targetDept.id) {
        dropTargetDept.value = null;
    }
};

const handleNodeDrop = (targetDept, event) => {
    event.preventDefault();
    if (!draggingDept.value) return;
    if (disabledDropIds.value.includes(targetDept.id)) return;

    const movingDept = draggingDept.value;
    draggingDept.value = null;
    dropTargetDept.value = null;

    if (movingDept.parent_id === targetDept.id) {
        return; // 原本就隸屬於該部門，無需更動
    }

    if (confirm(`確定要將部門「${movingDept.name}」拖曳調整隸屬於「${targetDept.name}」嗎？`)) {
        router.patch(route('org-management.departments.move', movingDept.id), {
            parent_id: targetDept.id,
        }, {
            preserveScroll: true,
        });
    }
};

const handleRootDragOver = (event) => {
    if (!draggingDept.value) return;
    event.preventDefault();
    isOverRootDropZone.value = true;
};

const handleRootDragLeave = () => {
    isOverRootDropZone.value = false;
};

const handleRootDrop = (event) => {
    event.preventDefault();
    isOverRootDropZone.value = false;
    if (!draggingDept.value) return;

    const movingDept = draggingDept.value;
    draggingDept.value = null;
    dropTargetDept.value = null;

    if (!movingDept.parent_id) {
        return; // 本身就是頂層部門
    }

    if (confirm(`確定要將部門「${movingDept.name}」提升為【頂層公司直屬部門】嗎？`)) {
        router.patch(route('org-management.departments.move', movingDept.id), {
            parent_id: null,
        }, {
            preserveScroll: true,
        });
    }
};

const handleDragEnd = () => {
    draggingDept.value = null;
    dropTargetDept.value = null;
    isOverRootDropZone.value = false;
};

// 部門成員抽屜 (Slide-over Drawer)
const showMembersDrawer = ref(false);
const currentDrawerDept = ref(null);

const activeDrawerDept = computed(() => {
    if (!currentDrawerDept.value) return null;
    return props.departments.find(d => d.id === currentDrawerDept.value.id) || currentDrawerDept.value;
});

const openMembersDrawer = (dept) => {
    currentDrawerDept.value = dept;
    addMemberForm.reset();
    showMembersDrawer.value = true;
};

const closeMembersDrawer = () => {
    showMembersDrawer.value = false;
    currentDrawerDept.value = null;
    addMemberForm.reset();
};

const addMemberForm = useForm({
    user_id: '',
});

const availableMembersForDrawer = computed(() => {
    if (!activeDrawerDept.value) return [];
    // 找出所有在職但尚未在此部門的同仁 (可包含未分配部門或其他部門待調動同仁)
    return (props.activeUsers || []).filter(u => u.department_id !== activeDrawerDept.value.id);
});

const submitAddMember = () => {
    if (!activeDrawerDept.value || !addMemberForm.user_id) return;
    addMemberForm.post(route('org-management.departments.members.add', activeDrawerDept.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            addMemberForm.reset();
        },
    });
};

const removeMemberFromDept = (dept, member) => {
    const isLeader = dept.leader_id === member.id;
    const warningExtra = isLeader ? '\n⚠️ 注意：此同仁為該部門主管，移出後主管職務將一併清除為未指定！' : '';
    if (confirm(`確定要將同仁「${member.name}」從「${dept.name}」移出嗎？\n移出後同仁帳號仍完整保留，其部門將變更為「未分配部門」。${warningExtra}`)) {
        router.delete(route('org-management.departments.members.remove', [dept.id, member.id]), {
            preserveScroll: true,
        });
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
                    <!-- 頂部視圖切換與畫布控制列 -->
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                        <!-- 左側：視圖切換按鈕組 -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-500 mr-1">檢視模式：</span>
                            <div class="inline-flex rounded-lg border border-gray-200 p-0.5 bg-gray-50">
                                <button
                                    @click="orgViewMode = 'chart'"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-md transition',
                                        orgViewMode === 'chart'
                                            ? 'bg-white text-indigo-600 shadow-xs border border-gray-100'
                                            : 'text-gray-500 hover:text-gray-900'
                                    ]"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                                    互動視覺組織樹 (可拖動調整)
                                </button>
                                <button
                                    @click="orgViewMode = 'list'"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-md transition',
                                        orgViewMode === 'list'
                                            ? 'bg-white text-indigo-600 shadow-xs border border-gray-100'
                                            : 'text-gray-500 hover:text-gray-900'
                                    ]"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                    階層清單總覽
                                </button>
                            </div>

                            <span class="hidden sm:inline-flex items-center px-2 py-1 text-2xs font-semibold rounded-md bg-indigo-50 text-indigo-700">
                                共 {{ departments.length }} 個部門
                            </span>
                        </div>

                        <!-- 右側：縮放控制與展開/收合 (僅樹狀圖顯示) -->
                        <div v-if="orgViewMode === 'chart'" class="flex items-center gap-3">
                            <!-- 縮放控制器 -->
                            <div class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-xs">
                                <button
                                    @click="zoomOut"
                                    class="p-1.5 text-gray-600 hover:text-indigo-600 hover:bg-white rounded transition"
                                    title="縮小畫布"
                                    :disabled="zoomLevel <= 60"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                </button>
                                <button
                                    @click="resetZoom"
                                    class="px-2 py-1 font-mono font-bold text-3xs text-gray-700 hover:text-indigo-600 transition"
                                    title="重設為 100%"
                                >
                                    {{ zoomLevel }}%
                                </button>
                                <button
                                    @click="zoomIn"
                                    class="p-1.5 text-gray-600 hover:text-indigo-600 hover:bg-white rounded transition"
                                    title="放大畫布"
                                    :disabled="zoomLevel >= 140"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </div>

                            <!-- 展開/收合全部 -->
                            <div class="inline-flex items-center gap-1.5">
                                <button
                                    @click="expandAll"
                                    class="px-2.5 py-1.5 text-2xs font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50 border border-gray-200 rounded-lg transition"
                                >
                                    全部展開
                                </button>
                                <button
                                    @click="collapseAll"
                                    class="px-2.5 py-1.5 text-2xs font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50 border border-gray-200 rounded-lg transition"
                                >
                                    全部收合
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 拖曳中：提示與頂層部門放置區 -->
                    <div
                        v-if="draggingDept"
                        @dragover="handleRootDragOver"
                        @dragleave="handleRootDragLeave"
                        @drop="handleRootDrop"
                        :class="[
                            'p-4 rounded-xl border-2 border-dashed transition-all flex items-center justify-between gap-4',
                            isOverRootDropZone
                                ? 'bg-indigo-100 border-indigo-500 scale-[1.01] shadow-lg text-indigo-900'
                                : 'bg-indigo-50/70 border-indigo-300 text-indigo-800'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold shrink-0">
                                <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm">正在調整「{{ draggingDept.name }}」的組織階層</h4>
                                <p class="text-xs text-indigo-600 mt-0.5">
                                    拖曳至下方任意目標部門即可設為子部門；或者<span class="font-bold underline ml-1">拖放到此區域，將其提升為【頂層公司直屬部門】</span>
                                </p>
                            </div>
                        </div>

                        <button
                            @click="handleDragEnd"
                            class="px-3 py-1.5 bg-white text-gray-600 hover:text-gray-900 text-xs font-semibold rounded-lg border border-gray-300 shadow-xs shrink-0"
                        >
                            取消拖曳
                        </button>
                    </div>

                    <!-- ================= 視圖 1: 互動視覺組織樹 (Chart View) ================= -->
                    <div v-if="orgViewMode === 'chart'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <!-- 視覺畫布說明列 -->
                        <div class="px-6 py-3.5 bg-slate-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"/></svg>
                                    <strong>按住卡片直接拖曳</strong>：放置到另一部門即可重新配置上下級隸屬關係
                                </span>
                                <span class="hidden md:inline text-slate-300">|</span>
                                <span class="hidden md:inline">具備自動防呆：嚴格禁止將部門拖曳設置為自己或其子孫部門</span>
                            </div>
                            <span class="font-mono text-3xs text-slate-400">目前畫布縮放：{{ zoomLevel }}%</span>
                        </div>

                        <!-- 樹狀圖可拖曳縮放大畫布 -->
                        <div class="p-8 sm:p-12 overflow-x-auto min-h-[550px] bg-radial from-slate-50/50 to-white flex justify-center">
                            <div
                                :style="{ transform: `scale(${zoomLevel / 100})`, transformOrigin: 'top center' }"
                                class="transition-transform duration-150 inline-flex flex-col items-center select-none"
                            >
                                <!-- 頂層企業總部全景根節點 -->
                                <div class="flex flex-col items-center">
                                    <div class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-indigo-700 via-indigo-600 to-indigo-800 text-white shadow-md border border-indigo-900/10 flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-white/15 text-white flex items-center justify-center font-bold shadow-2xs">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="font-extrabold text-sm tracking-wide">企業總部組織架構</h3>
                                            <p class="text-3xs text-indigo-100 mt-0.5">全員 {{ stats.total_users }} 人 · {{ departments.length }} 個部門單位</p>
                                        </div>
                                        <button
                                            @click="openCreateDeptModal"
                                            class="ml-2 px-2.5 py-1 bg-white/20 hover:bg-white/30 text-white rounded-lg text-3xs font-bold transition flex items-center gap-1"
                                            title="新增頂層一級部門"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            新增一級部門
                                        </button>
                                    </div>

                                    <!-- 總部向下主幹中線 -->
                                    <div v-if="departmentTree.length > 0" class="w-0.5 h-8 bg-slate-300"></div>
                                </div>

                                <!-- 所有頂層部門並排迴圈 -->
                                <div v-if="departmentTree.length > 0" class="flex items-start justify-center gap-8 pt-0">
                                    <OrgTreeNode
                                        v-for="(rootDept, idx) in departmentTree"
                                        :key="rootDept.id"
                                        :node="rootDept"
                                        :has-parent="true"
                                        :is-first="idx === 0"
                                        :is-last="idx === departmentTree.length - 1"
                                        :is-single-child="departmentTree.length === 1"
                                        :collapsed-ids="collapsedDeptIds"
                                        :dragging-dept-id="draggingDept?.id || null"
                                        :drop-target-id="dropTargetDept?.id || null"
                                        :disabled-drop-ids="disabledDropIds"
                                        @create-child="openCreateChildDeptModal"
                                        @edit="openEditDeptModal"
                                        @delete="deleteDept"
                                        @view-members="openMembersDrawer"
                                        @toggle-collapse="toggleCollapse"
                                        @drag-start="handleNodeDragStart"
                                        @drag-over="handleNodeDragOver"
                                        @drag-leave="handleNodeDragLeave"
                                        @drop="handleNodeDrop"
                                    />
                                </div>

                                <!-- 空白狀態 -->
                                <div v-else class="text-center py-12 text-slate-400 space-y-3">
                                    <p class="text-sm font-medium">目前尚未建立任何部門架構</p>
                                    <button
                                        @click="openCreateDeptModal"
                                        class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg shadow-sm hover:bg-indigo-700"
                                    >
                                        立即新增第一個部門
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ================= 視圖 2: 階層卡片與總表清單 (List View) ================= -->
                    <div v-else class="space-y-6">
                        <!-- 樹狀組織卡片階層檢視 -->
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">企業部門階層卡片清單</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">直觀呈現父子部門隸屬關係、負責主管與在職人數</p>
                                </div>
                                <button
                                    @click="openCreateDeptModal"
                                    class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    新增部門
                                </button>
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
                                                    在職同仁：<button @click="openMembersDrawer(rootDept)" class="font-semibold text-indigo-600 hover:underline">{{ rootDept.users_count || (rootDept.users?.length || 0) }} 人</button>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button
                                                @click="openCreateChildDeptModal(rootDept)"
                                                class="px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 border border-indigo-200 rounded-lg transition flex items-center gap-1"
                                                title="新增此部門的下級子部門"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                子部門
                                            </button>
                                            <button
                                                @click="openMembersDrawer(rootDept)"
                                                class="px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50 border border-gray-200 rounded-lg transition"
                                            >
                                                同仁名冊
                                            </button>
                                            <button
                                                @click="openEditDeptModal(rootDept)"
                                                class="px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:text-indigo-600 hover:bg-indigo-50 border border-gray-200 rounded-lg transition"
                                            >
                                                編輯
                                            </button>
                                            <button
                                                @click="deleteDept(rootDept)"
                                                class="px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 border border-rose-200 rounded-lg transition"
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
                                                        同仁：<button @click="openMembersDrawer(subDept)" class="font-semibold text-indigo-600 hover:underline">{{ subDept.users_count || (subDept.users?.length || 0) }} 人</button>
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <button
                                                    @click="openCreateChildDeptModal(subDept)"
                                                    class="px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 border border-indigo-200 rounded-md transition"
                                                    title="新增此部門的下級子部門"
                                                >
                                                    + 子部門
                                                </button>
                                                <button
                                                    @click="openMembersDrawer(subDept)"
                                                    class="px-2 py-1 text-xs font-medium text-gray-600 hover:text-indigo-600 border border-gray-200 rounded-md transition"
                                                >
                                                    同仁
                                                </button>
                                                <button
                                                    @click="openEditDeptModal(subDept)"
                                                    class="px-2 py-1 text-xs font-medium text-gray-600 hover:text-indigo-600 border border-gray-200 rounded-md transition"
                                                >
                                                    編輯
                                                </button>
                                                <button
                                                    @click="deleteDept(subDept)"
                                                    class="px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 border border-rose-200 rounded-md transition"
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
                                            <td class="px-4 py-3 font-bold text-indigo-600">
                                                <button @click="openMembersDrawer(dept)" class="hover:underline">
                                                    {{ dept.users_count || (dept.users?.length || 0) }} 人
                                                </button>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500">{{ dept.sort_order }}</td>
                                            <td class="px-4 py-3">
                                                <span :class="['px-2 py-0.5 rounded-full text-2xs font-bold border', dept.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-600 border-gray-200']">
                                                    {{ dept.is_active ? '啟用中' : '已停用' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right space-x-2">
                                                <button @click="openCreateChildDeptModal(dept)" class="text-indigo-600 hover:underline font-semibold">+ 子部門</button>
                                                <button @click="openEditDeptModal(dept)" class="text-gray-600 hover:underline font-medium">編輯</button>
                                                <button @click="deleteDept(dept)" class="text-rose-600 hover:underline font-medium">刪除</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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
                        <SearchableUserSelect
                            v-model="deptForm.leader_id"
                            :users="activeUsers"
                            placeholder="輸入姓名、帳號 (Email) 或工號搜尋指派主管..."
                            empty-message="查無符合搜尋條件的在職同仁"
                        />
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
        <!-- ================= 部門在職同仁名冊抽屜 (Slide-over Drawer) ================= -->
        <div v-if="showMembersDrawer && activeDrawerDept" class="fixed inset-0 z-50 overflow-hidden">
            <!-- 背景遮罩 -->
            <div @click="closeMembersDrawer" class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"></div>

            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col">
                    <!-- 抽屜頂部 -->
                    <div class="p-6 bg-slate-50 border-b border-slate-200 flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-2xs font-bold font-mono rounded bg-indigo-100 text-indigo-700">
                                    {{ activeDrawerDept.code }}
                                </span>
                                <h3 class="text-base font-extrabold text-slate-900">{{ activeDrawerDept.name }}</h3>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                部門主管：<span class="font-semibold text-slate-700">{{ activeDrawerDept.leader?.name ? `${activeDrawerDept.leader.name} (${activeDrawerDept.leader.job_title || '主管'})` : '未指定' }}</span> ·
                                在職成員：<span class="font-bold text-indigo-600">{{ activeDrawerDept.users?.length || 0 }}</span> 人
                            </p>
                        </div>
                        <button @click="closeMembersDrawer" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200/60 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- 抽屜同仁列表主體 -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-5">
                        <!-- 區塊 1：從公司已開好帳號的同仁下拉選單新增成員 -->
                        <div class="p-4 bg-indigo-50/60 border border-indigo-200/80 rounded-xl space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-indigo-900 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                                    指派同仁加入此部門
                                </h4>
                                <span class="text-3xs text-indigo-500 font-medium">從現有在職員工選取</span>
                            </div>

                            <form @submit.prevent="submitAddMember" class="space-y-2">
                                <div>
                                    <SearchableUserSelect
                                        v-model="addMemberForm.user_id"
                                        :users="availableMembersForDrawer"
                                        placeholder="輸入姓名、帳號 (Email) 或工號搜尋同仁..."
                                        empty-message="查無符合搜尋條件的同仁"
                                    />
                                    <p v-if="availableMembersForDrawer.length === 0" class="text-3xs text-slate-500 mt-1">
                                        公司目前無其他可指派之在職同仁
                                    </p>
                                </div>

                                <div class="flex items-center justify-between gap-2 pt-1">
                                    <p class="text-3xs text-slate-500">
                                        若為新人，請先至「員工維護」建立帳號
                                    </p>
                                    <button
                                        type="submit"
                                        :disabled="addMemberForm.processing || !addMemberForm.user_id"
                                        class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-xs font-bold shadow-xs transition flex items-center gap-1 shrink-0"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        加入部門
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 區塊 2：現有部門成員清單 -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-xs font-bold text-slate-700">目前所屬同仁 ({{ activeDrawerDept.users?.length || 0 }} 人)</h4>
                            </div>

                            <div v-if="activeDrawerDept.users && activeDrawerDept.users.length > 0" class="space-y-2.5">
                                <div
                                    v-for="member in activeDrawerDept.users"
                                    :key="member.id"
                                    class="p-3 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 transition shadow-2xs flex items-center justify-between gap-3"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                            {{ member.name.charAt(0) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <h5 class="font-bold text-slate-900 text-xs truncate">{{ member.name }}</h5>
                                                <span v-if="member.id === activeDrawerDept.leader_id" class="px-1.5 py-0.5 text-3xs font-extrabold rounded bg-amber-100 text-amber-800">
                                                    主管
                                                </span>
                                                <span :class="['px-1.5 py-0.5 rounded text-3xs font-semibold', roleBadgeClass(member.role)]">
                                                    {{ roleLabel(member.role) }}
                                                </span>
                                            </div>
                                            <p class="text-3xs text-slate-500 mt-0.5 truncate">{{ member.job_title || '未配置職稱' }} · {{ member.employee_no || '無工號' }}</p>
                                            <p class="text-3xs font-mono text-slate-400 truncate">{{ member.email }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <button
                                            @click="openEditUserModal(member); closeMembersDrawer();"
                                            class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
                                            title="編輯人事資料"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button
                                            @click="removeMemberFromDept(activeDrawerDept, member)"
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="將同仁移出此部門"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- 無成員提示 -->
                            <div v-else class="text-center py-8 text-slate-400 space-y-2 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <p class="text-xs">此部門目前尚無分配同仁</p>
                                <p class="text-3xs text-slate-400">請於上方下拉選單選取已建立之公司在職同仁加入！</p>
                            </div>
                        </div>
                    </div>

                    <!-- 抽屜底部 -->
                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-between items-center">
                        <button
                            @click="openCreateChildDeptModal(activeDrawerDept); closeMembersDrawer();"
                            class="px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 border border-indigo-200 rounded-lg transition flex items-center gap-1"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            新增下級子部門
                        </button>
                        <button
                            @click="closeMembersDrawer"
                            class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold transition"
                        >
                            關閉
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
