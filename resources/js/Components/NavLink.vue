<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    [
        'inline-flex items-center rounded-lg px-2.5 py-1.5 text-sm font-medium whitespace-nowrap transition-colors focus:outline-none',
        props.active
            ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-50'
            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100',
    ].join(' '),
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
