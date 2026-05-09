<script setup>
import { ref, computed } from 'vue';
import { useScanField } from '@/Composables/useScanField';
import { useBusiness } from '@/Composables/useBusiness';

const props = defineProps({
    workflow: { type: String, default: 'check_in' }, // check_in | check_out
    jobName: { type: String, default: null },
    recentScans: { type: Array, default: () => [] },
});

const emit = defineEmits(['scan']);

const { businessName } = useBusiness();
const status = ref('armed'); // armed | success | unknown | duplicate

const contextLabel = computed(() => {
    if (props.workflow === 'check_out' && props.jobName) {
        return `Checking out for ${props.jobName} · ${businessName.value}`;
    }
    return `Checking in to ${businessName.value}`;
});

let flashTimer = null;

function handleScan(value) {
    clearTimeout(flashTimer);

    const isDuplicate = props.recentScans.some((s) => s.upc === value);
    if (isDuplicate) {
        status.value = 'duplicate';
        flashTimer = setTimeout(() => {
            status.value = 'armed';
        }, 200);
        emit('scan', value);
        return;
    }

    status.value = 'success';
    emit('scan', value);
    flashTimer = setTimeout(() => {
        status.value = 'armed';
    }, 200);
}

const { inputRef, focusInput } = useScanField(handleScan);

const fieldBg = computed(() => {
    if (status.value === 'success') return 'var(--color-accent-soft)';
    if (status.value === 'unknown') return 'var(--color-warning-soft)';
    return 'var(--color-surface)';
});
</script>

<template>
    <div class="flex flex-col gap-1">
        <!-- eyebrow label -->
        <span
            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
        >
            Ready to scan
        </span>

        <!-- field wrapper -->
        <div class="relative">
            <input
                ref="inputRef"
                type="text"
                readonly
                placeholder="Scan a barcode…"
                class="h-14 w-full rounded-md border-2 border-accent bg-surface px-4 pr-12 font-sans text-[15px] text-ink-primary placeholder-ink-tertiary transition-colors focus:outline-none"
                :style="{ backgroundColor: fieldBg }"
                @focus="status = 'armed'"
                @blur="status = status === 'armed' ? 'armed' : status"
            />

            <!-- status dot -->
            <span
                class="absolute right-4 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full transition-colors"
                :style="{ backgroundColor: 'var(--color-success)' }"
            />

            <!-- manual entry icon -->
            <button
                type="button"
                class="absolute right-9 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center text-ink-tertiary hover:text-ink-primary"
                title="Manual entry"
                @click="focusInput"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M2 5a1 1 0 011-1h14a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V5zm1 0v10h14V5H3zm2 2a.5.5 0 000 1h1a.5.5 0 000-1H5zm3 0a.5.5 0 000 1h1a.5.5 0 000-1H8zm3 0a.5.5 0 000 1h1a.5.5 0 000-1h-1zm3 0a.5.5 0 000 1h1a.5.5 0 000-1h-1zM5 9.5a.5.5 0 000 1h1a.5.5 0 000-1H5zm3 0a.5.5 0 000 1h1a.5.5 0 000-1H8zm3 0a.5.5 0 000 1h1a.5.5 0 000-1h-1zm3 0a.5.5 0 000 1h1a.5.5 0 000-1h-1zM5 12a.5.5 0 000 1h6a.5.5 0 000-1H5zm7 0a.5.5 0 000 1h1a.5.5 0 000-1h-1z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
        </div>

        <!-- workflow context -->
        <p class="font-sans text-[13px] text-ink-secondary">
            {{ contextLabel }}
        </p>

        <!-- unknown UPC message -->
        <div
            v-if="status === 'unknown'"
            class="mt-1 flex items-center gap-2 rounded-md border border-warning bg-warning-soft px-3 py-2"
        >
            <p class="flex-1 font-sans text-[13px] text-ink-primary">
                Unknown UPC — tap to assign SKU
            </p>
            <button
                type="button"
                class="font-sans text-[13px] font-medium text-warning hover:underline"
            >
                Assign
            </button>
        </div>
    </div>
</template>
