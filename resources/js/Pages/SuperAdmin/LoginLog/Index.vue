<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    events: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    failed7d: { type: Number, default: 0 },
});

const search = ref(props.filters.search ?? '');
const event = ref(props.filters.event ?? '');

const EVENT_FILTERS = [
    { value: '', label: 'super_admin.login_log.filter_all' },
    { value: 'success', label: 'super_admin.user_detail.login_event.success' },
    { value: 'failed', label: 'super_admin.user_detail.login_event.failed' },
    { value: 'lockout', label: 'super_admin.user_detail.login_event.lockout' },
];

function navigate() {
    router.get(
        route('admin.login-log.index'),
        {
            search: search.value || undefined,
            event: event.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let debounce;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(navigate, 350);
});
watch(event, navigate);

function formatDateTime(val) {
    if (!val) return '—';
    return new Date(val).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function deviceLabel(ua) {
    if (!ua) return '—';
    const browser =
        /Edg\//.test(ua) ? 'Edge'
        : /OPR\/|Opera/.test(ua) ? 'Opera'
        : /Chrome\//.test(ua) ? 'Chrome'
        : /Firefox\//.test(ua) ? 'Firefox'
        : /Safari\//.test(ua) ? 'Safari'
        : null;
    const os =
        /iPhone|iPad|iPod/.test(ua) ? 'iOS'
        : /Android/.test(ua) ? 'Android'
        : /Mac OS X/.test(ua) ? 'macOS'
        : /Windows/.test(ua) ? 'Windows'
        : /Linux/.test(ua) ? 'Linux'
        : null;
    if (browser && os) return `${browser} · ${os}`;
    return browser || os || ua.slice(0, 40);
}

function outcomeClass(e) {
    if (e === 'success') return 'text-success';
    if (e === 'lockout') return 'text-danger';
    return 'text-warning';
}
</script>

<template>
    <Head :title="$t('super_admin.login_log.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.login_log.heading') }}
                </h1>
                <AdminBackLink />
            </div>
        </template>

        <div class="py-2">
            <div class="rounded-lg border border-border bg-surface">
                <div class="border-b border-border px-6 py-4">
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('super_admin.login_log.subheading') }}
                        <span
                            v-if="failed7d > 0"
                            class="ml-1 rounded-full bg-warning-soft px-2 py-0.5 font-sans text-[12px] font-semibold text-warning"
                        >
                            {{ $t('super_admin.login_log.failed_badge', { count: failed7d }) }}
                        </span>
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="$t('super_admin.login_log.search_placeholder')"
                            class="w-72 max-w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        />
                        <select
                            v-model="event"
                            class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        >
                            <option
                                v-for="opt in EVENT_FILTERS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ $t(opt.label) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.login_log.col_when') }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.login_log.col_user') }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.login_log.col_outcome') }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.login_log.col_ip') }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.login_log.col_device') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="events.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-6 py-10 text-center text-ink-tertiary"
                                >
                                    {{ $t('super_admin.login_log.empty') }}
                                </td>
                            </tr>
                            <tr
                                v-for="e in events.data"
                                :key="e.id"
                                class="text-ink-primary"
                            >
                                <td class="whitespace-nowrap px-6 py-3 text-ink-secondary">
                                    {{ formatDateTime(e.created_at) }}
                                </td>
                                <td class="px-6 py-3">
                                    <Link
                                        v-if="e.user"
                                        :href="route('admin.users.show', e.user.id)"
                                        class="text-accent hover:underline"
                                    >
                                        {{ e.user.name }}
                                    </Link>
                                    <span v-else class="text-ink-tertiary">
                                        {{ e.email ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <span
                                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow"
                                        :class="outcomeClass(e.event)"
                                    >
                                        {{ $t(`super_admin.user_detail.login_event.${e.event}`) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 font-mono text-[12px] text-ink-secondary">
                                    {{ e.ip_address ?? '—' }}
                                </td>
                                <td
                                    class="px-6 py-3 text-ink-secondary"
                                    :title="e.user_agent"
                                >
                                    {{ deviceLabel(e.user_agent) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="events.last_page > 1"
                    class="flex items-center justify-between border-t border-border px-6 py-3"
                >
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ events.current_page }} / {{ events.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="events.prev_page_url"
                            :href="events.prev_page_url"
                            preserve-state
                            preserve-scroll
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ‹
                        </Link>
                        <Link
                            v-if="events.next_page_url"
                            :href="events.next_page_url"
                            preserve-state
                            preserve-scroll
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ›
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
