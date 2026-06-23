<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    notice: { type: Object, required: true },
});

const switchForm = useForm({ business: props.notice.business_id });
const ackForm = useForm({ invitation_id: props.notice.invitation_id });

function switchBusiness() {
    switchForm.post(route('business.switch', { business: props.notice.business_id }), {
        preserveScroll: true,
    });
}

function dismiss() {
    ackForm.post(route('invitations.acknowledge'), { preserveScroll: true });
}
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-lg border border-success/30 bg-success-soft px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="font-sans text-[13px] text-ink-primary">
            {{ $t('dashboard.membership_status.notice', { business: notice.business_name, role: notice.role_label }) }}
        </p>
        <div class="flex flex-shrink-0 gap-2">
            <button
                type="button"
                :disabled="switchForm.processing || ackForm.processing"
                class="rounded-md bg-accent px-3 py-1.5 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                @click="switchBusiness"
            >
                {{ $t('dashboard.membership_status.switch') }}
            </button>
            <button
                type="button"
                :disabled="switchForm.processing || ackForm.processing"
                class="rounded-md border border-border-strong bg-surface px-3 py-1.5 font-sans text-[13px] font-semibold text-ink-primary transition hover:bg-background disabled:opacity-40"
                @click="dismiss"
            >
                {{ $t('dashboard.membership_status.dismiss') }}
            </button>
        </div>
    </div>
</template>
