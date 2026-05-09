<script setup>
defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm | md
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center gap-2 rounded-md font-sans text-[14px] font-medium leading-none transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
        :class="{
            'bg-accent px-4 py-[10px] text-accent-on hover:bg-accent-hover':
                variant === 'primary',
            'border border-border-strong bg-surface px-4 py-[10px] text-ink-primary hover:bg-background':
                variant === 'secondary',
            'bg-transparent px-3 py-[10px] text-ink-primary hover:bg-background':
                variant === 'ghost',
            'bg-danger px-4 py-[10px] text-white hover:bg-red-700':
                variant === 'danger',
            'px-3 py-2 text-[13px]': size === 'sm',
        }"
    >
        <slot />
    </button>
</template>
