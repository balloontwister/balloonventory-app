<script setup>
import { roleLabelFor } from '@/Composables/useBusiness';

const props = defineProps({
    role: { type: String, required: true }, // owner | manager | staff | guest | none
    // When the business is suspended, the badge reads "Suspended" regardless of
    // the member's actual role — they effectively have No Access until restored.
    frozen: { type: Boolean, default: false },
});
</script>

<template>
    <span
        v-if="frozen"
        class="inline-flex items-center rounded-pill bg-warning-soft px-2 py-0.5 font-sans text-[11px] font-semibold uppercase leading-none tracking-eyebrow text-warning"
    >
        {{ $t('nav.suspended') }}
    </span>
    <span
        v-else
        class="inline-flex items-center rounded-pill px-2 py-0.5 font-sans text-[11px] font-semibold uppercase leading-none tracking-eyebrow"
        :class="{
            'bg-ink-primary text-surface': role === 'owner',
            'bg-accent-soft text-accent': role === 'manager',
            'border border-border bg-surface text-ink-secondary': role === 'staff',
            'text-ink-tertiary': role === 'guest',
            'border border-dashed border-border text-ink-tertiary opacity-60': role === 'none',
        }"
    >
        {{ roleLabelFor(props.role) }}
    </span>
</template>
