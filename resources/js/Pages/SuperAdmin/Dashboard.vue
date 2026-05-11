<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    recentUsers: { type: Array, required: true },
    recentlyActive: { type: Array, required: true },
    pendingVerification: { type: Array, required: true },
    recentlyPruned: { type: Array, required: true },
    emailByDay: { type: Array, required: true },
    emailByMonth: { type: Array, required: true },
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
    router.post(route('super-admin.tickets.reply', ticket.id), { body: replyBody.value }, {
        preserveScroll: true,
        onSuccess: () => { replyingTo.value = null; replyBody.value = ''; replyProcessing.value = false; },
        onError:   () => { replyProcessing.value = false; },
    });
}

function archiveTicket(ticket) {
    router.patch(route('super-admin.tickets.archive', ticket.id), {}, { preserveScroll: true });
}

function unarchiveTicket(ticket) {
    router.patch(route('super-admin.tickets.unarchive', ticket.id), {}, { preserveScroll: true });
}

function confirmDelete(ticketId) {
    confirmingDelete.value = ticketId;
    replyingTo.value = null;
}

function cancelDelete() {
    confirmingDelete.value = null;
}

function destroyTicket(ticket) {
    router.delete(route('super-admin.tickets.destroy', ticket.id), { preserveScroll: true });
    confirmingDelete.value = null;
}

function toggleArchived() {
    router.get(route('super-admin.dashboard'), { showArchived: !props.showArchivedTickets }, { preserveScroll: true, preserveState: false });
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

// Section nav
const navItems = [
    { id: 'overview', label: 'Overview' },
    { id: 'email-templates', label: 'Email Templates' },
    { id: 'support-tickets', label: 'Support Tickets' },
    { id: 'catalog', label: 'Catalog' },
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
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head title="Super Admin" />

    <AuthenticatedLayout>
        <template #header>
            <!-- Title flush-left, section nav centered, spacer mirrors title col for balance -->
            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4">
                <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">
                    Super Admin
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
                        {{ item.label }}
                    </button>
                </nav>
                <div />
            </div>
        </template>

        <div class="flex flex-col gap-12 py-2">

            <!-- ══ Overview ════════════════════════════════════════════════ -->
            <section id="overview" class="flex scroll-mt-6 flex-col gap-6">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Overview
                </h2>

                <!-- Key stats -->
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
                        class="rounded-lg border border-border bg-surface p-4"
                    >
                        <p class="font-sans text-[12px] text-ink-secondary">{{ label }}</p>
                        <p class="mt-1 font-display text-[28px] font-semibold text-ink-primary">
                            {{ value }}
                        </p>
                    </div>
                </div>

                <!-- Email volume: last 30 days -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3 class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary">
                        Email sends — last 30 days
                    </h3>
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

                <!-- Email volume: by month -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3 class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary">
                        Email sends — by month
                    </h3>
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

                <!-- Recently registered -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3 class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary">
                        Recently registered
                    </h3>
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
                                    :class="{ 'opacity-50': user.deleted_at }"
                                >
                                    <td class="py-1.5 text-ink-primary" :class="{ 'line-through': user.deleted_at }">{{ user.name }}</td>
                                    <td class="py-1.5 text-ink-secondary" :class="{ 'line-through': user.deleted_at }">{{ user.original_email ?? user.email }}</td>
                                    <td class="py-1.5">
                                        <span v-if="user.deleted_at" class="italic text-ink-secondary">pruned</span>
                                        <span
                                            v-else
                                            :class="user.email_verified_at ? 'text-success' : 'text-danger'"
                                        >
                                            {{ user.email_verified_at ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="py-1.5 text-ink-secondary" :class="{ 'line-through': user.deleted_at }">{{ formatDate(user.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recently active -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3 class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary">
                        Recently active
                    </h3>
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

                <!-- Pending verification -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3 class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary">
                        Pending email verification
                        <span
                            v-if="pendingVerification.length > 0"
                            class="ml-2 rounded-full bg-warning-soft px-2 py-0.5 font-sans text-[12px] font-medium text-ink-primary"
                        >
                            {{ pendingVerification.length }}
                        </span>
                    </h3>
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

                <!-- Recently pruned -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <h3 class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary">
                        Recently pruned
                        <span
                            v-if="recentlyPruned.length > 0"
                            class="ml-2 rounded-full bg-danger-soft px-2 py-0.5 font-sans text-[12px] font-medium text-danger"
                        >
                            {{ recentlyPruned.length }}
                        </span>
                    </h3>
                    <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                        Accounts removed by the nightly prune for never verifying their email.
                    </p>
                    <div v-if="recentlyPruned.length === 0" class="mt-4 font-sans text-[13px] text-ink-secondary">
                        No accounts pruned yet.
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full font-sans text-[13px]">
                            <thead>
                                <tr class="border-b border-border text-left text-ink-secondary">
                                    <th class="pb-2 font-medium">Name</th>
                                    <th class="pb-2 font-medium">Email</th>
                                    <th class="pb-2 font-medium">Registered</th>
                                    <th class="pb-2 font-medium">Pruned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in recentlyPruned"
                                    :key="user.id"
                                    class="border-b border-border/50 last:border-0 opacity-60"
                                >
                                    <td class="py-1.5 text-ink-primary line-through">{{ user.name }}</td>
                                    <td class="py-1.5 text-ink-secondary line-through">{{ user.original_email }}</td>
                                    <td class="py-1.5 text-ink-secondary">{{ formatDate(user.created_at) }}</td>
                                    <td class="py-1.5 text-danger">{{ formatDateTime(user.deleted_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ══ Email Templates ════════════════════════════════════════ -->
            <section id="email-templates" class="flex scroll-mt-6 flex-col gap-4">
                <div>
                    <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                        Email Templates
                    </h2>
                    <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                        Emails sent by Tallie on behalf of Balloonventory. Compose and save templates here; each one fires automatically on the trigger described below.
                    </p>
                </div>

                <!-- Welcome email -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary">
                                Trigger · Email verified
                            </p>
                            <h3 class="mt-1 font-sans text-[15px] font-semibold text-ink-primary">
                                Welcome to Balloonventory
                            </h3>
                            <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                                Sent automatically after a new user verifies their email address. Sets the tone and introduces Tallie as the friendly face of the product.
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full bg-accent-soft px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent">
                            Not yet active
                        </span>
                    </div>
                    <div class="mt-5 rounded-md border border-dashed border-border-strong bg-background p-5 font-sans text-[13px] text-ink-tertiary">
                        Template editor coming soon. The composed email will appear here and can be previewed before activating.
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            disabled
                            class="cursor-not-allowed rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-accent-on opacity-40"
                        >
                            Edit template
                        </button>
                        <button
                            type="button"
                            disabled
                            class="cursor-not-allowed rounded-md border border-border-strong px-4 py-2 font-sans text-[13px] font-semibold text-ink-tertiary opacity-40"
                        >
                            Send preview
                        </button>
                    </div>
                </div>

                <!-- Subscription confirmation -->
                <div class="rounded-lg border border-border bg-surface p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary">
                                Trigger · Subscription upgraded
                            </p>
                            <h3 class="mt-1 font-sans text-[15px] font-semibold text-ink-primary">
                                Subscription Upgrade Confirmation
                            </h3>
                            <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                                Sent when a user upgrades their subscription plan. Will be activated once subscription tiers are wired in.
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full bg-background px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary ring-1 ring-border">
                            Deferred
                        </span>
                    </div>
                    <div class="mt-5 rounded-md border border-dashed border-border-strong bg-background p-5 font-sans text-[13px] text-ink-tertiary">
                        Template editor coming soon. Will be enabled when subscription management is implemented.
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            disabled
                            class="cursor-not-allowed rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-accent-on opacity-40"
                        >
                            Edit template
                        </button>
                        <button
                            type="button"
                            disabled
                            class="cursor-not-allowed rounded-md border border-border-strong px-4 py-2 font-sans text-[13px] font-semibold text-ink-tertiary opacity-40"
                        >
                            Send preview
                        </button>
                    </div>
                </div>
            </section>

            <!-- ══ Support Tickets ════════════════════════════════════════ -->
            <section id="support-tickets" class="flex scroll-mt-6 flex-col gap-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                            Support Tickets
                            <span
                                v-if="!showArchivedTickets && supportTickets.length > 0"
                                class="ml-2 rounded-full bg-accent-soft px-2 py-0.5 font-sans text-[12px] font-medium text-accent"
                            >
                                {{ supportTickets.length }}
                            </span>
                        </h2>
                        <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                            {{ showArchivedTickets ? 'Archived tickets — replied to or manually dismissed.' : 'Open tickets awaiting a reply.' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-border-strong px-3 py-1.5 font-sans text-[12px] font-medium text-ink-secondary transition hover:bg-background"
                        @click="toggleArchived"
                    >
                        {{ showArchivedTickets ? 'Show open' : 'Show archived' }}
                    </button>
                </div>

                <div v-if="supportTickets.length === 0" class="rounded-lg border border-dashed border-border-strong bg-surface p-12 text-center">
                    <p class="font-sans text-[14px] font-medium text-ink-secondary">
                        {{ showArchivedTickets ? 'No archived tickets.' : 'No open tickets. All clear.' }}
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
                                <p class="font-sans text-[15px] font-semibold text-ink-primary">{{ ticket.subject }}</p>
                                <p class="mt-0.5 font-sans text-[13px] text-ink-secondary">
                                    {{ ticket.user_name }}
                                    <span class="text-ink-tertiary">·</span>
                                    {{ ticket.user_email }}
                                </p>
                            </div>
                            <p class="shrink-0 font-sans text-[12px] text-ink-tertiary">{{ formatDateTime(ticket.created_at) }}</p>
                        </div>

                        <!-- Original message -->
                        <div class="mt-3 border-t border-border pt-3 font-sans text-[13px] leading-relaxed text-ink-primary" style="white-space: pre-wrap;">{{ ticket.body }}</div>

                        <!-- Replies (archived view) -->
                        <template v-if="ticket.replies && ticket.replies.length > 0">
                            <div
                                v-for="reply in ticket.replies"
                                :key="reply.id"
                                class="mt-3 rounded-md bg-accent-soft px-4 py-3"
                            >
                                <p class="mb-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent">Your reply · {{ formatDateTime(reply.created_at) }}</p>
                                <div class="font-sans text-[13px] leading-relaxed text-ink-primary" style="white-space: pre-wrap;">{{ reply.body }}</div>
                            </div>
                        </template>

                        <!-- Reply form -->
                        <template v-if="replyingTo === ticket.id">
                            <div class="mt-4 border-t border-border pt-4">
                                <textarea
                                    v-model="replyBody"
                                    rows="4"
                                    placeholder="Write your reply to {{ ticket.user_name }}…"
                                    :disabled="replyProcessing"
                                    class="w-full resize-y rounded-md border border-border-strong bg-background px-3 py-2.5 font-sans text-[13px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:opacity-50"
                                />
                                <div class="mt-2 flex gap-2">
                                    <button
                                        type="button"
                                        :disabled="replyProcessing || !replyBody.trim()"
                                        class="rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-40"
                                        @click="submitReply(ticket)"
                                    >
                                        {{ replyProcessing ? 'Sending…' : 'Send reply' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-border-strong px-4 py-2 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                        @click="cancelReply"
                                    >
                                        Cancel
                                    </button>
                                    <p class="self-center font-sans text-[12px] text-ink-tertiary">Sends from tallie@ · auto-archives on send</p>
                                </div>
                            </div>
                        </template>

                        <!-- Delete confirmation -->
                        <template v-else-if="confirmingDelete === ticket.id">
                            <div class="mt-4 flex items-center gap-3 border-t border-border pt-4">
                                <p class="font-sans text-[13px] text-ink-secondary">Delete this ticket permanently?</p>
                                <button
                                    type="button"
                                    class="rounded-md bg-danger px-3 py-1.5 font-sans text-[13px] font-semibold text-white transition hover:bg-red-700"
                                    @click="destroyTicket(ticket)"
                                >
                                    Yes, delete
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                    @click="cancelDelete"
                                >
                                    Cancel
                                </button>
                            </div>
                        </template>

                        <!-- Action bar -->
                        <template v-else>
                            <div class="mt-4 flex gap-2 border-t border-border pt-4">
                                <button
                                    v-if="!showArchivedTickets"
                                    type="button"
                                    class="rounded-md bg-accent px-3 py-1.5 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover"
                                    @click="openReply(ticket.id)"
                                >
                                    Reply
                                </button>
                                <button
                                    v-if="!showArchivedTickets"
                                    type="button"
                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                    @click="archiveTicket(ticket)"
                                >
                                    Archive
                                </button>
                                <button
                                    v-if="showArchivedTickets"
                                    type="button"
                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                    @click="unarchiveTicket(ticket)"
                                >
                                    Unarchive
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1.5 font-sans text-[13px] font-medium text-danger transition hover:bg-danger-soft"
                                    @click="confirmDelete(ticket.id)"
                                >
                                    Delete
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
            </section>

        </div>
    </AuthenticatedLayout>
</template>
