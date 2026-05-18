<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    recentUsers: { type: Array, required: true },
    recentlyActive: { type: Array, required: true },
    pendingVerification: { type: Array, required: true },
    recentlyPruned: { type: Array, required: true },
    emailByDay: { type: Array, required: true },
    emailByMonth: { type: Array, required: true },
    emailTemplates: { type: Array, required: true },
    supportTickets: { type: Array, required: true },
    showArchivedTickets: { type: Boolean, default: false },
});

// ── Ticket actions ────────────────────────────────────────────────────────────
const replyingTo = ref(null);
const replyBody = ref('');
const replyProcessing = ref(false);
const confirmingDelete = ref(null);

function openReply(ticketId) {
    replyingTo.value = ticketId;
    replyBody.value = '';
    confirmingDelete.value = null;
}

function cancelReply() {
    replyingTo.value = null;
    replyBody.value = '';
}

function submitReply(ticket) {
    replyProcessing.value = true;
    router.post(
        route('super-admin.tickets.reply', ticket.id),
        { body: replyBody.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                replyingTo.value = null;
                replyBody.value = '';
                replyProcessing.value = false;
            },
            onError: () => {
                replyProcessing.value = false;
            },
        },
    );
}

function archiveTicket(ticket) {
    router.patch(
        route('super-admin.tickets.archive', ticket.id),
        {},
        { preserveScroll: true },
    );
}

function unarchiveTicket(ticket) {
    router.patch(
        route('super-admin.tickets.unarchive', ticket.id),
        {},
        { preserveScroll: true },
    );
}

function confirmDelete(ticketId) {
    confirmingDelete.value = ticketId;
    replyingTo.value = null;
}

function cancelDelete() {
    confirmingDelete.value = null;
}

function destroyTicket(ticket) {
    router.delete(route('super-admin.tickets.destroy', ticket.id), {
        preserveScroll: true,
    });
    confirmingDelete.value = null;
}

function toggleArchived() {
    router.get(
        route('super-admin.dashboard'),
        { showArchived: !props.showArchivedTickets },
        { preserveScroll: true, preserveState: false },
    );
}

const emailDailyTotals = computed(() => {
    const map = {};
    for (const row of props.emailByDay) {
        map[row.date] = (map[row.date] ?? 0) + row.count;
    }
    return Object.entries(map)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([date, count]) => ({ date, count }));
});

const emailMonthlyTotals = computed(() => {
    const map = {};
    for (const row of props.emailByMonth) {
        map[row.month] = (map[row.month] ?? 0) + row.count;
    }
    return Object.entries(map)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([month, count]) => ({ month, count }));
});

function formatDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(val) {
    if (!val) return '—';
    return new Date(val).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatMonth(val) {
    const [year, month] = val.split('-');
    return new Date(year, month - 1).toLocaleDateString('en-US', {
        month: 'short',
        year: 'numeric',
    });
}

// Section nav — labels resolve via $t() in the template using these keys.
const navItems = [
    { id: 'overview', labelKey: 'super_admin.dashboard.nav.overview' },
    {
        id: 'email-templates',
        labelKey: 'super_admin.dashboard.nav.email_templates',
    },
    {
        id: 'support-tickets',
        labelKey: 'super_admin.dashboard.nav.support_tickets',
    },
    { id: 'catalog', labelKey: 'super_admin.dashboard.nav.catalog' },
    { id: 'users', labelKey: 'super_admin.dashboard.nav.users' },
];

const activeSection = ref('overview');
let observer = null;

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    activeSection.value = entry.target.id;
                }
            }
        },
        { rootMargin: '-30% 0px -60% 0px' },
    );
    for (const item of navItems) {
        const el = document.getElementById(item.id);
        if (el) observer.observe(el);
    }
});

onUnmounted(() => observer?.disconnect());

function scrollToSection(id) {
    document
        .getElementById(id)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head :title="$t('super_admin.dashboard.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <!-- Title flush-left, section nav centered, spacer mirrors title col for balance -->
            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.heading') }}
                </h1>
                <nav class="flex gap-1">
                    <button
                        v-for="item in navItems"
                        :key="item.id"
                        type="button"
                        class="rounded-md px-3 py-1.5 font-sans text-[13px] font-medium transition"
                        :class="
                            activeSection === item.id
                                ? 'bg-accent-soft text-accent'
                                : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                        "
                        @click="scrollToSection(item.id)"
                    >
                        {{ $t(item.labelKey) }}
                    </button>
                </nav>
                <div />
            </div>
        </template>

        <div class="flex flex-col gap-12 py-2">
            <!-- ══ Overview ════════════════════════════════════════════════ -->
            <section id="overview" class="flex scroll-mt-6 flex-col gap-6">
                <h2
                    class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.overview_heading') }}
                </h2>

                <!-- Key stats -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div
                        v-for="stat in [
                            {
                                key: 'total_users',
                                value: stats.total_users,
                            },
                            { key: 'verified', value: stats.verified_users },
                            {
                                key: 'unverified',
                                value: stats.unverified_users,
                            },
                            { key: 'new_7d', value: stats.new_users_7d },
                            { key: 'new_30d', value: stats.new_users_30d },
                            { key: 'shared_skus', value: stats.shared_skus },
                            {
                                key: 'emails_today',
                                value: stats.emails_sent_today,
                            },
                            { key: 'emails_30d', value: stats.emails_sent_30d },
                        ]"
                        :key="stat.key"
                        class="rounded-lg border border-border bg-surface p-4"
                    >
                        <p class="font-sans text-[12px] text-ink-secondary">
                            {{ $t(`super_admin.dashboard.stats.${stat.key}`) }}
                        </p>
                        <p
                            class="mt-1 font-display text-[28px] font-semibold text-ink-primary"
                        >
                            {{ stat.value }}
                        </p>
                    </div>
                </div>

                <!-- Email volume: last 30 days -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3
                        class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{ $t('super_admin.dashboard.email_30d_heading') }}
                    </h3>
                    <div
                        v-if="emailDailyTotals.length === 0"
                        class="mt-4 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('super_admin.dashboard.email_empty') }}
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full font-sans text-[13px]">
                            <thead>
                                <tr
                                    class="border-b border-border text-left text-ink-secondary"
                                >
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t('super_admin.dashboard.col_date')
                                        }}
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_emails_sent',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in emailDailyTotals"
                                    :key="row.date"
                                    class="border-border/50 border-b last:border-0"
                                >
                                    <td class="py-1.5 text-ink-primary">
                                        {{ formatDate(row.date) }}
                                    </td>
                                    <td
                                        class="py-1.5 text-right tabular-nums text-ink-primary"
                                    >
                                        {{ row.count }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Email volume: by month -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3
                        class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{ $t('super_admin.dashboard.email_month_heading') }}
                    </h3>
                    <div
                        v-if="emailMonthlyTotals.length === 0"
                        class="mt-4 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('super_admin.dashboard.email_empty') }}
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full font-sans text-[13px]">
                            <thead>
                                <tr
                                    class="border-b border-border text-left text-ink-secondary"
                                >
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_month',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_emails_sent',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in emailMonthlyTotals"
                                    :key="row.month"
                                    class="border-border/50 border-b last:border-0"
                                >
                                    <td class="py-1.5 text-ink-primary">
                                        {{ formatMonth(row.month) }}
                                    </td>
                                    <td
                                        class="py-1.5 text-right tabular-nums text-ink-primary"
                                    >
                                        {{ row.count }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recently registered -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3
                        class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{ $t('super_admin.dashboard.recent_users_heading') }}
                    </h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full font-sans text-[13px]">
                            <thead>
                                <tr
                                    class="border-b border-border text-left text-ink-secondary"
                                >
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t('super_admin.dashboard.col_name')
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_email',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_verified',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_registered',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in recentUsers"
                                    :key="user.id"
                                    class="border-border/50 border-b last:border-0"
                                    :class="{ 'opacity-50': user.deleted_at }"
                                >
                                    <td
                                        class="py-1.5 text-ink-primary"
                                        :class="{
                                            'line-through': user.deleted_at,
                                        }"
                                    >
                                        {{ user.name }}
                                    </td>
                                    <td
                                        class="py-1.5 text-ink-secondary"
                                        :class="{
                                            'line-through': user.deleted_at,
                                        }"
                                    >
                                        {{ user.original_email ?? user.email }}
                                    </td>
                                    <td class="py-1.5">
                                        <span
                                            v-if="user.deleted_at"
                                            class="italic text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'super_admin.dashboard.pruned_badge',
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-else
                                            :class="
                                                user.email_verified_at
                                                    ? 'text-success'
                                                    : 'text-danger'
                                            "
                                        >
                                            {{
                                                user.email_verified_at
                                                    ? $t(
                                                          'super_admin.dashboard.verified_yes',
                                                      )
                                                    : $t(
                                                          'super_admin.dashboard.verified_no',
                                                      )
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="py-1.5 text-ink-secondary"
                                        :class="{
                                            'line-through': user.deleted_at,
                                        }"
                                    >
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recently active -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3
                        class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{
                            $t('super_admin.dashboard.recently_active_heading')
                        }}
                    </h3>
                    <div
                        v-if="recentlyActive.length === 0"
                        class="mt-4 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('super_admin.dashboard.recently_active_empty') }}
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full font-sans text-[13px]">
                            <thead>
                                <tr
                                    class="border-b border-border text-left text-ink-secondary"
                                >
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t('super_admin.dashboard.col_name')
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_email',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_last_login',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in recentlyActive"
                                    :key="user.id"
                                    class="border-border/50 border-b last:border-0"
                                >
                                    <td class="py-1.5 text-ink-primary">
                                        {{ user.name }}
                                    </td>
                                    <td class="py-1.5 text-ink-secondary">
                                        {{ user.email }}
                                    </td>
                                    <td class="py-1.5 text-ink-secondary">
                                        {{ formatDateTime(user.last_login_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pending verification -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3
                        class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{
                            $t(
                                'super_admin.dashboard.pending_verification_heading',
                            )
                        }}
                        <span
                            v-if="pendingVerification.length > 0"
                            class="ml-2 rounded-full bg-warning-soft px-2 py-0.5 font-sans text-[12px] font-medium text-ink-primary"
                        >
                            {{ pendingVerification.length }}
                        </span>
                    </h3>
                    <div
                        v-if="pendingVerification.length === 0"
                        class="mt-4 font-sans text-[13px] text-ink-secondary"
                    >
                        {{
                            $t(
                                'super_admin.dashboard.pending_verification_empty',
                            )
                        }}
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full font-sans text-[13px]">
                            <thead>
                                <tr
                                    class="border-b border-border text-left text-ink-secondary"
                                >
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t('super_admin.dashboard.col_name')
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_email',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_registered',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in pendingVerification"
                                    :key="user.id"
                                    class="border-border/50 border-b last:border-0"
                                >
                                    <td class="py-1.5 text-ink-primary">
                                        {{ user.name }}
                                    </td>
                                    <td class="py-1.5 text-ink-secondary">
                                        {{ user.email }}
                                    </td>
                                    <td class="py-1.5 text-ink-secondary">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recently pruned -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3
                        class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{
                            $t('super_admin.dashboard.recently_pruned_heading')
                        }}
                        <span
                            v-if="recentlyPruned.length > 0"
                            class="ml-2 rounded-full bg-danger-soft px-2 py-0.5 font-sans text-[12px] font-medium text-danger"
                        >
                            {{ recentlyPruned.length }}
                        </span>
                    </h3>
                    <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                        {{
                            $t(
                                'super_admin.dashboard.recently_pruned_subheading',
                            )
                        }}
                    </p>
                    <div
                        v-if="recentlyPruned.length === 0"
                        class="mt-4 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('super_admin.dashboard.recently_pruned_empty') }}
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full font-sans text-[13px]">
                            <thead>
                                <tr
                                    class="border-b border-border text-left text-ink-secondary"
                                >
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t('super_admin.dashboard.col_name')
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_email',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_registered',
                                            )
                                        }}
                                    </th>
                                    <th class="pb-2 font-medium">
                                        {{
                                            $t(
                                                'super_admin.dashboard.col_pruned',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in recentlyPruned"
                                    :key="user.id"
                                    class="border-border/50 border-b opacity-60 last:border-0"
                                >
                                    <td
                                        class="py-1.5 text-ink-primary line-through"
                                    >
                                        {{ user.name }}
                                    </td>
                                    <td
                                        class="py-1.5 text-ink-secondary line-through"
                                    >
                                        {{ user.original_email }}
                                    </td>
                                    <td class="py-1.5 text-ink-secondary">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="py-1.5 text-danger">
                                        {{ formatDateTime(user.deleted_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ══ Email Templates ════════════════════════════════════════ -->
            <section
                id="email-templates"
                class="flex scroll-mt-6 flex-col gap-4"
            >
                <div>
                    <h2
                        class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{ $t('super_admin.dashboard.templates_heading') }}
                    </h2>
                    <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                        {{ $t('super_admin.dashboard.templates_subheading') }}
                    </p>
                </div>

                <div
                    v-for="template in emailTemplates"
                    :key="template.id"
                    class="rounded-lg border border-border bg-surface p-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                            >
                                {{ template.trigger_description }}
                            </p>
                            <h3
                                class="mt-1 font-sans text-[15px] font-semibold text-ink-primary"
                            >
                                {{ template.label }}
                            </h3>
                            <p
                                v-if="template.last_edited_by"
                                class="mt-1 font-sans text-[12px] text-ink-tertiary"
                            >
                                {{
                                    $t(
                                        'super_admin.dashboard.template_last_edited',
                                        {
                                            timestamp: formatDateTime(
                                                template.updated_at,
                                            ),
                                            name: template.last_edited_by.name,
                                        },
                                    )
                                }}
                            </p>
                        </div>
                        <span
                            v-if="template.is_active"
                            class="shrink-0 rounded-full bg-success-soft px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-success"
                        >
                            {{
                                $t('super_admin.dashboard.template_status_live')
                            }}
                        </span>
                        <span
                            v-else-if="!template.has_body"
                            class="shrink-0 rounded-full bg-background px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary ring-1 ring-border"
                        >
                            {{
                                $t(
                                    'super_admin.dashboard.template_status_unwritten',
                                )
                            }}
                        </span>
                        <span
                            v-else
                            class="shrink-0 rounded-full bg-accent-soft px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                        >
                            {{
                                $t(
                                    'super_admin.dashboard.template_status_draft',
                                )
                            }}
                        </span>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <Link
                            :href="
                                route(
                                    'super-admin.email-templates.edit',
                                    template.id,
                                )
                            "
                            class="rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover"
                        >
                            {{
                                $t('super_admin.dashboard.template_edit_button')
                            }}
                        </Link>
                    </div>
                </div>
            </section>

            <!-- ══ Support Tickets ════════════════════════════════════════ -->
            <section
                id="support-tickets"
                class="flex scroll-mt-6 flex-col gap-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2
                            class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                        >
                            {{ $t('super_admin.dashboard.tickets_heading') }}
                            <span
                                v-if="
                                    !showArchivedTickets &&
                                    supportTickets.length > 0
                                "
                                class="ml-2 rounded-full bg-accent-soft px-2 py-0.5 font-sans text-[12px] font-medium text-accent"
                            >
                                {{ supportTickets.length }}
                            </span>
                        </h2>
                        <p
                            class="mt-1 font-sans text-[13px] text-ink-secondary"
                        >
                            {{
                                showArchivedTickets
                                    ? $t(
                                          'super_admin.dashboard.tickets_subheading_archived',
                                      )
                                    : $t(
                                          'super_admin.dashboard.tickets_subheading_open',
                                      )
                            }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-border-strong px-3 py-1.5 font-sans text-[12px] font-medium text-ink-secondary transition hover:bg-background"
                        @click="toggleArchived"
                    >
                        {{
                            showArchivedTickets
                                ? $t('super_admin.dashboard.tickets_show_open')
                                : $t(
                                      'super_admin.dashboard.tickets_show_archived',
                                  )
                        }}
                    </button>
                </div>

                <div
                    v-if="supportTickets.length === 0"
                    class="rounded-lg border border-dashed border-border-strong bg-surface p-12 text-center"
                >
                    <p
                        class="font-sans text-[14px] font-medium text-ink-secondary"
                    >
                        {{
                            showArchivedTickets
                                ? $t(
                                      'super_admin.dashboard.tickets_empty_archived',
                                  )
                                : $t('super_admin.dashboard.tickets_empty_open')
                        }}
                    </p>
                </div>

                <div v-else class="flex flex-col gap-3">
                    <div
                        v-for="ticket in supportTickets"
                        :key="ticket.id"
                        class="rounded-lg border border-border bg-surface p-5"
                    >
                        <!-- Header -->
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p
                                    class="font-sans text-[15px] font-semibold text-ink-primary"
                                >
                                    {{ ticket.subject }}
                                </p>
                                <p
                                    class="mt-0.5 font-sans text-[13px] text-ink-secondary"
                                >
                                    {{ ticket.user_name }}
                                    <span class="text-ink-tertiary">·</span>
                                    {{ ticket.user_email }}
                                </p>
                            </div>
                            <p
                                class="shrink-0 font-sans text-[12px] text-ink-tertiary"
                            >
                                {{ formatDateTime(ticket.created_at) }}
                            </p>
                        </div>

                        <!-- Original message -->
                        <div
                            class="mt-3 border-t border-border pt-3 font-sans text-[13px] leading-relaxed text-ink-primary"
                            style="white-space: pre-wrap"
                        >
                            {{ ticket.body }}
                        </div>

                        <!-- Replies (archived view) -->
                        <template
                            v-if="ticket.replies && ticket.replies.length > 0"
                        >
                            <div
                                v-for="reply in ticket.replies"
                                :key="reply.id"
                                class="mt-3 rounded-md bg-accent-soft px-4 py-3"
                            >
                                <p
                                    class="mb-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.ticket_reply_eyebrow',
                                            {
                                                timestamp: formatDateTime(
                                                    reply.created_at,
                                                ),
                                            },
                                        )
                                    }}
                                </p>
                                <div
                                    class="font-sans text-[13px] leading-relaxed text-ink-primary"
                                    style="white-space: pre-wrap"
                                >
                                    {{ reply.body }}
                                </div>
                            </div>
                        </template>

                        <!-- Reply form -->
                        <template v-if="replyingTo === ticket.id">
                            <div class="mt-4 border-t border-border pt-4">
                                <textarea
                                    v-model="replyBody"
                                    rows="4"
                                    :placeholder="
                                        $t(
                                            'super_admin.dashboard.ticket_reply_placeholder',
                                            { name: ticket.user_name },
                                        )
                                    "
                                    :disabled="replyProcessing"
                                    class="w-full resize-y rounded-md border border-border-strong bg-background px-3 py-2.5 font-sans text-[13px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:opacity-50"
                                />
                                <div class="mt-2 flex gap-2">
                                    <button
                                        type="button"
                                        :disabled="
                                            replyProcessing || !replyBody.trim()
                                        "
                                        class="rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-40"
                                        @click="submitReply(ticket)"
                                    >
                                        {{
                                            replyProcessing
                                                ? $t(
                                                      'super_admin.dashboard.ticket_reply_submitting',
                                                  )
                                                : $t(
                                                      'super_admin.dashboard.ticket_reply_submit',
                                                  )
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-border-strong px-4 py-2 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                        @click="cancelReply"
                                    >
                                        {{
                                            $t(
                                                'super_admin.dashboard.ticket_reply_cancel',
                                            )
                                        }}
                                    </button>
                                    <p
                                        class="self-center font-sans text-[12px] text-ink-tertiary"
                                    >
                                        {{
                                            $t(
                                                'super_admin.dashboard.ticket_reply_footnote',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Delete confirmation -->
                        <template v-else-if="confirmingDelete === ticket.id">
                            <div
                                class="mt-4 flex items-center gap-3 border-t border-border pt-4"
                            >
                                <p
                                    class="font-sans text-[13px] text-ink-secondary"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.ticket_delete_confirm',
                                        )
                                    }}
                                </p>
                                <button
                                    type="button"
                                    class="rounded-md bg-danger px-3 py-1.5 font-sans text-[13px] font-semibold text-white transition hover:bg-red-700"
                                    @click="destroyTicket(ticket)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.ticket_delete_yes',
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                    @click="cancelDelete"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.ticket_delete_cancel',
                                        )
                                    }}
                                </button>
                            </div>
                        </template>

                        <!-- Action bar -->
                        <template v-else>
                            <div
                                class="mt-4 flex gap-2 border-t border-border pt-4"
                            >
                                <button
                                    v-if="!showArchivedTickets"
                                    type="button"
                                    class="rounded-md bg-accent px-3 py-1.5 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover"
                                    @click="openReply(ticket.id)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.ticket_reply_button',
                                        )
                                    }}
                                </button>
                                <button
                                    v-if="!showArchivedTickets"
                                    type="button"
                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                    @click="archiveTicket(ticket)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.ticket_archive_button',
                                        )
                                    }}
                                </button>
                                <button
                                    v-if="showArchivedTickets"
                                    type="button"
                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                    @click="unarchiveTicket(ticket)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.ticket_unarchive_button',
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1.5 font-sans text-[13px] font-medium text-danger transition hover:bg-danger-soft"
                                    @click="confirmDelete(ticket.id)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.ticket_delete_button',
                                        )
                                    }}
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <!-- ══ Catalog ════════════════════════════════════════════════ -->
            <section id="catalog" class="scroll-mt-6">
                <div class="rounded-lg border border-border bg-surface p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2
                                class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                            >
                                {{
                                    $t('super_admin.dashboard.catalog_heading')
                                }}
                            </h2>
                            <p
                                class="mt-1 font-sans text-[13px] text-ink-secondary"
                            >
                                {{
                                    $t(
                                        'super_admin.dashboard.catalog_subheading',
                                        { count: stats.shared_skus },
                                    )
                                }}
                            </p>
                        </div>
                        <a
                            href="/super-admin/catalog"
                            class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover"
                        >
                            {{ $t('super_admin.dashboard.catalog_manage') }}
                        </a>
                    </div>
                </div>
            </section>

            <!-- ══ Users ══════════════════════════════════════════════════ -->
            <section id="users" class="scroll-mt-6">
                <div class="rounded-lg border border-border bg-surface p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2
                                class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                            >
                                {{ $t('super_admin.dashboard.users_heading') }}
                            </h2>
                            <p
                                class="mt-1 font-sans text-[13px] text-ink-secondary"
                            >
                                {{
                                    $t(
                                        'super_admin.dashboard.users_subheading',
                                        { count: stats.total_users },
                                    )
                                }}
                            </p>
                        </div>
                        <Link
                            :href="route('super-admin.users.index')"
                            class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover"
                        >
                            {{ $t('super_admin.dashboard.users_manage') }}
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
