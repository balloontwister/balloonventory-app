<script setup>
import { ref, onMounted } from 'vue';
import BalloonSwatch from '@/Components/BalloonSwatch.vue';
import SizeChip from '@/Components/SizeChip.vue';
import BrandTag from '@/Components/BrandTag.vue';

const props = defineProps({
    scan: {
        type: Object,
        required: true,
        // { id, sku: { name, hex, finish, size, brand }, delta, workflow }
    },
});

const emit = defineEmits(['undo']);

const LIFETIME_MS = 4000;
const visible = ref(true);

onMounted(() => {
    setTimeout(() => { visible.value = false; }, LIFETIME_MS);
});

const borderColor = props.scan.workflow === 'check_in'
    ? 'var(--color-success)'
    : 'var(--color-warning)';
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
            <BalloonSwatch :hex="scan.sku.hex" :finish="scan.sku.finish" :size="20" />

            <span class="min-w-0 flex-1 truncate font-sans text-[14px] text-ink-primary">
                {{ scan.sku.name }}
            </span>

            <SizeChip :size="scan.sku.size" />
            <BrandTag :brand="scan.sku.brand" />

            <!-- delta -->
            <span class="font-mono text-[16px] font-semibold text-accent">
                {{ scan.delta > 0 ? `+${scan.delta}` : scan.delta }}
            </span>

            <!-- undo -->
            <button
                type="button"
                class="ml-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded text-ink-tertiary hover:text-danger"
                title="Undo"
                @click="emit('undo', scan.id)"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M2.22 2.22a.75.75 0 011.06 0l.543.543C4.945 1.8 6.395 1 8 1c3.866 0 7 3.134 7 7s-3.134 7-7 7-7-3.134-7-7a.75.75 0 011.5 0 5.5 5.5 0 105.5-5.5c-1.12 0-2.163.334-3.032.908L5.28 4.72a.75.75 0 010 1.06L2.22 8.84a.75.75 0 01-1.06 0L2.22 2.22z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </Transition>
</template>
