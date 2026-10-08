<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    rooms: Array,
    selectedDate: String,
    myBookings: Array,
    isAdmin: Boolean,
});

// 日期切換
const currentDate = ref(props.selectedDate);

const changeDate = (dateStr) => {
    currentDate.value = dateStr;
    router.get(route('meeting-rooms.index'), { date: dateStr }, { preserveState: true });
};

const shiftDate = (days) => {
    const parts = currentDate.value.split('-').map(Number);
    const d = new Date(parts[0], parts[1] - 1, parts[2] + days);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const dt = String(d.getDate()).padStart(2, '0');
    changeDate(`${y}-${m}-${dt}`);
};

// 預約 Modal 狀態與表單
const isBookingModalOpen = ref(false);
const bookingForm = useForm({
    meeting_room_id: '',
    title: '',
    start_time: '',
    end_time: '',
    attendees_count: 2,
    description: '',
});

const openBookingModal = (roomId = null) => {
    bookingForm.reset();
    bookingForm.clearErrors();
    if (roomId) {
        bookingForm.meeting_room_id = roomId;
    } else if (props.rooms.length > 0) {
        bookingForm.meeting_room_id = props.rooms[0].id;
    }

    // 預設開始時間為所選日期的 10:00，結束時間 11:00
    bookingForm.start_time = `${currentDate.value}T10:00`;
    bookingForm.end_time = `${currentDate.value}T11:00`;
    isBookingModalOpen.value = true;
};

const closeBookingModal = () => {
    isBookingModalOpen.value = false;
};

const submitBooking = () => {
    bookingForm.post(route('meeting-rooms.bookings.store'), {
        onSuccess: () => {
            closeBookingModal();
        },
    });
};

// 取消預約操作
const cancelBooking = (bookingId) => {
    if (confirm('確定要取消這筆會議預約嗎？')) {
        router.post(route('meeting-rooms.bookings.cancel', bookingId));
    }
};

// 管理員：新增會議室 Modal
const isRoomModalOpen = ref(false);
const availableEquipments = ['投影機', '視訊會議設備', '電子白板', '傳統白板', '會議電話', '獨立音響', '茶水設備'];
const roomForm = useForm({
    name: '',
    location: '',
    capacity: 10,
    equipment: [],
    description: '',
    is_active: true,
});

const openRoomModal = () => {
    roomForm.reset();
    roomForm.clearErrors();
    roomForm.equipment = ['投影機', '視訊會議設備', '傳統白板'];
    isRoomModalOpen.value = true;
};

const closeRoomModal = () => {
    isRoomModalOpen.value = false;
};

const submitRoom = () => {
    roomForm.post(route('meeting-rooms.store'), {
        onSuccess: () => {
            closeRoomModal();
        },
    });
};

const toggleEquipment = (eq) => {
    const idx = roomForm.equipment.indexOf(eq);
    if (idx > -1) {
        roomForm.equipment.splice(idx, 1);
    } else {
        roomForm.equipment.push(eq);
    }
};

// 格式化時間
const formatTime = (isoString) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
};
</script>

<template>
    <Head title="會議室借用與行事曆" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800">
                        會議室借用與公用日程
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        即時查看會議室佔用時段、線上防衝突快速預約
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        v-if="isAdmin"
                        @click="openRoomModal"
                        class="px-3.5 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition"
                    >
                        + 新增會議室 (管理員)
                    </button>
                    <button
                        @click="openBookingModal()"
                        class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg shadow-sm hover:bg-blue-700 transition flex items-center space-x-1"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>預約會議室</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- 日期選擇控制列 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center space-x-2">
                        <button
                            @click="shiftDate(-1)"
                            class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                            title="前一天"
                        >
                            &larr; 前一天
                        </button>
                        <button
                            @click="changeDate(new Date().toISOString().split('T')[0])"
                            class="px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition"
                        >
                            回到今天
                        </button>
                        <button
                            @click="shiftDate(1)"
                            class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                            title="後一天"
                        >
                            後一天 &rarr;
                        </button>
                    </div>

                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-medium text-gray-700">檢視日期：</span>
                        <input
                            type="date"
                            v-model="currentDate"
                            @change="changeDate(currentDate)"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-800 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>

                <!-- 我的近期有效預約橫幅 (如果有) -->
                <div v-if="myBookings && myBookings.length > 0" class="bg-gradient-to-r from-blue-900 to-indigo-900 rounded-xl p-5 text-white shadow-sm">
                    <div class="flex items-center justify-between mb-3 border-b border-blue-700/50 pb-2">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 bg-blue-400 rounded-full animate-pulse"></span>
                            <h3 class="font-bold text-sm tracking-wide">我的即將開始會議行程</h3>
                        </div>
                        <span class="text-xs text-blue-200">共 {{ myBookings.length }} 場預約</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div
                            v-for="mb in myBookings"
                            :key="mb.id"
                            class="bg-white/10 backdrop-blur-sm border border-white/15 rounded-lg p-3 flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-sm text-white">{{ mb.title }}</span>
                                    <button
                                        @click="cancelBooking(mb.id)"
                                        class="text-xs text-rose-300 hover:text-rose-100 hover:underline"
                                    >
                                        取消
                                    </button>
                                </div>
                                <p class="text-xs text-blue-200 mt-1 flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1 text-blue-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    {{ mb.room?.name }} ({{ mb.room?.location }})
                                </p>
                            </div>
                            <div class="mt-2 text-xs font-mono text-blue-100 bg-white/5 px-2 py-1 rounded flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1 text-blue-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ new Date(mb.start_time).toLocaleDateString() }} {{ formatTime(mb.start_time) }} - {{ formatTime(mb.end_time) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 會議室清單與預約時段看板 -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div
                        v-for="room in rooms"
                        :key="room.id"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col"
                    >
                        <!-- 會議室頭部卡片 -->
                        <div class="p-5 border-b border-gray-100 bg-slate-50/50">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-gray-900">{{ room.name }}</h3>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    容納 {{ room.capacity }} 人
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1 flex items-center space-x-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>{{ room.location }}</span>
                            </p>
                            <p v-if="room.description" class="text-xs text-gray-400 mt-1">{{ room.description }}</p>

                            <!-- 設備標籤 -->
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span
                                    v-for="(eq, i) in (room.equipment || [])"
                                    :key="i"
                                    class="px-2 py-0.5 text-[11px] font-medium bg-gray-100 text-gray-600 rounded"
                                >
                                    {{ eq }}
                                </span>
                            </div>
                        </div>

                        <!-- 該日預約時間軸 / 清單 -->
                        <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                            <div>
                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                                    {{ currentDate }} 時段預約狀況
                                </h4>

                                <!-- 空閒狀態 -->
                                <div
                                    v-if="!room.bookings || room.bookings.length === 0"
                                    class="p-4 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-800 text-xs flex items-center space-x-2"
                                >
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span>此日全時段空閒，可直接預約借用。</span>
                                </div>

                                <!-- 已被借用清單 -->
                                <div v-else class="space-y-2">
                                    <div
                                        v-for="b in room.bookings"
                                        :key="b.id"
                                        class="p-3 rounded-lg bg-gray-50 border border-gray-200 text-xs flex items-center justify-between"
                                    >
                                        <div>
                                            <div class="flex items-center space-x-1.5">
                                                <span class="font-bold text-gray-800">{{ b.title }}</span>
                                                <span class="text-gray-400">({{ b.attendees_count }}人)</span>
                                            </div>
                                            <p class="text-blue-600 font-mono mt-0.5">
                                                ⏰ {{ formatTime(b.start_time) }} ~ {{ formatTime(b.end_time) }}
                                            </p>
                                            <p class="text-gray-400 text-[11px] mt-0.5">
                                                預約人：{{ b.user?.name }}
                                            </p>
                                        </div>
                                        <button
                                            v-if="isAdmin || b.user_id === $page.props.auth.user.id"
                                            @click="cancelBooking(b.id)"
                                            class="text-rose-600 hover:text-rose-800 text-[11px] font-medium px-2 py-1 rounded hover:bg-rose-50"
                                            title="取消此筆預約"
                                        >
                                            取消
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- 底部預約按鈕 -->
                            <button
                                @click="openBookingModal(room.id)"
                                class="w-full py-2 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-600 hover:text-white transition text-center"
                            >
                                預約此會議室
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 預約會議室 Modal -->
        <div v-if="isBookingModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">預約會議室</h3>
                    <button @click="closeBookingModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitBooking" class="space-y-4 text-sm">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">選擇會議室</label>
                        <select
                            v-model="bookingForm.meeting_room_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                            required
                        >
                            <option v-for="r in rooms" :key="r.id" :value="r.id">
                                {{ r.name }} ({{ r.location }} · 容納 {{ r.capacity }} 人)
                            </option>
                        </select>
                        <p v-if="bookingForm.errors.meeting_room_id" class="text-xs text-rose-500 mt-1">{{ bookingForm.errors.meeting_room_id }}</p>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">會議主題</label>
                        <input
                            type="text"
                            v-model="bookingForm.title"
                            placeholder="例如：2026 Q4 專案啟動會議"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                            required
                        />
                        <p v-if="bookingForm.errors.title" class="text-xs text-rose-500 mt-1">{{ bookingForm.errors.title }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">開始時間</label>
                            <input
                                type="datetime-local"
                                v-model="bookingForm.start_time"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">結束時間</label>
                            <input
                                type="datetime-local"
                                v-model="bookingForm.end_time"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                        </div>
                    </div>
                    <p v-if="bookingForm.errors.start_time" class="text-xs text-rose-600 font-semibold">{{ bookingForm.errors.start_time }}</p>
                    <p v-if="bookingForm.errors.end_time" class="text-xs text-rose-600 font-semibold">{{ bookingForm.errors.end_time }}</p>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">與會人數</label>
                        <input
                            type="number"
                            min="1"
                            v-model="bookingForm.attendees_count"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                            required
                        />
                        <p v-if="bookingForm.errors.attendees_count" class="text-xs text-rose-500 mt-1">{{ bookingForm.errors.attendees_count }}</p>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">會議說明 / 備註需求 (選填)</label>
                        <textarea
                            v-model="bookingForm.description"
                            rows="2"
                            placeholder="如：需使用視訊鏡頭、遠端同仁連線..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3 border-t">
                        <button
                            type="button"
                            @click="closeBookingModal"
                            class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="bookingForm.processing"
                            class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm disabled:opacity-50"
                        >
                            確認預約
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 管理員新增會議室 Modal -->
        <div v-if="isRoomModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-lg font-bold text-gray-900">新增會議室 (管理員)</h3>
                    <button @click="closeRoomModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitRoom" class="space-y-4 text-sm">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">會議室名稱</label>
                        <input
                            type="text"
                            v-model="roomForm.name"
                            placeholder="如：301 敏捷創新室"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                            required
                        />
                        <p v-if="roomForm.errors.name" class="text-xs text-rose-500 mt-1">{{ roomForm.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">所在位置</label>
                            <input
                                type="text"
                                v-model="roomForm.location"
                                placeholder="如：總部 B 棟 3F"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">容納人數</label>
                            <input
                                type="number"
                                min="1"
                                v-model="roomForm.capacity"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">配備設施</label>
                        <div class="grid grid-cols-2 gap-2 mt-1">
                            <label
                                v-for="eq in availableEquipments"
                                :key="eq"
                                class="flex items-center space-x-2 text-xs text-gray-700 cursor-pointer p-1.5 rounded hover:bg-gray-50 border border-gray-100"
                            >
                                <input
                                    type="checkbox"
                                    :checked="roomForm.equipment.includes(eq)"
                                    @change="toggleEquipment(eq)"
                                    class="rounded text-blue-600 focus:ring-blue-500"
                                />
                                <span>{{ eq }}</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-gray-700 mb-1">說明備註 (選填)</label>
                        <textarea
                            v-model="roomForm.description"
                            rows="2"
                            placeholder="如：會議室附有玻璃白板、採光佳..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3 border-t">
                        <button
                            type="button"
                            @click="closeRoomModal"
                            class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            :disabled="roomForm.processing"
                            class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm disabled:opacity-50"
                        >
                            建立會議室
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
