<script setup>
import { computed } from 'vue';

const props = defineProps({
    // Accepts an array of URLs (or a single URL string for convenience). Falsy
    // entries are filtered out. Designed so we can grow from 1-2 fixed image
    // slots today to a real gallery later without changing the prop shape.
    urls: { type: [Array, String, null], default: () => [] },
    size: {
        type: String,
        default: 'sm', // 'xs' | 'sm' | 'md'
        validator: (v) => ['xs', 'sm', 'md'].includes(v),
    },
    alt: { type: String, default: 'Image' },
});

const cleanUrls = computed(() => {
    if (!props.urls) return [];
    const arr = Array.isArray(props.urls) ? props.urls : [props.urls];
    return arr.filter(Boolean);
});

const sizeClass = computed(() =>
    ({
        xs: 'h-6 w-6',
        sm: 'h-10 w-10',
        md: 'h-16 w-16',
    })[props.size],
);
</script>

<template>
    <div v-if="cleanUrls.length" class="flex items-center gap-1.5">
        <img
            v-for="(url, idx) in cleanUrls"
            :key="idx"
            :src="url"
            :alt="alt"
            :class="sizeClass"
            class="shrink-0 rounded-sm object-contain ring-1 ring-inset ring-border"
        />
    </div>
    <span v-else class="font-sans text-[12px] text-ink-tertiary">—</span>
</template>
