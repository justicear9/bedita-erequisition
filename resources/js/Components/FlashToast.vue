<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success');
let hideTimer = null;

const flash = computed(() => page.props.flash || {});

const show = (text, kind = 'success') => {
    if (!text) return;
    message.value = text;
    type.value = kind;
    visible.value = true;
    if (hideTimer) {
        window.clearTimeout(hideTimer);
    }
    hideTimer = window.setTimeout(() => {
        visible.value = false;
    }, 4500);
};

watch(
    () => [flash.value.success, flash.value.error],
    ([success, error]) => {
        if (success) {
            show(success, 'success');
        } else if (error) {
            show(error, 'error');
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    if (hideTimer) {
        window.clearTimeout(hideTimer);
    }
});

defineExpose({ show });
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="visible && message"
                class="pointer-events-none fixed inset-x-0 bottom-20 z-[60] flex justify-center px-4 sm:inset-x-auto sm:bottom-auto sm:right-6 sm:top-6 sm:justify-end"
                role="status"
                aria-live="polite"
            >
                <div
                    class="pointer-events-auto flex max-w-md items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1"
                    :class="
                        type === 'error'
                            ? 'bg-red-600 text-white ring-red-700/40'
                            : 'bg-bedita-navy text-white ring-bedita-navy/40'
                    "
                >
                    <p class="flex-1 font-medium leading-snug">{{ message }}</p>
                    <button
                        type="button"
                        class="shrink-0 rounded-md px-1 text-white/80 hover:text-white"
                        aria-label="Dismiss"
                        @click="visible = false"
                    >
                        ×
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
