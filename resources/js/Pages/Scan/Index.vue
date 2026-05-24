<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScanField from '@/Components/ScanField.vue';
import ScanToast from '@/Components/ScanToast.vue';
import CameraScanner from '@/Components/CameraScanner.vue';
import QuantityStepper from '@/Components/QuantityStepper.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onBeforeUnmount, watch } from 'vue';

// ── Mode ────────────────────────────────────────────────────────────────────────
const mode = ref('add'); // add | remove
const isAdding = computed(() => mode.value === 'add');
const isRemoving = computed(() => mode.value === 'remove');

function setMode(val) {
    mode.value = val;
}

// ── Quantity & open bag ─────────────────────────────────────────────────────────
const quantity = ref(1);
const isOpenBag = ref(false);

// When the user flips into "open bag" mode, reset quantity to 1 — an "open
// bag" is by definition a partial bag, so the multi-bag presets don't make
// sense in that context. The user can still bump it up manually with the
// stepper if they really mean N partial bags.
watch(isOpenBag, (next) => {
    if (next) {
        quantity.value = 1;
    }
});

// ── Scan state ──────────────────────────────────────────────────────────────────
const scanStatus = ref(null); // null (armed) | looking_up | error
const unknownUpc = ref(null); // { upc }
const recentScans = ref([]);
// Tracks UPCs of scans currently in-flight so we don't race ourselves when a
// scanner double-fires the same code before the first lookup resolves.
const inFlightUpcs = ref(new Set());

let errorClearTimer = null;

function scheduleErrorClear() {
    clearTimeout(errorClearTimer);
    errorClearTimer = setTimeout(() => {
        scanStatus.value = null;
        errorClearTimer = null;
    }, 4000);
}

onBeforeUnmount(() => {
    if (errorClearTimer) {
        clearTimeout(errorClearTimer);
        errorClearTimer = null;
    }
});

// ── Camera ──────────────────────────────────────────────────────────────────────
const showCamera = ref(false);
const cameraSupported = computed(() => {
    if (typeof navigator === 'undefined') return false;
    if (typeof window !== 'undefined' && 'BarcodeDetector' in window)
        return true;
    return !!navigator.mediaDevices?.getUserMedia;
});

function openCamera() {
    showCamera.value = true;
}

function closeCamera() {
    showCamera.value = false;
}

function onCameraDetected(upc) {
    // CameraScanner now owns the close timing — it shows a "Got it!"
    // confirmation overlay for ~700ms before emitting `close`. We just kick
    // off the lookup in parallel here.
    processScan(upc);
}

// ── Manual entry ────────────────────────────────────────────────────────────────
const showManualEntry = ref(false);
const manualUpc = ref('');

function openManualEntry() {
    manualUpc.value = '';
    showManualEntry.value = true;
}

function closeManualEntry() {
    showManualEntry.value = false;
}

function submitManualEntry() {
    const upc = manualUpc.value.trim();
    if (upc.length < 4) return;
    closeManualEntry();
    processScan(upc);
}

// ── Scan processing ─────────────────────────────────────────────────────────────
async function processScan(upc) {
    // Concurrent-scan guard. The USB scanner can fire two reads of the same
    // code <300ms apart; without this, both would hit /scan/lookup and we'd
    // record two movements. Different UPCs are still allowed to overlap.
    if (inFlightUpcs.value.has(upc)) return;
    inFlightUpcs.value.add(upc);

    clearTimeout(errorClearTimer);
    errorClearTimer = null;

    scanStatus.value = 'looking_up';
    unknownUpc.value = null;

    try {
        // 1. Look up UPC
        const lookup = await window.axios.post(route('scan.lookup'), { upc });

        if (!lookup.data.found) {
            scanStatus.value = null;
            unknownUpc.value = { upc };
            return;
        }

        // 2. Record the movement (auto-commit; the toast carries an Undo).
        const payload = {
            sku_id: lookup.data.sku.id,
            upc,
            full_bags_change: isOpenBag.value ? 0 : quantity.value,
            open_bags_change: isOpenBag.value ? quantity.value : 0,
        };

        const endpoint = isAdding.value
            ? route('scan.check-in')
            : route('scan.check-out');
        const response = await window.axios.post(endpoint, payload);

        // 3. Success — add to recent scans
        recentScans.value.unshift({
            movement_id: response.data.movement_id,
            upc,
            direction: response.data.direction,
            full_bags_change: response.data.full_bags_change,
            open_bags_change: response.data.open_bags_change,
            sku: response.data.sku,
        });

        // Keep only last 30.
        if (recentScans.value.length > 30) {
            recentScans.value = recentScans.value.slice(0, 30);
        }

        scanStatus.value = null;
    } catch {
        scanStatus.value = 'error';
        scheduleErrorClear();
    } finally {
        inFlightUpcs.value.delete(upc);
    }
}

// ── Undo ────────────────────────────────────────────────────────────────────────
async function undoScan(movementId) {
    try {
        await window.axios.post(
            route('scan.undo', { stockMovement: movementId }),
        );
        recentScans.value = recentScans.value.filter(
            (s) => s.movement_id !== movementId,
        );
    } catch {
        // Undo failed; leave the scan in the list so the user can retry.
    }
}

// "Hide all" empties the local recent-scans UI. Movements remain in the
// database — this is intentional (the recent list is a UI affordance, not a
// transactional record). Per-row Undo is the only path that reverses stock.
function hideRecent() {
    recentScans.value = [];
}

// ── Assign unknown UPC ──────────────────────────────────────────────────────────
function assignUnknown() {
    const upc = unknownUpc.value?.upc;
    if (!upc) return;
    router.get(route('inventory.index'), { search: upc });
}

// ── Context hint translation key ────────────────────────────────────────────────
const contextHintKey = computed(() => {
    const action = isAdding.value ? 'adding' : 'removing';
    const bag = isOpenBag.value ? 'open' : 'full';
    return `scan.${action}_${bag}_context`;
});
</script>

<template>
    <Head :title="$t('scan.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-[22px] font-semibold text-ink-primary">
                {{ $t('scan.heading') }}
            </h1>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <!-- ── Mode toggle ────────────────────────────────────────────────── -->
            <div class="flex gap-3">
                <button
                    type="button"
                    class="flex-1 rounded-lg px-6 py-4 font-display text-[20px] font-semibold transition-colors"
                    :class="
                        isAdding
                            ? 'bg-success text-white shadow-pop'
                            : 'border-2 border-border-strong bg-surface text-ink-tertiary hover:bg-background'
                    "
                    @click="setMode('add')"
                >
                    + {{ $t('scan.mode_add') }}
                </button>

                <button
                    type="button"
                    class="flex-1 rounded-lg px-6 py-4 font-display text-[20px] font-semibold transition-colors"
                    :class="
                        isRemoving
                            ? 'bg-warning text-white shadow-pop'
                            : 'border-2 border-border-strong bg-surface text-ink-tertiary hover:bg-background'
                    "
                    @click="setMode('remove')"
                >
                    &minus; {{ $t('scan.mode_remove') }}
                </button>
            </div>

            <!-- ── Quantity controls ──────────────────────────────────────────── -->
            <div
                class="flex flex-wrap items-start gap-6 rounded-lg border border-border bg-surface px-4 py-3"
            >
                <div>
                    <label
                        class="mb-1 block font-sans text-[12px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('scan.qty_label') }}
                    </label>
                    <QuantityStepper v-model="quantity" />
                </div>

                <div class="flex items-center gap-2 pt-6">
                    <input
                        id="open-bag-toggle"
                        v-model="isOpenBag"
                        type="checkbox"
                        class="h-4 w-4 rounded border-border-strong text-accent focus:ring-accent"
                    />
                    <label
                        for="open-bag-toggle"
                        class="cursor-pointer font-sans text-[14px] text-ink-primary"
                    >
                        {{ $t('scan.open_bag_label') }}
                    </label>
                </div>

                <!-- Context hint (hidden on small screens) -->
                <div
                    class="ml-auto hidden pt-6 font-sans text-[13px] text-ink-secondary md:block"
                >
                    {{
                        $tChoice(contextHintKey, quantity, { count: quantity })
                    }}
                </div>
            </div>

            <!-- ── Scan input ─────────────────────────────────────────────────── -->
            <ScanField
                :workflow="isAdding ? 'check_in' : 'check_out'"
                :recent-scans="recentScans"
                :external-status="scanStatus"
                :show-camera-button="true"
                :camera-supported="cameraSupported"
                @scan="processScan"
                @camera="openCamera"
                @manual-entry="openManualEntry"
            />

            <!-- ── Unknown UPC banner ─────────────────────────────────────────── -->
            <div
                v-if="unknownUpc"
                class="flex items-center gap-3 rounded-lg border-2 border-warning bg-warning-soft px-4 py-3"
            >
                <div class="flex-1">
                    <p
                        class="font-sans text-[14px] font-semibold text-ink-primary"
                    >
                        {{ $t('scan.unknown_upc') }}:
                        <span class="font-mono">{{ unknownUpc.upc }}</span>
                    </p>
                    <p class="mt-0.5 font-sans text-[13px] text-ink-secondary">
                        {{ $t('scan.unknown_upc_body') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-md bg-warning px-4 py-2 font-sans text-[14px] font-semibold text-white hover:brightness-110"
                    @click="assignUnknown"
                >
                    {{ $t('scan.unknown_assign') }}
                </button>
            </div>

            <!-- ── Toasts ─────────────────────────────────────────────────────── -->
            <TransitionGroup
                tag="div"
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                leave-active-class="transition duration-300 ease-in"
                leave-to-class="opacity-0 translate-y-2"
                class="pointer-events-none fixed bottom-6 left-1/2 z-50 flex w-full max-w-md -translate-x-1/2 flex-col-reverse gap-2 md:pointer-events-auto md:static md:bottom-auto md:left-auto md:w-auto md:max-w-none md:translate-x-0 md:flex-col md:gap-2"
            >
                <ScanToast
                    v-for="s in recentScans.slice(0, 3)"
                    :key="s.movement_id"
                    :scan="s"
                    @undo="undoScan"
                />
            </TransitionGroup>

            <!-- ── Recent scans list ───────────────────────────────────────────── -->
            <div class="rounded-lg border border-border">
                <div
                    class="flex items-center justify-between border-b border-border bg-background px-4 py-2.5"
                >
                    <h2
                        class="font-sans text-[12px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('scan.recent_heading') }}
                    </h2>
                    <button
                        v-if="recentScans.length > 0"
                        type="button"
                        class="font-sans text-[12px] text-ink-tertiary hover:text-ink-primary"
                        @click="hideRecent"
                    >
                        {{ $t('scan.hide_recent') }}
                    </button>
                </div>

                <!-- Empty -->
                <div
                    v-if="recentScans.length === 0"
                    class="px-4 py-8 text-center font-sans text-[14px] text-ink-tertiary"
                >
                    {{ $t('scan.recent_empty') }}
                </div>

                <!-- List -->
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="s in recentScans"
                        :key="s.movement_id"
                        class="flex items-center gap-3 px-4 py-2.5"
                    >
                        <!-- Direction marker -->
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-mono text-[14px] font-bold text-white"
                            :class="
                                s.direction === 'in'
                                    ? 'bg-success'
                                    : 'bg-warning'
                            "
                        >
                            {{ s.direction === 'in' ? '+' : '−' }}
                        </span>

                        <!-- Swatch -->
                        <span
                            v-if="s.sku?.color?.color_hex"
                            class="inline-block h-5 w-5 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: s.sku.color.color_hex }"
                        />

                        <!-- Name -->
                        <span
                            class="min-w-0 flex-1 truncate font-sans text-[14px] text-ink-primary"
                        >
                            {{
                                s.sku?.computed_name ??
                                s.sku?.name ??
                                $t('scan.unknown_sku')
                            }}
                        </span>

                        <!-- Open bag badge -->
                        <span
                            v-if="
                                s.open_bags_change > 0 &&
                                s.full_bags_change === 0
                            "
                            class="bg-ink-tertiary/15 shrink-0 rounded-full px-2 py-0.5 font-sans text-[11px] font-medium text-ink-secondary"
                        >
                            {{ $t('scan.bag_open') }}
                        </span>

                        <!-- Quantity badge -->
                        <span
                            class="shrink-0 font-mono text-[14px] font-semibold tabular-nums"
                            :class="
                                s.direction === 'in'
                                    ? 'text-success'
                                    : 'text-warning'
                            "
                        >
                            {{ s.direction === 'in' ? '+' : '−'
                            }}{{ s.full_bags_change + s.open_bags_change }}
                        </span>

                        <!-- Undo button -->
                        <button
                            type="button"
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-ink-tertiary hover:bg-danger-soft hover:text-danger"
                            :title="$t('scan.undo')"
                            :aria-label="$t('scan.undo')"
                            @click="undoScan(s.movement_id)"
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
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── Camera scanner modal ─────────────────────────────────────────────
             :closeable="false" — backdrop tap and Escape do NOT dismiss this
             modal. iOS users frequently tap the screen to focus the camera,
             which previously hit the backdrop and killed the modal. Closing
             is owned by CameraScanner's X button and its post-detection
             auto-close. -->
        <Modal :show="showCamera" max-width="md" :closeable="false">
            <div class="p-2">
                <CameraScanner
                    @detected="onCameraDetected"
                    @close="closeCamera"
                />
            </div>
        </Modal>

        <!-- ── Manual entry modal ─────────────────────────────────────────────── -->
        <Modal :show="showManualEntry" max-width="sm" @close="closeManualEntry">
            <div class="p-6">
                <div class="mb-4 flex items-start justify-between">
                    <h2
                        class="font-sans text-[17px] font-semibold text-ink-primary"
                    >
                        {{ $t('scan.manual_entry_title') }}
                    </h2>
                    <button
                        type="button"
                        class="ml-4 flex h-7 w-7 items-center justify-center rounded text-ink-tertiary hover:bg-background hover:text-ink-primary"
                        :aria-label="$t('scan.close')"
                        @click="closeManualEntry"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                    </button>
                </div>

                <form
                    class="flex flex-col gap-4"
                    @submit.prevent="submitManualEntry"
                >
                    <div>
                        <label
                            for="manual-upc-input"
                            class="mb-1 block font-sans text-[13px] font-medium text-ink-secondary"
                        >
                            {{ $t('scan.manual_entry_label') }}
                        </label>
                        <input
                            id="manual-upc-input"
                            v-model="manualUpc"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            class="h-14 w-full rounded-md border border-border-strong bg-surface px-4 font-mono text-[18px] tracking-wider text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            placeholder="012345678901"
                            @keydown.enter.prevent="submitManualEntry"
                        />
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-border-strong bg-surface px-4 py-2 font-sans text-[14px] text-ink-primary hover:bg-background"
                            @click="closeManualEntry"
                        >
                            {{ $t('scan.cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-medium text-accent-on hover:bg-accent-hover disabled:opacity-40"
                            :disabled="manualUpc.trim().length < 4"
                        >
                            {{ $t('scan.manual_entry_scan') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
