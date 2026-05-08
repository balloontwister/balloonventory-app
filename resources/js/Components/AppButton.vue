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
            'bg-accent text-accent-on hover:bg-accent-hover px-4 py-[10px]':
                variant === 'primary',
            'bg-surface text-ink-primary border border-border-strong hover:bg-background px-4 py-[10px]':
                variant === 'secondary',
            'bg-transparent text-ink-primary hover:bg-background px-3 py-[10px]':
                variant === 'ghost',
            'bg-danger text-white hover:bg-red-700 px-4 py-[10px]':
                variant === 'danger',
            'px-3 py-2 text-[13px]': size === 'sm',
        }"
    >
        <slot />
    </button>
</template>
