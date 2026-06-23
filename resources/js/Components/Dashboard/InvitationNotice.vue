<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    invitation: { type: Object, required: true },
});

const acceptForm = useForm({ token: props.invitation.token });
const declineForm = useForm({ token: props.invitation.token });

function accept() {
    acceptForm.post(route('invitations.accept-in-app'), { preserveScroll: true });
}

function decline() {
    declineForm.post(route('invitations.decline'), { preserveScroll: true });
}
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-lg border border-accent/30 bg-accent-soft px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="font-sans text-[13px] text-ink-primary">
            {{ $t('dashboard.invitations.notice', { inviter: invitation.inviter_name, business: invitation.business_name, role: invitation.role_label }) }}
        </p>
        <div class="flex flex-shrink-0 gap-2">
            <button
                type="button"
                :disabled="acceptForm.processing || declineForm.processing"
                class="rounded-md bg-accent px-3 py-1.5 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                @click="accept"
            >
                {{ $t('dashboard.invitations.accept') }}
            </button>
            <button
                type="button"
                :disabled="acceptForm.processing || declineForm.processing"
                class="rounded-md border border-border-strong bg-surface px-3 py-1.5 font-sans text-[13px] font-semibold text-ink-primary transition hover:bg-background disabled:opacity-40"
                @click="decline"
            >
                {{ $t('dashboard.invitations.decline') }}
            </button>
        </div>
    </div>
</template>
