<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, required: true },
    count: { type: Number, required: true },
});

const hasItems = computed(() => props.count > 0);
</script>

<template>
    <div class="flex flex-col rounded-lg border border-border bg-surface p-5">
        <div class="flex items-center gap-2.5">
            <span
                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                        clip-rule="evenodd"
                    />
                </svg>
            </span>
            <h2 class="font-sans text-[15px] font-semibold text-ink-primary">
                {{ $t('dashboard.low_stock.title') }}
            </h2>
            <Link
                :href="route('reorder.index')"
                class="ml-auto font-sans text-[12px] text-accent hover:underline"
            >
                {{ $t('dashboard.low_stock.view_all') }}
            </Link>
        </div>

        <div class="mt-4 flex flex-1 flex-col">
            <!-- Empty state -->
            <p
                v-if="!hasItems"
                class="font-sans text-[13px] text-ink-tertiary"
            >
                {{ $t('dashboard.low_stock.empty') }}
            </p>

            <!-- Low stock items -->
            <template v-else>
                <p class="mb-3 font-sans text-[13px] font-semibold text-red-600">
                    {{ $tChoice('dashboard.low_stock.count', count, { count }) }}
                </p>
                <ul class="flex flex-col divide-y divide-border">
                    <li
                        v-for="item in items"
                        :key="item.sku_id"
                        class="flex items-center justify-between gap-3 py-2"
                    >
                        <span class="min-w-0 flex-1 truncate font-sans text-[13px] text-ink-primary">
                            {{ item.name }}
                        </span>
                        <span class="flex-shrink-0 font-sans text-[12px] text-ink-tertiary">
                            {{ item.on_hand }}
                            <span class="text-ink-quaternary">/</span>
                            {{ item.threshold }}
                        </span>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>
