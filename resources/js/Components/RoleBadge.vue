<script setup>
import { roleLabelFor } from '@/Composables/useBusiness';

defineProps({
    role: { type: String, required: true }, // owner | manager | staff | guest | none
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-pill px-2 py-0.5 font-sans text-[11px] font-semibold uppercase leading-none tracking-eyebrow"
        :class="{
            'bg-ink-primary text-surface': role === 'owner',
            'bg-accent-soft text-accent': role === 'manager',
            'border border-border bg-surface text-ink-secondary': role === 'staff',
            'text-ink-tertiary': role === 'guest',
            'border border-dashed border-border text-ink-tertiary opacity-60': role === 'none',
        }"
    >
        {{ roleLabelFor(role) }}
    </span>
</template>
