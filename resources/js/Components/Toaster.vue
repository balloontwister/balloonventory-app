<script setup>
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toasts, pushToast, dismissToast } from '@/Composables/useToast';

const page = usePage();

// Bridge one-shot server flashes into the toast store. Inertia replaces props on
// every visit, so a flash value is present only on the response that set it.
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) pushToast(flash.success, 'success');
        if (flash.warning) pushToast(flash.warning, 'warning');
        if (flash.error) pushToast(flash.error, 'error');
    },
    { immediate: true, deep: true },
);

const ACCENT = {
    success: 'var(--color-success)',
    error: 'var(--color-danger)',
    warning: 'var(--color-warning)',
    info: 'var(--color-accent)',
};
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed right-4 top-4 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"
        >
            <TransitionGroup
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                leave-active-class="transition duration-300 ease-in"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    class="pointer-events-auto flex items-start gap-2 rounded-md border border-border bg-surface px-3 py-2.5 shadow-lg"
                    :style="{ borderLeft: `4px solid ${ACCENT[t.type] ?? ACCENT.info}` }"
                    role="status"
                >
                    <span
                        class="min-w-0 flex-1 font-sans text-[13px] text-ink-primary"
                    >
                        {{ t.message }}
                    </span>
                    <button
                        type="button"
                        class="-mr-1 -mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded text-ink-tertiary transition hover:text-ink-primary"
                        aria-label="Dismiss"
                        @click="dismissToast(t.id)"
                    >
                        <!-- icon: x-mark -->
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-3.5 w-3.5"
                            aria-hidden="true"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
