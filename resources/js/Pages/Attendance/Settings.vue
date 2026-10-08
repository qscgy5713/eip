<script setup>
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    config: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    office_name: props.config.name || '台北企業總部大樓',
    office_address: props.config.address || '台北市信義區信義路五段7號',
    office_lat: props.config.lat || 25.033964,
    office_lng: props.config.lng || 121.564468,
    allowed_radius: props.config.radius || 500,
});

const isLocating = ref(false);
const locateError = ref('');

const radiusPresets = [100, 200, 300, 500, 800, 1000];

const getCurrentLocation = () => {
    if (!navigator.geolocation) {
        locateError.value = '您的瀏覽器不支援定位功能。';
        return;
    }

    isLocating.value = true;
    locateError.value = '';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            form.office_lat = Number(position.coords.latitude.toFixed(6));
            form.office_lng = Number(position.coords.longitude.toFixed(6));
            isLocating.value = false;
        },
        (error) => {
            isLocating.value = false;
            locateError.value = '無法獲取目前位置，請確認瀏覽器已允許位置存取權限。';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
};

const submit = () => {
    form.post(route('attendance.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="考勤地理圍欄與打卡半徑設定" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800">考勤打卡與地理圍欄設定</h2>
                    <p class="text-xs text-gray-500 mt-1">設定公司地址、總部 GPS 座標與有效打卡半徑，超出範圍打卡將強制填寫事由說明</p>
                </div>
                <Link
                    :href="route('attendance.index')"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-sm transition"
                >
                    <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    返回打卡頁面
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- 成功/錯誤訊息橫幅 -->
                <div v-if="$page.props.flash?.success" class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center text-emerald-800 shadow-sm">
                    <svg class="w-5 h-5 mr-3 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-medium">{{ $page.props.flash.success }}</span>
                </div>

                <div v-if="$page.props.flash?.error" class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-center text-rose-800 shadow-sm">
                    <svg class="w-5 h-5 mr-3 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-medium">{{ $page.props.flash.error }}</span>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-5 bg-gradient-to-r from-blue-50/50 to-indigo-50/50 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">公司基地與圍欄範圍設定</h3>
                                <p class="text-xs text-gray-500">更新後同仁考勤打卡立即套用新規則</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                            管理員/人資專屬
                        </span>
                    </div>

                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">
                        <!-- 辦公室名稱與地址 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    辦公室 / 總部名稱 <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.office_name"
                                    type="text"
                                    required
                                    placeholder="例如：台北企業總部大樓"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition"
                                />
                                <p v-if="form.errors.office_name" class="mt-1 text-xs text-rose-600">{{ form.errors.office_name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    公司詳細地址
                                </label>
                                <input
                                    v-model="form.office_address"
                                    type="text"
                                    placeholder="例如：台北市信義區信義路五段7號"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition"
                                />
                                <p v-if="form.errors.office_address" class="mt-1 text-xs text-rose-600">{{ form.errors.office_address }}</p>
                            </div>
                        </div>

                        <!-- 經緯度座標與即時抓取 -->
                        <div class="p-5 bg-gray-50/80 rounded-xl border border-gray-200/80 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-800">總部中心 GPS 經緯度座標</h4>
                                    <p class="text-xs text-gray-500">若不設定座標，全體同仁將自動判定為「遠端打卡」，不限制距離且無需填寫事由</p>
                                </div>
                                <div class="flex items-center flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="getCurrentLocation"
                                        :disabled="isLocating"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100/70 hover:bg-blue-200/80 rounded-lg transition disabled:opacity-50"
                                    >
                                        <svg v-if="!isLocating" class="w-3.5 h-3.5 mr-1 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <svg v-else class="animate-spin -ml-0.5 mr-1 h-3.5 w-3.5 text-blue-700" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        {{ isLocating ? '定位中...' : '抓取我當前位置' }}
                                    </button>

                                    <button
                                        type="button"
                                        @click="form.office_lat = null; form.office_lng = null; form.allowed_radius = null"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition"
                                    >
                                        清空座標 (全遠端模式)
                                    </button>

                                    <a
                                        v-if="form.office_lat && form.office_lng"
                                        :href="`https://www.google.com/maps?q=${form.office_lat},${form.office_lng}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition"
                                    >
                                        <svg class="w-3.5 h-3.5 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                        地圖確認
                                    </a>
                                </div>
                            </div>

                            <div v-if="locateError" class="text-xs text-rose-600 bg-rose-50 p-2.5 rounded-lg border border-rose-200">
                                {{ locateError }}
                            </div>

                            <!-- 全遠端提示橫幅 -->
                            <div v-if="!form.office_lat || !form.office_lng" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800 flex items-center space-x-2">
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>目前未指定座標：系統將啟用<strong>「全遠端自由打卡」</strong>模式，同仁打卡全數判定為遠端模式，無需設定距離限制，亦不會強制要求填寫外勤事由。</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                        緯度 (Latitude) <span class="text-gray-400 font-normal">(選填)</span>
                                    </label>
                                    <input
                                        v-model.number="form.office_lat"
                                        type="number"
                                        step="any"
                                        placeholder="未設定（例如 25.033964）"
                                        class="w-full px-3.5 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono"
                                    />
                                    <p v-if="form.errors.office_lat" class="mt-1 text-xs text-rose-600">{{ form.errors.office_lat }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                        經度 (Longitude) <span class="text-gray-400 font-normal">(選填)</span>
                                    </label>
                                    <input
                                        v-model.number="form.office_lng"
                                        type="number"
                                        step="any"
                                        placeholder="未設定（例如 121.564468）"
                                        class="w-full px-3.5 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono"
                                    />
                                    <p v-if="form.errors.office_lng" class="mt-1 text-xs text-rose-600">{{ form.errors.office_lng }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- 限制打卡半徑 -->
                        <div class="space-y-3" :class="{ 'opacity-60': !form.office_lat || !form.office_lng }">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-semibold text-gray-700">
                                    限制打卡半徑（公尺）
                                </label>
                                <span class="text-xs text-gray-500">
                                    <template v-if="form.office_lat && form.office_lng">
                                        目前設定：<strong class="text-blue-600 font-bold">{{ form.allowed_radius || 500 }} 公尺</strong>
                                    </template>
                                    <template v-else>
                                        （未設定公司座標，不限制距離）
                                    </template>
                                </span>
                            </div>

                            <div class="flex items-center space-x-3">
                                <input
                                    v-model.number="form.allowed_radius"
                                    type="number"
                                    min="10"
                                    max="50000"
                                    :disabled="!form.office_lat || !form.office_lng"
                                    placeholder="500"
                                    class="w-48 px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-semibold text-gray-900 disabled:bg-gray-100 disabled:text-gray-400"
                                />
                                <span class="text-sm text-gray-500">公尺 (m)</span>
                            </div>
                            <p v-if="form.errors.allowed_radius" class="text-xs text-rose-600">{{ form.errors.allowed_radius }}</p>

                            <!-- 快速選取標籤 -->
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <span class="text-xs text-gray-400">常用推薦：</span>
                                <button
                                    v-for="preset in radiusPresets"
                                    :key="preset"
                                    type="button"
                                    @click="form.allowed_radius = preset"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-medium transition border',
                                        form.allowed_radius === preset
                                            ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                            : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'
                                    ]"
                                >
                                    {{ preset }}m
                                </button>
                            </div>
                        </div>

                        <!-- 規範說明卡片 -->
                        <div class="p-4 bg-amber-50/70 border border-amber-200/80 rounded-xl flex items-start space-x-3 text-amber-900 text-xs leading-relaxed">
                            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="font-bold mb-1">考勤圍欄管理規範說明：</p>
                                <ul class="list-disc list-inside space-y-0.5 text-amber-800">
                                    <li>同仁打卡時直線距離若在 <strong>{{ form.allowed_radius }} 公尺</strong> 內，系統將自動判定為<strong>「辦公室內勤打卡」</strong>。</li>
                                    <li>若超出 <strong>{{ form.allowed_radius }} 公尺</strong>，系統將判定為<strong>「外勤/遠端打卡」</strong>，並<strong>強制跳出事由說明欄位，未填寫者將無法打卡</strong>。</li>
                                    <li>更新設定後將自動記錄至系統審計日誌 (Audit Log)。</li>
                                </ul>
                            </div>
                        </div>

                        <!-- 儲存按鈕 -->
                        <div class="pt-4 border-t border-gray-100 flex items-center justify-end space-x-3">
                            <Link
                                :href="route('attendance.index')"
                                class="px-5 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition"
                            >
                                取消
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-sm hover:shadow transition disabled:opacity-50"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                儲存設定並即刻生效
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
