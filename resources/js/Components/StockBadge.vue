<script setup>
import { computed } from 'vue';

const props = defineProps({
    count: { type: Number, required: true },
    lowThreshold: { type: Number, default: 3 },
});

const state = computed(() => {
    if (props.count <= 0) return 'out';
    if (props.count <= props.lowThreshold) return 'low';
    return 'in';
});

const label = computed(() => `${props.count} bags`);
</script>

<template>
    <span
        class="inline-flex items-center rounded-pill px-[10px] py-1 font-mono text-[13px] font-medium leading-none"
        :class="{
            'bg-success-soft text-success': state === 'in',
            'bg-warning-soft text-warning': state === 'low',
            'bg-danger-soft text-danger':   state === 'out',
        }"
    >
        {{ label }}
    </span>
</template>
