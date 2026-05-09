<script setup>
import { computed } from 'vue';

const BRAND_COLORS = {
    Qualatex: '#8B0000',
    Sempertex: '#005B8E',
    Tuftex: '#1A6B3A',
    Betallic: '#7B3F00',
    Kalisan: '#4B0082',
};

const props = defineProps({
    brand: { type: String, required: true },
});

const dotColor = computed(() => BRAND_COLORS[props.brand] ?? '#A1A1AA');
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 font-sans text-[11px] font-medium text-ink-secondary"
    >
        <span
            class="h-1.5 w-1.5 flex-shrink-0 rounded-full"
            :style="{ backgroundColor: dotColor }"
        />
        {{ brand }}
    </span>
</template>
