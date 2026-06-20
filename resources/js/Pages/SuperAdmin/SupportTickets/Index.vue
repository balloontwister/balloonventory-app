<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    supportTickets: { type: Array, required: true },
    showArchivedTickets: { type: Boolean, default: false },
    openCount: { type: Number, default: 0 },
});

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
        route('admin.tickets.reply', ticket.id),
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
        route('admin.tickets.archive', ticket.id),
        {},
        { preserveScroll: true },
    );
}

function unarchiveTicket(ticket) {
    router.patch(
        route('admin.tickets.unarchive', ticket.id),
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
    router.delete(route('admin.tickets.destroy', ticket.id), {
        preserveScroll: true,
    });
    confirmingDelete.value = null;
}

function toggleArchived() {
    router.get(
        route('admin.tickets.index'),
        { showArchived: !props.showArchivedTickets },
        { preserveScroll: true, preserveState: false },
    );
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
    <Head :title="$t('super_admin.dashboard.tickets_heading')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.tickets_heading') }}
                </h1>
                <AdminBackLink />
            </div>
        </template>

        <div class="flex flex-col gap-4 py-2">
            <div class="flex items-start justify-between gap-4">
                <p class="font-sans text-[13px] text-ink-secondary">
                    {{
                        showArchivedTickets
                            ? $t('super_admin.dashboard.tickets_subheading_archived')
                            : $t('super_admin.dashboard.tickets_subheading_open')
                    }}
                </p>
                <button
                    type="button"
                    class="shrink-0 rounded-md border border-border-strong px-3 py-1.5 font-sans text-[12px] font-medium text-ink-secondary transition hover:bg-background"
                    @click="toggleArchived"
                >
                    {{
                        showArchivedTickets
                            ? $t('super_admin.dashboard.tickets_show_open')
                            : $t('super_admin.dashboard.tickets_show_archived')
                    }}
                </button>
            </div>

            <div
                v-if="supportTickets.length === 0"
                class="rounded-lg border border-dashed border-border-strong bg-surface p-12 text-center"
            >
                <p class="font-sans text-[14px] font-medium text-ink-secondary">
                    {{
                        showArchivedTickets
                            ? $t('super_admin.dashboard.tickets_empty_archived')
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

                    <div
                        class="mt-3 border-t border-border pt-3 font-sans text-[13px] leading-relaxed text-ink-primary"
                        style="white-space: pre-wrap"
                    >
                        {{ ticket.body }}
                    </div>

                    <template v-if="ticket.replies && ticket.replies.length > 0">
                        <div
                            v-for="reply in ticket.replies"
                            :key="reply.id"
                            class="mt-3 rounded-md bg-accent-soft px-4 py-3"
                        >
                            <p
                                class="mb-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                            >
                                {{
                                    $t('super_admin.dashboard.ticket_reply_eyebrow', {
                                        timestamp: formatDateTime(reply.created_at),
                                    })
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

                    <template v-if="replyingTo === ticket.id">
                        <div class="mt-4 border-t border-border pt-4">
                            <textarea
                                v-model="replyBody"
                                rows="4"
                                :placeholder="
                                    $t('super_admin.dashboard.ticket_reply_placeholder', {
                                        name: ticket.user_name,
                                    })
                                "
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
                                    {{
                                        replyProcessing
                                            ? $t('super_admin.dashboard.ticket_reply_submitting')
                                            : $t('super_admin.dashboard.ticket_reply_submit')
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-border-strong px-4 py-2 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                    @click="cancelReply"
                                >
                                    {{ $t('super_admin.dashboard.ticket_reply_cancel') }}
                                </button>
                                <p
                                    class="self-center font-sans text-[12px] text-ink-tertiary"
                                >
                                    {{ $t('super_admin.dashboard.ticket_reply_footnote') }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="confirmingDelete === ticket.id">
                        <div
                            class="mt-4 flex items-center gap-3 border-t border-border pt-4"
                        >
                            <p class="font-sans text-[13px] text-ink-secondary">
                                {{ $t('super_admin.dashboard.ticket_delete_confirm') }}
                            </p>
                            <button
                                type="button"
                                class="rounded-md bg-danger px-3 py-1.5 font-sans text-[13px] font-semibold text-white transition hover:bg-red-700"
                                @click="destroyTicket(ticket)"
                            >
                                {{ $t('super_admin.dashboard.ticket_delete_yes') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                @click="cancelDelete"
                            >
                                {{ $t('super_admin.dashboard.ticket_delete_cancel') }}
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <div class="mt-4 flex gap-2 border-t border-border pt-4">
                            <button
                                v-if="!showArchivedTickets"
                                type="button"
                                class="rounded-md bg-accent px-3 py-1.5 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover"
                                @click="openReply(ticket.id)"
                            >
                                {{ $t('super_admin.dashboard.ticket_reply_button') }}
                            </button>
                            <button
                                v-if="!showArchivedTickets"
                                type="button"
                                class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                @click="archiveTicket(ticket)"
                            >
                                {{ $t('super_admin.dashboard.ticket_archive_button') }}
                            </button>
                            <button
                                v-if="showArchivedTickets"
                                type="button"
                                class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                @click="unarchiveTicket(ticket)"
                            >
                                {{ $t('super_admin.dashboard.ticket_unarchive_button') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 font-sans text-[13px] font-medium text-danger transition hover:bg-danger-soft"
                                @click="confirmDelete(ticket.id)"
                            >
                                {{ $t('super_admin.dashboard.ticket_delete_button') }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
