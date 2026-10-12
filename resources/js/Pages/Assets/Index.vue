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
    assets: Object,
    stats: Object,
    departments: Array,
    users: Array,
    filters: Object,
    canManage: Boolean,
});

const currentStatus = ref(props.filters.status || 'all');
const currentCategory = ref(props.filters.category || 'all');
const searchQuery = ref(props.filters.search || '');
const showMyAssets = ref(props.filters.my_assets || false);

const showCreateModal = ref(false);
const showBorrowModal = ref(false);
const showReturnModal = ref(false);
const targetAsset = ref(null);

const createForm = useForm({
    asset_tag: '',
    name: '',
    category: 'it_hardware',
    serial_number: '',
    specification: '',
    location: '',
    status: 'available',
    department_id: '',
    purchase_date: '',
    purchase_cost: '',
    notes: '',
});

const borrowForm = useForm({
    borrower_id: '',
    expected_return_date: '',
    purpose: '',
});

const returnForm = useForm({
    return_notes: '',
});

const handleSearch = () => {
    router.get(
        route('assets.index'),
        {
            status: currentStatus.value,
            category: currentCategory.value,
            search: searchQuery.value,
            my_assets: showMyAssets.value ? 1 : 0,
        },
        { preserveState: true, replace: true }
    );
};

const filterByStatus = (status) => {
    currentStatus.value = status;
    handleSearch();
};

const filterByCategory = (cat) => {
    currentCategory.value = cat;
    handleSearch();
};

const toggleMyAssets = () => {
    showMyAssets.value = !showMyAssets.value;
    handleSearch();
};

const openCreateModal = () => {
    createForm.reset();
    createForm.asset_tag = `AST-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 9000) + 1000)}`;
    createForm.status = 'available';
    createForm.category = 'it_hardware';
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('assets.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const openBorrowModal = (asset) => {
    targetAsset.value = asset;
    borrowForm.reset();
    borrowForm.borrower_id = '';
    showBorrowModal.value = true;
};

const submitBorrow = () => {
    if (!targetAsset.value) return;
    borrowForm.post(route('assets.borrow', targetAsset.value.id), {
        onSuccess: () => {
            showBorrowModal.value = false;
            targetAsset.value = null;
        },
    });
};

const openReturnModal = (asset) => {
    targetAsset.value = asset;
    returnForm.reset();
    showReturnModal.value = true;
};

const submitReturn = () => {
    if (!targetAsset.value) return;
    returnForm.post(route('assets.return', targetAsset.value.id), {
        onSuccess: () => {
            showReturnModal.value = false;
            targetAsset.value = null;
        },
    });
};

const categoryBadge = (cat) => {
    switch (cat) {
        case 'it_hardware': return 'bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        case 'office_equipment': return 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        case 'av_media': return 'bg-purple-100 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        case 'vehicles': return 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
        case 'peripherals': return 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        default: return 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700';
    }
};

const categoryLabel = (cat) => {
    switch (cat) {
        case 'it_hardware': return 'IT 資訊硬體';
        case 'office_equipment': return '辦公 OA 設備';
        case 'av_media': return '視聽攝影器材';
        case 'vehicles': return '公務車輛';
        case 'peripherals': return '週邊配件耗材';
        default: return '其他公務資產';
    }
};

const statusBadge = (status) => {
    switch (status) {
        case 'available': return 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        case 'in_use': return 'bg-indigo-100 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800';
        case 'maintenance': return 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        default: return 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'available': return '在庫可借';
        case 'in_use': return '使用中';
        case 'maintenance': return '維護中';
        case 'retired': return '已報廢';
        default: return status;
    }
};
</script>

<template>
    <Head title="企業固定資產與公務設備借調" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>企業固定資產與設備借調</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-300 font-semibold">Asset Hub</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">公務設備在席狀態、線上領用借調、保管歷程與盤點驗收管理</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="toggleMyAssets"
                        :class="[
                            'px-3.5 py-2 rounded-lg text-xs font-semibold border transition shadow-xs',
                            showMyAssets
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'
                        ]"
                    >
                        {{ showMyAssets ? '顯示全體資產' : '我保管的資產' }}
                    </button>
                    <button
                        v-if="canManage"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>登錄新資產</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 px-4">
                <!-- 頂部統計指標卡片 -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-xs">
                        <p class="text-xs text-gray-400 dark:text-gray-500 font-medium">總登記資產</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-1">{{ stats.total }} <span class="text-xs font-normal text-gray-400">件</span></p>
                    </div>
                    <div class="bg-emerald-50/60 dark:bg-emerald-950/20 p-4 rounded-xl border border-emerald-100 dark:border-emerald-900/40 shadow-xs">
                        <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">在庫可借用</p>
                        <p class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">{{ stats.available }} <span class="text-xs font-normal text-emerald-600/70">件</span></p>
                    </div>
                    <div class="bg-indigo-50/60 dark:bg-indigo-950/20 p-4 rounded-xl border border-indigo-100 dark:border-indigo-900/40 shadow-xs">
                        <p class="text-xs text-indigo-700 dark:text-indigo-400 font-medium">借出使用中</p>
                        <p class="text-2xl font-black text-indigo-700 dark:text-indigo-300 mt-1">{{ stats.in_use }} <span class="text-xs font-normal text-indigo-600/70">件</span></p>
                    </div>
                    <div class="bg-amber-50/60 dark:bg-amber-950/20 p-4 rounded-xl border border-amber-100 dark:border-amber-900/40 shadow-xs">
                        <p class="text-xs text-amber-700 dark:text-amber-400 font-medium">維修保養中</p>
                        <p class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ stats.maintenance }} <span class="text-xs font-normal text-amber-600/70">件</span></p>
                    </div>
                    <div class="bg-teal-50/60 dark:bg-teal-950/20 p-4 rounded-xl border border-teal-100 dark:border-teal-900/40 shadow-xs">
                        <p class="text-xs text-teal-700 dark:text-teal-400 font-medium">我目前保管</p>
                        <p class="text-2xl font-black text-teal-700 dark:text-teal-300 mt-1">{{ stats.my_held_count }} <span class="text-xs font-normal text-teal-600/70">件</span></p>
                    </div>
                </div>

                <!-- 搜尋與分類篩選列 -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-3">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <!-- 狀態篩選 Tabs -->
                        <div class="flex items-center space-x-1.5 overflow-x-auto pb-1 md:pb-0">
                            <button
                                v-for="tab in [
                                    { key: 'all', label: '全部狀態' },
                                    { key: 'available', label: '在庫可借' },
                                    { key: 'in_use', label: '借出中' },
                                    { key: 'maintenance', label: '維修中' },
                                    { key: 'retired', label: '已報廢' },
                                ]"
                                :key="tab.key"
                                @click="filterByStatus(tab.key)"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors',
                                    currentStatus === tab.key
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                                ]"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <!-- 關鍵字搜尋框 -->
                        <div class="flex items-center space-x-2">
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="searchQuery"
                                    @keyup.enter="handleSearch"
                                    placeholder="搜尋編號、名稱、序號、地點..."
                                    class="w-64 pl-8 pr-3 py-1.5 text-xs rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 focus:border-teal-500 focus:ring-teal-500"
                                />
                                <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <button
                                @click="handleSearch"
                                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-semibold transition"
                            >
                                篩選
                            </button>
                        </div>
                    </div>

                    <!-- 分類篩選 Tags -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pt-2 border-t border-gray-50 dark:border-gray-700/60 text-xs">
                        <span class="text-gray-400 dark:text-gray-500 text-[11px] shrink-0 font-medium">分類：</span>
                        <button
                            v-for="cat in [
                                { key: 'all', label: '全部分類' },
                                { key: 'it_hardware', label: 'IT 資訊硬體' },
                                { key: 'office_equipment', label: '辦公 OA 設備' },
                                { key: 'av_media', label: '視聽攝影器材' },
                                { key: 'vehicles', label: '公務車輛' },
                                { key: 'peripherals', label: '週邊配件耗材' },
                            ]"
                            :key="cat.key"
                            @click="filterByCategory(cat.key)"
                            :class="[
                                'px-2.5 py-1 rounded-md text-[11px] font-medium whitespace-nowrap transition',
                                currentCategory === cat.key
                                    ? 'bg-teal-100 dark:bg-teal-900/60 text-teal-800 dark:text-teal-200 font-bold'
                                    : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/60'
                            ]"
                        >
                            {{ cat.label }}
                        </button>
                    </div>
                </div>

                <!-- 資產卡片網格 -->
                <div v-if="assets.data && assets.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="asset in assets.data"
                        :key="asset.id"
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between"
                    >
                        <div>
                            <!-- 頂部編號與狀態 -->
                            <div class="flex items-center justify-between gap-2 pb-3 border-b border-gray-50 dark:border-gray-700/60">
                                <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                    {{ asset.asset_tag }}
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <span :class="['px-2 py-0.5 text-[10px] font-bold rounded-full border', categoryBadge(asset.category)]">
                                        {{ categoryLabel(asset.category) }}
                                    </span>
                                    <span :class="['px-2 py-0.5 text-[10px] font-bold rounded-full border', statusBadge(asset.status)]">
                                        {{ statusLabel(asset.status) }}
                                    </span>
                                </div>
                            </div>

                            <!-- 資產名稱與規格 -->
                            <div class="mt-3 space-y-1.5">
                                <Link :href="route('assets.show', asset.id)" class="text-base font-bold text-gray-900 dark:text-gray-100 hover:text-teal-600 dark:hover:text-teal-400 transition block truncate">
                                    {{ asset.name }}
                                </Link>
                                <p v-if="asset.specification" class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 leading-relaxed">
                                    {{ asset.specification }}
                                </p>
                            </div>

                            <!-- 屬性細節 -->
                            <div class="mt-4 pt-3 border-t border-gray-50 dark:border-gray-700/60 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-400">存放位置：</span>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ asset.location || '總部庫存區' }}</span>
                                </div>
                                <div v-if="asset.serial_number" class="flex items-center justify-between">
                                    <span class="text-gray-400">原廠序號：</span>
                                    <span class="font-mono text-gray-600 dark:text-gray-400">{{ asset.serial_number }}</span>
                                </div>
                                <div v-if="asset.department" class="flex items-center justify-between">
                                    <span class="text-gray-400">歸屬部門：</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ asset.department.name }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 底部保管人與借還操作 -->
                        <div class="mt-5 pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                            <!-- 目前保管人 -->
                            <div class="flex items-center gap-1.5 text-xs">
                                <span v-if="asset.current_holder" class="flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-semibold" :title="`保管人：${asset.current_holder.name}`">
                                    <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-[10px] font-bold">
                                        {{ asset.current_holder.name.charAt(0) }}
                                    </span>
                                    <span class="truncate max-w-[80px]">{{ asset.current_holder.name }}</span>
                                </span>
                                <span v-else class="text-gray-400 dark:text-gray-500 text-[11px]">
                                    在庫無保管人
                                </span>
                            </div>

                            <!-- 快捷按鈕 -->
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="asset.status === 'available'"
                                    @click="openBorrowModal(asset)"
                                    class="px-2.5 py-1 text-xs font-semibold rounded bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 hover:bg-teal-100 dark:hover:bg-teal-900/60 transition"
                                >
                                    借用登記
                                </button>
                                <button
                                    v-else-if="asset.status === 'in_use' && (canManage || asset.current_holder_id === $page.props.auth.user.id)"
                                    @click="openReturnModal(asset)"
                                    class="px-2.5 py-1 text-xs font-semibold rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 hover:bg-amber-100 dark:hover:bg-amber-900/60 transition"
                                >
                                    歸還驗收
                                </button>
                                <Link
                                    :href="route('assets.show', asset.id)"
                                    class="px-2.5 py-1 text-xs font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition"
                                >
                                    詳情 &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 空狀態提示 -->
                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-12 text-center shadow-sm">
                    <div class="w-16 h-16 rounded-full bg-teal-50 dark:bg-teal-950/40 text-teal-500 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h4 class="text-base font-bold text-gray-800 dark:text-gray-200">目前尚無符合條件的公務資產</h4>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 max-w-sm mx-auto">
                        您可以嘗試清除搜尋條件，或點選右上角「登錄新資產」登記公司固定資產與公務設備。
                    </p>
                </div>
            </div>
        </div>

        <!-- 登錄新資產 Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="lg">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">登錄固定資產與公務設備</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="asset_tag" value="資產編號 *" />
                            <TextInput id="asset_tag" v-model="createForm.asset_tag" type="text" class="mt-1 w-full text-xs font-mono" required />
                            <InputError :message="createForm.errors.asset_tag" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="category" value="資產分類 *" />
                            <select id="category" v-model="createForm.category" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="it_hardware">IT 資訊硬體 (筆電/桌機/伺服器)</option>
                                <option value="office_equipment">辦公 OA 設備 (印表機/碎紙機)</option>
                                <option value="av_media">視聽攝影器材 (相機/投影機)</option>
                                <option value="vehicles">公務車輛</option>
                                <option value="peripherals">週邊配件耗材</option>
                                <option value="other">其他資產</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="name" value="資產名稱/型號 *" />
                        <TextInput id="name" v-model="createForm.name" type="text" placeholder="例：MacBook Pro 16吋 M3 Max" class="mt-1 w-full text-xs" required />
                        <InputError :message="createForm.errors.name" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="serial_number" value="原廠序號 (S/N)" />
                            <TextInput id="serial_number" v-model="createForm.serial_number" type="text" class="mt-1 w-full text-xs font-mono" />
                        </div>
                        <div>
                            <InputLabel for="location" value="存放地點" />
                            <TextInput id="location" v-model="createForm.location" type="text" placeholder="例：台北 7F IT 倉庫" class="mt-1 w-full text-xs" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="specification" value="規格配置說明" />
                        <textarea
                            id="specification"
                            v-model="createForm.specification"
                            rows="2"
                            placeholder="例：Apple M3 Max 晶片, 64GB 記憶體, 1TB SSD..."
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="department_id" value="歸屬部門" />
                            <select id="department_id" v-model="createForm.department_id" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="">-- 全公司公用 --</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="purchase_date" value="採購日期" />
                            <TextInput id="purchase_date" v-model="createForm.purchase_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end space-x-2">
                        <SecondaryButton @click="showCreateModal = false">取消</SecondaryButton>
                        <PrimaryButton :disabled="createForm.processing">儲存登錄</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 借調登記 Modal -->
        <Modal :show="showBorrowModal" @close="showBorrowModal = false" maxWidth="md">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">公務資產借調登記</h3>
                    <button @click="showBorrowModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <div v-if="targetAsset" class="mt-3 p-3 bg-teal-50/50 dark:bg-teal-950/20 rounded-lg border border-teal-100 dark:border-teal-900/40 text-xs">
                    <span class="font-mono font-bold text-teal-700 dark:text-teal-300">[{{ targetAsset.asset_tag }}]</span>
                    <span class="ml-1.5 font-bold text-gray-800 dark:text-gray-200">{{ targetAsset.name }}</span>
                </div>

                <form @submit.prevent="submitBorrow" class="mt-4 space-y-4">
                    <div v-if="canManage">
                        <InputLabel for="borrower_id" value="借用同仁 (管理員可代為登記)" />
                        <select id="borrower_id" v-model="borrowForm.borrower_id" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            <option value="">-- 由登入者本人領用 --</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.job_title || u.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="expected_return_date" value="預計歸還日期" />
                        <TextInput id="expected_return_date" v-model="borrowForm.expected_return_date" type="date" class="mt-1 w-full text-xs" />
                    </div>

                    <div>
                        <InputLabel for="purpose" value="借用事由與專案用途 *" />
                        <textarea
                            id="purpose"
                            v-model="borrowForm.purpose"
                            rows="3"
                            placeholder="請具體說明借用目的、專案名稱或外勤活動..."
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                            required
                        ></textarea>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end space-x-2">
                        <SecondaryButton @click="showBorrowModal = false">取消</SecondaryButton>
                        <PrimaryButton :disabled="borrowForm.processing">確認借出</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 歸還驗收 Modal -->
        <Modal :show="showReturnModal" @close="showReturnModal = false" maxWidth="md">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">公務資產歸還驗收</h3>
                    <button @click="showReturnModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <div v-if="targetAsset" class="mt-3 p-3 bg-amber-50/50 dark:bg-amber-950/20 rounded-lg border border-amber-100 dark:border-amber-900/40 text-xs">
                    <span class="font-mono font-bold text-amber-700 dark:text-amber-300">[{{ targetAsset.asset_tag }}]</span>
                    <span class="ml-1.5 font-bold text-gray-800 dark:text-gray-200">{{ targetAsset.name }}</span>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">目前保管人：{{ targetAsset.current_holder?.name }}</p>
                </div>

                <form @submit.prevent="submitReturn" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="return_notes" value="歸還機況與驗收備註 (選填)" />
                        <textarea
                            id="return_notes"
                            v-model="returnForm.return_notes"
                            rows="3"
                            placeholder="例：外觀良好、功能正常、充電配件齊全..."
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        ></textarea>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end space-x-2">
                        <SecondaryButton @click="showReturnModal = false">取消</SecondaryButton>
                        <PrimaryButton :disabled="returnForm.processing">確認歸還並入庫</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
