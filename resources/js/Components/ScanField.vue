<script setup>
import { ref, computed, watch } from 'vue';
import { useScanField } from '@/Composables/useScanField';
import { useBusiness } from '@/Composables/useBusiness';

const props = defineProps({
    workflow: { type: String, default: 'check_in' }, // check_in | check_out
    jobName: { type: String, default: null },
    recentScans: { type: Array, default: () => [] },
    externalStatus: { type: String, default: null }, // looking_up | error | null
    showCameraButton: { type: Boolean, default: false },
    cameraSupported: { type: Boolean, default: false },
    // When true, scanning a UPC already in recentScans does NOT emit a `scan`
    // event — the field flashes a duplicate state instead. The parent can opt
    // out by setting this to false; default behavior matches the new UX intent
    // (visible duplicate-rejection rather than silent recommit).
    blockDuplicates: { type: Boolean, default: true },
});

const emit = defineEmits(['scan', 'camera', 'manual-entry']);

const { businessName } = useBusiness();
const internalStatus = ref('armed');

const status = computed(() => props.externalStatus ?? internalStatus.value);

let flashTimer = null;

function handleScan(value) {
    clearTimeout(flashTimer);

    const isDuplicate = props.recentScans.some((s) => s.upc === value);

    if (isDuplicate) {
        if (!props.externalStatus) {
            internalStatus.value = 'duplicate';
            flashTimer = setTimeout(() => {
                internalStatus.value = 'armed';
            }, 600);
        }
        if (!props.blockDuplicates) {
            emit('scan', value);
        }
        return;
    }

    if (!props.externalStatus) {
        internalStatus.value = 'success';
        flashTimer = setTimeout(() => {
            internalStatus.value = 'armed';
        }, 200);
    }
    emit('scan', value);
}

// Reset internal status when the parent clears its external status.
watch(
    () => props.externalStatus,
    (val) => {
        if (val === null) {
            internalStatus.value = 'armed';
        }
    },
);

const { inputRef } = useScanField(handleScan);

const fieldBg = computed(() => {
    if (status.value === 'success') return 'var(--color-accent-soft)';
    if (status.value === 'error') return 'var(--color-danger-soft)';
    if (status.value === 'duplicate') return 'var(--color-warning-soft)';
    return 'var(--color-surface)';
});

const statusDotColor = computed(() => {
    if (status.value === 'looking_up') return 'var(--color-warning)';
    if (status.value === 'error') return 'var(--color-danger)';
    if (status.value === 'duplicate') return 'var(--color-warning)';
    return 'var(--color-success)';
});

const eyebrowKey = computed(() => {
    if (status.value === 'looking_up') return 'scan.looking_up';
    if (status.value === 'error') return 'scan.scan_error';
    if (status.value === 'duplicate') return 'scan.duplicate';
    return 'scan.ready_to_scan';
});

const placeholderKey = computed(() => {
    return status.value === 'looking_up'
        ? 'scan.looking_up'
        : 'scan.scan_placeholder';
});

function onManualEntryClick() {
    emit('manual-entry');
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <!-- Eyebrow label -->
        <span
            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
        >
            {{ $t(eyebrowKey) }}
        </span>

        <!-- Field wrapper -->
        <div class="relative">
            <input
                ref="inputRef"
                type="text"
                readonly
                :placeholder="$t(placeholderKey)"
                class="h-14 w-full rounded-md border-2 bg-surface px-4 font-sans text-[15px] text-ink-primary placeholder-ink-tertiary transition-colors focus:outline-none"
                :class="
                    status === 'error'
                        ? 'border-danger'
                        : status === 'duplicate'
                          ? 'border-warning'
                          : 'border-accent'
                "
                :style="{ backgroundColor: fieldBg }"
                @focus="internalStatus = 'armed'"
            />

            <!-- Status dot / spinner -->
            <span
                v-if="status === 'looking_up'"
                class="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-accent border-t-transparent"
            />
            <span
                v-else
                class="absolute right-4 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full transition-colors"
                :style="{ backgroundColor: statusDotColor }"
            />

            <!-- Camera button -->
            <button
                v-if="showCameraButton && cameraSupported"
                type="button"
                class="absolute right-9 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center text-ink-tertiary hover:text-ink-primary"
                :title="$t('scan.camera_button')"
                :aria-label="$t('scan.camera_button')"
                @click="emit('camera')"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M1 8a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 018.07 3h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0016.07 6H17a2 2 0 012 2v7a2 2 0 01-2 2H3a2 2 0 01-2-2V8zm13.5 3a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM10 14a3 3 0 100-6 3 3 0 000 6z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>

            <!-- Manual entry icon -->
            <button
                type="button"
                class="flex h-6 w-6 items-center justify-center text-ink-tertiary hover:text-ink-primary"
                :class="
                    showCameraButton && cameraSupported
                        ? 'absolute right-14 top-1/2 -translate-y-1/2'
                        : 'absolute right-9 top-1/2 -translate-y-1/2'
                "
                :title="$t('scan.manual_entry_title')"
                :aria-label="$t('scan.manual_entry_title')"
                @click="onManualEntryClick"
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

        <!-- Workflow context hint — shows which business / job the scan posts to. -->
        <p
            v-if="status !== 'looking_up' && status !== 'error'"
            class="font-sans text-[13px] text-ink-secondary"
        >
            <template v-if="workflow === 'check_out' && jobName">
                {{
                    $t('scan.checking_out_for', {
                        job: jobName,
                        business: businessName,
                    })
                }}
            </template>
            <template v-else-if="workflow === 'check_out'">
                {{ $t('scan.checking_out_to', { business: businessName }) }}
            </template>
            <template v-else>
                {{ $t('scan.checking_in_to', { business: businessName }) }}
            </template>
        </p>

        <!-- Status messages -->
        <p v-if="status === 'error'" class="font-sans text-[13px] text-danger">
            {{ $t('scan.error_network') }}
        </p>
        <p
            v-else-if="status === 'duplicate'"
            class="font-sans text-[13px] text-warning"
        >
            {{ $t('scan.duplicate_hint') }}
        </p>
    </div>
</template>
