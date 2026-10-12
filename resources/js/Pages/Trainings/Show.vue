<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    course: Object,
    myRegistration: Object,
    canManage: Boolean,
});

// 課後滿意度回饋 Modal
const showFeedbackModal = ref(false);
const feedbackForm = useForm({
    rating: props.myRegistration?.rating || 5,
    feedback: props.myRegistration?.feedback || '',
});

// 變更課程狀態 Form
const statusForm = useForm({
    status: props.course.status,
});

const registerCourse = async () => {
    if (await confirmDialog(props.course.is_full ? '此課程正取名額已額滿，確認要加入【候補名單】嗎？若有名額釋出將自動遞補。' : '確定要報名此門企業內訓課程嗎？')) {
        useForm({}).post(route('trainings.register', props.course.id));
    }
};

const cancelRegistration = async () => {
    if (await confirmDialog('確定要取消此課程的報名嗎？您的名額將釋出供候補同仁遞補。')) {
        useForm({}).post(route('trainings.cancel', props.course.id));
    }
};

const checkInParticipant = async (registrationId) => {
    if (await confirmDialog('確定要為此位同仁簽到出席並正式核發研習學分與時數嗎？')) {
        useForm({}).post(route('trainings.check-in', [props.course.id, registrationId]));
    }
};

const submitFeedback = () => {
    feedbackForm.post(route('trainings.feedback', props.course.id), {
        onSuccess: () => {
            showFeedbackModal.value = false;
        },
    });
};

const updateCourseStatus = async (newStatus) => {
    if (await confirmDialog(`確定要將課程狀態更新為【${newStatus}】嗎？`)) {
        statusForm.status = newStatus;
        statusForm.patch(route('trainings.status', props.course.id));
    }
};
</script>

<template>
    <Head :title="`課程詳情 - ${course.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('trainings.index')"
                        class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition dark:text-gray-400"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 font-mono text-xs font-bold rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                {{ course.course_code }}
                            </span>
                            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                                {{ course.title }}
                            </h2>
                            <span :class="['px-2.5 py-0.5 text-xs font-semibold rounded-full', course.status_color]">
                                {{ course.status_label }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            領域：{{ course.category_label }} · 主辦：{{ course.creator?.name }} · 建立於 {{ new Date(course.created_at).toLocaleDateString('sv-SE') }}
                        </p>
                    </div>
                </div>

                <!-- 報名與行動按鈕 -->
                <div class="flex items-center gap-2">
                    <template v-if="!myRegistration || myRegistration.status === 'cancelled'">
                        <button
                            v-if="course.status === 'upcoming'"
                            @click="registerCourse"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm transition inline-flex items-center gap-1.5"
                        >
                            <span>{{ course.is_full ? ' 額滿登記候補' : ' 線上報名研習' }}</span>
                        </button>
                    </template>

                    <template v-else-if="myRegistration.status === 'registered' || myRegistration.status === 'waitlist'">
                        <button
                            @click="cancelRegistration"
                            class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-xs font-semibold rounded-lg transition"
                        >
                            取消報名
                        </button>
                    </template>

                    <template v-else-if="myRegistration.status === 'attended'">
                        <button
                            @click="showFeedbackModal = true"
                            class="px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:hover:bg-emerald-900/50 text-xs font-bold rounded-lg transition inline-flex items-center gap-1"
                        >
                             {{ myRegistration.rating ? '修改課後回饋' : '填寫課後回饋與評分' }}
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- 我的報名狀態提示橫幅 -->
            <div
                v-if="myRegistration && myRegistration.status !== 'cancelled'"
                :class="[
                    'p-4 rounded-xl border flex items-center justify-between',
                    myRegistration.status === 'attended'
                        ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 text-indigo-900 dark:text-indigo-200'
                        : myRegistration.status === 'waitlist'
                        ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 text-amber-900 dark:text-amber-200'
                        : 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 text-emerald-900 dark:text-emerald-200'
                ]"
            >
                <div class="flex items-center gap-3">
                    <span class="text-indigo-600 dark:text-indigo-400"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg></span>
                    <div>
                        <h4 class="font-bold text-sm">
                            {{ myRegistration.status_label }}
                        </h4>
                        <p class="text-xs opacity-90 mt-0.5">
                            <span v-if="myRegistration.status === 'attended'">
                                恭喜您已圓滿結訓！已成功認證入帳 {{ course.credits }} 學分與 {{ course.hours }} 小時研習時數。
                            </span>
                            <span v-else-if="myRegistration.status === 'waitlist'">
                                您目前位於候補名單中。當有正取同仁釋出名額時，系統將自動依登記順序為您遞補並發送通知。
                            </span>
                            <span v-else>
                                您已完成研習正取登記，請準時出席。開課當日將由主辦單位進行簽到與點名。
                            </span>
                        </p>
                    </div>
                </div>

                <div v-if="myRegistration.status === 'attended' && myRegistration.rating" class="text-xs font-semibold text-amber-600 bg-white/80 dark:bg-gray-800/80 px-3 py-1 rounded-lg">
                    已評分：{{ '★'.repeat(myRegistration.rating) }}
                </div>
            </div>

            <!-- 主管/人資/開課者 管理控制台 -->
            <div v-if="canManage" class="bg-gradient-to-r from-indigo-500/10 via-indigo-500/10 to-indigo-500/10 dark:from-indigo-900/30 dark:to-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-xl p-5 shadow-sm">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-indigo-950 dark:text-indigo-200 flex items-center gap-2">
                            <span>講師與人資訓練控制台</span>
                        </h3>
                        <p class="text-xs text-indigo-700 dark:text-indigo-400 mt-1">
                            您擁有管理本課程、審核出勤、點名核發研習學分與調整開課狀態之完整權限。
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="course.status === 'upcoming'"
                            @click="updateCourseStatus('in_progress')"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-lg transition"
                        >
                            標記為研習進行中
                        </button>
                        <button
                            v-if="course.status === 'in_progress' || course.status === 'upcoming'"
                            @click="updateCourseStatus('completed')"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-lg transition"
                        >
                            完訓結案
                        </button>
                        <button
                            v-if="course.status !== 'cancelled' && course.status !== 'completed'"
                            @click="updateCourseStatus('cancelled')"
                            class="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white font-medium text-xs rounded-lg transition"
                        >
                            停辦課程
                        </button>
                    </div>
                </div>
            </div>

            <!-- 兩欄格局：左側課程內容、右側講師與時間學分資訊 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- 左側：課程詳細說明與學員名單 (佔 2 欄) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- 課程大綱卡片 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 space-y-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-100 dark:border-gray-700">
                             課程綱要與預期學習成效
                        </h3>
                        <div class="prose dark:prose-invert max-w-none text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">
                            {{ course.description || '講師與學習發展小組精心設計之專業課程。' }}
                        </div>
                    </div>

                    <!-- 學員名冊與簽到點名清單 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                     參訓學員名冊與簽到狀況
                                </h3>
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                    正取 {{ course.registered_count }} / 候補 {{ course.waitlist_count }} 人
                                </span>
                            </div>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div
                                v-for="reg in course.registrations"
                                :key="reg.id"
                                class="py-3 flex items-center justify-between gap-4"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 flex items-center justify-center font-bold text-xs">
                                        {{ reg.user?.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                                                {{ reg.user?.name }}
                                            </p>
                                            <span class="text-2xs text-gray-400">
                                                ({{ reg.user?.department?.name || '總部' }})
                                            </span>
                                            <span :class="['px-2 py-0.5 text-2xs font-semibold rounded-full', reg.status_color]">
                                                {{ reg.status_label }}
                                            </span>
                                        </div>
                                        <p class="text-2xs text-gray-400 mt-0.5">
                                            登記於 {{ new Date(reg.registered_at).toLocaleString('zh-TW', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                            <span v-if="reg.attended_at" class="text-indigo-500">
                                                · 完訓核發於 {{ new Date(reg.attended_at).toLocaleTimeString() }}
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- 完訓評語提示 -->
                                    <span v-if="reg.rating" class="text-xs text-amber-500 font-bold">
                                        {{ '★'.repeat(reg.rating) }}
                                    </span>

                                    <!-- 管理員/講師點名按鈕 -->
                                    <button
                                        v-if="canManage && reg.status === 'registered'"
                                        @click="checkInParticipant(reg.id)"
                                        class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-xs transition"
                                    >
                                        簽到並核發學分
                                    </button>
                                </div>
                            </div>

                            <div v-if="!course.registrations || course.registrations.length === 0" class="py-8 text-center text-xs text-gray-400">
                                目前尚無同仁報名此門課程。
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右側：主講專家、開課時間、時數認證與學分 (佔 1 欄) -->
                <div class="space-y-6">
                    <!-- 主講導師簡介 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-indigo-100 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-gray-100 text-base">
                                    {{ course.instructor }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ course.instructor_title || '專業研發與訓練講座講師' }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2.5 pt-3 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex justify-between">
                                <span>領域範疇：</span>
                                <span class="font-medium text-gray-900 dark:text-gray-200">{{ course.category_label }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>認證學分：</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400 font-mono">{{ course.credits }} 學分</span>
                            </div>
                            <div class="flex justify-between">
                                <span>研習時數：</span>
                                <span class="font-bold text-gray-900 dark:text-gray-200 font-mono">{{ course.hours }} 小時</span>
                            </div>
                        </div>
                    </div>

                    <!-- 研習時間與地點 -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 space-y-3">
                        <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100">
                             研習時程與上課地點
                        </h4>

                        <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                            <div>
                                <span class="text-gray-400 block mb-0.5">起訖時間：</span>
                                <p class="font-medium text-gray-900 dark:text-gray-200 font-mono">
                                    {{ new Date(course.start_time).toLocaleString() }}
                                    <br>至 {{ new Date(course.end_time).toLocaleString() }}
                                </p>
                            </div>
                            <div class="pt-2">
                                <span class="text-gray-400 block mb-0.5">研習地點 / 視訊：</span>
                                <p class="font-medium text-gray-900 dark:text-gray-200">
                                    {{ course.location }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 完訓滿意度與課後回饋 Modal -->
        <div v-if="showFeedbackModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">課後滿意度與心得回饋</h3>
                <p class="text-xs text-gray-500 mb-4 dark:text-gray-400">您的寶貴意見將協助講師與人資精進未來的課程設計與教學品質。</p>

                <form @submit.prevent="submitFeedback" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">整體滿意度評分 *</label>
                        <div class="flex items-center gap-2">
                            <button
                                v-for="star in 5"
                                :key="star"
                                type="button"
                                @click="feedbackForm.rating = star"
                                class="text-2xl transition hover:scale-110"
                            >
                                <span :class="star <= feedbackForm.rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'">★</span>
                            </button>
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300 ml-2">
                                {{ feedbackForm.rating }} 星
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">課後心得與具體建議</label>
                        <textarea
                            v-model="feedbackForm.feedback"
                            rows="4"
                            placeholder="這門課程對我的日常專案工作非常有啟發、講師案例實用..."
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="showFeedbackModal = false"
                            class="px-4 py-2 border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300 rounded-lg text-sm"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="feedbackForm.processing"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold"
                        >
                            送出回饋
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
