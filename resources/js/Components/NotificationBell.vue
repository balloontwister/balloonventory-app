<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    notificationMessageKey,
    notificationMessageParams,
    notificationTimeAgo,
} from '@/Composables/useNotifications';

// Bell + unread badge with a dropdown of recent notifications. Mirrors
// AdminMenu's teleported-dropdown mechanics. Reads the shared `notifications`
// prop ({ unreadCount, recent }), so no extra fetch is needed.
defineProps({
    compact: { type: Boolean, default: false },
});

const page = usePage();

const unreadCount = computed(() => page.props.notifications?.unreadCount ?? 0);
const recent = computed(() => page.props.notifications?.recent ?? []);
const badgeLabel = computed(() => (unreadCount.value > 9 ? '9+' : String(unreadCount.value)));

const open = ref(false);
const menuStyle = ref({});

function toggle(e) {
    if (open.value) {
        open.value = false;
        return;
    }
    const r = e.currentTarget.getBoundingClientRect();
    const width = 320;
    let left = r.right - width;
    if (left < 8) {
        left = 8;
    }
    menuStyle.value = {
        top: `${r.bottom + 6}px`,
        left: `${left}px`,
        width: `${width}px`,
    };
    open.value = true;
}

function close() {
    open.value = false;
}

function markRead(notification) {
    if (notification.read_at) {
        return;
    }
    router.delete(route('notifications.destroy', { notification: notification.id }), {
        preserveScroll: true,
    });
}

function markAllRead() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
}

function onKey(e) {
    if (e.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKey);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
});
onUnmounted(() => {
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
});
</script>

<template>
    <div>
        <button
            type="button"
            :title="$t('dashboard.notifications.title')"
            :aria-label="$t('dashboard.notifications.title')"
            class="relative flex flex-shrink-0 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
            :class="compact ? 'h-8 w-8' : 'h-9 w-9 ring-1 ring-border hover:ring-accent'"
            @click="toggle"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-4 w-4"
            >
                <path
                    fill-rule="evenodd"
                    d="M10 2a6 6 0 00-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 00.515 1.076 32.91 32.91 0 003.256.508 3.5 3.5 0 006.972 0 32.903 32.903 0 003.256-.508.75.75 0 00.515-1.076A11.448 11.448 0 0116 8a6 6 0 00-6-6zm0 14.5a2 2 0 01-1.95-1.557 33.54 33.54 0 003.9 0A2 2 0 0110 16.5z"
                    clip-rule="evenodd"
                />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-sans text-[10px] font-semibold leading-none text-accent-on"
            >
                {{ badgeLabel }}
            </span>
        </button>

        <Teleport to="body">
            <template v-if="open">
                <div class="fixed inset-0 z-[55]" @click="close" />
                <div
                    class="fixed z-[60] overflow-hidden rounded-md border border-border bg-surface shadow-lg"
                    :style="menuStyle"
                >
                    <div
                        class="flex items-center justify-between border-b border-border px-4 py-2.5"
                    >
                        <p class="font-sans text-[13px] font-semibold text-ink-primary">
                            {{ $t('dashboard.notifications.title') }}
                        </p>
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            class="font-sans text-[12px] font-medium text-accent transition hover:text-accent-hover"
                            @click="markAllRead"
                        >
                            {{ $t('dashboard.notifications.mark_all_read') }}
                        </button>
                    </div>

                    <div class="max-h-80 overflow-y-auto">
                        <p
                            v-if="recent.length === 0"
                            class="px-4 py-6 text-center font-sans text-[13px] text-ink-tertiary"
                        >
                            {{ $t('dashboard.notifications.empty') }}
                        </p>

                        <button
                            v-for="notification in recent"
                            :key="notification.id"
                            type="button"
                            class="flex w-full items-start gap-2.5 px-4 py-2.5 text-left transition hover:bg-background"
                            :class="notification.read_at ? '' : 'bg-accent-soft/40'"
                            @click="markRead(notification)"
                        >
                            <span
                                class="mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                                :class="notification.read_at ? 'bg-transparent' : 'bg-accent'"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block font-sans text-[13px] text-ink-primary">
                                    <template v-if="notificationMessageKey(notification)">
                                        {{ $t(notificationMessageKey(notification), notificationMessageParams(notification)) }}
                                    </template>
                                </span>
                                <span class="mt-0.5 block font-sans text-[11px] text-ink-tertiary">
                                    {{ notificationTimeAgo(notification.created_at) }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>
            </template>
        </Teleport>
    </div>
</template>
