<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    availableForms: Array,
    myRequests: Object,
    pendingApprovals: Array,
});

const statusBadge = (status) => {
    switch (status) {
        case 'approved': return 'bg-emerald-100 text-emerald-800';
        case 'rejected': return 'bg-rose-100 text-rose-800';
        default: return 'bg-amber-100 text-amber-800';
    }
};
</script>

<template>
    <Head title="表單與簽核中心" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-gray-800">表單與線上簽核</h2>
                <span class="text-sm text-gray-500">標準化行政工作流與透明審批歷程</span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                <!-- 可發起表單區塊 -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>發起新申請單</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div
                            v-for="form in availableForms"
                            :key="form.id"
                            class="bg-white p-6 rounded-xl border border-gray-200/80 shadow-sm hover:shadow-md hover:border-blue-300 transition flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-base font-bold text-gray-900">{{ form.name }}</h4>
                                    <span class="px-2 py-0.5 text-xs font-mono font-semibold bg-gray-100 text-gray-600 rounded">{{ form.code }}</span>
                                </div>
                                <p class="text-sm text-gray-500 mb-4">{{ form.description }}</p>
                            </div>
                            <Link
                                :href="route('forms.create', form.id)"
                                class="w-full text-center py-2 px-4 bg-blue-50 text-blue-600 font-semibold text-sm rounded-lg hover:bg-blue-600 hover:text-white transition"
                            >
                                立即填寫申請
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 待我審核單據 (若有) -->
                <div v-if="pendingApprovals.length > 0" class="bg-amber-50/70 border border-amber-200 rounded-xl p-6">
                    <h3 class="text-base font-bold text-amber-900 mb-3 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>待您審批的單據 ({{ pendingApprovals.length }} 件)</span>
                    </h3>
                    <div class="divide-y divide-amber-100 bg-white rounded-lg border border-amber-200 overflow-hidden">
                        <div v-for="item in pendingApprovals" :key="item.id" class="p-4 flex items-center justify-between">
                            <div>
                                <p class="font-bold text-sm text-gray-900">{{ item.form_request?.title }}</p>
                                <p class="text-xs text-gray-500">申請人：{{ item.form_request?.user?.name }} · 單號：{{ item.form_request?.request_no }}</p>
                            </div>
                            <Link :href="route('forms.show', item.form_request_id)" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-sm">
                                進入審核
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 我的申請歷史清單 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">我的申請紀錄</h3>
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="req in myRequests.data"
                            :key="req.id"
                            class="py-4 first:pt-0 last:pb-0 flex items-center justify-between gap-4"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs font-mono font-medium text-gray-400">[{{ req.request_no }}]</span>
                                    <Link :href="route('forms.show', req.id)" class="font-bold text-gray-900 hover:text-blue-600">
                                        {{ req.title }}
                                    </Link>
                                </div>
                                <p class="text-xs text-gray-400">申請類別：{{ req.form?.name }} · 申請時間：{{ new Date(req.created_at).toLocaleString() }}</p>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span :class="['px-3 py-1 text-xs font-semibold rounded-full', statusBadge(req.status)]">
                                    {{ req.status === 'approved' ? '已核准' : (req.status === 'rejected' ? '已駁回' : '審批中') }}
                                </span>
                                <Link :href="route('forms.show', req.id)" class="text-xs font-medium text-blue-600 hover:underline">
                                    查看詳情 &rarr;
                                </Link>
                            </div>
                        </div>
                        <div v-if="myRequests.data.length === 0" class="py-8 text-center text-gray-400">尚無申請紀錄</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
