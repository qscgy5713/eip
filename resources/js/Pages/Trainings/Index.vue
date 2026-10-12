<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    courses: Object,
    metrics: Object,
    filters: Object,
    canManageAll: Boolean,
});

const search = ref(props.filters.search || '');
const category = ref(props.filters.category || '');
const status = ref(props.filters.status || '');
const currentTab = ref(props.filters.tab || 'all');

const applyFilter = () => {
    router.get(
        route('trainings.index'),
        {
            search: search.value,
            category: category.value,
            status: status.value,
            tab: currentTab.value,
        },
        { preserveState: true, replace: true }
    );
};

const switchTab = (tab) => {
    currentTab.value = tab;
    applyFilter();
};

const resetFilter = () => {
    search.value = '';
    category.value = '';
    status.value = '';
    applyFilter();
};
</script>

<template>
    <Head title="企業內訓與員工學習學分中心" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                         企業內訓課程與員工學習學分系統
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        賦能同仁持續精進成長，研習專業技術、領導管理與法規通識，打造高學習力企業組織。
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="route('trainings.export', { category })"
                        class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg transition inline-flex items-center gap-1.5 shadow-sm"
                    >
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        匯出研習報表 (CSV)
                    </a>

                    <Link
                        v-if="canManageAll"
                        :href="route('trainings.create')"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm transition inline-flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        開設內訓新課程
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- 4 大學習指標卡 -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">開放報名課程</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-0.5">
                            {{ metrics.upcoming_courses }}
                        </p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">同仁參訓總人次</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-0.5">
                            {{ metrics.total_participants }}
                        </p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">我已獲認證學分</p>
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5 font-mono">
                            {{ metrics.my_credits }} <span class="text-xs font-normal text-gray-500 dark:text-gray-400">學分</span>
                        </p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">我累計研習時數</p>
                        <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5 font-mono">
                            {{ metrics.my_hours }} <span class="text-xs font-normal text-gray-500 dark:text-gray-400">小時</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tab 切換 & 搜尋過濾器 -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-100 dark:border-gray-700 pb-3">
                    <div class="flex space-x-2">
                        <button
                            @click="switchTab('all')"
                            :class="[
                                'px-4 py-2 rounded-lg text-xs font-bold transition',
                                currentTab === 'all'
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                            ]"
                        >
                            全體企業內訓課程
                        </button>
                        <button
                            @click="switchTab('mine')"
                            :class="[
                                'px-4 py-2 rounded-lg text-xs font-bold transition',
                                currentTab === 'mine'
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                            ]"
                        >
                            我的研習與報名歷程
                        </button>
                    </div>

                    <div class="text-xs text-gray-400">
                        共 {{ courses.total }} 門內訓課程
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <input
                            type="text"
                            v-model="search"
                            @keyup.enter="applyFilter"
                            placeholder="搜尋課程代碼、名稱、講師、地點..."
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-gray-200"
                        />
                    </div>

                    <div>
                        <select
                            v-model="category"
                            @change="applyFilter"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-gray-200"
                        >
                            <option value="">全部領域分類</option>
                            <option value="technical">專業技術與研發</option>
                            <option value="management">主管領導與管理</option>
                            <option value="compliance">法規遵循與資安</option>
                            <option value="general">職場通識與軟實力</option>
                        </select>
                    </div>

                    <div>
                        <select
                            v-model="status"
                            @change="applyFilter"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-gray-200"
                        >
                            <option value="">全部開課狀態</option>
                            <option value="upcoming">熱烈報名中</option>
                            <option value="in_progress">研習進行中</option>
                            <option value="completed">已完訓結案</option>
                            <option value="draft">草稿</option>
                            <option value="cancelled">已取消</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="applyFilter"
                            class="px-4 py-2 bg-gray-900 hover:bg-black dark:bg-gray-700 dark:hover:bg-gray-600 text-white text-xs font-semibold rounded-lg transition"
                        >
                            篩選
                        </button>
                        <button
                            @click="resetFilter"
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-xs rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        >
                            重設
                        </button>
                    </div>
                </div>
            </div>

            <!-- 課程卡片矩陣 -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="course in courses.data"
                    :key="course.id"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition flex flex-col justify-between overflow-hidden group"
                >
                    <div class="p-5 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <span class="px-2 py-0.5 font-mono text-[11px] font-bold rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                {{ course.course_code }}
                            </span>
                            <span :class="['px-2 py-0.5 text-2xs font-semibold rounded-full', course.status_color]">
                                {{ course.status_label }}
                            </span>
                        </div>

                        <div>
                            <span class="text-2xs text-gray-400 font-medium">{{ course.category_label }}</span>
                            <h3 class="font-bold text-base text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition mt-0.5 line-clamp-1">
                                {{ course.title }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 mt-1">
                                {{ course.description || '講師與人資精選規畫之內部訓練課程。' }}
                            </p>
                        </div>

                        <!-- 講師與開課資訊 -->
                        <div class="space-y-1.5 pt-2 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400">主講：</span>
                                <span class="font-medium text-gray-900 dark:text-gray-200">{{ course.instructor }}</span>
                                <span v-if="course.instructor_title" class="text-2xs text-gray-400">({{ course.instructor_title }})</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400">時間：</span>
                                <span>{{ new Date(course.start_time).toLocaleString('zh-TW', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400">地點：</span>
                                <span class="truncate">{{ course.location }}</span>
                            </div>
                        </div>

                        <!-- 名額與額滿進度條 -->
                        <div class="pt-2">
                            <div class="flex justify-between text-2xs text-gray-500 mb-1 dark:text-gray-400">
                                <span>名額進度</span>
                                <span class="font-mono">
                                    {{ course.registered_count }} / {{ course.max_participants > 0 ? course.max_participants : '不限' }} 人
                                    <span v-if="course.waitlist_count > 0" class="text-amber-500">(候補 {{ course.waitlist_count }})</span>
                                </span>
                            </div>
                            <div v-if="course.max_participants > 0" class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                <div
                                    :class="[
                                        'h-1.5 rounded-full transition-all',
                                        course.is_full ? 'bg-amber-500' : 'bg-indigo-600'
                                    ]"
                                    :style="{ width: `${Math.min(100, (course.registered_count / course.max_participants) * 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- 底部行動列 -->
                    <div class="p-4 bg-gray-50/70 dark:bg-gray-750 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-2xs font-bold bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300">
                                 {{ course.credits }} 學分
                            </span>
                            <span class="text-2xs text-gray-400 font-mono">
                                {{ course.hours }} 小時
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- 個人報名狀態 Badge -->
                            <span
                                v-if="course.my_registration_status === 'registered'"
                                class="px-2.5 py-1 text-2xs font-bold rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                            >
                                ✓ 已報名正取
                            </span>
                            <span
                                v-else-if="course.my_registration_status === 'waitlist'"
                                class="px-2.5 py-1 text-2xs font-bold rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                 候補名單中
                            </span>
                            <span
                                v-else-if="course.my_registration_status === 'attended'"
                                class="px-2.5 py-1 text-2xs font-bold rounded-lg bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300"
                            >
                                 完訓合格
                            </span>

                            <Link
                                :href="route('trainings.show', course.id)"
                                class="px-3 py-1.5 bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-gray-600 text-indigo-600 dark:text-indigo-400 text-xs font-semibold rounded-lg border border-indigo-200 dark:border-indigo-800 shadow-xs transition"
                            >
                                查看詳情 &rarr;
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 空狀態 -->
            <div v-if="courses.data.length === 0" class="p-12 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700">
                <div class="w-16 h-16 mx-auto mb-4 bg-indigo-50 dark:bg-indigo-950/40 rounded-full flex items-center justify-center text-3xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3 class="text-base font-bold text-gray-800 dark:text-gray-200">查無符合條件的內訓課程</h3>
                <p class="text-xs text-gray-400 mt-1">您可以更換領域分類或搜尋字詞，或聯絡人資學習發展小組諮詢。</p>
            </div>

            <!-- 分頁列 -->
            <div v-if="courses.links && courses.links.length > 3" class="flex justify-center pt-4">
                <div class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, k) in courses.links"
                        :key="k"
                        :href="link.url || '#'"
                        :class="[
                            'px-3 py-1.5 text-xs rounded-lg border',
                            link.active
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : link.url
                                ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
