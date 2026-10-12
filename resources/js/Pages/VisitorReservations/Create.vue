<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    meetingRooms: Array,
    hosts: Array,
});

const form = useForm({
    visitor_name: '',
    visitor_company: '',
    visitor_email: '',
    visitor_phone: '',
    visit_date: new Date().toISOString().split('T')[0],
    expected_arrival_time: '14:00',
    purpose: 'business',
    visitor_count: 1,
    meeting_room_id: '',
    host_id: '',
    notes: '',
});

const submit = () => {
    form.post(route('visitor-reservations.store'));
};
</script>

<template>
    <Head title="預約貴賓來訪 - 企業訪客與門禁接待" />

    <AuthenticatedLayout>
        <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- 麵包屑與標題 -->
            <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                <Link :href="route('visitor-reservations.index')" class="hover:underline">訪客接待大廳</Link>
                <span>/</span>
                <span class="text-gray-800 dark:text-gray-200 font-semibold">發起預約來訪</span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-6 sm:p-8">
                <div class="pb-6 border-b border-gray-100 dark:border-gray-700">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </span>
                        預約貴賓來訪通行
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        系統將自動產生唯一的通行代碼 (VR-Code)，貴賓抵達大廳時即可向前台快速報到！
                    </p>
                </div>

                <form @submit.prevent="submit" class="mt-6 space-y-5 text-xs">
                    <!-- 貴賓基本資料 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                貴賓姓名 <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="form.visitor_name"
                                required
                                placeholder="例：陳大明 總監"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            />
                            <p v-if="form.errors.visitor_name" class="text-rose-500 text-2xs mt-1">{{ form.errors.visitor_name }}</p>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                所屬單位 / 公司機構
                            </label>
                            <input
                                type="text"
                                v-model="form.visitor_company"
                                placeholder="例：微軟台灣分公司、合作金庫"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            />
                        </div>
                    </div>

                    <!-- 聯絡方式 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                貴賓手機電話
                            </label>
                            <input
                                type="text"
                                v-model="form.visitor_phone"
                                placeholder="例：0912-345-678"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            />
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                貴賓 Email
                            </label>
                            <input
                                type="email"
                                v-model="form.visitor_email"
                                placeholder="例：guest@example.com"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            />
                        </div>
                    </div>

                    <!-- 來訪排程 -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                來訪日期 <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                v-model="form.visit_date"
                                required
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            />
                            <p v-if="form.errors.visit_date" class="text-rose-500 text-2xs mt-1">{{ form.errors.visit_date }}</p>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                預計到達時間
                            </label>
                            <input
                                type="text"
                                v-model="form.expected_arrival_time"
                                placeholder="例：14:00 或 10:30"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            />
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                同行總人數 <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                v-model="form.visitor_count"
                                min="1"
                                max="50"
                                required
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            />
                        </div>
                    </div>

                    <!-- 事由與會議室 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                來訪事由 <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="form.purpose"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            >
                                <option value="business">商務洽談</option>
                                <option value="interview">人才面試</option>
                                <option value="audit">稽核查檢</option>
                                <option value="vendor">協力廠商 / 工程維修</option>
                                <option value="other">其他事由</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                指定接待會議室 (可選)
                            </label>
                            <select
                                v-model="form.meeting_room_id"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                            >
                                <option value="">無指定 / 大廳會客區</option>
                                <option v-for="room in meetingRooms" :key="room.id" :value="room.id">
                                    {{ room.name }} ({{ room.location }} · 容納 {{ room.capacity }} 人)
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Admin 指派接待同仁 (若有) -->
                    <div v-if="hosts && hosts.length > 0">
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                            指派接待同仁 (管理者專用)
                        </label>
                        <select
                            v-model="form.host_id"
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                        >
                            <option value="">由我自己接待 (預設)</option>
                            <option v-for="host in hosts" :key="host.id" :value="host.id">
                                {{ host.name }} ({{ host.department?.name || host.department || '未分配' }})
                            </option>
                        </select>
                    </div>

                    <!-- 接待備註 -->
                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                            接待備註與特殊需求
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="請提供車牌號碼以便保留停車位、需準備茶水點心或簡報設備..."
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-sky-500"
                        ></textarea>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3">
                        <Link
                            :href="route('visitor-reservations.index')"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        >
                            取消返回
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-md transition"
                        >
                            確認發行訪客預約
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
