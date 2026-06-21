<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    can: { type: Object, required: true },
});

const hasAnyAction = computed(
    () => props.can.checkIn || props.can.checkOut || props.can.addInventory,
);
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
                        d="M10 1a.75.75 0 01.75.75v1.5h1.5a.75.75 0 010 1.5h-1.5v1.5a.75.75 0 01-1.5 0v-1.5h-1.5a.75.75 0 010-1.5h1.5v-1.5A.75.75 0 0110 1zM5.5 7.5A1.5 1.5 0 017 6h6a1.5 1.5 0 011.5 1.5v8A1.5 1.5 0 0113 17H7a1.5 1.5 0 01-1.5-1.5v-8z"
                        clip-rule="evenodd"
                    />
                </svg>
            </span>
            <h2 class="font-sans text-[15px] font-semibold text-ink-primary">
                {{ $t('dashboard.quick_actions.title') }}
            </h2>
        </div>

        <div class="mt-4 flex flex-col gap-2">
            <!-- Primary mutate actions -->
            <template v-if="hasAnyAction">
                <Link
                    v-if="can.checkIn"
                    :href="route('scan.index', { mode: 'add' })"
                    class="flex items-center justify-center gap-2 rounded-md bg-accent px-4 py-2.5 font-sans text-[14px] font-semibold text-white transition hover:bg-accent-hover"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            d="M10.75 6.75a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z"
                        />
                        <path
                            fill-rule="evenodd"
                            d="M2 10a8 8 0 1116 0 8 8 0 01-16 0zm8-6.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    {{ $t('dashboard.quick_actions.scan_in') }}
                </Link>

                <Link
                    v-if="can.checkOut"
                    :href="route('scan.index', { mode: 'remove' })"
                    class="flex items-center justify-center gap-2 rounded-md border border-border bg-background px-4 py-2.5 font-sans text-[14px] font-semibold text-ink-primary transition hover:border-border-strong hover:bg-surface"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            d="M6.75 9.25a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z"
                        />
                        <path
                            fill-rule="evenodd"
                            d="M2 10a8 8 0 1116 0 8 8 0 01-16 0zm8-6.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    {{ $t('dashboard.quick_actions.scan_out') }}
                </Link>

                <Link
                    v-if="can.addInventory"
                    :href="route('inventory.index')"
                    class="flex items-center justify-center gap-2 rounded-md border border-border bg-background px-4 py-2.5 font-sans text-[14px] font-semibold text-ink-primary transition hover:border-border-strong hover:bg-surface"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            d="M10.75 1.66a1.5 1.5 0 00-1.5 0L1.6 6.04a.75.75 0 000 1.32l7.65 4.37a1.5 1.5 0 001.5 0l7.65-4.37a.75.75 0 000-1.32l-7.65-4.37z"
                        />
                        <path
                            d="m2.69 9.21-1.09.62a.75.75 0 000 1.32l7.65 4.37a1.5 1.5 0 001.5 0l7.65-4.37a.75.75 0 000-1.32l-1.09-.62-5.81 3.32a3 3 0 01-3 0L2.69 9.21z"
                        />
                    </svg>
                    {{ $t('dashboard.quick_actions.add_inventory') }}
                </Link>
            </template>

            <!-- Read-only link always available -->
            <Link
                :href="route('reorder.index')"
                class="flex items-center justify-center gap-2 rounded-md border border-border bg-background px-4 py-2.5 font-sans text-[14px] font-semibold text-ink-primary transition hover:border-border-strong hover:bg-surface"
            >
                {{ $t('dashboard.quick_actions.reorder_list') }}
            </Link>

            <!-- Guest fallback when no mutate actions -->
            <p
                v-if="!hasAnyAction"
                class="mt-1 font-sans text-[13px] text-ink-tertiary"
            >
                {{ $t('dashboard.quick_actions.no_actions') }}
            </p>
        </div>
    </div>
</template>
