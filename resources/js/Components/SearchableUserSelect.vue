<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String, null],
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: '輸入姓名、帳號 (Email) 或工號搜尋同仁...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    emptyMessage: {
        type: String,
        default: '查無符合搜尋條件的在職同仁',
    },
});

const emit = defineEmits(['update:modelValue', 'select']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const searchInputRef = ref(null);
const highlightedIndex = ref(0);

// 當前已選取的同仁物件
const selectedUser = computed(() => {
    if (!props.modelValue) return null;
    return props.users.find((u) => u.id === Number(props.modelValue)) || null;
});

// 即時搜尋過濾 (比對姓名、帳號 Email、工號、職稱)
const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.users;
    }

    return props.users.filter((user) => {
        const nameMatch = (user.name || '').toLowerCase().includes(q);
        const emailMatch = (user.email || '').toLowerCase().includes(q);
        const empNoMatch = (user.employee_no || '').toLowerCase().includes(q);
        const titleMatch = (user.job_title || '').toLowerCase().includes(q);

        return nameMatch || emailMatch || empNoMatch || titleMatch;
    });
});

watch(filteredUsers, () => {
    highlightedIndex.value = 0;
});

// 開啟下拉面板
const openDropdown = () => {
    if (props.disabled) return;
    isOpen.value = true;
    highlightedIndex.value = 0;
    // 下一次 tick 聚焦搜尋欄
    setTimeout(() => {
        searchInputRef.value?.focus();
    }, 50);
};

// 關閉下拉面板
const closeDropdown = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

// 選取同仁
const selectUser = (user) => {
    emit('update:modelValue', user ? user.id : null);
    emit('select', user);
    closeDropdown();
};

// 清除選取
const clearSelection = (e) => {
    e?.stopPropagation();
    emit('update:modelValue', null);
    emit('select', null);
    searchQuery.value = '';
};

// 鍵盤導航
const handleKeyDown = (e) => {
    if (!isOpen.value) {
        if (e.key === 'ArrowDown' || e.key === 'Enter') {
            e.preventDefault();
            openDropdown();
        }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (highlightedIndex.value < filteredUsers.value.length - 1) {
            highlightedIndex.value += 1;
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (highlightedIndex.value > 0) {
            highlightedIndex.value -= 1;
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (filteredUsers.value[highlightedIndex.value]) {
            selectUser(filteredUsers.value[highlightedIndex.value]);
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        closeDropdown();
    }
};

// 點擊外部關閉
const handleClickOutside = (e) => {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative w-full" @keydown="handleKeyDown">
        <!-- 主觸發按鈕區塊 -->
        <div
            @click="openDropdown"
            :class="[
                'w-full min-h-[38px] px-3 py-1.5 bg-white border rounded-lg text-xs flex items-center justify-between gap-2 cursor-pointer transition select-none',
                isOpen
                    ? 'border-indigo-500 ring-2 ring-indigo-100 shadow-xs'
                    : 'border-gray-300 hover:border-gray-400',
                disabled ? 'opacity-60 cursor-not-allowed bg-gray-50' : ''
            ]"
        >
            <!-- 已選取狀態 -->
            <div v-if="selectedUser" class="flex items-center gap-2 min-w-0 flex-1">
                <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white font-bold text-3xs flex items-center justify-center shrink-0 shadow-2xs">
                    {{ selectedUser.name.charAt(0) }}
                </div>
                <div class="flex items-center gap-1.5 truncate">
                    <span class="font-bold text-gray-900 truncate">{{ selectedUser.name }}</span>
                    <span v-if="selectedUser.employee_no" class="text-3xs font-mono text-gray-500">
                        ({{ selectedUser.employee_no }})
                    </span>
                    <span class="text-3xs text-gray-500 truncate">
                        · {{ selectedUser.job_title || '未設職稱' }}
                    </span>
                </div>
            </div>

            <!-- 未選取 / Placeholder -->
            <div v-else class="flex items-center gap-2 text-gray-400 text-xs flex-1 truncate">
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span class="truncate">{{ placeholder }}</span>
            </div>

            <!-- 右側操作按鈕：清除與箭頭 -->
            <div class="flex items-center gap-1 shrink-0">
                <button
                    v-if="selectedUser && !disabled"
                    type="button"
                    @click="clearSelection"
                    class="p-0.5 text-gray-400 hover:text-rose-600 rounded-full hover:bg-rose-50 transition"
                    title="清除選取"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <svg
                    :class="['w-4 h-4 text-gray-400 transition-transform duration-200', isOpen ? 'rotate-180 text-indigo-500' : '']"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>

        <!-- 下拉彈出選單 (Combobox Dropdown Panel) -->
        <div
            v-if="isOpen"
            class="absolute z-50 left-0 right-0 mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden flex flex-col text-xs max-h-72"
        >
            <!-- 搜尋輸入框 -->
            <div class="p-2.5 bg-slate-50 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    placeholder="輸入姓名、帳號 (Email) 或工號搜尋..."
                    class="w-full text-xs bg-white border border-gray-200 rounded-md py-1.5 px-2.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                    @click.stop
                />
                <button
                    v-if="searchQuery"
                    type="button"
                    @click.stop="searchQuery = ''"
                    class="p-1 text-gray-400 hover:text-gray-600 rounded text-3xs"
                    title="清空關鍵字"
                >
                    清空
                </button>
            </div>

            <!-- 同仁列表 -->
            <div class="flex-1 overflow-y-auto divide-y divide-gray-50 py-1">
                <div
                    v-for="(user, idx) in filteredUsers"
                    :key="user.id"
                    @click.stop="selectUser(user)"
                    @mouseenter="highlightedIndex = idx"
                    :class="[
                        'px-3 py-2.5 cursor-pointer flex items-center justify-between gap-3 transition select-none',
                        user.id === Number(modelValue)
                            ? 'bg-indigo-50/80 font-bold'
                            : highlightedIndex === idx
                                ? 'bg-slate-50'
                                : 'hover:bg-slate-50/70'
                    ]"
                >
                    <!-- 左側同仁詳細資訊 -->
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                            {{ user.name.charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-bold text-gray-900 text-xs">{{ user.name }}</span>
                                <span v-if="user.employee_no" class="px-1.5 py-0.2 bg-gray-100 text-gray-600 rounded text-3xs font-mono">
                                    {{ user.employee_no }}
                                </span>
                                <span v-if="user.job_title" class="text-3xs text-gray-500">
                                    {{ user.job_title }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5 text-3xs text-gray-400 truncate">
                                <span class="font-mono">{{ user.email }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 右側部門隸屬狀態標籤 -->
                    <div class="shrink-0 flex items-center gap-1.5">
                        <span
                            v-if="user.department"
                            class="px-2 py-0.5 rounded text-3xs font-medium bg-amber-50 text-amber-700 border border-amber-200"
                        >
                            現屬：{{ user.department.name }}
                        </span>
                        <span
                            v-else
                            class="px-2 py-0.5 rounded text-3xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"
                        >
                            未分配部門
                        </span>

                        <svg
                            v-if="user.id === Number(modelValue)"
                            class="w-4 h-4 text-indigo-600 shrink-0 ml-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>

                <!-- 空狀態：查無資料 -->
                <div v-if="filteredUsers.length === 0" class="py-6 px-4 text-center text-gray-400 space-y-1">
                    <svg class="w-6 h-6 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-xs">{{ emptyMessage }}</p>
                    <p v-if="searchQuery" class="text-3xs text-gray-400">
                        關鍵字：「<span class="text-indigo-600 font-semibold">{{ searchQuery }}</span>」無匹配同仁
                    </p>
                </div>
            </div>

            <!-- 底部資訊提示列 -->
            <div class="px-3 py-1.5 bg-slate-100/70 border-t border-gray-100 flex items-center justify-between text-3xs text-gray-400">
                <span>共 {{ filteredUsers.length }} 位候選同仁</span>
                <span class="hidden sm:inline">支援 ↑ ↓ 鍵導航、Enter 選取、Esc 關閉</span>
            </div>
        </div>
    </div>
</template>
