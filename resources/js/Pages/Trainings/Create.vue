<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const form = useForm({
    title: '',
    description: '',
    instructor: '',
    instructor_title: '',
    category: 'technical',
    location: '總部 3F 階梯國際培訓廳 (或 Google Meet 線上研討)',
    start_time: '',
    end_time: '',
    max_participants: 30,
    credits: 2,
    hours: 2.0,
    status: 'upcoming',
});

const submit = () => {
    form.post(route('trainings.store'));
};
</script>

<template>
    <Head title="開辦企業內訓課程" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('trainings.index')"
                    class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        🎓 開設新企業內訓課程
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        規劃內部研討或外部專家名師講座，發布後系統將公告於學習中心並開放同仁報名。
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 sm:p-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- 基本課程資訊 -->
                    <div class="space-y-4">
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                            <span>📌 課程主軸與講師經歷</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">課程主題名稱 *</label>
                                <input
                                    type="text"
                                    v-model="form.title"
                                    required
                                    placeholder="例如：生成式 AI 企業實務落地與 Prompt Engineering 工作坊"
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white"
                                />
                                <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">領域類別 *</label>
                                <select
                                    v-model="form.category"
                                    required
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white"
                                >
                                    <option value="technical">專業技術與研發</option>
                                    <option value="management">主管領導與管理</option>
                                    <option value="compliance">法規遵循與資安</option>
                                    <option value="general">職場通識與軟實力</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">主講講師姓名 *</label>
                                <input
                                    type="text"
                                    v-model="form.instructor"
                                    required
                                    placeholder="例如：張研華 博士 或 李資深架構師"
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white"
                                />
                                <p v-if="form.errors.instructor" class="text-xs text-red-500 mt-1">{{ form.errors.instructor }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">講師單位與專業職稱</label>
                                <input
                                    type="text"
                                    v-model="form.instructor_title"
                                    placeholder="例如：技術研發處首席顧問 / 外部知名客座名師"
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">課程大綱與核心學習目標</label>
                            <textarea
                                v-model="form.description"
                                rows="4"
                                placeholder="請條列本課程之預期產出、課程綱要與適用對象..."
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white"
                            ></textarea>
                        </div>
                    </div>

                    <!-- 開課時段與研習地點 -->
                    <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                            <span>🕒 開課時程與名額學分配置</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">研習開始時間 *</label>
                                <input
                                    type="datetime-local"
                                    v-model="form.start_time"
                                    required
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white font-mono"
                                />
                                <p v-if="form.errors.start_time" class="text-xs text-red-500 mt-1">{{ form.errors.start_time }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">研習結束時間 *</label>
                                <input
                                    type="datetime-local"
                                    v-model="form.end_time"
                                    required
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white font-mono"
                                />
                                <p v-if="form.errors.end_time" class="text-xs text-red-500 mt-1">{{ form.errors.end_time }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">實體研習地點 / 線上會議連結 *</label>
                            <input
                                type="text"
                                v-model="form.location"
                                required
                                placeholder="例如：總部 3F 階梯國際會議廳、或 https://meet.google.com/xxx-xxxx-xxx"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">名額上限 (人) *</label>
                                <input
                                    type="number"
                                    v-model="form.max_participants"
                                    min="0"
                                    required
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white font-mono"
                                />
                                <p class="text-2xs text-gray-400 mt-1">填 0 代表不限名額</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">認證學分 (點) *</label>
                                <input
                                    type="number"
                                    v-model="form.credits"
                                    min="1"
                                    required
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white font-mono"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">認證時數 (小時) *</label>
                                <input
                                    type="number"
                                    step="0.5"
                                    v-model="form.hours"
                                    min="0.5"
                                    required
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white font-mono"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">發布狀態 *</label>
                                <select
                                    v-model="form.status"
                                    required
                                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white"
                                >
                                    <option value="upcoming">熱烈報名中</option>
                                    <option value="draft">儲存為草稿</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 操作按鈕列 -->
                    <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-700">
                        <Link
                            :href="route('trainings.index')"
                            class="px-5 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750 text-sm font-semibold rounded-lg transition"
                        >
                            取消返回
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg shadow-sm transition disabled:opacity-50"
                        >
                            {{ form.processing ? '開辦建立中...' : '確認開辦並建立課程' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
