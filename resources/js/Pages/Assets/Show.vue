<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    asset: Object,
    users: Array,
    departments: Array,
});

const page = usePage();
const currentUser = page.props.auth.user;
const canManage = currentUser.role === 'admin' || currentUser.role === 'manager';

const showEditModal = ref(false);
const showBorrowModal = ref(false);
const showReturnModal = ref(false);

const editForm = useForm({
    name: props.asset.name,
    category: props.asset.category,
    serial_number: props.asset.serial_number || '',
    specification: props.asset.specification || '',
    location: props.asset.location || '',
    status: props.asset.status,
    department_id: props.asset.department_id || '',
    purchase_date: props.asset.purchase_date ? props.asset.purchase_date.substring(0, 10) : '',
    purchase_cost: props.asset.purchase_cost || '',
    notes: props.asset.notes || '',
});

const borrowForm = useForm({
    borrower_id: '',
    expected_return_date: '',
    purpose: '',
});

const returnForm = useForm({
    return_notes: '',
});

const openEditModal = () => {
    editForm.name = props.asset.name;
    editForm.category = props.asset.category;
    editForm.serial_number = props.asset.serial_number || '';
    editForm.specification = props.asset.specification || '';
    editForm.location = props.asset.location || '';
    editForm.status = props.asset.status;
    editForm.department_id = props.asset.department_id || '';
    editForm.purchase_date = props.asset.purchase_date ? props.asset.purchase_date.substring(0, 10) : '';
    editForm.purchase_cost = props.asset.purchase_cost || '';
    editForm.notes = props.asset.notes || '';
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('assets.update', props.asset.id), {
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const deleteAsset = async () => {
    if (await confirmDialog(`確定要刪除資產「[${props.asset.asset_tag}] ${props.asset.name}」嗎？這將刪除所有相關紀錄且無法復原！`)) {
        router.delete(route('assets.destroy', props.asset.id));
    }
};

const openBorrowModal = () => {
    borrowForm.reset();
    borrowForm.borrower_id = '';
    showBorrowModal.value = true;
};

const submitBorrow = () => {
    borrowForm.post(route('assets.borrow', props.asset.id), {
        onSuccess: () => {
            showBorrowModal.value = false;
        },
    });
};

const openReturnModal = () => {
    returnForm.reset();
    showReturnModal.value = true;
};

const submitReturn = () => {
    returnForm.post(route('assets.return', props.asset.id), {
        onSuccess: () => {
            showReturnModal.value = false;
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
    <Head :title="`資產履歷 - [${asset.asset_tag}] ${asset.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <Link :href="route('assets.index')" class="text-xs text-teal-600 dark:text-teal-400 hover:underline flex items-center gap-1">
                            &larr; 返回資產清單
                        </Link>
                        <span class="text-xs text-gray-400">/</span>
                        <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            {{ asset.asset_tag }}
                        </span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span>{{ asset.name }}</span>
                    </h2>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="canManage"
                        @click="openEditModal"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-lg text-xs font-semibold shadow-xs transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        <span>編輯資產</span>
                    </button>
                    <button
                        v-if="asset.status === 'available'"
                        @click="openBorrowModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                    >
                        <span>登記借調</span>
                    </button>
                    <button
                        v-else-if="asset.status === 'in_use' && (canManage || asset.current_holder_id === currentUser.id)"
                        @click="openReturnModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                    >
                        <span>歸還驗收</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 px-4">
                <!-- 資產全景檔案卡片 -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- 左側主規格資訊 -->
                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <span :class="['px-2.5 py-1 text-xs font-bold rounded-full border', categoryBadge(asset.category)]">
                                    {{ categoryLabel(asset.category) }}
                                </span>
                                <span :class="['px-2.5 py-1 text-xs font-bold rounded-full border', statusBadge(asset.status)]">
                                    {{ statusLabel(asset.status) }}
                                </span>
                            </div>
                            <span class="text-xs font-mono text-gray-400 dark:text-gray-500">ID: #{{ asset.id }}</span>
                        </div>

                        <!-- 規格配置 -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-gray-400">規格配備描述</h4>
                            <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed bg-gray-50 dark:bg-gray-900/40 p-3.5 rounded-lg border border-gray-100 dark:border-gray-700/60">
                                {{ asset.specification || '無特殊規格描述，標準公司公務設備。' }}
                            </p>
                        </div>

                        <!-- 詳細屬性表 -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-2 text-xs">
                            <div class="bg-gray-50/60 dark:bg-gray-900/30 p-3 rounded-lg border border-gray-100 dark:border-gray-700/50">
                                <span class="text-gray-400 block mb-0.5">原廠序號 (S/N)</span>
                                <span class="font-mono font-bold text-gray-800 dark:text-gray-200">{{ asset.serial_number || '未登記' }}</span>
                            </div>
                            <div class="bg-gray-50/60 dark:bg-gray-900/30 p-3 rounded-lg border border-gray-100 dark:border-gray-700/50">
                                <span class="text-gray-400 block mb-0.5">存放位置</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ asset.location || '總部庫存' }}</span>
                            </div>
                            <div class="bg-gray-50/60 dark:bg-gray-900/30 p-3 rounded-lg border border-gray-100 dark:border-gray-700/50">
                                <span class="text-gray-400 block mb-0.5">歸屬部門</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ asset.department?.name || '公司全體公用' }}</span>
                            </div>
                            <div class="bg-gray-50/60 dark:bg-gray-900/30 p-3 rounded-lg border border-gray-100 dark:border-gray-700/50">
                                <span class="text-gray-400 block mb-0.5">採購日期</span>
                                <span class="font-mono font-bold text-gray-800 dark:text-gray-200">{{ asset.purchase_date ? asset.purchase_date.substring(0, 10) : '未登記' }}</span>
                            </div>
                            <div class="bg-gray-50/60 dark:bg-gray-900/30 p-3 rounded-lg border border-gray-100 dark:border-gray-700/50">
                                <span class="text-gray-400 block mb-0.5">採購金額</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ asset.purchase_cost ? `NT$ ${Number(asset.purchase_cost).toLocaleString()}` : '未標註' }}</span>
                            </div>
                            <div class="bg-gray-50/60 dark:bg-gray-900/30 p-3 rounded-lg border border-gray-100 dark:border-gray-700/50">
                                <span class="text-gray-400 block mb-0.5">歷史累計借出</span>
                                <span class="font-bold text-teal-600 dark:text-teal-400">{{ asset.loans?.length || 0 }} 次</span>
                            </div>
                        </div>

                        <!-- 備註說明 -->
                        <div v-if="asset.notes" class="pt-2">
                            <h4 class="text-xs font-bold text-gray-400 mb-1">備註說明</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ asset.notes }}</p>
                        </div>
                    </div>

                    <!-- 右側在席/保管人狀態卡片 -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 pb-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                <span>目前保管在席狀態</span>
                                <span :class="['w-2 h-2 rounded-full', asset.status === 'available' ? 'bg-emerald-500' : 'bg-indigo-500']"></span>
                            </h3>

                            <div v-if="asset.current_holder" class="mt-4 space-y-4">
                                <div class="flex items-center space-x-3 p-3.5 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl border border-indigo-100 dark:border-indigo-900/40">
                                    <span class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
                                        {{ asset.current_holder.name.charAt(0) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ asset.current_holder.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ asset.current_holder.job_title || asset.current_holder.email }}</p>
                                    </div>
                                </div>

                                <div v-if="asset.active_loan" class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-400">借出時間：</span>
                                        <span class="font-mono">{{ new Date(asset.active_loan.borrowed_at).toLocaleDateString('sv-SE') }}</span>
                                    </div>
                                    <div v-if="asset.active_loan.expected_return_date" class="flex items-center justify-between">
                                        <span class="text-gray-400">預計歸還：</span>
                                        <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ asset.active_loan.expected_return_date.substring(0, 10) }}</span>
                                    </div>
                                    <div v-if="asset.active_loan.purpose" class="pt-2 border-t border-gray-100 dark:border-gray-700/60">
                                        <span class="text-gray-400 block mb-0.5">借用用途：</span>
                                        <p class="text-gray-700 dark:text-gray-300 italic">{{ asset.active_loan.purpose }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="mt-6 py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                                <svg class="w-8 h-8 text-emerald-500 mx-auto mb-2 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                目前在庫無人保管，可直接登記借調
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 借調歷史履歷時間軸 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 bg-teal-600 rounded-full"></span>
                            <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">借調履歷與盤點時間軸 (Loan History)</h3>
                        </div>
                        <span class="text-xs text-gray-400 font-mono">共 {{ asset.loans?.length || 0 }} 筆借還紀錄</span>
                    </div>

                    <div v-if="asset.loans && asset.loans.length > 0" class="mt-6 space-y-4">
                        <div
                            v-for="loan in asset.loans"
                            :key="loan.id"
                            class="p-4 rounded-xl border border-gray-100 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/30 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs"
                        >
                            <div class="space-y-1.5 flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100">
                                        {{ loan.user?.name }}
                                    </span>
                                    <span :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-bold border',
                                        loan.status === 'borrowed'
                                            ? 'bg-indigo-100 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
                                            : 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                                    ]">
                                        {{ loan.status === 'borrowed' ? '使用借出中' : '已歸還入庫' }}
                                    </span>
                                </div>
                                <p class="text-gray-600 dark:text-gray-400">
                                    <span class="text-gray-400">用途：</span>{{ loan.purpose || '未特別備註' }}
                                </p>
                                <p v-if="loan.return_notes" class="text-gray-500 dark:text-gray-400 text-[11px]">
                                    <span class="text-gray-400">歸還驗收備註：</span>{{ loan.return_notes }}
                                </p>
                            </div>

                            <div class="text-right text-[11px] text-gray-400 space-y-0.5 shrink-0">
                                <div>借出：<span class="font-mono text-gray-600 dark:text-gray-300">{{ new Date(loan.borrowed_at).toLocaleDateString('sv-SE') }}</span></div>
                                <div v-if="loan.returned_at">
                                    歸還：<span class="font-mono text-emerald-600 dark:text-emerald-400">{{ new Date(loan.returned_at).toLocaleDateString('sv-SE') }}</span>
                                </div>
                                <div v-else-if="loan.expected_return_date">
                                    預計：<span class="font-mono text-indigo-600 dark:text-indigo-400">{{ loan.expected_return_date.substring(0, 10) }}</span>
                                </div>
                                <div v-if="loan.handler" class="text-gray-400 pt-0.5">經手人：{{ loan.handler.name }}</div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-xs text-gray-400 dark:text-gray-500">
                        目前尚無借調履歷紀錄
                    </div>
                </div>
            </div>
        </div>

        <!-- 編輯資產 Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="lg">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">編輯公務資產設定</h3>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="edit_status" value="資產狀態 *" />
                            <select id="edit_status" v-model="editForm.status" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="available">在庫可借</option>
                                <option value="in_use">使用中</option>
                                <option value="maintenance">維護保養中</option>
                                <option value="retired">報廢除役</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="edit_category" value="資產分類 *" />
                            <select id="edit_category" v-model="editForm.category" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="it_hardware">IT 資訊硬體</option>
                                <option value="office_equipment">辦公 OA 設備</option>
                                <option value="av_media">視聽攝影器材</option>
                                <option value="vehicles">公務車輛</option>
                                <option value="peripherals">週邊配件耗材</option>
                                <option value="other">其他資產</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="edit_name" value="資產名稱/型號 *" />
                        <TextInput id="edit_name" v-model="editForm.name" type="text" class="mt-1 w-full text-xs" required />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="edit_serial_number" value="原廠序號 (S/N)" />
                            <TextInput id="edit_serial_number" v-model="editForm.serial_number" type="text" class="mt-1 w-full text-xs font-mono" />
                        </div>
                        <div>
                            <InputLabel for="edit_location" value="存放地點" />
                            <TextInput id="edit_location" v-model="editForm.location" type="text" class="mt-1 w-full text-xs" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="edit_specification" value="規格配置說明" />
                        <textarea
                            id="edit_specification"
                            v-model="editForm.specification"
                            rows="2"
                            class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="edit_department_id" value="歸屬部門" />
                            <select id="edit_department_id" v-model="editForm.department_id" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="">-- 全公司公用 --</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="edit_purchase_date" value="採購日期" />
                            <TextInput id="edit_purchase_date" v-model="editForm.purchase_date" type="date" class="mt-1 w-full text-xs" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <button
                            v-if="canManage && asset.status !== 'in_use'"
                            type="button"
                            @click="deleteAsset"
                            class="text-xs text-rose-600 hover:text-rose-700 dark:text-rose-400 font-semibold hover:underline"
                        >
                            刪除此資產
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

        <!-- 借調登記 Modal -->
        <Modal :show="showBorrowModal" @close="showBorrowModal = false" maxWidth="md">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">公務資產借調登記</h3>
                    <button @click="showBorrowModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                </div>

                <form @submit.prevent="submitBorrow" class="mt-4 space-y-4">
                    <div v-if="canManage">
                        <InputLabel for="show_borrower_id" value="借用同仁 (管理員可代為登記)" />
                        <select id="show_borrower_id" v-model="borrowForm.borrower_id" class="mt-1 w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            <option value="">-- 由登入者本人領用 --</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.job_title || u.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="show_expected_return_date" value="預計歸還日期" />
                        <TextInput id="show_expected_return_date" v-model="borrowForm.expected_return_date" type="date" class="mt-1 w-full text-xs" />
                    </div>

                    <div>
                        <InputLabel for="show_purpose" value="借用事由與專案用途 *" />
                        <textarea
                            id="show_purpose"
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

                <form @submit.prevent="submitReturn" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="show_return_notes" value="歸還機況與驗收備註 (選填)" />
                        <textarea
                            id="show_return_notes"
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
