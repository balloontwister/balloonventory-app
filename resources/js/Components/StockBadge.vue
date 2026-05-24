<script setup>
import { computed } from 'vue';

const props = defineProps({
    fullBags: { type: Number, required: true },
    openBags: { type: Number, default: 0 },
    lowThreshold: { type: Number, default: 3 },
});

const total = computed(() => props.fullBags + props.openBags);

const state = computed(() => {
    if (total.value <= 0) return 'out';
    if (total.value <= props.lowThreshold) return 'low';
    return 'in';
});

const label = computed(() => {
    if (props.openBags > 0) return `${props.fullBags} + ${props.openBags} open`;
    return `${props.fullBags} bags`;
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-pill px-[10px] py-1 font-mono text-[13px] font-medium leading-none"
        :class="{
            'bg-success-soft text-success': state === 'in',
            'bg-warning-soft text-warning': state === 'low',
            'bg-danger-soft text-danger': state === 'out',
        }"
    >
        {{ label }}
    </span>
</template>
