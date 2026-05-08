<script setup>
import { computed } from 'vue';

const props = defineProps({
    hex: { type: String, required: true },
    finish: { type: String, default: 'standard' }, // standard | metallic | chrome | pearl | matte | confetti | print
    size: { type: Number, default: 24 },
});

const isMetallic = computed(() => ['metallic', 'chrome', 'pearl'].includes(props.finish));
const isPrint = computed(() => ['confetti', 'print'].includes(props.finish));

const containerStyle = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    borderRadius: '6px',
    flexShrink: 0,
}));
</script>

<template>
    <span class="relative inline-flex" :style="containerStyle">
        <!-- base color fill -->
        <span
            class="absolute inset-0"
            style="border-radius: 6px"
            :style="{ backgroundColor: hex }"
        />

        <!-- metallic / chrome / pearl sheen overlay -->
        <span
            v-if="isMetallic"
            class="absolute inset-0"
            style="border-radius: 6px; background: linear-gradient(45deg, transparent 0%, rgba(255,255,255,0.12) 100%)"
        />

        <!-- inner border for visibility on any surface -->
        <span
            class="absolute inset-0 dark:shadow-[inset_0_0_0_1px_rgba(255,255,255,0.12)]"
            style="border-radius: 6px; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08)"
        />

        <!-- print / confetti glyph -->
        <span
            v-if="isPrint"
            class="absolute -bottom-0.5 -right-0.5 flex h-3 w-3 items-center justify-center rounded-full bg-surface text-[7px] font-bold text-ink-secondary shadow-pop"
        >
            P
        </span>
    </span>
</template>
