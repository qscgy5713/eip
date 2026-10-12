<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    vehicles: Array,
});

// 預設今天下午 13:00 至 18:00
const now = new Date();
const todayStr = now.toISOString().split('T')[0];
const defaultStart = `${todayStr}T13:00`;
const defaultEnd = `${todayStr}T18:00`;

const form = useForm({
    vehicle_id: props.vehicles.length > 0 ? props.vehicles[0].id : '',
    start_time: defaultStart,
    end_time: defaultEnd,
    destination: '',
    purpose: 'client_visit',
    passenger_count: 1,
});

const selectedVehicle = computed(() => {
    return props.vehicles.find((v) => v.id === form.vehicle_id);
});

const submit = () => {
    form.post(route('vehicles.store'));
};
</script>

<template>
    <Head title="預約借調公務車 - 企業公務派車" />

    <AuthenticatedLayout>
        <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- 麵包屑 -->
            <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                <Link :href="route('vehicles.index')" class="hover:underline">派車總表</Link>
                <span>/</span>
                <span class="text-gray-800 dark:text-gray-200 font-semibold">填寫派車申請單</span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-6 sm:p-8">
                <div class="pb-6 border-b border-gray-100 dark:border-gray-700">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4m-11-7l2.5-5h9L19 10m-14 0h14m-14 0a2 2 0 00-2 2v5a2 2 0 002 2h1m12 0h1a2 2 0 002-2v-5a2 2 0 00-2-2" />
                            </svg>
                        </span>
                        申請借調公務車
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        系統將自動檢核車輛時段衝突，請於出差前如實填寫行程目的地與隨行人數。
                    </p>
                </div>

                <form @submit.prevent="submit" class="mt-6 space-y-5 text-xs">
                    <!-- 選擇車輛 -->
                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                            借用公務車輛款型 <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.vehicle_id"
                            required
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-amber-500"
                        >
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">
                                【{{ v.plate_number }}】{{ v.name }} ({{ v.vehicle_type_label }} · 乘載 {{ v.capacity }} 人 · 里程 {{ v.current_mileage.toLocaleString() }} km)
                            </option>
                        </select>
                        <p v-if="form.errors.vehicle_id" class="text-rose-500 text-2xs mt-1">{{ form.errors.vehicle_id }}</p>

                        <!-- 所選車輛狀態小卡 -->
                        <div v-if="selectedVehicle" class="mt-2.5 p-3 rounded-xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/60 flex items-center justify-between text-2xs text-amber-900 dark:text-amber-200">
                            <div>
                                <span class="font-bold">{{ selectedVehicle.plate_number }}</span>
                                <span class="mx-1.5">·</span>
                                <span>車位：{{ selectedVehicle.parking_location || '總部平面停車場' }}</span>
                                <span class="mx-1.5">·</span>
                                <span>能源：{{ selectedVehicle.fuel_type_label }}</span>
                            </div>
                            <div>
                                目前累積里程：<strong class="font-mono">{{ selectedVehicle.current_mileage.toLocaleString() }}</strong> km
                            </div>
                        </div>
                    </div>

                    <!-- 用車時段 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                預計借車出發時間 <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="datetime-local"
                                v-model="form.start_time"
                                required
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-amber-500"
                            />
                            <p v-if="form.errors.start_time" class="text-rose-500 text-2xs mt-1">{{ form.errors.start_time }}</p>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                預計用畢歸還時間 <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="datetime-local"
                                v-model="form.end_time"
                                required
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-amber-500"
                            />
                            <p v-if="form.errors.end_time" class="text-rose-500 text-2xs mt-1">{{ form.errors.end_time }}</p>
                        </div>
                    </div>

                    <!-- 目的地與事由 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                出差前往目的地 <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="form.destination"
                                required
                                placeholder="例：新竹科學園區台積電十二廠、桃園國際機場"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-amber-500"
                            />
                            <p v-if="form.errors.destination" class="text-rose-500 text-2xs mt-1">{{ form.errors.destination }}</p>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                                公務出差事由 <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="form.purpose"
                                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-amber-500"
                            >
                                <option value="client_visit">客戶拜訪 / 業務洽談</option>
                                <option value="airport_pickup">機場高鐵 / 貴賓接送</option>
                                <option value="vendor_meeting">協力廠商 / 外包訪查</option>
                                <option value="official_errand">機關公務 / 送件洽公</option>
                                <option value="other">其他外勤出差事由</option>
                            </select>
                        </div>
                    </div>

                    <!-- 乘客人數 -->
                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">
                            同行總人數 (含駕駛) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="number"
                            v-model="form.passenger_count"
                            min="1"
                            :max="selectedVehicle ? selectedVehicle.capacity : 10"
                            required
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 focus:ring-amber-500"
                        />
                        <p v-if="form.errors.passenger_count" class="text-rose-500 text-2xs mt-1">{{ form.errors.passenger_count }}</p>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3">
                        <Link
                            :href="route('vehicles.index')"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        >
                            取消返回
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md transition"
                        >
                            送出派車申請
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
