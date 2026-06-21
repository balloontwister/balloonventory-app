<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import BackLink from '@/Components/BackLink.vue';
import Modal from '@/Components/Modal.vue';
import StockBadge from '@/Components/StockBadge.vue';
import FavoriteStar from '@/Components/FavoriteStar.vue';
import ListChip from '@/Components/ListChip.vue';
import ItemFeedbackModal from '@/Components/ItemFeedbackModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    sku: { type: Object, required: true },
    override: { type: Object, default: null },
    stockLevels: { type: Array, required: true },
    identicalSkus: { type: Array, default: () => [] },
    bins: { type: Array, default: () => [] },
    recentMovements: { type: Array, required: true },
    favoritesListId: { type: String, default: null },
    isFavorite: { type: Boolean, default: false },
    reorderQuantity: { type: [Number, String], default: null },
    onLists: { type: Array, default: () => [] },
    returnQuery: { type: String, default: '' },
});

// Back link restores the list's filters (returnQuery) and scrolls to the row
// that was opened (#sku-<id>), mirroring the master catalog.
const backHref = computed(
    () => route('inventory.index') + props.returnQuery + '#sku-' + props.sku.id,
);

// Photos available for this SKU (the controller falls back to the color's image
// when the SKU has none). Single is the primary; any others are swap thumbnails.
const galleryImages = computed(() => {
    const imgs = props.sku.images ?? {};
    return [
        { url: imgs.single, label: trans('inventory.show.image_single') },
        { url: imgs.cluster, label: trans('inventory.show.image_cluster') },
    ].filter((img) => img.url);
});

const activeImage = ref(null);

// Default to the primary image, and keep the selection valid when navigating
// between SKUs (the component instance is reused, so props change in place).
watch(
    galleryImages,
    (imgs) => {
        const urls = imgs.map((img) => img.url);
        if (!urls.includes(activeImage.value)) {
            activeImage.value = urls[0] ?? null;
        }
    },
    { immediate: true },
);

const displayName = computed(
    () => props.override?.custom_name || props.sku.name,
);

const subtitle = computed(() => {
    const parts = [
        props.sku.brand?.name,
        props.sku.balloon_size?.size?.name,
        props.sku.balloon_size?.shape?.name,
    ].filter(Boolean);
    return parts.join(' · ');
});

// ── Item feedback (report a data discrepancy) ─────────────────────────────────
const showFeedback = ref(false);

// What our record shows for each reportable field, so the feedback report can
// capture the current value alongside the user's correction. Keys MUST match
// InventoryController::FEEDBACK_FIELDS.
const feedbackFieldValues = computed(() => {
    const s = props.sku;
    const barcode = [s.upc, s.ean].filter(Boolean).join(' / ');
    return {
        name: s.name,
        brand: s.brand?.name,
        size: s.balloon_size?.size?.name,
        shape: s.balloon_size?.shape?.name,
        color: s.color?.name,
        texture: s.color?.texture?.name,
        material: s.material?.name,
        count_per_bag: s.default_count_per_bag
            ? String(s.default_count_per_bag)
            : '',
        packaging: s.packaging_type?.name,
        barcode,
    };
});

const totalFullBags = computed(() =>
    props.stockLevels.reduce((sum, l) => sum + (l.full_bags ?? 0), 0),
);
const totalOpenBags = computed(() =>
    props.stockLevels.reduce((sum, l) => sum + (l.open_bags ?? 0), 0),
);

// ── Per-bin stock rows (pending + save) ───────────────────────────────────────
// Each existing stock level becomes an editable row. The steppers change a local
// pending value; a per-bin Save commits the net change as one `adjusted` movement.

function buildRows() {
    return props.stockLevels.map((l) => ({
        bin_id: l.bin?.id,
        bin_name: l.bin?.name ?? 'Default',
        location_name: l.bin?.location?.name ?? null,
        number: l.bin?.number ?? null,
        currentFull: l.full_bags ?? 0,
        currentOpen: l.open_bags ?? 0,
        full: l.full_bags ?? 0,
        open: l.open_bags ?? 0,
        saving: false,
        error: '',
        isNew: false,
    }));
}

const rows = ref(buildRows());

// After a save, Inertia replaces props with fresh server state — rebuild the rows
// so the just-saved bin settles back to a clean (non-dirty) state.
watch(
    () => props.stockLevels,
    () => {
        rows.value = buildRows();
    },
);

function isDirty(row) {
    return row.full !== row.currentFull || row.open !== row.currentOpen;
}

function step(row, field, delta) {
    row[field] = Math.max(0, (row[field] ?? 0) + delta);
}

function resetRow(row) {
    row.full = row.currentFull;
    row.open = row.currentOpen;
    row.error = '';
}

function rowIsEmpty(row) {
    return row.currentFull === 0 && row.currentOpen === 0;
}

// Dismiss an empty bin for this item. Unsaved (added-but-not-saved) rows are just
// dropped locally; persisted empty rows are removed on the server.
function removeBinRow(row) {
    if (row.isNew) {
        rows.value = rows.value.filter((r) => r !== row);
        return;
    }
    if (!window.confirm(trans('inventory.show.stock_remove_bin_confirm'))) {
        return;
    }
    router.delete(route('inventory.sku.bin.remove', [props.sku.id, row.bin_id]), {
        preserveScroll: true,
        preserveState: true,
    });
}

function saveRow(row) {
    if (!isDirty(row)) return;
    row.saving = true;
    row.error = '';
    router.post(
        route('inventory.sku.adjust', props.sku.id),
        {
            bin_id: row.bin_id,
            full_bags: row.full,
            open_bags: row.open,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                row.error =
                    errors.full_bags ||
                    errors.open_bags ||
                    errors.bin_id ||
                    trans('inventory.show.stock_save');
            },
            onFinish: () => {
                row.saving = false;
            },
        },
    );
}

// ── Add a bin that doesn't yet hold this SKU ──────────────────────────────────

const availableBins = computed(() =>
    props.bins.filter((b) => !rows.value.some((r) => r.bin_id === b.id)),
);

const addingBin = ref(false);
const newBinId = ref('');

function addBinRow() {
    const bin = props.bins.find((b) => b.id === newBinId.value);
    if (!bin) return;
    rows.value.push({
        bin_id: bin.id,
        bin_name: bin.name,
        location_name: bin.location_name ?? null,
        number: bin.number ?? null,
        currentFull: 0,
        currentOpen: 0,
        full: 0,
        open: 0,
        saving: false,
        error: '',
        isNew: true,
    });
    addingBin.value = false;
    newBinId.value = '';
}

function binRowLabel(row) {
    const number = row.number != null ? ` #${row.number}` : '';
    if (row.location_name) {
        return `${row.location_name} · ${row.bin_name}${number}`;
    }
    return `${row.bin_name}${number}`;
}

// ── Transfer between bins ─────────────────────────────────────────────────────

const binsWithStock = computed(() =>
    props.stockLevels
        .filter((l) => (l.full_bags ?? 0) > 0 || (l.open_bags ?? 0) > 0)
        .map((l) => ({
            bin_id: l.bin?.id,
            name: l.bin?.name,
            location_name: l.bin?.location?.name,
            full_bags: l.full_bags ?? 0,
            open_bags: l.open_bags ?? 0,
        })),
);

const canTransfer = computed(() => props.bins.length > 1);

const transferOpen = ref(false);
const transferForm = useForm({
    from_bin_id: '',
    to_bin_id: '',
    full_bags_change: 0,
    open_bags_change: 0,
});

function binOptionLabel(bin) {
    const number = bin.number != null ? `#${bin.number} ` : '';
    const location = bin.location_name ? `${bin.location_name} · ` : '';
    return `${location}${number}${bin.name}`;
}

function rowHasStock(row) {
    return row.currentFull > 0 || row.currentOpen > 0;
}

function openTransfer(fromBinId = null) {
    transferForm.reset();
    transferForm.clearErrors();
    transferForm.from_bin_id =
        fromBinId ?? binsWithStock.value[0]?.bin_id ?? '';
    transferOpen.value = true;
}

function submitTransfer() {
    transferForm.post(route('inventory.sku.transfer', props.sku.id), {
        preserveScroll: true,
        onSuccess: () => {
            transferOpen.value = false;
        },
    });
}

// ── Customizations (hidden until opened) ──────────────────────────────────────

const hasOverride = computed(
    () =>
        !!(
            props.override?.custom_name ||
            props.override?.custom_color_hex ||
            props.override?.notes
        ),
);

const editingOverride = ref(false);

const overrideForm = useForm({
    custom_name: props.override?.custom_name ?? '',
    custom_color_hex: props.override?.custom_color_hex ?? '',
    notes: props.override?.notes ?? '',
});

function saveOverride() {
    overrideForm.patch(route('inventory.override.update', props.sku.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingOverride.value = false;
        },
    });
}

// ── Activity history ──────────────────────────────────────────────────────────
// Show the 3 most recent by default; the rest expand behind a toggle.

const HISTORY_PREVIEW = 3;
const showAllHistory = ref(false);

const visibleMovements = computed(() =>
    showAllHistory.value
        ? props.recentMovements
        : props.recentMovements.slice(0, HISTORY_PREVIEW),
);

const hiddenHistoryCount = computed(() =>
    Math.max(0, props.recentMovements.length - HISTORY_PREVIEW),
);

function directionLabel(direction) {
    const map = {
        in: trans('inventory.show.history_direction_in'),
        out: trans('inventory.show.history_direction_out'),
        removed: trans('inventory.show.history_direction_removed'),
        restored: trans('inventory.show.history_direction_restored'),
        adjusted: trans('inventory.show.history_direction_adjusted'),
    };
    return map[direction] ?? direction;
}

function movementSummary(movement) {
    const full = movement.full_bags_change ?? 0;
    const open = movement.open_bags_change ?? 0;
    if (full === 0 && open === 0) return '—';
    const parts = [];
    if (full !== 0) parts.push(`${full > 0 ? '+' : ''}${full} bags`);
    if (open !== 0) parts.push(`${open > 0 ? '+' : ''}${open} open`);
    return parts.join(', ');
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="displayName" />

    <AuthenticatedLayout>
        <template #header>
            <BackLink :href="backHref" :label="$t('inventory.show.back')" />
        </template>

        <div class="mx-auto max-w-4xl">
            <!-- SKU header -->
            <div class="mb-6 flex items-start gap-3">
                <span
                    v-if="sku.color?.color_hex"
                    class="mt-1 inline-block h-5 w-5 shrink-0 rounded ring-1 ring-inset ring-black/10"
                    :style="{ backgroundColor: sku.color.color_hex }"
                />
                <div class="min-w-0 flex-1">
                    <h1
                        class="font-display text-[22px] font-semibold text-ink-primary"
                    >
                        {{ displayName }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="mt-0.5 font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ subtitle }}
                    </p>
                    <p
                        v-if="override?.custom_name"
                        class="mt-0.5 font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ sku.name }}
                    </p>
                </div>
                <FavoriteStar
                    v-if="favoritesListId"
                    :sku-id="sku.id"
                    :is-favorite="isFavorite"
                    :favorite-list-id="favoritesListId"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Details card — top on mobile, right sidebar on desktop -->
                <aside class="order-first lg:order-last lg:col-span-1">
                    <div
                        class="rounded-lg border border-border bg-surface p-4 lg:sticky lg:top-4"
                    >
                        <div v-if="galleryImages.length" class="mb-4 flex gap-2">
                            <img
                                :src="activeImage"
                                :alt="
                                    $t('inventory.show.image_alt', {
                                        name: sku.name,
                                    })
                                "
                                class="h-28 w-28 shrink-0 rounded-md object-contain ring-1 ring-inset ring-border"
                            />
                            <div
                                v-if="galleryImages.length > 1"
                                class="flex flex-col gap-2"
                            >
                                <button
                                    v-for="img in galleryImages"
                                    :key="img.url"
                                    type="button"
                                    :aria-label="img.label"
                                    :aria-pressed="img.url === activeImage"
                                    class="h-10 w-10 shrink-0 overflow-hidden rounded-md bg-surface transition"
                                    :class="
                                        img.url === activeImage
                                            ? 'ring-2 ring-accent'
                                            : 'ring-1 ring-inset ring-border hover:ring-border-strong'
                                    "
                                    @click="activeImage = img.url"
                                >
                                    <img
                                        :src="img.url"
                                        :alt="img.label"
                                        class="h-full w-full object-contain"
                                    />
                                </button>
                            </div>
                        </div>
                        <h2
                            class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.section_details') }}
                        </h2>
                        <dl class="flex flex-col gap-2 font-sans text-[13px]">
                            <div
                                v-if="sku.brand"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_brand') }}
                                </dt>
                                <dd class="text-right text-ink-primary">
                                    {{ sku.brand.name }}
                                </dd>
                            </div>
                            <div
                                v-if="sku.balloon_size?.size"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_size') }}
                                </dt>
                                <dd class="text-right text-ink-primary">
                                    {{ sku.balloon_size.size.name }}
                                </dd>
                            </div>
                            <div
                                v-if="sku.color"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_color') }}
                                </dt>
                                <dd
                                    class="flex items-center gap-1.5 text-right text-ink-primary"
                                >
                                    <span
                                        v-if="sku.color.color_hex"
                                        class="inline-block h-2.5 w-2.5 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor: sku.color.color_hex,
                                        }"
                                    />
                                    {{ sku.color.name }}
                                </dd>
                            </div>
                            <div
                                v-if="sku.color?.texture"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_texture') }}
                                </dt>
                                <dd class="text-right text-ink-primary">
                                    {{ sku.color.texture.name }}
                                </dd>
                            </div>
                            <div
                                v-if="sku.material"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_material') }}
                                </dt>
                                <dd class="text-right text-ink-primary">
                                    {{ sku.material.name }}
                                </dd>
                            </div>
                            <div
                                v-if="sku.default_count_per_bag"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_count') }}
                                </dt>
                                <dd class="text-right text-ink-primary">
                                    {{
                                        $t('inventory.show.detail_count_value', {
                                            count: sku.default_count_per_bag,
                                        })
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="sku.packaging_type"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_packaging') }}
                                </dt>
                                <dd class="text-right text-ink-primary">
                                    {{ sku.packaging_type.name }}
                                </dd>
                            </div>
                            <!-- Barcode the system matches scans against, so the
                                 user can confirm our record matches the bag. -->
                            <div
                                v-if="sku.upc"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_upc') }}
                                </dt>
                                <dd class="text-right font-mono text-ink-primary">
                                    {{ sku.upc }}
                                </dd>
                            </div>
                            <div
                                v-if="sku.ean"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_ean') }}
                                </dt>
                                <dd class="text-right font-mono text-ink-primary">
                                    {{ sku.ean }}
                                </dd>
                            </div>
                            <div
                                v-if="!sku.upc && !sku.ean"
                                class="flex items-center justify-between gap-3"
                            >
                                <dt class="text-ink-secondary">
                                    {{ $t('inventory.show.detail_upc') }}
                                </dt>
                                <dd class="text-right text-ink-tertiary">
                                    {{ $t('inventory.show.detail_barcode_none') }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </aside>

                <!-- Main column: stock + customizations + activity -->
                <div class="space-y-8 lg:col-span-2">
                    <!-- Stock section -->
                    <section>
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-baseline gap-3">
                                <h2
                                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('inventory.show.section_stock') }}
                                </h2>
                                <StockBadge
                                    :full-bags="totalFullBags"
                                    :open-bags="totalOpenBags"
                                />
                            </div>
                            <AppButton
                                v-if="availableBins.length > 0 && !addingBin"
                                variant="ghost"
                                size="sm"
                                @click="addingBin = true"
                            >
                                + {{ $t('inventory.show.stock_add_bin') }}
                            </AppButton>
                        </div>

                        <!-- Add-bin picker -->
                        <div
                            v-if="addingBin"
                            class="mb-3 flex items-center gap-2 rounded-md border border-border bg-background px-3 py-2"
                        >
                            <select
                                v-model="newBinId"
                                class="flex-1 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            >
                                <option value="" disabled>
                                    {{ $t('inventory.show.stock_choose_bin') }}
                                </option>
                                <option
                                    v-for="b in availableBins"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ binOptionLabel(b) }}
                                </option>
                            </select>
                            <AppButton
                                variant="primary"
                                size="sm"
                                :disabled="!newBinId"
                                @click="addBinRow"
                            >
                                {{ $t('inventory.show.stock_add') }}
                            </AppButton>
                            <AppButton
                                variant="ghost"
                                size="sm"
                                @click="
                                    addingBin = false;
                                    newBinId = '';
                                "
                            >
                                {{ $t('inventory.show.stock_add_cancel') }}
                            </AppButton>
                        </div>

                        <!-- Per-bin rows -->
                        <div class="space-y-2">
                            <div
                                v-for="row in rows"
                                :key="row.bin_id"
                                class="rounded-md border border-border px-3 py-2.5"
                                :class="{
                                    'border-accent bg-accent-soft/30':
                                        isDirty(row),
                                }"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="font-sans text-[13px] font-medium text-ink-primary"
                                    >
                                        {{ binRowLabel(row) }}
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <AppButton
                                            v-if="
                                                canTransfer &&
                                                rowHasStock(row) &&
                                                !isDirty(row)
                                            "
                                            variant="ghost"
                                            size="sm"
                                            @click="openTransfer(row.bin_id)"
                                        >
                                            {{ $t('inventory.show.stock_move') }}
                                        </AppButton>
                                        <AppButton
                                            v-if="rowIsEmpty(row) && !isDirty(row)"
                                            variant="ghost"
                                            size="sm"
                                            class="text-ink-tertiary hover:text-danger"
                                            @click="removeBinRow(row)"
                                        >
                                            {{
                                                row.isNew
                                                    ? $t(
                                                          'inventory.show.stock_discard_bin',
                                                      )
                                                    : $t(
                                                          'inventory.show.stock_remove_bin',
                                                      )
                                            }}
                                        </AppButton>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-4">
                                    <!-- Full bags stepper -->
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="w-10 font-sans text-[12px] text-ink-secondary"
                                            >{{
                                                $t('inventory.show.stock_full')
                                            }}</span
                                        >
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 items-center justify-center rounded-md border border-border-strong text-ink-secondary hover:bg-background disabled:opacity-40"
                                            :disabled="row.full <= 0"
                                            :aria-label="`− ${$t('inventory.show.stock_full')}`"
                                            @click="step(row, 'full', -1)"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                class="h-3.5 w-3.5"
                                            >
                                                <path
                                                    d="M4 10a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z"
                                                />
                                            </svg>
                                        </button>
                                        <span
                                            class="min-w-[1.5rem] text-center font-mono text-[15px] font-medium text-ink-primary"
                                            >{{ row.full }}</span
                                        >
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 items-center justify-center rounded-md border border-border-strong text-ink-secondary hover:bg-background"
                                            :aria-label="`+ ${$t('inventory.show.stock_full')}`"
                                            @click="step(row, 'full', 1)"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                class="h-3.5 w-3.5"
                                            >
                                                <path
                                                    d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"
                                                />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Open bags stepper -->
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="w-10 font-sans text-[12px] text-ink-secondary"
                                            >{{
                                                $t('inventory.show.stock_open')
                                            }}</span
                                        >
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 items-center justify-center rounded-md border border-border-strong text-ink-secondary hover:bg-background disabled:opacity-40"
                                            :disabled="row.open <= 0"
                                            :aria-label="`− ${$t('inventory.show.stock_open')}`"
                                            @click="step(row, 'open', -1)"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                class="h-3.5 w-3.5"
                                            >
                                                <path
                                                    d="M4 10a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z"
                                                />
                                            </svg>
                                        </button>
                                        <span
                                            class="min-w-[1.5rem] text-center font-mono text-[15px] font-medium text-ink-primary"
                                            >{{ row.open }}</span
                                        >
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 items-center justify-center rounded-md border border-border-strong text-ink-secondary hover:bg-background"
                                            :aria-label="`+ ${$t('inventory.show.stock_open')}`"
                                            @click="step(row, 'open', 1)"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                class="h-3.5 w-3.5"
                                            >
                                                <path
                                                    d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"
                                                />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Save / reset (only when dirty) -->
                                    <div
                                        v-if="isDirty(row)"
                                        class="ml-auto flex items-center gap-2"
                                    >
                                        <span
                                            class="font-sans text-[12px] text-ink-tertiary"
                                        >
                                            {{
                                                $t(
                                                    'inventory.show.stock_pending_hint',
                                                    {
                                                        full: row.currentFull,
                                                        open: row.currentOpen,
                                                    },
                                                )
                                            }}
                                        </span>
                                        <AppButton
                                            variant="ghost"
                                            size="sm"
                                            @click="resetRow(row)"
                                        >
                                            {{
                                                $t('inventory.show.stock_reset')
                                            }}
                                        </AppButton>
                                        <AppButton
                                            variant="primary"
                                            size="sm"
                                            :disabled="row.saving"
                                            @click="saveRow(row)"
                                        >
                                            {{ $t('inventory.show.stock_save') }}
                                        </AppButton>
                                    </div>
                                </div>

                                <p
                                    v-if="row.error"
                                    class="mt-2 font-sans text-[13px] text-danger"
                                >
                                    {{ row.error }}
                                </p>
                            </div>
                        </div>

                        <!-- Reorder quantity from Favorites -->
                        <div
                            v-if="reorderQuantity !== null"
                            class="mt-3 flex items-center gap-2"
                        >
                            <span class="font-sans text-[13px] text-ink-secondary"
                                >{{ $t('inventory.show.reorder_label') }}:</span
                            >
                            <span
                                class="font-mono text-[13px] font-medium text-ink-primary"
                                >{{ reorderQuantity }}</span
                            >
                            <span class="font-sans text-[12px] text-ink-tertiary"
                                >— {{ $t('inventory.show.reorder_hint') }}</span
                            >
                        </div>

                        <!-- Lists this item is on -->
                        <div
                            v-if="onLists.length"
                            class="mt-4 rounded-md border border-accent/20 bg-accent-soft/40 px-4 py-3"
                        >
                            <p
                                class="mb-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                            >
                                {{ $t('inventory.show.on_lists_label') }}
                            </p>
                            <ListChip :lists="onLists" />
                        </div>
                    </section>

                    <!-- Identical items in inventory -->
                    <section v-if="identicalSkus.length > 0">
                        <h2
                            class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.section_identical') }}
                        </h2>
                        <div class="space-y-2">
                            <Link
                                v-for="item in identicalSkus"
                                :key="item.id"
                                :href="route('inventory.sku.show', item.id)"
                                class="group flex items-center gap-3 rounded-md border border-border px-3 py-2.5 transition hover:bg-accent-soft/40"
                            >
                                <span
                                    v-if="item.color?.color_hex"
                                    class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                    :style="{
                                        backgroundColor: item.color.color_hex,
                                    }"
                                />
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate font-sans text-[14px] font-medium text-ink-primary group-hover:underline"
                                    >
                                        {{ item.name }}
                                    </p>
                                    <p
                                        class="font-sans text-[12px] text-ink-tertiary"
                                    >
                                        {{ item.brand?.abbreviation }}
                                        <template v-if="item.balloon_size?.size">
                                            · {{ item.balloon_size.size.name }}
                                        </template>
                                    </p>
                                </div>
                                <StockBadge
                                    :full-bags="item.full_bags_total ?? 0"
                                    :open-bags="item.open_bags_total ?? 0"
                                />
                            </Link>
                        </div>
                    </section>

                    <!-- Similar items (stub) -->
                    <section>
                        <h2
                            class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.section_similar') }}
                        </h2>
                        <div
                            class="rounded-md border border-dashed border-border px-3 py-4 text-center font-sans text-[13px] text-ink-tertiary"
                        >
                            {{ $t('inventory.show.similar_coming_soon') }}
                        </div>
                    </section>

                    <!-- Customizations (collapsed by default) -->
                    <section>
                        <!-- Collapsed: prompt / summary -->
                        <div v-if="!editingOverride">
                            <button
                                v-if="!hasOverride"
                                type="button"
                                class="flex items-center gap-2 font-sans text-[13px] text-ink-secondary hover:text-ink-primary"
                                @click="editingOverride = true"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4"
                                >
                                    <path
                                        d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"
                                    />
                                </svg>
                                {{ $t('inventory.show.customize_add') }}
                            </button>
                            <div
                                v-else
                                class="flex items-center justify-between rounded-md border border-border px-3 py-2.5"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="font-sans text-[13px] font-medium text-ink-primary"
                                    >
                                        {{
                                            override?.custom_name ||
                                            $t(
                                                'inventory.show.customize_summary',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="override?.notes"
                                        class="mt-0.5 truncate font-sans text-[12px] text-ink-tertiary"
                                    >
                                        {{ override.notes }}
                                    </p>
                                </div>
                                <AppButton
                                    variant="ghost"
                                    size="sm"
                                    @click="editingOverride = true"
                                >
                                    {{ $t('inventory.show.customize_edit') }}
                                </AppButton>
                            </div>
                        </div>

                        <!-- Expanded: the form -->
                        <div v-else>
                            <h2
                                class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                            >
                                {{ $t('inventory.show.section_override') }}
                            </h2>
                            <form
                                @submit.prevent="saveOverride"
                                class="flex flex-col gap-4"
                            >
                                <!-- Custom name -->
                                <div class="flex flex-col gap-1">
                                    <label
                                        for="custom-name"
                                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                    >
                                        {{
                                            $t(
                                                'inventory.show.override_custom_name_label',
                                            )
                                        }}
                                    </label>
                                    <input
                                        id="custom-name"
                                        v-model="overrideForm.custom_name"
                                        type="text"
                                        :placeholder="
                                            $t(
                                                'inventory.show.override_custom_name_placeholder',
                                            )
                                        "
                                        class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                        :class="{
                                            'border-danger focus:border-danger focus:ring-danger-soft':
                                                overrideForm.errors.custom_name,
                                        }"
                                    />
                                    <p
                                        v-if="overrideForm.errors.custom_name"
                                        class="font-sans text-[13px] text-danger"
                                    >
                                        {{ overrideForm.errors.custom_name }}
                                    </p>
                                </div>

                                <!-- Custom color hex -->
                                <div class="flex flex-col gap-1">
                                    <label
                                        for="custom-color"
                                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                    >
                                        {{
                                            $t(
                                                'inventory.show.override_color_hex_label',
                                            )
                                        }}
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="custom-color"
                                            v-model="
                                                overrideForm.custom_color_hex
                                            "
                                            type="text"
                                            placeholder="#RRGGBB"
                                            maxlength="7"
                                            class="w-32 rounded-md border border-border-strong bg-surface px-3 py-2 font-mono text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                            :class="{
                                                'border-danger focus:border-danger focus:ring-danger-soft':
                                                    overrideForm.errors
                                                        .custom_color_hex,
                                            }"
                                        />
                                        <span
                                            v-if="
                                                overrideForm.custom_color_hex?.match(
                                                    /^#[0-9a-fA-F]{6}$/,
                                                )
                                            "
                                            class="inline-block h-7 w-7 rounded ring-1 ring-inset ring-black/10"
                                            :style="{
                                                backgroundColor:
                                                    overrideForm.custom_color_hex,
                                            }"
                                        />
                                    </div>
                                    <p
                                        v-if="
                                            overrideForm.errors.custom_color_hex
                                        "
                                        class="font-sans text-[13px] text-danger"
                                    >
                                        {{
                                            overrideForm.errors.custom_color_hex
                                        }}
                                    </p>
                                </div>

                                <!-- Notes -->
                                <div class="flex flex-col gap-1">
                                    <label
                                        for="notes"
                                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                    >
                                        {{
                                            $t(
                                                'inventory.show.override_notes_label',
                                            )
                                        }}
                                    </label>
                                    <textarea
                                        id="notes"
                                        v-model="overrideForm.notes"
                                        rows="3"
                                        :placeholder="
                                            $t(
                                                'inventory.show.override_notes_placeholder',
                                            )
                                        "
                                        class="resize-y rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                        :class="{
                                            'border-danger focus:border-danger focus:ring-danger-soft':
                                                overrideForm.errors.notes,
                                        }"
                                    />
                                    <p
                                        v-if="overrideForm.errors.notes"
                                        class="font-sans text-[13px] text-danger"
                                    >
                                        {{ overrideForm.errors.notes }}
                                    </p>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <AppButton
                                        variant="secondary"
                                        type="button"
                                        @click="editingOverride = false"
                                    >
                                        {{
                                            $t('inventory.show.customize_cancel')
                                        }}
                                    </AppButton>
                                    <AppButton
                                        variant="primary"
                                        type="submit"
                                        :disabled="overrideForm.processing"
                                    >
                                        {{ $t('inventory.show.override_save') }}
                                    </AppButton>
                                </div>
                            </form>
                        </div>
                    </section>

                    <!-- Recent activity section -->
                    <section>
                        <h2
                            class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.section_history') }}
                        </h2>

                        <p
                            v-if="recentMovements.length === 0"
                            class="font-sans text-[14px] text-ink-tertiary"
                        >
                            {{ $t('inventory.show.history_no_activity') }}
                        </p>

                        <div
                            v-else
                            class="overflow-hidden rounded-lg border border-border"
                        >
                            <table class="w-full">
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="movement in visibleMovements"
                                        :key="movement.id"
                                    >
                                        <td
                                            class="px-3 py-2.5 font-sans text-[13px] text-ink-secondary"
                                        >
                                            {{ formatDate(movement.created_at) }}
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span
                                                class="font-sans text-[13px] font-medium text-ink-primary"
                                            >
                                                {{
                                                    directionLabel(
                                                        movement.direction,
                                                    )
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-3 py-2.5 font-mono text-[13px] text-ink-secondary"
                                        >
                                            {{ movementSummary(movement) }}
                                        </td>
                                        <td
                                            class="px-3 py-2.5 font-sans text-[13px] text-ink-secondary"
                                        >
                                            {{ movement.user?.name ?? '—' }}
                                        </td>
                                        <td
                                            class="px-3 py-2.5 font-sans text-[13px] text-ink-tertiary"
                                        >
                                            {{ movement.notes ?? '' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button
                            v-if="hiddenHistoryCount > 0"
                            type="button"
                            class="mt-2 font-sans text-[13px] font-medium text-accent hover:underline"
                            @click="showAllHistory = !showAllHistory"
                        >
                            {{
                                showAllHistory
                                    ? $t('inventory.show.history_show_less')
                                    : $t('inventory.show.history_show_more', {
                                          count: hiddenHistoryCount,
                                      })
                            }}
                        </button>
                    </section>

                    <!-- Report a discrepancy between our data and the bag -->
                    <section class="border-t border-border pt-4">
                        <button
                            type="button"
                            class="flex items-center gap-2 font-sans text-[13px] text-ink-tertiary transition hover:text-ink-primary"
                            @click="showFeedback = true"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t('inventory.show.feedback_trigger') }}
                        </button>
                    </section>
                </div>
            </div>
        </div>

        <!-- Item feedback modal -->
        <ItemFeedbackModal
            :show="showFeedback"
            :sku="{ id: sku.id, name: sku.name }"
            :field-values="feedbackFieldValues"
            @close="showFeedback = false"
        />

        <!-- Transfer modal -->
        <Modal :show="transferOpen" max-width="md" @close="transferOpen = false">
            <form class="flex flex-col gap-4 p-6" @submit.prevent="submitTransfer">
                <h2 class="font-display text-[18px] font-semibold text-ink-primary">
                    {{ $t('inventory.show.transfer_title') }}
                </h2>

                <div class="flex flex-col gap-1">
                    <label
                        for="transfer-from"
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('inventory.show.transfer_from') }}
                    </label>
                    <select
                        id="transfer-from"
                        v-model="transferForm.from_bin_id"
                        class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    >
                        <option
                            v-for="b in binsWithStock"
                            :key="b.bin_id"
                            :value="b.bin_id"
                        >
                            {{ binOptionLabel(b) }} —
                            {{
                                $t('inventory.show.transfer_bin_holds', {
                                    full: b.full_bags,
                                    open: b.open_bags,
                                })
                            }}
                        </option>
                    </select>
                    <p
                        v-if="transferForm.errors.from_bin_id"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ transferForm.errors.from_bin_id }}
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label
                        for="transfer-to"
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('inventory.show.transfer_to') }}
                    </label>
                    <select
                        id="transfer-to"
                        v-model="transferForm.to_bin_id"
                        class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    >
                        <option value="" disabled>—</option>
                        <option
                            v-for="b in bins.filter(
                                (x) => x.id !== transferForm.from_bin_id,
                            )"
                            :key="b.id"
                            :value="b.id"
                        >
                            {{ binOptionLabel(b) }}
                        </option>
                    </select>
                    <p
                        v-if="transferForm.errors.to_bin_id"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ transferForm.errors.to_bin_id }}
                    </p>
                </div>

                <div class="flex gap-3">
                    <div class="flex-1">
                        <AppInput
                            id="transfer-full"
                            v-model="transferForm.full_bags_change"
                            type="number"
                            :label="$t('inventory.show.transfer_full_bags')"
                            :error="transferForm.errors.full_bags_change"
                        />
                    </div>
                    <div class="flex-1">
                        <AppInput
                            id="transfer-open"
                            v-model="transferForm.open_bags_change"
                            type="number"
                            :label="$t('inventory.show.transfer_open_bags')"
                            :error="transferForm.errors.open_bags_change"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <AppButton
                        variant="secondary"
                        type="button"
                        @click="transferOpen = false"
                    >
                        {{ $t('inventory.show.transfer_cancel') }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        type="submit"
                        :disabled="transferForm.processing"
                    >
                        {{ $t('inventory.show.transfer_submit') }}
                    </AppButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
