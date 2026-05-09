<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import BalloonSwatch from '@/Components/BalloonSwatch.vue';
import SizeChip from '@/Components/SizeChip.vue';
import { useBusiness } from '@/Composables/useBusiness';

const { businessName } = useBusiness();

const props = defineProps({
    job: {
        type: Object,
        required: true,
        // { id, name, date, line_items: [{ sku: { name, hex, finish, size }, quantity, in_stock }], business_name }
    },
});

const totalSkus = computed(() => props.job.line_items?.length ?? 0);
const inStockCount = computed(
    () =>
        props.job.line_items?.filter((i) => i.in_stock >= i.quantity).length ??
        0,
);
const readinessPercent = computed(() =>
    totalSkus.value > 0
        ? Math.round((inStockCount.value / totalSkus.value) * 100)
        : 0,
);
const shortfallItems = computed(
    () => props.job.line_items?.filter((i) => i.in_stock < i.quantity) ?? [],
);
const hasShortfall = computed(() => shortfallItems.value.length > 0);

const jobDate = computed(() => {
    if (!props.job.date) return null;
    const d = new Date(props.job.date);
    return {
        day: d.toLocaleDateString('en-US', { day: 'numeric' }),
        month: d.toLocaleDateString('en-US', { month: 'short' }).toUpperCase(),
    };
});
</script>

<template>
    <Link :href="route('jobs.show', { job: job.id })">
        <div
            class="rounded-lg border border-border bg-surface p-5 transition hover:border-border-strong"
        >
            <!-- header row: date block + title + business tag -->
            <div class="mb-4 flex items-start gap-4">
                <!-- date block -->
                <div v-if="jobDate" class="flex flex-col items-center">
                    <span
                        class="font-mono text-[22px] font-semibold leading-[1.2] text-ink-primary"
                    >
                        {{ jobDate.day }}
                    </span>
                    <span
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ jobDate.month }}
                    </span>
                </div>

                <div class="min-w-0 flex-1">
                    <h3
                        class="font-sans text-[18px] font-semibold leading-[1.3] tracking-h3 text-ink-primary"
                    >
                        {{ job.name }}
                    </h3>
                    <span class="font-sans text-[12px] text-ink-secondary">{{
                        businessName
                    }}</span>
                </div>
            </div>

            <!-- line items -->
            <ul class="mb-4 flex flex-col gap-2">
                <li
                    v-for="item in job.line_items"
                    :key="item.sku.id"
                    class="flex items-center gap-2"
                >
                    <BalloonSwatch
                        :hex="item.sku.hex"
                        :finish="item.sku.finish"
                        :size="20"
                    />
                    <span
                        class="min-w-0 flex-1 truncate font-sans text-[14px] text-ink-primary"
                    >
                        {{ item.sku.name }}
                    </span>
                    <SizeChip :size="item.sku.size" />
                    <span class="font-mono text-[13px] text-ink-secondary"
                        >× {{ item.quantity }}</span
                    >
                </li>
            </ul>

            <!-- readiness footer -->
            <div class="border-t border-border pt-3">
                <div class="mb-1.5 flex items-center justify-between">
                    <span
                        class="font-sans text-[13px]"
                        :class="
                            hasShortfall ? 'text-warning' : 'text-ink-secondary'
                        "
                    >
                        {{ inStockCount }} / {{ totalSkus }} SKUs in stock
                    </span>
                    <span class="font-mono text-[12px] text-ink-tertiary"
                        >{{ readinessPercent }}%</span
                    >
                </div>

                <!-- progress bar -->
                <div
                    class="h-1 w-full overflow-hidden rounded-full bg-background"
                >
                    <div
                        class="h-full rounded-full transition-all"
                        :class="hasShortfall ? 'bg-warning' : 'bg-accent'"
                        :style="{ width: `${readinessPercent}%` }"
                    />
                </div>

                <!-- shortfall items -->
                <ul v-if="hasShortfall" class="mt-2 flex flex-col gap-1">
                    <li
                        v-for="item in shortfallItems"
                        :key="item.sku.id"
                        class="flex items-center gap-2 font-sans text-[13px] text-warning"
                    >
                        <BalloonSwatch
                            :hex="item.sku.hex"
                            :finish="item.sku.finish"
                            :size="16"
                        />
                        <span class="min-w-0 flex-1 truncate">{{
                            item.sku.name
                        }}</span>
                        <span class="font-mono text-[12px]">
                            need {{ item.quantity }}, have {{ item.in_stock }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </Link>
</template>
