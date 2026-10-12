<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useTheme } from '@/Composables/useTheme';

const props = defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const { theme, isDark, setTheme } = useTheme();
const isOpen = ref(false);
const dropdownRef = ref(null);

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const selectTheme = (newTheme) => {
    setTheme(newTheme);
    isOpen.value = false;
};

const handleClickOutside = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
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
    <div class="relative inline-block text-left" ref="dropdownRef">
        <!-- 觸發按鈕 -->
        <button
            type="button"
            @click="toggleDropdown"
            class="p-2 rounded-lg text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors focus:outline-none flex items-center gap-1.5"
            :title="`當前主題：${theme === 'dark' ? '深色' : (theme === 'light' ? '淺色' : '跟隨系統')}`"
            aria-label="切換外觀主題"
        >
            <!-- 太陽圖示 (淺色) -->
            <svg
                v-if="theme === 'light'"
                class="w-5 h-5 text-amber-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>

            <!-- 月亮圖示 (深色) -->
            <svg
                v-else-if="theme === 'dark'"
                class="w-5 h-5 text-indigo-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>

            <!-- 系統自動圖示 (跟隨系統) -->
            <svg
                v-else
                class="w-5 h-5 text-gray-500 dark:text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>

            <span v-if="!compact" class="text-xs font-medium hidden md:inline-block">
                {{ theme === 'dark' ? '深色' : (theme === 'light' ? '淺色' : '系統') }}
            </span>
        </button>

        <!-- 下拉選單 -->
        <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-36 rounded-xl shadow-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 py-1.5 z-50 text-xs focus:outline-none"
            >
                <button
                    type="button"
                    @click="selectTheme('light')"
                    :class="[
                        theme === 'light'
                            ? 'bg-amber-50 text-amber-900 font-bold dark:bg-gray-700 dark:text-amber-300'
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50',
                        'w-full text-left px-3.5 py-2 flex items-center gap-2 transition-colors'
                    ]"
                >
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>淺色模式</span>
                    <span v-if="theme === 'light'" class="ml-auto text-amber-600 dark:text-amber-400">✓</span>
                </button>

                <button
                    type="button"
                    @click="selectTheme('dark')"
                    :class="[
                        theme === 'dark'
                            ? 'bg-indigo-50 text-indigo-900 font-bold dark:bg-gray-700 dark:text-indigo-300'
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50',
                        'w-full text-left px-3.5 py-2 flex items-center gap-2 transition-colors'
                    ]"
                >
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <span>深色模式</span>
                    <span v-if="theme === 'dark'" class="ml-auto text-indigo-600 dark:text-indigo-400">✓</span>
                </button>

                <button
                    type="button"
                    @click="selectTheme('system')"
                    :class="[
                        theme === 'system'
                            ? 'bg-gray-100 text-gray-900 font-bold dark:bg-gray-700 dark:text-gray-200'
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50',
                        'w-full text-left px-3.5 py-2 flex items-center gap-2 transition-colors'
                    ]"
                >
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>跟隨系統</span>
                    <span v-if="theme === 'system'" class="ml-auto text-gray-600 dark:text-gray-400">✓</span>
                </button>
            </div>
        </transition>
    </div>
</template>
