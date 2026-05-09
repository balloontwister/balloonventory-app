<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    recentUsers: { type: Array, required: true },
    recentlyActive: { type: Array, required: true },
    pendingVerification: { type: Array, required: true },
    emailByDay: { type: Array, required: true },
    emailByMonth: { type: Array, required: true },
});

// Roll up email totals by date (combining mailable types)
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
</script>

<template>
    <Head title="Super Admin" />

    <AuthenticatedLayout>
        <template #header>
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                Super Admin
            </h1>
        </template>

        <div class="flex flex-col gap-6 py-2">

            <!-- ── Key stats ─────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="(value, label) in {
                        'Total users': stats.total_users,
                        'Verified': stats.verified_users,
                        'Unverified': stats.unverified_users,
                        'New (7d)': stats.new_users_7d,
                        'New (30d)': stats.new_users_30d,
                        'Shared SKUs': stats.shared_skus,
                        'Emails today': stats.emails_sent_today,
                        'Emails (30d)': stats.emails_sent_30d,
                    }"
                    :key="label"
                    class="rounded-lg border border-border bg-surface p-4 shadow-pop"
                >
                    <p class="font-sans text-[12px] text-ink-secondary">{{ label }}</p>
                    <p class="mt-1 font-display text-[28px] font-semibold text-ink-primary">
                        {{ value }}
                    </p>
                </div>
            </div>

            <!-- ── Email volume: last 30 days ────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Email sends — last 30 days
                </h2>
                <div v-if="emailDailyTotals.length === 0" class="mt-4 font-sans text-[13px] text-ink-secondary">
                    No emails sent yet.
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="pb-2 font-medium">Date</th>
                                <th class="pb-2 text-right font-medium">Emails sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in emailDailyTotals"
                                :key="row.date"
                                class="border-b border-border/50 last:border-0"
                            >
                                <td class="py-1.5 text-ink-primary">{{ formatDate(row.date) }}</td>
                                <td class="py-1.5 text-right tabular-nums text-ink-primary">{{ row.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Email volume: by month ─────────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Email sends — by month
                </h2>
                <div v-if="emailMonthlyTotals.length === 0" class="mt-4 font-sans text-[13px] text-ink-secondary">
                    No emails sent yet.
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="pb-2 font-medium">Month</th>
                                <th class="pb-2 text-right font-medium">Emails sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in emailMonthlyTotals"
                                :key="row.month"
                                class="border-b border-border/50 last:border-0"
                            >
                                <td class="py-1.5 text-ink-primary">{{ formatMonth(row.month) }}</td>
                                <td class="py-1.5 text-right tabular-nums text-ink-primary">{{ row.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Recently registered ───────────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Recently registered
                </h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="pb-2 font-medium">Name</th>
                                <th class="pb-2 font-medium">Email</th>
                                <th class="pb-2 font-medium">Verified</th>
                                <th class="pb-2 font-medium">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in recentUsers"
                                :key="user.id"
                                class="border-b border-border/50 last:border-0"
                            >
                                <td class="py-1.5 text-ink-primary">{{ user.name }}</td>
                                <td class="py-1.5 text-ink-secondary">{{ user.email }}</td>
                                <td class="py-1.5">
                                    <span
                                        :class="user.email_verified_at
                                            ? 'text-success'
                                            : 'text-danger'"
                                    >
                                        {{ user.email_verified_at ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="py-1.5 text-ink-secondary">{{ formatDate(user.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Recently active ───────────────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Recently active
                </h2>
                <div v-if="recentlyActive.length === 0" class="mt-4 font-sans text-[13px] text-ink-secondary">
                    No login activity recorded yet.
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="pb-2 font-medium">Name</th>
                                <th class="pb-2 font-medium">Email</th>
                                <th class="pb-2 font-medium">Last login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in recentlyActive"
                                :key="user.id"
                                class="border-b border-border/50 last:border-0"
                            >
                                <td class="py-1.5 text-ink-primary">{{ user.name }}</td>
                                <td class="py-1.5 text-ink-secondary">{{ user.email }}</td>
                                <td class="py-1.5 text-ink-secondary">{{ formatDateTime(user.last_login_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Pending verification ──────────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Pending email verification
                    <span
                        v-if="pendingVerification.length > 0"
                        class="ml-2 rounded-full bg-warning-soft px-2 py-0.5 font-sans text-[12px] font-medium text-ink-primary"
                    >
                        {{ pendingVerification.length }}
                    </span>
                </h2>
                <div v-if="pendingVerification.length === 0" class="mt-4 font-sans text-[13px] text-ink-secondary">
                    No accounts pending verification.
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="pb-2 font-medium">Name</th>
                                <th class="pb-2 font-medium">Email</th>
                                <th class="pb-2 font-medium">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in pendingVerification"
                                :key="user.id"
                                class="border-b border-border/50 last:border-0"
                            >
                                <td class="py-1.5 text-ink-primary">{{ user.name }}</td>
                                <td class="py-1.5 text-ink-secondary">{{ user.email }}</td>
                                <td class="py-1.5 text-ink-secondary">{{ formatDate(user.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Shared catalog ────────────────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                            Shared balloon catalog
                        </h2>
                        <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                            {{ stats.shared_skus }} SKUs in the master catalog.
                        </p>
                    </div>
                    <a
                        href="/super-admin/catalog"
                        class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover"
                    >
                        Manage catalog
                    </a>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
