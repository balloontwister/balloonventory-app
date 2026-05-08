<script setup>
defineProps({
    state: { type: String, default: null }, // success | warning | danger — adds left stripe
    title: { type: String, default: null },
    padding: { type: Boolean, default: true },
});
</script>

<template>
    <div
        class="relative rounded-lg border border-border bg-surface overflow-hidden"
        :class="{ 'border-l-4': state }"
        :style="state ? { borderLeftColor: `var(--color-${state})` } : {}"
    >
        <div :class="{ 'p-5': padding }">
            <h3 v-if="title" class="mb-4 font-sans text-[18px] font-semibold leading-[1.3] tracking-h3 text-ink-primary">
                {{ title }}
            </h3>
            <slot />
        </div>
    </div>
</template>
