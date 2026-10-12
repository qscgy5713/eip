<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { dialogState } from '@/Components/useDialog';

const input = ref(null);
const value = ref('');
const okBtn = ref(null);

watch(
    () => dialogState.open,
    async (open) => {
        if (!open) return;
        value.value = dialogState.defaultValue;
        await nextTick();
        (dialogState.type === 'prompt' ? input.value : okBtn.value)?.focus();
        if (dialogState.type === 'prompt') input.value?.select();
    },
);

const close = (result) => {
    const resolve = dialogState.resolve;
    dialogState.open = false;
    dialogState.resolve = null;
    resolve?.(result);
};

const ok = () => close(dialogState.type === 'prompt' ? value.value : true);
const cancel = () => close(dialogState.type === 'prompt' ? null : false);

const onKey = (e) => {
    if (!dialogState.open) return;
    if (e.key === 'Escape') {
        e.preventDefault();
        cancel();
    }
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="dialogState.open" class="fixed inset-0 z-[100] flex items-center justify-center px-4">
                <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="cancel"></div>
                <div
                    role="dialog"
                    aria-modal="true"
                    class="relative w-full max-w-sm rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xl p-6"
                >
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">{{ dialogState.title }}</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ dialogState.message }}</p>

                    <form @submit.prevent="ok">
                        <input
                            v-if="dialogState.type === 'prompt'"
                            ref="input"
                            v-model="value"
                            type="text"
                            class="mt-4 w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <div class="mt-6 flex justify-end gap-2">
                            <button
                                v-if="dialogState.type !== 'alert'"
                                type="button"
                                class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                @click="cancel"
                            >
                                取消
                            </button>
                            <button
                                ref="okBtn"
                                type="submit"
                                :class="[
                                    'px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800',
                                    dialogState.danger && dialogState.type === 'confirm'
                                        ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
                                        : 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500',
                                ]"
                            >
                                {{ dialogState.type === 'alert' ? '知道了' : '確定' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
