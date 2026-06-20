<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import { Head, Link } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps({
    user: { type: Object, required: true },
    businesses: { type: Array, default: () => [] },
    feedback: { type: Array, default: () => [] },
    tickets: { type: Array, default: () => [] },
    emails: { type: Array, default: () => [] },
    loginEvents: { type: Array, default: () => [] },
});

const CONTACT_FIELDS = ['phone', 'website', 'city', 'country'];

// Long lists preview the 5 most recent and expand on demand (these come from
// the controller capped at 50). Keyed per section.
const PREVIEW = 5;
const expanded = reactive({
    feedback: false,
    tickets: false,
    emails: false,
    login: false,
});

function visible(list, key) {
    return expanded[key] ? list : list.slice(0, PREVIEW);
}

// Lightweight device label from a user-agent string (no dependency). Falls back
// to the raw string (shown in full on hover via the title attribute).
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

function formatDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

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

// EmailLog stores the fully-qualified mailable class; show just the short name.
function mailableLabel(mailable) {
    if (!mailable) return '—';
    return mailable.split('\\').pop();
}
</script>

<template>
    <Head :title="user.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-2">
                    <Link
                        :href="route('admin.users.index')"
                        class="font-sans text-[14px] text-ink-secondary transition hover:text-ink-primary"
                    >
                        {{ $t('super_admin.dashboard.nav.users') }}
                    </Link>
                    <span class="text-ink-tertiary">/</span>
                    <h1
                        class="truncate font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                    >
                        {{ user.name }}
                    </h1>
                </div>
                <AdminBackLink />
            </div>
        </template>

        <div class="mx-auto flex max-w-5xl flex-col gap-6 py-2">
            <!-- Identity summary -->
            <div class="rounded-lg border border-border bg-surface p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-4">
                        <img
                            :src="user.avatar_url"
                            :alt="user.name"
                            class="h-16 w-16 flex-shrink-0 rounded-full object-cover ring-2 ring-border"
                        />
                        <div class="min-w-0">
                            <p
                                class="font-display text-[18px] font-semibold text-ink-primary"
                            >
                                {{ user.name }}
                            </p>
                            <p
                                class="mt-0.5 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ user.original_email ?? user.email }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="user.deleted_at"
                            class="rounded-full bg-background px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.users.status_deleted') }}
                        </span>
                        <span
                            v-else-if="user.frozen_at"
                            class="rounded-full bg-warning-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-warning"
                        >
                            {{ $t('super_admin.users.status_frozen') }}
                        </span>
                        <span
                            v-if="user.admin_level === 'super_admin'"
                            class="rounded-full bg-accent-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                        >
                            {{ $t('super_admin.users.level_super_admin') }}
                        </span>
                        <span
                            v-else-if="user.admin_level === 'site_admin'"
                            class="rounded-full bg-success-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-success"
                        >
                            {{ $t('super_admin.users.level_site_admin') }}
                        </span>
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                    <div>
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.users.col_registered') }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-primary">
                            {{ formatDateTime(user.created_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.users.col_last_login') }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-primary">
                            {{ formatDateTime(user.last_login_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.user_detail.email_verified') }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-primary">
                            {{ formatDateTime(user.email_verified_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.user_detail.locale') }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-primary">
                            {{ user.locale ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.user_detail.timezone') }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-primary">
                            {{ user.timezone ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Contact (not collected yet — placeholders) -->
            <section class="rounded-lg border border-border bg-surface p-5">
                <h2
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.user_detail.sections.contact') }}
                </h2>
                <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
                    <div v-for="field in CONTACT_FIELDS" :key="field">
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t(`super_admin.user_detail.contact.${field}`) }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-tertiary italic">
                            {{ $t('super_admin.user_detail.contact.not_collected') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Businesses -->
            <section class="rounded-lg border border-border bg-surface p-5">
                <h2
                    class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.user_detail.sections.businesses') }}
                </h2>
                <p
                    v-if="businesses.length === 0"
                    class="font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('super_admin.user_detail.biz_empty') }}
                </p>
                <div v-else class="flex flex-col gap-2">
                    <div
                        v-for="b in businesses"
                        :key="b.id"
                        class="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2"
                    >
                        <span class="font-sans text-[14px] font-medium text-ink-primary">
                            {{ b.name }}
                        </span>
                        <span class="font-sans text-[12px] text-ink-secondary">
                            {{ b.role }}
                            <span class="text-ink-tertiary">·</span>
                            {{ $t('super_admin.user_detail.biz_joined') }}
                            {{ formatDate(b.joined_at) }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- Item feedback -->
            <section class="rounded-lg border border-border bg-surface p-5">
                <h2
                    class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.user_detail.sections.feedback') }}
                    <span v-if="feedback.length" class="text-ink-tertiary">
                        ({{ feedback.length }})
                    </span>
                </h2>
                <p
                    v-if="feedback.length === 0"
                    class="font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('super_admin.user_detail.feedback_empty') }}
                </p>
                <div v-else class="flex flex-col gap-2">
                    <div
                        v-for="f in visible(feedback, 'feedback')"
                        :key="f.id"
                        class="rounded-md border border-border px-3 py-2"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <Link
                                    :href="route('admin.catalog.skus.show', f.sku_id)"
                                    class="font-sans text-[14px] font-medium text-accent hover:underline"
                                >
                                    {{ f.sku_name }}
                                </Link>
                                <p class="font-sans text-[12px] text-ink-secondary">
                                    {{ $t(`inventory.show.feedback_field_${f.field}`) }}
                                    <template v-if="f.suggested_value">
                                        <span class="text-ink-tertiary">
                                            · {{ $t('super_admin.user_detail.feedback_says') }}
                                        </span>
                                        {{ f.suggested_value }}
                                    </template>
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span
                                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow"
                                    :class="
                                        f.status === 'open'
                                            ? 'text-warning'
                                            : 'text-ink-tertiary'
                                    "
                                >
                                    {{ $t(`super_admin.dashboard.feedback.status_${f.status}`) }}
                                </span>
                                <p class="font-sans text-[12px] text-ink-tertiary">
                                    {{ formatDate(f.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <button
                        v-if="feedback.length > PREVIEW"
                        type="button"
                        class="mt-1 self-start font-sans text-[13px] font-medium text-accent hover:underline"
                        @click="expanded.feedback = !expanded.feedback"
                    >
                        {{
                            expanded.feedback
                                ? $t('super_admin.user_detail.show_less')
                                : $t('super_admin.user_detail.show_more', {
                                      count: feedback.length - PREVIEW,
                                  })
                        }}
                    </button>
                </div>
            </section>

            <!-- Support tickets -->
            <section class="rounded-lg border border-border bg-surface p-5">
                <h2
                    class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.user_detail.sections.tickets') }}
                    <span v-if="tickets.length" class="text-ink-tertiary">
                        ({{ tickets.length }})
                    </span>
                </h2>
                <p
                    v-if="tickets.length === 0"
                    class="font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('super_admin.user_detail.tickets_empty') }}
                </p>
                <div v-else class="flex flex-col gap-2">
                    <Link
                        v-for="t in visible(tickets, 'tickets')"
                        :key="t.id"
                        :href="route('admin.tickets.index')"
                        class="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2 transition hover:bg-background"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-sans text-[14px] font-medium text-ink-primary">
                                {{ t.subject }}
                            </p>
                            <p class="font-sans text-[12px] text-ink-tertiary">
                                {{ $t('super_admin.user_detail.ticket_replies', { count: t.replies_count }) }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span
                                class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow"
                                :class="t.archived_at ? 'text-ink-tertiary' : 'text-warning'"
                            >
                                {{
                                    t.archived_at
                                        ? $t('super_admin.user_detail.ticket_archived')
                                        : $t('super_admin.user_detail.ticket_open')
                                }}
                            </span>
                            <p class="font-sans text-[12px] text-ink-tertiary">
                                {{ formatDate(t.created_at) }}
                            </p>
                        </div>
                    </Link>
                    <button
                        v-if="tickets.length > PREVIEW"
                        type="button"
                        class="mt-1 self-start font-sans text-[13px] font-medium text-accent hover:underline"
                        @click="expanded.tickets = !expanded.tickets"
                    >
                        {{
                            expanded.tickets
                                ? $t('super_admin.user_detail.show_less')
                                : $t('super_admin.user_detail.show_more', {
                                      count: tickets.length - PREVIEW,
                                  })
                        }}
                    </button>
                </div>
            </section>

            <!-- Emails sent -->
            <section class="rounded-lg border border-border bg-surface p-5">
                <h2
                    class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.user_detail.sections.emails') }}
                    <span v-if="emails.length" class="text-ink-tertiary">
                        ({{ emails.length }})
                    </span>
                </h2>
                <p
                    v-if="emails.length === 0"
                    class="font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('super_admin.user_detail.emails_empty') }}
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="py-2 pr-4 font-medium">
                                    {{ $t('super_admin.user_detail.email_subject') }}
                                </th>
                                <th class="py-2 pr-4 font-medium">
                                    {{ $t('super_admin.user_detail.email_type') }}
                                </th>
                                <th class="py-2 text-right font-medium">
                                    {{ $t('super_admin.user_detail.email_sent') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            <tr v-for="e in visible(emails, 'emails')" :key="e.id">
                                <td class="py-2 pr-4 text-ink-primary">{{ e.subject }}</td>
                                <td class="py-2 pr-4 text-ink-tertiary">
                                    {{ mailableLabel(e.mailable) }}
                                </td>
                                <td class="whitespace-nowrap py-2 text-right text-ink-secondary">
                                    {{ formatDateTime(e.sent_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button
                        v-if="emails.length > PREVIEW"
                        type="button"
                        class="mt-2 font-sans text-[13px] font-medium text-accent hover:underline"
                        @click="expanded.emails = !expanded.emails"
                    >
                        {{
                            expanded.emails
                                ? $t('super_admin.user_detail.show_less')
                                : $t('super_admin.user_detail.show_more', {
                                      count: emails.length - PREVIEW,
                                  })
                        }}
                    </button>
                </div>
            </section>

            <!-- Login history -->
            <section class="rounded-lg border border-border bg-surface p-5">
                <h2
                    class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.user_detail.sections.login_history') }}
                    <span v-if="loginEvents.length" class="text-ink-tertiary">
                        ({{ loginEvents.length }})
                    </span>
                </h2>
                <p
                    v-if="loginEvents.length === 0"
                    class="font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('super_admin.user_detail.login_empty') }}
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="py-2 pr-4 font-medium">
                                    {{ $t('super_admin.user_detail.login_when') }}
                                </th>
                                <th class="py-2 pr-4 font-medium">
                                    {{ $t('super_admin.user_detail.login_outcome') }}
                                </th>
                                <th class="py-2 pr-4 font-medium">
                                    {{ $t('super_admin.user_detail.login_ip') }}
                                </th>
                                <th class="py-2 font-medium">
                                    {{ $t('super_admin.user_detail.login_device') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            <tr v-for="e in visible(loginEvents, 'login')" :key="e.id">
                                <td class="whitespace-nowrap py-2 pr-4 text-ink-secondary">
                                    {{ formatDateTime(e.created_at) }}
                                </td>
                                <td class="py-2 pr-4">
                                    <span
                                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow"
                                        :class="
                                            e.event === 'success'
                                                ? 'text-success'
                                                : e.event === 'lockout'
                                                  ? 'text-danger'
                                                  : 'text-warning'
                                        "
                                    >
                                        {{ $t(`super_admin.user_detail.login_event.${e.event}`) }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4 font-mono text-[12px] text-ink-secondary">
                                    {{ e.ip_address ?? '—' }}
                                </td>
                                <td
                                    class="py-2 text-ink-secondary"
                                    :title="e.user_agent"
                                >
                                    {{ deviceLabel(e.user_agent) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button
                        v-if="loginEvents.length > PREVIEW"
                        type="button"
                        class="mt-2 font-sans text-[13px] font-medium text-accent hover:underline"
                        @click="expanded.login = !expanded.login"
                    >
                        {{
                            expanded.login
                                ? $t('super_admin.user_detail.show_less')
                                : $t('super_admin.user_detail.show_more', {
                                      count: loginEvents.length - PREVIEW,
                                  })
                        }}
                    </button>
                </div>
            </section>

            <!-- Ledger — still to come -->
            <section class="rounded-lg border border-border bg-surface p-5">
                <h2
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.user_detail.sections.ledger') }}
                </h2>
                <p
                    class="mt-3 rounded-md border border-dashed border-border px-3 py-6 text-center font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('super_admin.user_detail.coming_soon') }}
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
