<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import BalloonSwatch from '@/Components/BalloonSwatch.vue';

const props = defineProps({
    list: {
        type: Object,
        required: true,
        // { id, name, sku_count, notes, updated_at, preview_skus: [{ id, hex, finish }] }
    },
});

const MAX_SWATCHES = 6;

const visibleSwatches = computed(
    () => props.list.preview_skus?.slice(0, MAX_SWATCHES) ?? [],
);
const swatchOverflow = computed(() =>
    Math.max(0, (props.list.sku_count ?? 0) - MAX_SWATCHES),
);

const updatedAt = computed(() => {
    if (!props.list.updated_at) return null;
    return new Date(props.list.updated_at).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
});
</script>

<template>
    <Link :href="route('lists.show', { list: list.id })">
        <div
            class="rounded-lg border border-border bg-surface p-5 transition hover:border-border-strong"
        >
            <!-- header -->
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3
                    class="font-sans text-[18px] font-semibold leading-[1.3] tracking-h3 text-ink-primary"
                >
                    {{ list.name }}
                </h3>
                <span class="font-mono text-[14px] text-ink-secondary"
                    >{{ list.sku_count }} SKUs</span
                >
            </div>

            <!-- swatch preview row -->
            <div class="mb-3 flex items-center gap-0">
                <BalloonSwatch
                    v-for="sku in visibleSwatches"
                    :key="sku.id"
                    :hex="sku.hex"
                    :finish="sku.finish"
                    :size="24"
                />
                <span
                    v-if="swatchOverflow > 0"
                    class="ml-2 font-sans text-[12px] text-ink-tertiary"
                >
                    +{{ swatchOverflow }}
                </span>
            </div>

            <!-- footer -->
            <div class="flex items-end justify-between gap-2">
                <p
                    v-if="list.notes"
                    class="line-clamp-1 font-sans text-[13px] text-ink-secondary"
                >
                    {{ list.notes }}
                </p>
                <span
                    v-if="updatedAt"
                    class="ml-auto flex-shrink-0 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                >
                    Updated {{ updatedAt }}
                </span>
            </div>
        </div>
    </Link>
</template>
