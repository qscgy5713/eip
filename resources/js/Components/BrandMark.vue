<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

// 品牌識別:有自訂 Logo 用圖片,否則用預設圖樣。size: 'md'(導覽列) | 'lg'(登入頁)
const props = defineProps({
    size: { type: String, default: 'md' },
});

const logoUrl = computed(() => usePage().props.branding?.logo_url);
const box = computed(() => (props.size === 'lg' ? 'h-14 w-14 rounded-2xl' : 'h-9 w-9 rounded-xl'));
const icon = computed(() => (props.size === 'lg' ? 'h-8 w-8' : 'h-5 w-5'));
</script>

<template>
    <span
        :class="[
            'flex shrink-0 items-center justify-center overflow-hidden shadow-sm',
            box,
            logoUrl ? 'bg-white dark:bg-gray-100 border border-gray-200 dark:border-gray-700' : 'bg-indigo-600',
        ]"
    >
        <img v-if="logoUrl" :src="logoUrl" alt="" class="h-full w-full object-contain p-1" />
        <ApplicationLogo v-else :class="[icon, 'block fill-current text-white']" />
    </span>
</template>
