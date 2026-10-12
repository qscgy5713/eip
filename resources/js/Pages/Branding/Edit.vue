<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
    defaultName: String,
});

const page = usePage();
const branding = computed(() => page.props.branding);

const form = useForm({
    name: page.props.branding.name,
    logo: null,
    favicon: null,
    remove_logo: false,
    remove_favicon: false,
});

// 選檔後的即時預覽 (blob URL)
const logoPreview = ref(null);
const faviconPreview = ref(null);
const revoke = (u) => u && URL.revokeObjectURL(u);

const pick = (kind, e) => {
    const file = e.target.files?.[0] ?? null;
    form[kind] = file;
    form[`remove_${kind}`] = false;
    const prev = kind === 'logo' ? logoPreview : faviconPreview;
    revoke(prev.value);
    prev.value = file ? URL.createObjectURL(file) : null;
};

const clear = (kind) => {
    form[kind] = null;
    const prev = kind === 'logo' ? logoPreview : faviconPreview;
    revoke(prev.value);
    prev.value = null;
    form[`remove_${kind}`] = !!branding.value[`${kind}_url`];
};

onBeforeUnmount(() => {
    revoke(logoPreview.value);
    revoke(faviconPreview.value);
});

const shownLogo = computed(() => (form.remove_logo ? null : logoPreview.value || branding.value.logo_url));
const shownFavicon = computed(() => (form.remove_favicon ? null : faviconPreview.value || branding.value.favicon_url));
const previewName = computed(() => form.name.trim() || props.defaultName);

const dirty = computed(
    () =>
        form.name.trim() !== branding.value.name ||
        !!form.logo ||
        !!form.favicon ||
        form.remove_logo ||
        form.remove_favicon,
);

const submit = () => {
    form.transform((d) => ({ ...d, remove_logo: d.remove_logo ? 1 : 0, remove_favicon: d.remove_favicon ? 1 : 0 })).post(
        route('branding.update'),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                // 瀏覽器分頁標題與 favicon 由伺服端輸出,儲存後整頁重載才會套用
                window.location.reload();
            },
        },
    );
};
</script>

<template>
    <Head title="品牌設定" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">品牌設定</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                    設定網站名稱、Logo 與瀏覽器分頁圖示。變更會套用到所有人的導覽列、登入頁與瀏覽器分頁。
                </p>

                <div class="grid gap-6 lg:grid-cols-5">
                    <form class="space-y-6 lg:col-span-3" @submit.prevent="submit">
                        <!-- 網站名稱 -->
                        <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">網站名稱</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">顯示在導覽列、登入頁,以及瀏覽器分頁標題的後綴。</p>
                            <input
                                v-model="form.name"
                                type="text"
                                maxlength="60"
                                required
                                :placeholder="defaultName"
                                class="mt-4 w-full rounded-xl border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            />
                            <p v-if="form.errors.name" class="mt-2 text-xs text-red-600">{{ form.errors.name }}</p>
                        </section>

                        <!-- Logo -->
                        <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Logo</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">建議使用正方形圖片,PNG / JPG / WebP / GIF,2 MB 以內。</p>
                            <div class="mt-4 flex flex-wrap items-center gap-4">
                                <span class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700">
                                    <img v-if="shownLogo" :src="shownLogo" alt="Logo 預覽" class="h-full w-full object-contain p-1.5" />
                                    <span v-else class="text-[11px] text-gray-400">預設</span>
                                </span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <label class="cursor-pointer rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                        選擇圖片
                                        <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="sr-only" @change="pick('logo', $event)" />
                                    </label>
                                    <button
                                        v-if="shownLogo"
                                        type="button"
                                        class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                        @click="clear('logo')"
                                    >
                                        還原預設
                                    </button>
                                </div>
                            </div>
                            <p v-if="form.errors.logo" class="mt-2 text-xs text-red-600">{{ form.errors.logo }}</p>
                        </section>

                        <!-- Favicon -->
                        <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Favicon</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">瀏覽器分頁上的小圖示。PNG 或 ICO,建議 64×64 以上的正方形,512 KB 以內。</p>
                            <div class="mt-4 flex flex-wrap items-center gap-4">
                                <span class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700">
                                    <img v-if="shownFavicon" :src="shownFavicon" alt="Favicon 預覽" class="h-8 w-8 object-contain" />
                                    <span v-else class="text-[11px] text-gray-400">預設</span>
                                </span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <label class="cursor-pointer rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                        選擇圖片
                                        <input type="file" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico" class="sr-only" @change="pick('favicon', $event)" />
                                    </label>
                                    <button
                                        v-if="shownFavicon"
                                        type="button"
                                        class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                        @click="clear('favicon')"
                                    >
                                        還原預設
                                    </button>
                                </div>
                            </div>
                            <p v-if="form.errors.favicon" class="mt-2 text-xs text-red-600">{{ form.errors.favicon }}</p>
                        </section>

                        <div class="flex items-center justify-end gap-2">
                            <Link :href="route('dashboard')" class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                取消
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing || !dirty"
                                class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                儲存變更
                            </button>
                        </div>
                    </form>

                    <!-- 預覽 -->
                    <aside class="lg:col-span-2">
                        <div class="sticky top-6 space-y-4 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">預覽</h3>

                            <div>
                                <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">瀏覽器分頁</p>
                                <div class="flex items-center gap-2 rounded-t-xl border border-b-0 border-gray-200 bg-gray-100 px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                                    <img v-if="shownFavicon" :src="shownFavicon" alt="" class="h-4 w-4 shrink-0 object-contain" />
                                    <span v-else class="h-4 w-4 shrink-0 rounded bg-indigo-600"></span>
                                    <span class="truncate text-xs text-gray-700 dark:text-gray-200">總覽儀表板 - {{ previewName }}</span>
                                </div>
                            </div>

                            <div>
                                <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">導覽列</p>
                                <div class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2.5 dark:border-gray-700">
                                    <span
                                        :class="[
                                            'flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-sm',
                                            shownLogo ? 'border border-gray-200 bg-white' : 'bg-indigo-600',
                                        ]"
                                    >
                                        <img v-if="shownLogo" :src="shownLogo" alt="" class="h-full w-full object-contain p-1" />
                                        <ApplicationLogo v-else class="block h-5 w-5 fill-current text-white" />
                                    </span>
                                    <span class="truncate text-base font-semibold tracking-tight text-gray-900 dark:text-gray-50">{{ previewName }}</span>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
