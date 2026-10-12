<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    weekInfo: Object,
    completedTasks: Array,
});

const form = useForm({
    year: props.weekInfo.year,
    week_number: props.weekInfo.week_number,
    start_date: props.weekInfo.start_date,
    end_date: props.weekInfo.end_date,
    summary: '',
    achievements: '',
    plans_next_week: '',
    challenges_or_support: '',
    is_draft: false,
});

// 一鍵帶入看板完成之任務
const importCompletedTasks = () => {
    if (!props.completedTasks || props.completedTasks.length === 0) return;

    const taskLines = props.completedTasks.map((t, index) => {
        const proj = t.project ? `[${t.project.code}] ` : '';
        const hours = t.actual_hours ? ` (${t.actual_hours}h)` : '';
        return `${index + 1}. ${proj}${t.title}${hours}`;
    }).join('\n');

    if (form.achievements.trim()) {
        form.achievements += '\n\n【本週專案看板完成任務】：\n' + taskLines;
    } else {
        form.achievements = '【本週專案看板完成任務】：\n' + taskLines;
    }
};

const submitReport = (asDraft = false) => {
    form.is_draft = asDraft;
    form.post(route('weekly-reports.store'));
};
</script>

<template>
    <Head :title="`撰寫工作週報 - ${weekInfo.year} 年第 ${weekInfo.week_number} 週`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>撰寫工作週報</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-cyan-100 text-cyan-800 dark:bg-cyan-900/50 dark:text-cyan-300">
                            {{ weekInfo.year }} 年 第 {{ weekInfo.week_number }} 週
                        </span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        週期時間：{{ weekInfo.start_date }} ~ {{ weekInfo.end_date }}
                    </p>
                </div>
                <Link
                    :href="route('weekly-reports.index')"
                    class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                >
                    &larr; 返回列表
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 智慧任務帶入提示面板 -->
                <div v-if="completedTasks && completedTasks.length > 0" class="p-4 bg-indigo-50/80 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                    <div>
                        <p class="text-xs font-bold text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            智慧連鎖：系統偵測到您在專案看板本週共有 {{ completedTasks.length }} 項已完成任務！
                        </p>
                        <p class="text-[11px] text-indigo-700 dark:text-indigo-400 mt-0.5">
                            可點擊一鍵直接將任務名稱、所屬專案與實際工時自動注入到「具體完成事項」欄位。
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="importCompletedTasks"
                        class="px-3.5 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-xs transition shrink-0"
                    >
                         一鍵帶入完成事項
                    </button>
                </div>

                <!-- 週報填寫表單 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm p-6 space-y-6">
                    <form @submit.prevent="submitReport(false)" class="space-y-6">
                        
                        <!-- 1. 本週工作重點概述 -->
                        <div>
                            <InputLabel for="summary" value="本週工作重點概述 (Summary) *" class="text-sm font-bold" />
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mb-1.5">簡要用 1~2 句話總結本週最關鍵的工作進展與成果目標。</p>
                            <textarea
                                id="summary"
                                v-model="form.summary"
                                rows="3"
                                required
                                placeholder="例如：本週完成 Q4 電商促銷系統壓測並上線；協助客戶排除登入雙因子驗證問題..."
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 p-3"
                            ></textarea>
                            <InputError :message="form.errors.summary" class="mt-1" />
                        </div>

                        <!-- 2. 具體完成事項 -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <InputLabel for="achievements" value="具體完成事項 (Achievements) *" class="text-sm font-bold" />
                                <button
                                    v-if="completedTasks && completedTasks.length > 0"
                                    type="button"
                                    @click="importCompletedTasks"
                                    class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    + 帶入專案任務 ({{ completedTasks.length }})
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mb-1.5">詳細條列本週推進與完成的具體項目、產出與對接進度。</p>
                            <textarea
                                id="achievements"
                                v-model="form.achievements"
                                rows="6"
                                required
                                placeholder="1. 完成後端資料庫索引優化，查詢延遲降低 40%&#10;2. 召開跨部門需求對齊會議，產出 PRD 規格文件&#10;3. 協助新進同仁建立開發環境..."
                                class="w-full text-xs font-mono rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 p-3"
                            ></textarea>
                            <InputError :message="form.errors.achievements" class="mt-1" />
                        </div>

                        <!-- 3. 下週工作計畫 -->
                        <div>
                            <InputLabel for="plans_next_week" value="下週工作計畫 (Next Week Plans) *" class="text-sm font-bold" />
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mb-1.5">預計在下週推進的核心排程、里程碑或優先級任務。</p>
                            <textarea
                                id="plans_next_week"
                                v-model="form.plans_next_week"
                                rows="4"
                                required
                                placeholder="1. 啟動第二階段微服務拆分架構評估&#10;2. 完成主管審批中心效能壓測..."
                                class="w-full text-xs font-mono rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 p-3"
                            ></textarea>
                            <InputError :message="form.errors.plans_next_week" class="mt-1" />
                        </div>

                        <!-- 4. 遭遇困難或需跨部門支援 -->
                        <div>
                            <InputLabel for="challenges_or_support" value="困難、阻礙或需跨部門協助事項 (Challenges & Support)" class="text-sm font-bold" />
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mb-1.5">若有卡點、外部依賴或需要主管與其他單位資源協調，請在此提出（選填）。</p>
                            <textarea
                                id="challenges_or_support"
                                v-model="form.challenges_or_support"
                                rows="3"
                                placeholder="例如：測試伺服器記憶體配額不足，需請 IT 維運團隊協助擴展..."
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 p-3"
                            ></textarea>
                            <InputError :message="form.errors.challenges_or_support" class="mt-1" />
                        </div>

                        <!-- 操作按鈕列 -->
                        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <SecondaryButton
                                type="button"
                                @click="submitReport(true)"
                                :disabled="form.processing"
                                class="text-xs"
                            >
                                 暫存為草稿
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                @click="submitReport(false)"
                                :disabled="form.processing"
                                class="text-xs"
                            >
                                 正式提交送出週報
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
