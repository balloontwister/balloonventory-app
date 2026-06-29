<script setup>
/**
 * Shared search box for filter-as-you-type lists (admin Users/Businesses,
 * Inventory). It standardizes the presentation only — `type="search"` (so every
 * search field gets the browser's native clear "×"), the app's token styling,
 * and v-model. Debounce / navigation stays with each page, because pages debounce
 * differently (e.g. Inventory debounces search together with its other filters).
 *
 * Width/layout falls through via `class` (e.g. `class="w-72"` or `class="flex-1"`);
 * `placeholder` and other input attrs fall through too.
 *
 * This is NOT for the Scan field — that's a barcode capture input (commit on
 * Enter, auto-refocus), a different contract. See ScanField.vue.
 */
defineProps({
    modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <input
        type="search"
        :value="modelValue"
        class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>
