<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';

const props = defineProps({
    scan: {
        type: Object,
        required: true,
        // { movement_id, direction, full_bags_change, open_bags_change,
        //   sku: { name, computed_name, color: { color_hex }, brand: { abbreviation },
        //          balloon_size: { name, shape: { name } } } }
    },
});

const emit = defineEmits(['undo']);

const LIFETIME_MS = 4000;
const visible = ref(true);

let dismissTimer = null;

onMounted(() => {
    dismissTimer = setTimeout(() => {
        visible.value = false;
    }, LIFETIME_MS);
});

onBeforeUnmount(() => {
    if (dismissTimer) {
        clearTimeout(dismissTimer);
        dismissTimer = null;
    }
});

const isCheckIn = computed(() => props.scan.direction === 'in');

const delta = computed(() => {
    const total =
        (props.scan.full_bags_change ?? 0) + (props.scan.open_bags_change ?? 0);
    return isCheckIn.value ? `+${total}` : `−${total}`;
});

const bagBadgeKey = computed(() => {
    const open = (props.scan.open_bags_change ?? 0) > 0;
    const full = (props.scan.full_bags_change ?? 0) > 0;

    if (open && !full) return 'scan.bag_open';
    if (open && full) return 'scan.bag_mixed';
    return null; // Default case (full only) — no badge needed
});

const borderColor = computed(() =>
    isCheckIn.value ? 'var(--color-success)' : 'var(--color-warning)',
);

const displayName = computed(
    () => props.scan.sku?.computed_name ?? props.scan.sku?.name ?? null,
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        leave-active-class="transition duration-300 ease-in"
        leave-to-class="opacity-0 translate-y-1"
    >
        <div
            v-if="visible"
            class="flex h-12 items-center gap-2 rounded-md border border-border bg-surface px-3"
            :style="{ borderLeft: `4px solid ${borderColor}` }"
        >
            <!-- Color swatch -->
            <span
                v-if="scan.sku?.color?.color_hex"
                class="inline-block h-5 w-5 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                :style="{ backgroundColor: scan.sku.color.color_hex }"
            />

            <span
                class="min-w-0 flex-1 truncate font-sans text-[14px] text-ink-primary"
            >
                {{ displayName ?? $t('scan.unknown_sku') }}
            </span>

            <!-- Bag type badge -->
            <span
                v-if="bagBadgeKey"
                class="bg-ink-tertiary/15 shrink-0 rounded-full px-2 py-0.5 font-sans text-[11px] font-medium text-ink-secondary"
            >
                {{ $t(bagBadgeKey) }}
            </span>

            <!-- Delta -->
            <span
                class="font-mono text-[16px] font-semibold tabular-nums"
                :class="isCheckIn ? 'text-success' : 'text-warning'"
            >
                {{ delta }}
            </span>

            <!-- Undo -->
            <button
                type="button"
                class="ml-1 flex h-6 w-6 shrink-0 items-center justify-center rounded text-ink-tertiary hover:text-danger"
                :title="$t('scan.undo')"
                :aria-label="$t('scan.undo')"
                @click="emit('undo', scan.movement_id)"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M2.22 2.22a.75.75 0 011.06 0l.543.543C4.945 1.8 6.395 1 8 1c3.866 0 7 3.134 7 7s-3.134 7-7 7-7-3.134-7-7a.75.75 0 011.5 0 5.5 5.5 0 105.5-5.5c-1.12 0-2.163.334-3.032.908L5.28 4.72a.75.75 0 010 1.06L2.22 8.84a.75.75 0 01-1.06 0L2.22 2.22z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
        </div>
    </Transition>
</template>
