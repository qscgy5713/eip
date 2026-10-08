<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OrgTreeNode from '@/Components/OrgTreeNode.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    departments: Array,
    users: Array,
    selectedDepartmentId: [String, Number],
    search: String,
});

// 視圖切換：'list' (同仁名冊清單) | 'chart' (企業架構圖)
const viewMode = ref('list');

const searchInput = ref(props.search || '');

const filterDept = (deptId) => {
    router.get(route('directory.index'), { department_id: deptId, search: searchInput.value }, { preserveState: true });
};

const handleSearch = () => {
    router.get(route('directory.index'), { department_id: props.selectedDepartmentId, search: searchInput.value }, { preserveState: true });
};

// ================= 組織架構全景圖 (唯讀模式) =================
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

// 畫布即時關鍵字搜尋與高亮
const canvasSearch = ref('');
const handleCanvasSearchInput = (e) => {
    const val = typeof e === 'string' ? e : e?.target?.value || '';
    canvasSearch.value = val;
    if (val && val.trim() !== '') {
        expandAll();
    }
};
const clearCanvasSearch = () => {
    canvasSearch.value = '';
};

// 建立階層樹狀陣列
const departmentTree = computed(() => {
    const map = {};
    const roots = [];

    (props.departments || []).forEach((dept) => {
        map[dept.id] = { ...dept, childrenNodes: [] };
    });

    (props.departments || []).forEach((dept) => {
        if (dept.parent_id && map[dept.parent_id]) {
            map[dept.parent_id].childrenNodes.push(map[dept.id]);
        } else {
            roots.push(map[dept.id]);
        }
    });

    return roots;
});

// 部門成員抽屜 (唯讀模式)
const showMembersDrawer = ref(false);
const activeDrawerDept = ref(null);

const openMembersDrawer = (dept) => {
    activeDrawerDept.value = dept;
    showMembersDrawer.value = true;
};

const closeMembersDrawer = () => {
    showMembersDrawer.value = false;
    activeDrawerDept.value = null;
};
</script>

<template>
    <Head title="組織架構與通訊錄" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">組織架構與同仁通訊錄</h2>
                    <p class="text-xs text-gray-500 mt-1">快速查找跨部門夥伴、聯繫窗口與企業組織層級體系</p>
                </div>

                <!-- 視圖切換按鈕 -->
                <div class="inline-flex rounded-lg border border-gray-200 p-0.5 bg-gray-50">
                    <button
                        @click="viewMode = 'list'"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-md transition',
                            viewMode === 'list'
                                ? 'bg-white text-indigo-600 shadow-xs border border-gray-100'
                                : 'text-gray-500 hover:text-gray-900'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        同仁通訊名冊
                    </button>
                    <button
                        @click="viewMode = 'chart'"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-md transition',
                            viewMode === 'chart'
                                ? 'bg-white text-indigo-600 shadow-xs border border-gray-100'
                                : 'text-gray-500 hover:text-gray-900'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                        企業組織架構圖
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- ================= 視圖 1: 同仁名冊清單 (List View) ================= -->
                <div v-if="viewMode === 'list'" class="space-y-6">
                    <!-- 搜尋與部門篩選列 -->
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                        <!-- 部門按鈕標籤 -->
                        <div class="flex flex-wrap gap-2 w-full md:w-auto">
                            <button
                                @click="filterDept('')"
                                :class="[
                                    'px-3 py-1.5 text-xs font-semibold rounded-lg transition',
                                    !selectedDepartmentId ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                ]"
                            >
                                全體員工
                            </button>
                            <button
                                v-for="dept in departments"
                                :key="dept.id"
                                @click="filterDept(dept.id)"
                                :class="[
                                    'px-3 py-1.5 text-xs font-semibold rounded-lg transition',
                                    selectedDepartmentId == dept.id ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                ]"
                            >
                                {{ dept.name }} ({{ dept.users_count }})
                            </button>
                        </div>

                        <!-- 關鍵字搜尋 -->
                        <div class="flex items-center space-x-2 w-full md:w-72">
                            <input
                                v-model="searchInput"
                                @keyup.enter="handleSearch"
                                type="text"
                                placeholder="搜尋姓名、職稱、Email..."
                                class="w-full text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <button @click="handleSearch" class="px-3.5 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition shrink-0 shadow-xs">
                                搜尋
                            </button>
                        </div>
                    </div>

                    <!-- 同仁卡片 Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div
                            v-for="u in users"
                            :key="u.id"
                            class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-start space-x-4 hover:shadow-md transition"
                        >
                            <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white font-bold text-lg shrink-0 shadow-xs">
                                {{ u.name.slice(0, 1) }}
                            </div>
                            <div class="space-y-1 min-w-0 flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-bold text-gray-900 text-base truncate">{{ u.name }}</h4>
                                    <span class="px-2 py-0.5 text-2xs font-semibold bg-indigo-50 text-indigo-700 rounded border border-indigo-100">{{ u.department?.name || '公司同仁' }}</span>
                                </div>
                                <p class="text-xs text-gray-500">{{ u.job_title || '同仁' }} · 工號: {{ u.employee_no || '-' }}</p>
                                <div class="pt-2 border-t border-gray-100 text-xs text-gray-600 space-y-1">
                                    <p class="truncate flex items-center font-mono">
                                        <svg class="w-3.5 h-3.5 mr-1.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        {{ u.email }}
                                    </p>
                                    <p class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        {{ u.phone || '尚未提供' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-if="users.length === 0" class="col-span-full py-12 text-center text-gray-400">查無符合的同仁資料</div>
                    </div>
                </div>

                <!-- ================= 視圖 2: 企業組織架構全景圖 (Chart View) ================= -->
                <div v-else class="space-y-6">
                    <!-- 畫布控制工具列 -->
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-700">企業組織全景架構</span>
                            <span class="inline-flex items-center px-2 py-0.5 text-2xs font-semibold rounded-md bg-indigo-50 text-indigo-700">
                                共 {{ departments.length }} 個部門單位
                            </span>
                        </div>

                        <!-- 畫布搜尋框 -->
                        <div class="flex-1 max-w-xs sm:max-w-sm w-full mx-auto md:mx-0">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input
                                    :value="canvasSearch"
                                    @input="handleCanvasSearchInput"
                                    type="text"
                                    placeholder="搜尋部門、主管或同仁姓名..."
                                    class="w-full pl-9 pr-10 py-1.5 text-xs bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-indigo-500 rounded-lg focus:ring-1 focus:ring-indigo-500 transition placeholder:text-slate-400"
                                />
                                <div v-if="canvasSearch" class="absolute inset-y-0 right-0 pr-2 flex items-center">
                                    <button
                                        @click="clearCanvasSearch"
                                        type="button"
                                        class="p-1 text-slate-400 hover:text-slate-600 rounded transition"
                                        title="清除搜尋"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 縮放與展開控制 -->
                        <div class="flex items-center gap-3">
                            <div class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-xs">
                                <button
                                    @click="zoomOut"
                                    class="p-1.5 text-gray-600 hover:text-indigo-600 hover:bg-white rounded transition"
                                    title="縮小"
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
                                    title="放大"
                                    :disabled="zoomLevel >= 140"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </div>

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

                    <!-- 樹狀圖唯讀大畫布 -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
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
                                            <p class="text-3xs text-indigo-100 mt-0.5">{{ departments.length }} 個部門單位</p>
                                        </div>
                                    </div>

                                    <div v-if="departmentTree.length > 0" class="w-0.5 h-8 bg-slate-300"></div>
                                </div>

                                <!-- 所有頂層部門並排迴圈 (唯讀模式) -->
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
                                        :search-keyword="canvasSearch"
                                        :read-only="true"
                                        @view-members="openMembersDrawer"
                                        @toggle-collapse="toggleCollapse"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= 部門同仁名冊抽屜 (唯讀模式) ================= -->
        <div v-if="showMembersDrawer && activeDrawerDept" class="fixed inset-0 z-50 overflow-hidden">
            <div @click="closeMembersDrawer" class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"></div>

            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col">
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

                    <div class="flex-1 overflow-y-auto p-6 space-y-4">
                        <div v-if="activeDrawerDept.users && activeDrawerDept.users.length > 0" class="space-y-2.5">
                            <div
                                v-for="member in activeDrawerDept.users"
                                :key="member.id"
                                class="p-3.5 rounded-xl border border-slate-200 bg-white shadow-2xs flex items-center gap-3"
                            >
                                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                    {{ member.name.charAt(0) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <h5 class="font-bold text-slate-900 text-xs truncate">{{ member.name }}</h5>
                                        <span v-if="member.id === activeDrawerDept.leader_id" class="px-1.5 py-0.5 text-3xs font-extrabold rounded bg-amber-100 text-amber-800">
                                            主管
                                        </span>
                                    </div>
                                    <p class="text-3xs text-slate-500 mt-0.5 truncate">{{ member.job_title || '未配置職稱' }} · 工號: {{ member.employee_no || '-' }}</p>
                                    <p class="text-3xs font-mono text-slate-400 truncate">{{ member.email }}</p>
                                    <p v-if="member.phone" class="text-3xs text-slate-400 truncate">{{ member.phone }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-10 text-slate-400 space-y-2 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                            <p class="text-xs">此部門目前尚無分配同仁</p>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
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
