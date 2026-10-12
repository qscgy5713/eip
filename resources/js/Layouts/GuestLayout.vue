<script setup>
import BrandMark from '@/Components/BrandMark.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center bg-gray-50 dark:bg-gray-950 px-4 py-10 transition-colors"
    >
        <div class="absolute top-4 right-4">
            <ThemeToggle />
        </div>

        <Link href="/" class="flex flex-col items-center gap-3">
            <BrandMark size="lg" />
            <span class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-50">{{ $page.props.branding.name }}</span>
        </Link>

        <div
            class="mt-8 w-full overflow-hidden rounded-2xl border border-gray-200 bg-white px-6 py-6 shadow-md dark:border-gray-800 dark:bg-gray-900 sm:max-w-md sm:px-8"
        >
            <slot />
        </div>
    </div>
</template>
