<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NotificationCard from '@/Components/Dashboard/NotificationCard.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    notifications: { type: Object, required: true },
    filter: { type: String, default: 'all' },
    unreadCount: { type: Number, default: 0 },
});

function markAllRead() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('dashboard.notifications.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-ink-primary">
                {{ $t('dashboard.notifications.title') }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-4">
            <!-- Filter + mark all read -->
            <div class="flex items-center justify-between">
                <div class="flex gap-1">
                    <Link
                        :href="route('notifications.index')"
                        preserve-scroll
                        class="rounded-md px-3 py-1.5 font-sans text-[13px] font-medium transition"
                        :class="
                            filter === 'all'
                                ? 'bg-accent-soft text-accent'
                                : 'text-ink-secondary hover:bg-background'
                        "
                    >
                        {{ $t('dashboard.notifications.filter_all') }}
                    </Link>
                    <Link
                        :href="route('notifications.index', { filter: 'unread' })"
                        preserve-scroll
                        class="rounded-md px-3 py-1.5 font-sans text-[13px] font-medium transition"
                        :class="
                            filter === 'unread'
                                ? 'bg-accent-soft text-accent'
                                : 'text-ink-secondary hover:bg-background'
                        "
                    >
                        {{ $t('dashboard.notifications.filter_unread') }}
                    </Link>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="font-sans text-[13px] font-medium text-accent transition hover:text-accent-hover"
                    @click="markAllRead"
                >
                    {{ $t('dashboard.notifications.mark_all_read') }}
                </button>
            </div>

            <!-- Empty state -->
            <div
                v-if="notifications.data.length === 0"
                class="rounded-lg border border-border bg-surface p-10 text-center font-sans text-[14px] text-ink-tertiary"
            >
                {{ $t('dashboard.notifications.empty') }}
            </div>

            <!-- List -->
            <div v-else class="flex flex-col gap-3">
                <NotificationCard
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    :notification="notification"
                />
            </div>

            <!-- Pagination -->
            <div
                v-if="notifications.last_page > 1"
                class="flex items-center justify-between pt-2"
            >
                <p class="font-sans text-[13px] text-ink-secondary">
                    {{ notifications.current_page }} / {{ notifications.last_page }}
                </p>
                <div class="flex gap-2">
                    <Link
                        v-if="notifications.prev_page_url"
                        :href="notifications.prev_page_url"
                        preserve-scroll
                        class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                    >
                        ‹
                    </Link>
                    <Link
                        v-if="notifications.next_page_url"
                        :href="notifications.next_page_url"
                        preserve-scroll
                        class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                    >
                        ›
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
