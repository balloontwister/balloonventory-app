<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: { type: String, required: true }, // e.g. '5"', '11"', '260', 'Heart', 'Geo Blossom'
});

const isNumeric = computed(() => /^[\d.]+/.test(props.size));
</script>

<template>
    <span
        class="inline-flex items-center rounded-sm border border-border px-2 py-0.5 leading-none"
        :class="isNumeric ? 'font-mono text-[12px]' : 'font-sans text-[12px]'"
    >
        {{ size }}
    </span>
</template>
