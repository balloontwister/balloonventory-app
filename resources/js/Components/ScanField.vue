<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { useBusiness } from '@/Composables/useBusiness';

const props = defineProps({
    workflow: { type: String, default: 'check_in' }, // check_in | check_out
    jobName: { type: String, default: null },
    recentScans: { type: Array, default: () => [] },
    externalStatus: { type: String, default: null }, // looking_up | error | null
    showCameraButton: { type: Boolean, default: false },
    cameraSupported: { type: Boolean, default: false },
    // When true, scanning a UPC already in recentScans does NOT emit a `scan`
    // event — the field flashes a duplicate state instead.
    blockDuplicates: { type: Boolean, default: true },
});

const emit = defineEmits(['scan', 'camera']);

const { businessName } = useBusiness();

const inputRef = ref(null);
const inputValue = ref('');
const internalStatus = ref('armed');

const MIN_LENGTH = 4;
let flashTimer = null;

const status = computed(() => props.externalStatus ?? internalStatus.value);

function focusInput() {
    nextTick(() => inputRef.value?.focus());
}

// Exposed so the parent page can re-focus the field after closing a modal
// (e.g., the camera scanner) that takes focus away.
defineExpose({ focusInput });

function commit() {
    const value = inputValue.value.trim();
    if (value.length < MIN_LENGTH) return;

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
        inputValue.value = '';
        return;
    }

    if (!props.externalStatus) {
        internalStatus.value = 'success';
        flashTimer = setTimeout(() => {
            internalStatus.value = 'armed';
        }, 200);
    }
    emit('scan', value);
    inputValue.value = '';
}

// When the parent clears its status (i.e., scan completed), re-arm and
// re-focus so the next scan or keystroke lands here without an extra click.
watch(
    () => props.externalStatus,
    (val) => {
        if (val === null) {
            internalStatus.value = 'armed';
            focusInput();
        }
    },
);

onMounted(() => {
    focusInput();
});

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

// Reserve right padding for the status dot + optional camera button so typed
// digits don't slide under the icons.
const inputPaddingClass = computed(() =>
    props.showCameraButton && props.cameraSupported ? 'pr-16' : 'pr-10',
);
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
                v-model="inputValue"
                type="text"
                inputmode="numeric"
                enterkeyhint="done"
                autocomplete="off"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                :placeholder="$t(placeholderKey)"
                class="h-14 w-full rounded-md border-2 bg-surface pl-4 font-sans text-[15px] text-ink-primary placeholder-ink-tertiary transition-colors focus:outline-none"
                :class="[
                    inputPaddingClass,
                    status === 'error'
                        ? 'border-danger'
                        : status === 'duplicate'
                          ? 'border-warning'
                          : 'border-accent',
                ]"
                :style="{ backgroundColor: fieldBg }"
                @keydown.enter.prevent="commit"
            />

            <!-- Status dot / spinner -->
            <span
                v-if="status === 'looking_up'"
                class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-accent border-t-transparent"
            />
            <span
                v-else
                class="pointer-events-none absolute right-4 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full transition-colors"
                :style="{ backgroundColor: statusDotColor }"
            />

            <!-- Camera button — only icon left of the status dot. The keyboard
                 icon and its modal were removed: the field now accepts typed
                 input directly, so USB scanners and humans share one entry path. -->
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
