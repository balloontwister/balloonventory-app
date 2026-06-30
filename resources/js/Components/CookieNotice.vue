<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

// A one-time, dismissible informational notice — NOT a consent gate. The app
// sets only strictly-necessary cookies, so no consent is collected or recorded;
// dismissal is stored purely client-side (per browser). If analytics/marketing
// cookies are ever added, replace this with a real consent manager.
const STORAGE_KEY = 'cookie-notice.dismissed';
const visible = ref(false);

onMounted(() => {
    try {
        visible.value = localStorage.getItem(STORAGE_KEY) !== '1';
    } catch {
        // Storage unavailable (e.g. private mode) — show it; it just won't persist.
        visible.value = true;
    }
});

function dismiss() {
    visible.value = false;
    try {
        localStorage.setItem(STORAGE_KEY, '1');
    } catch {
        // Ignore — the notice simply reappears on the next load.
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="visible"
                class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface px-4 py-3 shadow-modal"
            >
                <div
                    class="mx-auto flex max-w-3xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('legal.cookie_notice.text') }}
                        <Link
                            :href="route('legal.cookies')"
                            class="font-medium text-accent underline"
                        >
                            {{ $t('legal.cookie_notice.learn_more') }}
                        </Link>
                    </p>
                    <button
                        type="button"
                        class="shrink-0 self-end rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover sm:self-auto"
                        @click="dismiss"
                    >
                        {{ $t('legal.cookie_notice.dismiss') }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
