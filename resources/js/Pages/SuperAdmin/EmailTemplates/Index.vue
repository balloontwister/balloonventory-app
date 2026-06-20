<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import UserEmailComposer from '@/Pages/SuperAdmin/EmailTemplates/Partials/UserEmailComposer.vue';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    templates: { type: Array, required: true },
    emailByDay: { type: Array, required: true },
    emailByMonth: { type: Array, required: true },
    sentEmails: { type: Array, default: () => [] },
    composeUser: { type: Object, default: null },
    draftTemplates: { type: Array, default: () => [] },
    appUrl: { type: String, default: '' },
});

// Outgoing log: preview the most recent few, expand to the full (≤50) set.
const LOG_PREVIEW = 10;
const logExpanded = ref(false);
const visibleEmails = computed(() =>
    logExpanded.value ? props.sentEmails : props.sentEmails.slice(0, LOG_PREVIEW),
);

// mailable is stored as the class basename; give the direct-message type a label.
function emailType(mailable) {
    if (!mailable) return '—';
    if (mailable === 'AdminUserMessageMail') {
        return trans('super_admin.dashboard.log_type_direct');
    }
    return mailable;
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
    return new Date(val).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatMonth(val) {
    const [year, month] = val.split('-');
    return new Date(year, month - 1).toLocaleDateString(undefined, {
        month: 'short',
        year: 'numeric',
    });
}

function formatDateTime(val) {
    if (!val) return '—';
    return new Date(val).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="$t('super_admin.dashboard.templates_heading')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.nav.email') }}
                </h1>
                <AdminBackLink />
            </div>
        </template>

        <div class="flex flex-col gap-8 py-2">
            <!-- Email a user directly -->
            <UserEmailComposer
                :compose-user="composeUser"
                :draft-templates="draftTemplates"
                :app-url="appUrl"
            />

            <!-- Templates -->
            <section class="flex flex-col gap-4">
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
                    v-for="template in templates"
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
                                    $t('super_admin.dashboard.template_last_edited', {
                                        timestamp: formatDateTime(template.updated_at),
                                        name: template.last_edited_by.name,
                                    })
                                }}
                            </p>
                        </div>
                        <span
                            v-if="template.is_active"
                            class="shrink-0 rounded-full bg-success-soft px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-success"
                        >
                            {{ $t('super_admin.dashboard.template_status_live') }}
                        </span>
                        <span
                            v-else-if="!template.has_body"
                            class="shrink-0 rounded-full bg-background px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary ring-1 ring-border"
                        >
                            {{ $t('super_admin.dashboard.template_status_unwritten') }}
                        </span>
                        <span
                            v-else
                            class="shrink-0 rounded-full bg-accent-soft px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                        >
                            {{ $t('super_admin.dashboard.template_status_draft') }}
                        </span>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <Link
                            :href="route('admin.email-templates.edit', template.id)"
                            class="rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover"
                        >
                            {{ $t('super_admin.dashboard.template_edit_button') }}
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Outgoing email log -->
            <section class="rounded-lg border border-border bg-surface p-6">
                <h2
                    class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.log_heading') }}
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('super_admin.dashboard.log_subheading') }}
                </p>
                <div
                    v-if="sentEmails.length === 0"
                    class="mt-4 font-sans text-[13px] text-ink-secondary"
                >
                    {{ $t('super_admin.dashboard.log_empty') }}
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="pb-2 pr-4 font-medium">
                                    {{ $t('super_admin.dashboard.log_col_to') }}
                                </th>
                                <th class="pb-2 pr-4 font-medium">
                                    {{ $t('super_admin.dashboard.log_col_subject') }}
                                </th>
                                <th class="pb-2 pr-4 font-medium">
                                    {{ $t('super_admin.dashboard.log_col_type') }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ $t('super_admin.dashboard.log_col_sent') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            <tr v-for="email in visibleEmails" :key="email.id">
                                <td class="py-2 pr-4">
                                    <Link
                                        v-if="email.user_id"
                                        :href="route('admin.users.show', email.user_id)"
                                        class="text-accent hover:underline"
                                    >
                                        {{ email.to }}
                                    </Link>
                                    <span v-else class="text-ink-primary">{{ email.to }}</span>
                                </td>
                                <td class="py-2 pr-4 text-ink-primary">{{ email.subject }}</td>
                                <td class="py-2 pr-4 text-ink-tertiary">
                                    {{ emailType(email.mailable) }}
                                </td>
                                <td class="whitespace-nowrap py-2 text-right text-ink-secondary">
                                    {{ formatDateTime(email.sent_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button
                        v-if="sentEmails.length > LOG_PREVIEW"
                        type="button"
                        class="mt-3 font-sans text-[13px] font-medium text-accent hover:underline"
                        @click="logExpanded = !logExpanded"
                    >
                        {{
                            logExpanded
                                ? $t('super_admin.dashboard.log_show_less')
                                : $t('super_admin.dashboard.log_show_more', {
                                      count: sentEmails.length - LOG_PREVIEW,
                                  })
                        }}
                    </button>
                </div>
            </section>

            <!-- Email volume: last 30 days -->
            <section class="rounded-lg border border-border bg-surface p-6">
                <h2
                    class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.email_30d_heading') }}
                </h2>
                <div
                    v-if="emailDailyTotals.length === 0"
                    class="mt-4 font-sans text-[13px] text-ink-secondary"
                >
                    {{ $t('super_admin.dashboard.email_empty') }}
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="pb-2 font-medium">
                                    {{ $t('super_admin.dashboard.col_date') }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ $t('super_admin.dashboard.col_emails_sent') }}
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
            </section>

            <!-- Email volume: by month -->
            <section class="rounded-lg border border-border bg-surface p-6">
                <h2
                    class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.email_month_heading') }}
                </h2>
                <div
                    v-if="emailMonthlyTotals.length === 0"
                    class="mt-4 font-sans text-[13px] text-ink-secondary"
                >
                    {{ $t('super_admin.dashboard.email_empty') }}
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="pb-2 font-medium">
                                    {{ $t('super_admin.dashboard.col_month') }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ $t('super_admin.dashboard.col_emails_sent') }}
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
            </section>
        </div>
    </AuthenticatedLayout>
</template>
