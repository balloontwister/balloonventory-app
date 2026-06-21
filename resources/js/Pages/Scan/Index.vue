<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScanField from '@/Components/ScanField.vue';
import ScanToast from '@/Components/ScanToast.vue';
import CameraScanner from '@/Components/CameraScanner.vue';
import QuantityStepper from '@/Components/QuantityStepper.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    bins: { type: Array, default: () => [] },
    defaultBinId: { type: String, default: null },
    initialMode: { type: String, default: 'add' },
});

// ── Working bin ───────────────────────────────────────────────────────────────
// '' = Auto: new items fall back to Default, known items go to their own bin.
// A chosen bin only applies to items that aren't in stock yet ("known location
// wins"). Held in a plain ref so it resets when the page is left and re-entered.
const workingBinId = ref('');

// Resolve which bin a scan should target. Known location wins: if the item
// already lives somewhere, use that; otherwise the working bin (or Default).
function resolveTargetBin(sku) {
    return sku.suggested_bin_id || workingBinId.value || props.defaultBinId;
}

// Removing from an item that's spread across several bins must not silently
// guess which bin to pull from — surface a chooser instead.
function needsBinChoice(sku) {
    return isRemoving.value && (sku.bins?.length ?? 0) > 1;
}

// ── Bin-label scan ────────────────────────────────────────────────────────────
// Transient banner confirming the working bin a scanned BIN- label just set.
const binNotice = ref(null); // { ok: bool, label: string }
let binNoticeTimer = null;

function handleBinScan(data) {
    scanStatus.value = null;

    if (!data.found) {
        binNotice.value = { ok: false, label: null };
    } else {
        workingBinId.value = data.bin.id;
        binNotice.value = { ok: true, label: binLabel(data.bin) };
    }

    clearTimeout(binNoticeTimer);
    binNoticeTimer = setTimeout(() => {
        binNotice.value = null;
        binNoticeTimer = null;
    }, 4000);
}

function binLabel(bin) {
    const number = bin.number != null ? `#${bin.number} ` : '';
    const location = bin.location_name ? `${bin.location_name} · ` : '';
    return `${location}${number}${bin.name}`;
}

// ── Mode ────────────────────────────────────────────────────────────────────────
const mode = ref(props.initialMode); // add | remove
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
// Set when a lookup found one or more candidates but the backend did NOT flag
// the scan for auto-apply (ambiguous or low-confidence match). Holds
// { upc, candidates } until the user confirms which SKU to record against.
const pendingMatch = ref(null);
// Set when a removal targets a SKU that lives in multiple bins. Holds
// { upc, sku } until the user picks which bin to pull from.
const pendingBinChoice = ref(null);
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
    if (binNoticeTimer) {
        clearTimeout(binNoticeTimer);
        binNoticeTimer = null;
    }
});

// ── Camera ──────────────────────────────────────────────────────────────────────
const showCamera = ref(false);
// Template ref to ScanField — used to re-focus the input after closing the
// camera modal (the modal steals focus while it's open).
const scanFieldRef = ref(null);

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
    // Restore focus to the scan field so the next scan/type lands there.
    scanFieldRef.value?.focusInput();
}

function onCameraDetected(upc) {
    // CameraScanner shows a "Got it!" overlay for ~700ms before emitting
    // close. We start the lookup in parallel here.
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
    pendingMatch.value = null;
    pendingBinChoice.value = null;

    try {
        // 1. Look up UPC
        const lookup = await window.axios.post(route('scan.lookup'), { upc });

        // A scanned bin label sets the working bin instead of recording stock.
        if (lookup.data.type === 'bin') {
            handleBinScan(lookup.data);
            return;
        }

        if (!lookup.data.found) {
            scanStatus.value = null;
            unknownUpc.value = { upc };
            return;
        }

        // 2. Honor the backend's confidence signal. Only a unique, exact GTIN
        //    match (auto_apply) commits straight away. Ambiguous or
        //    low-confidence matches — a missing-check-digit hit, a GS1-prefix
        //    fallback, or several candidates — wait for the user to confirm
        //    which SKU the scan refers to. Auto-committing the top guess here
        //    can silently record stock against the wrong product.
        if (!lookup.data.auto_apply) {
            scanStatus.value = null;
            pendingMatch.value = {
                upc,
                candidates: lookup.data.candidates ?? [],
            };
            return;
        }

        // 3. Confident match. Removing an item that lives in several bins still
        //    needs the user to say which bin — don't auto-guess on check-out.
        if (needsBinChoice(lookup.data.sku)) {
            scanStatus.value = null;
            pendingBinChoice.value = { upc, sku: lookup.data.sku };
            return;
        }

        await recordMovement(upc, lookup.data.sku.id, resolveTargetBin(lookup.data.sku));
        scanStatus.value = null;
    } catch {
        scanStatus.value = 'error';
        scheduleErrorClear();
    } finally {
        inFlightUpcs.value.delete(upc);
    }
}

/**
 * Post a check-in/out movement for a resolved SKU and prepend it to the recent
 * list. Shared by the auto-commit path and the manual confirm-a-candidate path.
 */
async function recordMovement(upc, skuId, binId = null) {
    const payload = {
        sku_id: skuId,
        upc,
        bin_id: binId || undefined,
        full_bags_change: isOpenBag.value ? 0 : quantity.value,
        open_bags_change: isOpenBag.value ? quantity.value : 0,
    };

    const endpoint = isAdding.value
        ? route('scan.check-in')
        : route('scan.check-out');
    const response = await window.axios.post(endpoint, payload);

    recentScans.value.unshift({
        movement_id: response.data.movement_id,
        upc,
        direction: response.data.direction,
        full_bags_change: response.data.full_bags_change,
        open_bags_change: response.data.open_bags_change,
        sku: response.data.sku,
        bin: response.data.bin,
    });

    // Keep only last 30.
    if (recentScans.value.length > 30) {
        recentScans.value = recentScans.value.slice(0, 30);
    }
}

// ── Confirm an ambiguous / low-confidence match ──────────────────────────────────
async function confirmPendingMatch(sku) {
    const upc = pendingMatch.value?.upc;
    if (!upc) return;

    pendingMatch.value = null;

    // A removal against a multi-bin SKU still needs a bin chosen.
    if (needsBinChoice(sku)) {
        pendingBinChoice.value = { upc, sku };
        return;
    }

    scanStatus.value = 'looking_up';

    try {
        await recordMovement(upc, sku.id, resolveTargetBin(sku));
        scanStatus.value = null;
    } catch {
        scanStatus.value = 'error';
        scheduleErrorClear();
    }
}

function dismissPendingMatch() {
    pendingMatch.value = null;
}

// ── Bin choice (which bin to pull from on a multi-bin removal) ─────────────────────
async function chooseBin(binId) {
    const choice = pendingBinChoice.value;
    if (!choice) return;

    pendingBinChoice.value = null;
    scanStatus.value = 'looking_up';

    try {
        await recordMovement(choice.upc, choice.sku.id, binId);
        scanStatus.value = null;
    } catch {
        scanStatus.value = 'error';
        scheduleErrorClear();
    }
}

function dismissBinChoice() {
    pendingBinChoice.value = null;
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

// ── Link an unknown barcode to a SKU ────────────────────────────────────────────
// Instead of dead-ending, let the user find the product this bag is and save the
// scanned barcode onto it, so it resolves on every future scan.
const linkModalOpen = ref(false);
const linkSearch = ref('');
const linkResults = ref([]);
const linkSearching = ref(false);
const linkError = ref('');
const linkProcessing = ref(false);
let linkSearchTimer = null;

function openLinkModal() {
    if (!unknownUpc.value?.upc) return;
    linkSearch.value = '';
    linkResults.value = [];
    linkError.value = '';
    linkModalOpen.value = true;
}

function closeLinkModal() {
    linkModalOpen.value = false;
    clearTimeout(linkSearchTimer);
}

watch(linkSearch, (q) => {
    clearTimeout(linkSearchTimer);
    linkError.value = '';
    const term = (q ?? '').trim();
    if (term.length < 2) {
        linkResults.value = [];
        linkSearching.value = false;
        return;
    }
    linkSearching.value = true;
    linkSearchTimer = setTimeout(async () => {
        try {
            const res = await window.axios.get(route('scan.search-skus'), {
                params: { q: term },
            });
            linkResults.value = res.data.skus ?? [];
        } catch {
            linkResults.value = [];
        } finally {
            linkSearching.value = false;
        }
    }, 300);
});

async function linkToSku(sku) {
    const upc = unknownUpc.value?.upc;
    if (!upc || linkProcessing.value) return;
    linkProcessing.value = true;
    linkError.value = '';
    try {
        await window.axios.post(route('scan.link-barcode'), {
            barcode: upc,
            sku_id: sku.id,
        });
        closeLinkModal();
        unknownUpc.value = null;
        // The barcode now resolves — re-run the scan to complete the original
        // add/remove the user was attempting.
        await processScan(upc);
    } catch (e) {
        linkError.value =
            e.response?.data?.errors?.barcode?.[0] ??
            e.response?.data?.message ??
            trans('scan.link.invalid_barcode');
    } finally {
        linkProcessing.value = false;
    }
}

function linkSkuLine(sku) {
    return [
        sku.brand?.abbreviation,
        sku.balloon_size?.name,
        sku.color?.name,
    ]
        .filter(Boolean)
        .join(' · ');
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

            <!-- ── Working bin ─────────────────────────────────────────────────── -->
            <div
                v-if="bins.length > 1"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-border bg-surface px-4 py-3"
            >
                <label
                    for="working-bin"
                    class="font-sans text-[12px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('scan.working_bin_label') }}
                </label>
                <select
                    id="working-bin"
                    v-model="workingBinId"
                    class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                >
                    <option value="">{{ $t('scan.working_bin_auto') }}</option>
                    <option v-for="b in bins" :key="b.id" :value="b.id">
                        {{ binLabel(b) }}
                        {{ b.is_default ? $t('scan.bin_default_suffix') : '' }}
                    </option>
                </select>
                <p
                    class="w-full font-sans text-[12px] text-ink-tertiary md:w-auto md:flex-1"
                >
                    {{ $t('scan.working_bin_hint') }}
                </p>
            </div>

            <!-- ── Scan input ───────────────────────────────────────────────────
                 The field now accepts typed input directly. USB scanners and
                 humans share one path: type or scan, then press Enter.
                 The previous "keyboard icon → modal" pattern was removed. -->
            <ScanField
                ref="scanFieldRef"
                :workflow="isAdding ? 'check_in' : 'check_out'"
                :recent-scans="recentScans"
                :external-status="scanStatus"
                :show-camera-button="true"
                :camera-supported="cameraSupported"
                @scan="processScan"
                @camera="openCamera"
            />

            <!-- ── Bin-label scan notice ──────────────────────────────────────── -->
            <div
                v-if="binNotice"
                class="flex items-center gap-2 rounded-lg border-2 px-4 py-3"
                :class="
                    binNotice.ok
                        ? 'border-success bg-success-soft'
                        : 'border-warning bg-warning-soft'
                "
            >
                <p class="font-sans text-[14px] font-semibold text-ink-primary">
                    <template v-if="binNotice.ok">
                        {{ $t('scan.bin_set', { bin: binNotice.label }) }}
                    </template>
                    <template v-else>
                        {{ $t('scan.bin_not_recognized') }}
                    </template>
                </p>
            </div>

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
                    @click="openLinkModal"
                >
                    {{ $t('scan.unknown_assign') }}
                </button>
            </div>

            <!-- ── Confirm match (ambiguous / low-confidence) ─────────────────────
                 Shown when the lookup found candidates but the backend did not
                 flag the scan for auto-apply. The user picks the right SKU
                 instead of us guessing and recording against the wrong one. -->
            <div
                v-if="pendingMatch"
                class="rounded-lg border-2 border-accent bg-accent-soft px-4 py-3"
            >
                <div class="mb-2 flex items-start gap-3">
                    <div class="flex-1">
                        <p
                            class="font-sans text-[14px] font-semibold text-ink-primary"
                        >
                            {{ $t('scan.confirm_heading') }}
                        </p>
                        <p
                            class="mt-0.5 font-sans text-[13px] text-ink-secondary"
                        >
                            {{ $t('scan.confirm_body') }}
                            <span class="font-mono">{{
                                pendingMatch.upc
                            }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        @click="dismissPendingMatch"
                    >
                        {{ $t('scan.confirm_cancel') }}
                    </button>
                </div>

                <ul
                    class="divide-y divide-border rounded-md border border-border bg-surface"
                >
                    <li
                        v-for="c in pendingMatch.candidates"
                        :key="c.sku.id"
                        class="flex items-center gap-3 px-3 py-2"
                    >
                        <span
                            v-if="c.sku.color?.color_hex"
                            class="inline-block h-5 w-5 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: c.sku.color.color_hex }"
                        />
                        <span
                            class="min-w-0 flex-1 truncate font-sans text-[14px] text-ink-primary"
                        >
                            {{
                                c.sku.computed_name ??
                                c.sku.name ??
                                $t('scan.unknown_sku')
                            }}
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded-md bg-accent px-3 py-1.5 font-sans text-[13px] font-semibold text-accent-on hover:bg-accent-hover"
                            @click="confirmPendingMatch(c.sku)"
                        >
                            {{ $t('scan.confirm_select') }}
                        </button>
                    </li>
                </ul>
            </div>

            <!-- ── Pick a bin (multi-bin removal) ─────────────────────────────────
                 Removing an item stored in several bins: the user says which
                 bin to pull from rather than us guessing the most-recent one. -->
            <div
                v-if="pendingBinChoice"
                class="rounded-lg border-2 border-warning bg-warning-soft px-4 py-3"
            >
                <div class="mb-2 flex items-start gap-3">
                    <div class="flex-1">
                        <p
                            class="font-sans text-[14px] font-semibold text-ink-primary"
                        >
                            {{ $t('scan.pick_bin_heading') }}
                        </p>
                        <p
                            class="mt-0.5 font-sans text-[13px] text-ink-secondary"
                        >
                            {{ $t('scan.pick_bin_body') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        @click="dismissBinChoice"
                    >
                        {{ $t('scan.pick_bin_cancel') }}
                    </button>
                </div>

                <ul
                    class="divide-y divide-border rounded-md border border-border bg-surface"
                >
                    <li
                        v-for="b in pendingBinChoice.sku.bins"
                        :key="b.bin_id"
                        class="flex items-center gap-3 px-3 py-2"
                    >
                        <span
                            class="min-w-0 flex-1 truncate font-sans text-[14px] text-ink-primary"
                        >
                            <span v-if="b.location_name" class="text-ink-tertiary"
                                >{{ b.location_name }} · </span
                            >{{ b.bin_name }}
                        </span>
                        <span class="shrink-0 font-mono text-[12px] text-ink-secondary">
                            {{
                                $t('scan.bin_holds', {
                                    full: b.full_bags,
                                    open: b.open_bags,
                                })
                            }}
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded-md bg-warning px-3 py-1.5 font-sans text-[13px] font-semibold text-white hover:brightness-110"
                            @click="chooseBin(b.bin_id)"
                        >
                            {{ $t('scan.confirm_select') }}
                        </button>
                    </li>
                </ul>
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

                        <!-- Name + bin -->
                        <span class="flex min-w-0 flex-1 flex-col">
                            <Link
                                v-if="s.sku?.id"
                                :href="route('inventory.sku.show', s.sku.id)"
                                class="truncate font-sans text-[14px] text-ink-primary hover:underline"
                            >
                                {{ s.sku.computed_name ?? s.sku.name }}
                            </Link>
                            <span
                                v-else
                                class="truncate font-sans text-[14px] text-ink-primary"
                            >
                                {{ $t('scan.unknown_sku') }}
                            </span>
                            <span
                                v-if="s.bin"
                                class="truncate font-sans text-[12px] text-ink-tertiary"
                            >
                                {{ $t('scan.recorded_to_bin', { bin: s.bin.name }) }}
                            </span>
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

        <!-- ── Link barcode to a product modal ──────────────────────────────── -->
        <Modal :show="linkModalOpen" max-width="md" @close="closeLinkModal">
            <div class="flex flex-col gap-4 p-6">
                <div>
                    <h2
                        class="font-display text-[18px] font-semibold text-ink-primary"
                    >
                        {{ $t('scan.link.title') }}
                    </h2>
                    <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                        {{ $t('scan.link.subtitle') }}
                    </p>
                    <p
                        v-if="unknownUpc"
                        class="mt-1 font-mono text-[13px] text-ink-tertiary"
                    >
                        {{ unknownUpc.upc }}
                    </p>
                </div>

                <input
                    v-model="linkSearch"
                    type="search"
                    autofocus
                    :placeholder="$t('scan.link.search_placeholder')"
                    class="w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                />

                <p
                    v-if="linkError"
                    class="font-sans text-[13px] text-danger"
                >
                    {{ linkError }}
                </p>

                <div class="max-h-72 overflow-y-auto">
                    <p
                        v-if="linkSearching"
                        class="py-4 text-center font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ $t('scan.link.searching') }}
                    </p>
                    <p
                        v-else-if="
                            linkSearch.trim().length >= 2 &&
                            linkResults.length === 0
                        "
                        class="py-4 text-center font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ $t('scan.link.no_results') }}
                    </p>
                    <ul v-else class="flex flex-col gap-1">
                        <li v-for="sku in linkResults" :key="sku.id">
                            <button
                                type="button"
                                :disabled="linkProcessing"
                                class="flex w-full items-center gap-3 rounded-md border border-border px-3 py-2.5 text-left transition hover:bg-accent-soft/40 disabled:opacity-50"
                                @click="linkToSku(sku)"
                            >
                                <span
                                    v-if="sku.color?.color_hex"
                                    class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                    :style="{
                                        backgroundColor: sku.color.color_hex,
                                    }"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate font-sans text-[14px] font-medium text-ink-primary"
                                    >
                                        {{ sku.name }}
                                    </span>
                                    <span
                                        class="block truncate font-sans text-[12px] text-ink-tertiary"
                                    >
                                        {{ linkSkuLine(sku) }}
                                    </span>
                                </span>
                                <span
                                    v-if="sku.has_barcode"
                                    class="shrink-0 rounded bg-background px-1.5 py-0.5 font-sans text-[10px] font-semibold uppercase tracking-eyebrow text-ink-tertiary ring-1 ring-border"
                                >
                                    {{ $t('scan.link.has_barcode_badge') }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        class="rounded-md border border-border-strong px-4 py-2 font-sans text-[14px] text-ink-secondary hover:bg-background"
                        @click="closeLinkModal"
                    >
                        {{ $t('scan.link.cancel') }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
