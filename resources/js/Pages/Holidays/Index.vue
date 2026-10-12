<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { confirmDialog } from '@/Components/useDialog';

const props = defineProps({
    year: { type: Number, required: true },
    holidays: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
});

const form = useForm({ date: '', name: '', type: 'holiday' });
const weekdays = ['日', '一', '二', '三', '四', '五', '六'];
const weekday = (d) => weekdays[new Date(d + 'T00:00:00').getDay()];

const goYear = (y) => router.get(route('holidays.index'), { year: y }, { preserveState: true });
const add = () => form.post(route('holidays.store'), { preserveScroll: true, onSuccess: () => form.reset('date', 'name') });
const remove = async (h) => {
    if (await confirmDialog(`確定要刪除「${h.date} ${h.name}」嗎?`)) {
        router.delete(route('holidays.destroy', h.id), { preserveScroll: true });
    }
};
const seedFixed = () => router.post(route('holidays.seed-fixed'), { year: props.year }, { preserveScroll: true });

const counts = computed(() => ({
    holiday: props.holidays.filter((h) => h.type === 'holiday').length,
    workday: props.holidays.filter((h) => h.type === 'workday').length,
}));
</script>

<template>
    <Head title="假日日曆" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">假日日曆</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">請假申請會依這裡的設定自動計算工作日數:週末與「放假日」不計,「補班日」雖然是週末但要計入。</p>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-200" @click="goYear(year - 1)">‹ {{ year - 1 }}</button>
                    <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ year }} 年</span>
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-200" @click="goYear(year + 1)">{{ year + 1 }} ›</button>
                    <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">放假 {{ counts.holiday }} 天 · 補班 {{ counts.workday }} 天</span>
                </div>

                <div v-if="canManage" class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">新增日期</h3>
                    <form class="mt-3 flex flex-wrap items-start gap-3" @submit.prevent="add">
                        <div>
                            <label for="h_date" class="sr-only">日期</label>
                            <input id="h_date" v-model="form.date" type="date" required class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            <InputError class="mt-1" :message="form.errors.date" />
                        </div>
                        <div>
                            <label for="h_name" class="sr-only">名稱</label>
                            <input id="h_name" v-model="form.name" type="text" maxlength="50" required placeholder="例如:春節、補班日" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div>
                            <label for="h_type" class="sr-only">類型</label>
                            <select id="h_type" v-model="form.type" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                <option value="holiday">放假日</option>
                                <option value="workday">補班日</option>
                            </select>
                        </div>
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">新增</button>
                    </form>
                    <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-200" @click="seedFixed">載入 {{ year }} 年固定日期國定假日</button>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">只會載入日期固定的假日(元旦、和平紀念日、兒童節、勞動節、國慶日)。春節、清明、端午、中秋與各項補假、補班日每年不同,請依人事行政總處公告自行新增。</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                    <p v-if="!holidays.length" class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">{{ year }} 年還沒有登錄任何假日。<template v-if="canManage">可以先按上方的「載入固定日期國定假日」。</template></p>
                    <table v-else class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr><th class="px-4 py-2">日期</th><th class="px-4 py-2">星期</th><th class="px-4 py-2">名稱</th><th class="px-4 py-2">類型</th><th v-if="canManage" class="px-4 py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-800 dark:divide-gray-700 dark:text-gray-200">
                            <tr v-for="h in holidays" :key="h.id">
                                <td class="px-4 py-2 font-mono">{{ h.date }}</td>
                                <td class="px-4 py-2">{{ weekday(h.date) }}</td>
                                <td class="px-4 py-2">{{ h.name }}</td>
                                <td class="px-4 py-2">
                                    <span :class="h.type === 'holiday' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200'" class="rounded-full px-2 py-0.5 text-xs">{{ h.type === 'holiday' ? '放假' : '補班' }}</span>
                                </td>
                                <td v-if="canManage" class="px-4 py-2 text-right"><button type="button" class="text-xs text-red-600 hover:underline dark:text-red-400" @click="remove(h)">刪除</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
