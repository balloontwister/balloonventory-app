<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    href: { type: String, default: null }, // when set, renders an Inertia <Link>
    variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm | md
});

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-md font-sans text-[14px] font-medium leading-none transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40',
    {
        'bg-accent px-4 py-[10px] text-accent-on hover:bg-accent-hover':
            props.variant === 'primary',
        'border border-border-strong bg-surface px-4 py-[10px] text-ink-primary hover:bg-background':
            props.variant === 'secondary',
        'bg-transparent px-3 py-[10px] text-ink-primary hover:bg-background':
            props.variant === 'ghost',
        'bg-danger px-4 py-[10px] text-white hover:bg-red-700':
            props.variant === 'danger',
        'px-3 py-2 text-[13px]': props.size === 'sm',
    },
]);
</script>

<template>
    <!-- With an href (and not disabled) this is navigation, so render a real link. -->
    <Link v-if="href && !disabled" :href="href" :class="classes">
        <slot />
    </Link>
    <button v-else :type="type" :disabled="disabled" :class="classes">
        <slot />
    </button>
</template>
