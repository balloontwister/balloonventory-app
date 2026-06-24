<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    notificationMessageKey,
    notificationMessageParams,
    notificationTimeAgo,
} from '@/Composables/useNotifications';

const props = defineProps({
    notification: { type: Object, required: true },
});

const page = usePage();

const messageKey = computed(() => notificationMessageKey(props.notification));
const messageParams = computed(() => notificationMessageParams(props.notification));
const timeAgo = computed(() => notificationTimeAgo(props.notification.created_at));
const isUnread = computed(() => !props.notification.read_at);

// Offer "Switch" whenever the notice is about a business other than the active one.
const canSwitch = computed(
    () =>
        !!props.notification.business_id &&
        page.props.business?.id !== props.notification.business_id,
);

const switchForm = useForm({ business: props.notification.business_id });
const dismissForm = useForm({});

function switchBusiness() {
    switchForm.post(route('business.switch', { business: props.notification.business_id }), {
        preserveScroll: true,
    });
}

function dismiss() {
    dismissForm.delete(route('notifications.destroy', { notification: props.notification.id }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-lg border border-border bg-surface px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        :class="isUnread ? 'border-l-4 border-l-accent' : ''"
    >
        <div class="min-w-0">
            <p v-if="messageKey" class="font-sans text-[13px] text-ink-primary">
                {{ $t(messageKey, messageParams) }}
            </p>
            <p class="mt-0.5 font-sans text-[11px] text-ink-tertiary">{{ timeAgo }}</p>
        </div>
        <div class="flex flex-shrink-0 gap-2">
            <button
                v-if="canSwitch"
                type="button"
                :disabled="switchForm.processing || dismissForm.processing"
                class="rounded-md bg-accent px-3 py-1.5 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                @click="switchBusiness"
            >
                {{ $t('dashboard.notifications.switch', { business: notification.business_name }) }}
            </button>
            <button
                type="button"
                :disabled="switchForm.processing || dismissForm.processing"
                class="rounded-md border border-border-strong bg-surface px-3 py-1.5 font-sans text-[13px] font-semibold text-ink-primary transition hover:bg-background disabled:opacity-40"
                @click="dismiss"
            >
                {{ $t('dashboard.notifications.dismiss') }}
            </button>
        </div>
    </div>
</template>
