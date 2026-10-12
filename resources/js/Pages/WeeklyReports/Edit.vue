<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    report: Object,
});

const form = useForm({
    summary: props.report.summary,
    achievements: props.report.achievements,
    plans_next_week: props.report.plans_next_week,
    challenges_or_support: props.report.challenges_or_support || '',
    is_draft: props.report.status === 'draft',
});

const submitUpdate = (asDraft = false) => {
    form.is_draft = asDraft;
    form.put(route('weekly-reports.update', props.report.id));
};
</script>

<template>
    <Head :title="`編輯週報 - ${report.year} 年第 ${report.week_number} 週`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span>編輯工作週報</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-cyan-100 text-cyan-800 dark:bg-cyan-900/50 dark:text-cyan-300">
                            {{ report.year }} 年 第 {{ report.week_number }} 週
                        </span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        週期時間：{{ String(report.start_date).slice(0, 10) }} ~ {{ String(report.end_date).slice(0, 10) }}
                    </p>
                </div>
                <Link
                    :href="route('weekly-reports.show', report.id)"
                    class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                >
                    &larr; 取消並返回
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm p-6 space-y-6">
                    <form @submit.prevent="submitUpdate(false)" class="space-y-6">
                        
                        <!-- 1. 本週工作重點概述 -->
                        <div>
                            <InputLabel for="summary" value="本週工作重點概述 (Summary) *" class="text-sm font-bold" />
                            <textarea
                                id="summary"
                                v-model="form.summary"
                                rows="3"
                                required
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 p-3 mt-1"
                            ></textarea>
                            <InputError :message="form.errors.summary" class="mt-1" />
                        </div>

                        <!-- 2. 具體完成事項 -->
                        <div>
                            <InputLabel for="achievements" value="具體完成事項 (Achievements) *" class="text-sm font-bold" />
                            <textarea
                                id="achievements"
                                v-model="form.achievements"
                                rows="6"
                                required
                                class="w-full text-xs font-mono rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 p-3 mt-1"
                            ></textarea>
                            <InputError :message="form.errors.achievements" class="mt-1" />
                        </div>

                        <!-- 3. 下週工作計畫 -->
                        <div>
                            <InputLabel for="plans_next_week" value="下週工作計畫 (Next Week Plans) *" class="text-sm font-bold" />
                            <textarea
                                id="plans_next_week"
                                v-model="form.plans_next_week"
                                rows="4"
                                required
                                class="w-full text-xs font-mono rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 p-3 mt-1"
                            ></textarea>
                            <InputError :message="form.errors.plans_next_week" class="mt-1" />
                        </div>

                        <!-- 4. 遭遇困難或需跨部門支援 -->
                        <div>
                            <InputLabel for="challenges_or_support" value="遭遇困難、阻礙或需跨部門協助事項 (Challenges & Support)" class="text-sm font-bold" />
                            <textarea
                                id="challenges_or_support"
                                v-model="form.challenges_or_support"
                                rows="3"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 p-3 mt-1"
                            ></textarea>
                            <InputError :message="form.errors.challenges_or_support" class="mt-1" />
                        </div>

                        <!-- 操作按鈕列 -->
                        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <SecondaryButton
                                type="button"
                                @click="submitUpdate(true)"
                                :disabled="form.processing"
                                class="text-xs"
                            >
                                 暫存為草稿
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                @click="submitUpdate(false)"
                                :disabled="form.processing"
                                class="text-xs"
                            >
                                 正式提交更新週報
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
