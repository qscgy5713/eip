<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    departments: Array,
    users: Array,
    selectedDepartmentId: [String, Number],
    search: String,
});

const searchInput = ref(props.search || '');

const filterDept = (deptId) => {
    router.get(route('directory.index'), { department_id: deptId, search: searchInput.value }, { preserveState: true });
};

const handleSearch = () => {
    router.get(route('directory.index'), { department_id: props.selectedDepartmentId, search: searchInput.value }, { preserveState: true });
};
</script>

<template>
    <Head title="組織架構與通訊錄" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-gray-800">組織架構與同仁通訊錄</h2>
                <span class="text-sm text-gray-500">快速查找跨部門夥伴與聯繫窗口</span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 搜尋與部門篩選列 -->
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                    <!-- 部門按鈕標籤 -->
                    <div class="flex flex-wrap gap-2 w-full md:w-auto">
                        <button
                            @click="filterDept('')"
                            :class="[
                                'px-3.5 py-1.5 text-xs font-semibold rounded-lg transition',
                                !selectedDepartmentId ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]"
                        >
                            全體員工
                        </button>
                        <button
                            v-for="dept in departments"
                            :key="dept.id"
                            @click="filterDept(dept.id)"
                            :class="[
                                'px-3.5 py-1.5 text-xs font-semibold rounded-lg transition',
                                selectedDepartmentId == dept.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
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
                            class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                        />
                        <button @click="handleSearch" class="px-3 py-1.5 bg-gray-800 text-white text-xs font-bold rounded-lg hover:bg-gray-900">
                            搜尋
                        </button>
                    </div>
                </div>

                <!-- 同仁卡片 Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div
                        v-for="u in users"
                        :key="u.id"
                        class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-start space-x-4 hover:shadow-md transition"
                    >
                        <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-white font-bold text-lg shrink-0">
                            {{ u.name.slice(0, 1) }}
                        </div>
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-gray-900 text-base truncate">{{ u.name }}</h4>
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-blue-50 text-blue-700 rounded">{{ u.department?.name || '公司同仁' }}</span>
                            </div>
                            <p class="text-xs text-gray-500">{{ u.job_title || '同仁' }} · 工號: {{ u.employee_no || '-' }}</p>
                            <div class="pt-2 border-t border-gray-50 text-xs text-gray-600 space-y-0.5">
                                <p class="truncate">📧 {{ u.email }}</p>
                                <p>📱 {{ u.phone || '尚未提供' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="users.length === 0" class="col-span-full py-12 text-center text-gray-400">查無符合的同仁資料</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
