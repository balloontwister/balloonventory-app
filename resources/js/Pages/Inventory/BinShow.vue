<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import BackLink from '@/Components/BackLink.vue';
import InfoButton from '@/Components/InfoButton.vue';
import Modal from '@/Components/Modal.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StockBadge from '@/Components/StockBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useBusiness } from '@/Composables/useBusiness.js';
import {
    LABEL_PRESETS,
    buildLabelSvg,
    labelToPngBlob,
} from '@/Composables/useBinLabels';

const props = defineProps({
    bin: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    bins: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    fullBagsTotal: { type: Number, default: 0 },
    openBagsTotal: { type: Number, default: 0 },
    // Where the user arrived from: '' (default, → By-Bin wall) or 'manage'.
    from: { type: String, default: '' },
});

const { can } = useBusiness();
const canManage = computed(() => can('inventory.manual_adjust'));

const binTitle = computed(() => {
    const number = props.bin.number != null ? `#${props.bin.number} ` : '';
    return `${number}${props.bin.name}`;
});

// Return to wherever the user came from — Manage storage when opened there,
// otherwise the By-Bin wall.
const backHref = computed(() =>
    props.from === 'manage'
        ? route('inventory.storage')
        : route('inventory.bins.index'),
);
const backLabel = computed(() =>
    props.from === 'manage'
        ? trans('bins.show.back_manage')
        : trans('bins.show.back'),
);

// Carry this bin as the origin when opening an item, so the SKU page's back
// link returns here instead of the inventory list.
function itemHref(skuId) {
    return route('inventory.sku.show', {
        sku: skuId,
        from: 'bin',
        bin: props.bin.id,
    });
}

const otherBins = computed(() =>
    props.bins.filter((b) => b.id !== props.bin.id),
);

function binOptionLabel(bin) {
    const number = bin.number != null ? `#${bin.number} ` : '';
    const location = bin.location_name ? `${bin.location_name} · ` : '';
    return `${location}${number}${bin.name}`;
}

// ── Item rows (local editable copy of props.items) ────────────────────────────
// Each row carries the bin's current counts plus a pending edit value, mirroring
// the per-bin stepper pattern on the SKU detail page.

function buildRows() {
    return props.items.map((item) => ({
        sku_id: item.sku_id,
        name: item.name,
        brand: item.brand,
        size: item.size,
        color_hex: item.color_hex,
        currentFull: item.full_bags ?? 0,
        currentOpen: item.open_bags ?? 0,
        full: item.full_bags ?? 0,
        open: item.open_bags ?? 0,
        editing: false,
        saving: false,
        error: '',
    }));
}

const rows = ref(buildRows());

// After any save, Inertia replaces props with fresh server state — rebuild rows
// so the page settles back to a clean (non-editing) state.
watch(
    () => props.items,
    () => {
        rows.value = buildRows();
    },
);

function isDirty(row) {
    return row.full !== row.currentFull || row.open !== row.currentOpen;
}

function startAdjust(row) {
    row.editing = true;
    row.error = '';
}

function step(row, field, delta) {
    row[field] = Math.max(0, (row[field] ?? 0) + delta);
}

function resetRow(row) {
    row.full = row.currentFull;
    row.open = row.currentOpen;
    row.error = '';
    row.editing = false;
}

function saveRow(row) {
    if (!isDirty(row)) {
        row.editing = false;
        return;
    }
    row.saving = true;
    row.error = '';
    router.post(
        route('inventory.sku.adjust', row.sku_id),
        {
            bin_id: props.bin.id,
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
                    trans('bins.show.save');
            },
            onFinish: () => {
                row.saving = false;
            },
        },
    );
}

// ── Modal (a single dialog switches between add / move / edit / label) ─────────
// One dialog at a time avoids stacked native-dialog backdrops trapping clicks.
const modalType = ref(null); // null | 'add' | 'move' | 'edit' | 'label'

function closeModal() {
    modalType.value = null;
}

// ── Edit this bin (name / number / location / description) ─────────────────────
const editForm = useForm({
    location_id: '',
    name: '',
    number: '',
    number_locked: false,
    description: '',
});

function openEdit() {
    editForm.clearErrors();
    editForm.location_id = props.bin.location_id;
    editForm.name = props.bin.name;
    editForm.number = props.bin.number ?? '';
    editForm.number_locked = !!props.bin.number_locked;
    editForm.description = props.bin.description ?? '';
    modalType.value = 'edit';
}

function submitEdit() {
    editForm.patch(route('inventory.bins.update', { bin: props.bin.id }), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
}

// Delete the bin (blocked server-side for the Default bin or one holding
// stock). On success the controller redirects to the By-Bin wall.
function deleteBin() {
    if (!window.confirm(trans('bins.delete.bin_confirm'))) {
        return;
    }
    router.delete(route('inventory.bins.destroy', { bin: props.bin.id }));
}

// ── Move an item to another bin ───────────────────────────────────────────────
const moveForm = useForm({
    from_bin_id: '',
    to_bin_id: '',
    full_bags_change: 0,
    open_bags_change: 0,
});
const moveRow = ref(null);

function openMove(row) {
    moveRow.value = row;
    moveForm.reset();
    moveForm.clearErrors();
    moveForm.from_bin_id = props.bin.id;
    moveForm.to_bin_id = otherBins.value[0]?.id ?? '';
    modalType.value = 'move';
}

function submitMove() {
    moveForm.post(route('inventory.sku.transfer', moveRow.value.sku_id), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
}

// ── Add an item to this bin ───────────────────────────────────────────────────
const searchTerm = ref('');
const searchResults = ref([]);
const searching = ref(false);
const searchedOnce = ref(false);
const selectedItem = ref(null);
const addForm = useForm({ sku_id: '', full_bags: 1, open_bags: 0 });

let searchTimer = null;

function openAdd() {
    searchTerm.value = '';
    searchResults.value = [];
    searchedOnce.value = false;
    selectedItem.value = null;
    addForm.reset();
    addForm.clearErrors();
    modalType.value = 'add';
}

watch(searchTerm, (term) => {
    clearTimeout(searchTimer);
    const q = term.trim();
    if (q.length < 2) {
        searchResults.value = [];
        searching.value = false;
        searchedOnce.value = false;
        return;
    }
    searching.value = true;
    searchTimer = setTimeout(async () => {
        try {
            const { data } = await window.axios.get(
                route('inventory.bins.search-items', { bin: props.bin.id }),
                { params: { q } },
            );
            searchResults.value = data.items;
        } catch {
            searchResults.value = [];
        } finally {
            searching.value = false;
            searchedOnce.value = true;
        }
    }, 300);
});

onUnmounted(() => clearTimeout(searchTimer));

function pickItem(item) {
    if (item.in_bin) return;
    selectedItem.value = item;
    addForm.sku_id = item.sku_id;
}

function clearPick() {
    selectedItem.value = null;
    addForm.sku_id = '';
}

function submitAdd() {
    addForm.post(route('inventory.bins.add-item', { bin: props.bin.id }), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
}

// ── Bin label (view / export) ─────────────────────────────────────────────────
const labelPresets = LABEL_PRESETS;
const sizeKey = ref(LABEL_PRESETS[0].key);
const customWidthIn = ref(2.625);
const customHeightIn = ref(1);
const copyState = ref('');

const labelText = computed(() => {
    const number = props.bin.number != null ? `#${props.bin.number} ` : '';
    const location = props.bin.location_name
        ? `${props.bin.location_name} · `
        : '';
    return `${location}${number}${props.bin.name}`;
});

const labelDims = computed(() => {
    if (sizeKey.value === 'custom') {
        return {
            widthIn: Math.min(
                Math.max(Number(customWidthIn.value) || 0, 0.5),
                8,
            ),
            heightIn: Math.min(
                Math.max(Number(customHeightIn.value) || 0, 0.25),
                11,
            ),
        };
    }
    const preset = LABEL_PRESETS.find((p) => p.key === sizeKey.value);
    return { widthIn: preset.widthIn, heightIn: preset.heightIn };
});

const previewSvg = computed(() =>
    props.bin.scan_code
        ? buildLabelSvg({
              name: labelText.value,
              code: props.bin.scan_code,
              widthIn: labelDims.value.widthIn,
              heightIn: labelDims.value.heightIn,
          })
        : '',
);

function openLabel() {
    copyState.value = '';
    modalType.value = 'label';
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

async function copyLabelImage() {
    copyState.value = '';
    try {
        const blob = await labelToPngBlob(
            previewSvg.value,
            labelDims.value.widthIn,
            labelDims.value.heightIn,
        );
        await navigator.clipboard.write([
            new ClipboardItem({ 'image/png': blob }),
        ]);
        copyState.value = 'copied';
    } catch {
        copyState.value = 'error';
    }
}

async function downloadLabelPng() {
    const blob = await labelToPngBlob(
        previewSvg.value,
        labelDims.value.widthIn,
        labelDims.value.heightIn,
    );
    downloadBlob(blob, `${props.bin.scan_code}.png`);
}

function downloadLabelSvg() {
    downloadBlob(
        new Blob([previewSvg.value], { type: 'image/svg+xml' }),
        `${props.bin.scan_code}.svg`,
    );
}
</script>

<template>
    <Head :title="binTitle" />

    <AuthenticatedLayout>
        <template #header>
            <BackLink :href="backHref" :label="backLabel" />
        </template>

        <div class="mx-auto max-w-3xl">
            <!-- Bin header -->
            <div class="mb-6 flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h1
                            class="font-display text-[22px] font-semibold text-ink-primary"
                        >
                            {{ bin.name }}
                        </h1>
                        <span
                            v-if="bin.number != null"
                            class="rounded-md bg-background px-1.5 py-0.5 font-mono text-[12px] font-semibold text-ink-secondary"
                        >
                            #{{ bin.number }}
                        </span>
                        <span
                            v-if="bin.is_default"
                            class="rounded-pill bg-background px-2 py-0.5 font-sans text-[10px] font-medium uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('bins.default_badge') }}
                        </span>
                    </div>
                    <p
                        v-if="bin.location_name"
                        class="mt-0.5 font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ bin.location_name }}
                    </p>
                    <p
                        v-if="bin.description"
                        class="mt-1 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ bin.description }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <StockBadge
                        :full-bags="fullBagsTotal"
                        :open-bags="openBagsTotal"
                    />
                    <AppButton variant="secondary" size="sm" @click="openLabel">
                        {{ $t('bins.view_label') }}
                    </AppButton>
                    <AppButton
                        v-if="canManage"
                        variant="secondary"
                        size="sm"
                        @click="openEdit"
                    >
                        {{ $t('bins.form.edit') }}
                    </AppButton>
                    <AppButton
                        v-if="canManage && !bin.is_default"
                        variant="ghost"
                        size="sm"
                        class="text-danger hover:bg-danger-soft"
                        @click="deleteBin"
                    >
                        {{ $t('bins.form.delete') }}
                    </AppButton>
                </div>
            </div>

            <!-- Items -->
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.show.items_heading') }}
                    </h2>
                    <AppButton
                        v-if="canManage"
                        variant="primary"
                        size="sm"
                        @click="openAdd"
                    >
                        + {{ $t('bins.show.add_item') }}
                    </AppButton>
                </div>

                <!-- Empty state -->
                <div
                    v-if="rows.length === 0"
                    class="rounded-md border border-dashed border-border px-4 py-10 text-center font-sans text-[14px] text-ink-tertiary"
                >
                    {{ $t('bins.show.empty') }}
                </div>

                <!-- Item rows -->
                <div v-else class="space-y-2">
                    <div
                        v-for="row in rows"
                        :key="row.sku_id"
                        class="rounded-md border border-border px-3 py-2.5"
                        :class="{
                            'bg-accent-soft/30 border-accent': isDirty(row),
                        }"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                v-if="row.color_hex"
                                class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                :style="{ backgroundColor: row.color_hex }"
                            />
                            <Link
                                :href="itemHref(row.sku_id)"
                                class="min-w-0 flex-1"
                            >
                                <p
                                    class="truncate font-sans text-[14px] font-medium text-ink-primary hover:underline"
                                >
                                    {{ row.name }}
                                </p>
                                <p
                                    v-if="row.brand || row.size"
                                    class="font-sans text-[12px] text-ink-tertiary"
                                >
                                    <span v-if="row.brand">{{
                                        row.brand
                                    }}</span>
                                    <template v-if="row.brand && row.size">
                                        ·
                                    </template>
                                    <span v-if="row.size">{{ row.size }}</span>
                                </p>
                            </Link>

                            <StockBadge
                                v-if="!row.editing"
                                :full-bags="row.currentFull"
                                :open-bags="row.currentOpen"
                            />

                            <div
                                v-if="canManage && !row.editing"
                                class="flex shrink-0 items-center gap-1"
                            >
                                <AppButton
                                    variant="ghost"
                                    size="sm"
                                    @click="startAdjust(row)"
                                >
                                    {{ $t('bins.show.adjust') }}
                                </AppButton>
                                <AppButton
                                    v-if="otherBins.length > 0"
                                    variant="ghost"
                                    size="sm"
                                    @click="openMove(row)"
                                >
                                    {{ $t('bins.show.move') }}
                                </AppButton>
                            </div>
                        </div>

                        <!-- Inline adjust steppers -->
                        <div
                            v-if="row.editing"
                            class="mt-3 flex flex-wrap items-center gap-4"
                        >
                            <!-- Full bags -->
                            <div class="flex items-center gap-2">
                                <span
                                    class="w-10 font-sans text-[12px] text-ink-secondary"
                                    >{{ $t('bins.show.full') }}</span
                                >
                                <button
                                    type="button"
                                    class="flex h-7 w-7 items-center justify-center rounded-md border border-border-strong text-ink-secondary hover:bg-background disabled:opacity-40"
                                    :disabled="row.full <= 0"
                                    :aria-label="`− ${$t('bins.show.full')}`"
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
                                    :aria-label="`+ ${$t('bins.show.full')}`"
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

                            <!-- Open bags -->
                            <div class="flex items-center gap-2">
                                <span
                                    class="w-10 font-sans text-[12px] text-ink-secondary"
                                    >{{ $t('bins.show.open_bags') }}</span
                                >
                                <button
                                    type="button"
                                    class="flex h-7 w-7 items-center justify-center rounded-md border border-border-strong text-ink-secondary hover:bg-background disabled:opacity-40"
                                    :disabled="row.open <= 0"
                                    :aria-label="`− ${$t('bins.show.open_bags')}`"
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
                                    :aria-label="`+ ${$t('bins.show.open_bags')}`"
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

                            <div class="ml-auto flex items-center gap-2">
                                <span
                                    v-if="isDirty(row)"
                                    class="font-sans text-[12px] text-ink-tertiary"
                                >
                                    {{
                                        $t('bins.show.pending_hint', {
                                            full: row.currentFull,
                                            open: row.currentOpen,
                                        })
                                    }}
                                </span>
                                <AppButton
                                    variant="ghost"
                                    size="sm"
                                    @click="resetRow(row)"
                                >
                                    {{ $t('bins.show.reset') }}
                                </AppButton>
                                <AppButton
                                    variant="primary"
                                    size="sm"
                                    :disabled="row.saving"
                                    @click="saveRow(row)"
                                >
                                    {{ $t('bins.show.save') }}
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
            </section>
        </div>

        <!-- Single dialog: add / move / label -->
        <Modal :show="modalType !== null" max-width="md" @close="closeModal">
            <!-- Add item -->
            <div v-if="modalType === 'add'" class="flex flex-col gap-4 p-6">
                <h2
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{ $t('bins.show.add_item_title') }}
                </h2>

                <!-- Step 1: search & pick -->
                <template v-if="!selectedItem">
                    <div class="flex flex-col gap-1">
                        <label
                            for="add-item-search"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('bins.show.add_item_search_label') }}
                        </label>
                        <SearchInput
                            id="add-item-search"
                            v-model="searchTerm"
                            class="w-full"
                            :placeholder="
                                $t('bins.show.add_item_search_placeholder')
                            "
                        />
                    </div>

                    <p
                        v-if="addForm.errors.sku_id"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ addForm.errors.sku_id }}
                    </p>

                    <div
                        class="max-h-64 overflow-y-auto rounded-md border border-border"
                    >
                        <p
                            v-if="searching"
                            class="animate-pulse px-3 py-3 font-sans text-[13px] text-ink-tertiary"
                        >
                            {{ $t('bins.show.add_item_searching') }}
                        </p>
                        <p
                            v-else-if="searchTerm.trim().length < 2"
                            class="px-3 py-3 font-sans text-[13px] text-ink-tertiary"
                        >
                            {{ $t('bins.show.add_item_hint') }}
                        </p>
                        <p
                            v-else-if="
                                searchedOnce && searchResults.length === 0
                            "
                            class="px-3 py-3 font-sans text-[13px] text-ink-tertiary"
                        >
                            {{ $t('bins.show.add_item_no_results') }}
                        </p>
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="result in searchResults"
                                :key="result.sku_id"
                            >
                                <button
                                    type="button"
                                    :disabled="result.in_bin"
                                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left transition hover:bg-background disabled:cursor-default disabled:opacity-60 disabled:hover:bg-transparent"
                                    @click="pickItem(result)"
                                >
                                    <span
                                        v-if="result.color_hex"
                                        class="inline-block h-3.5 w-3.5 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor: result.color_hex,
                                        }"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate font-sans text-[14px] text-ink-primary"
                                        >
                                            {{ result.name }}
                                        </span>
                                        <span
                                            v-if="result.brand || result.size"
                                            class="block font-sans text-[12px] text-ink-tertiary"
                                        >
                                            <span v-if="result.brand">{{
                                                result.brand
                                            }}</span>
                                            <template
                                                v-if="
                                                    result.brand && result.size
                                                "
                                            >
                                                ·
                                            </template>
                                            <span v-if="result.size">{{
                                                result.size
                                            }}</span>
                                        </span>
                                    </span>
                                    <span
                                        v-if="result.in_bin"
                                        class="shrink-0 font-sans text-[11px] text-ink-tertiary"
                                    >
                                        {{ $t('bins.show.add_item_in_bin') }}
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="flex justify-end">
                        <AppButton
                            variant="ghost"
                            size="sm"
                            @click="closeModal"
                        >
                            {{ $t('bins.show.cancel') }}
                        </AppButton>
                    </div>
                </template>

                <!-- Step 2: chosen item + initial counts -->
                <template v-else>
                    <div
                        class="flex items-center gap-2.5 rounded-md border border-border bg-background px-3 py-2.5"
                    >
                        <span
                            v-if="selectedItem.color_hex"
                            class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: selectedItem.color_hex }"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate font-sans text-[14px] font-medium text-ink-primary"
                            >
                                {{ selectedItem.name }}
                            </p>
                            <p
                                v-if="selectedItem.brand || selectedItem.size"
                                class="font-sans text-[12px] text-ink-tertiary"
                            >
                                <span v-if="selectedItem.brand">{{
                                    selectedItem.brand
                                }}</span>
                                <template
                                    v-if="
                                        selectedItem.brand && selectedItem.size
                                    "
                                >
                                    ·
                                </template>
                                <span v-if="selectedItem.size">{{
                                    selectedItem.size
                                }}</span>
                            </p>
                        </div>
                        <AppButton variant="ghost" size="sm" @click="clearPick">
                            {{ $t('bins.show.add_item_change') }}
                        </AppButton>
                    </div>

                    <div class="flex gap-3">
                        <div class="flex-1">
                            <AppInput
                                id="add-full"
                                v-model="addForm.full_bags"
                                type="number"
                                min="0"
                                :label="$t('bins.show.add_item_full')"
                                :error="addForm.errors.full_bags"
                            />
                        </div>
                        <div class="flex-1">
                            <AppInput
                                id="add-open"
                                v-model="addForm.open_bags"
                                type="number"
                                min="0"
                                :label="$t('bins.show.add_item_open')"
                                :error="addForm.errors.open_bags"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <AppButton
                            variant="secondary"
                            type="button"
                            @click="closeModal"
                        >
                            {{ $t('bins.show.cancel') }}
                        </AppButton>
                        <AppButton
                            variant="primary"
                            type="button"
                            :disabled="addForm.processing"
                            @click="submitAdd"
                        >
                            {{ $t('bins.show.add_item_submit') }}
                        </AppButton>
                    </div>
                </template>
            </div>

            <!-- Move item -->
            <form
                v-else-if="modalType === 'move'"
                class="flex flex-col gap-4 p-6"
                @submit.prevent="submitMove"
            >
                <h2
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{ $t('bins.show.move_title', { item: moveRow?.name }) }}
                </h2>

                <div class="flex flex-col gap-1">
                    <span
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.show.move_from') }}
                    </span>
                    <p class="font-sans text-[14px] text-ink-primary">
                        {{ binTitle }} —
                        {{
                            $t('bins.show.move_holds', {
                                full: moveRow?.currentFull ?? 0,
                                open: moveRow?.currentOpen ?? 0,
                            })
                        }}
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label
                        for="move-to"
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.show.move_to') }}
                    </label>
                    <select
                        id="move-to"
                        v-model="moveForm.to_bin_id"
                        class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    >
                        <option
                            v-for="b in otherBins"
                            :key="b.id"
                            :value="b.id"
                        >
                            {{ binOptionLabel(b) }}
                        </option>
                    </select>
                    <p
                        v-if="moveForm.errors.to_bin_id"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ moveForm.errors.to_bin_id }}
                    </p>
                </div>

                <div class="flex gap-3">
                    <div class="flex-1">
                        <AppInput
                            id="move-full"
                            v-model="moveForm.full_bags_change"
                            type="number"
                            min="0"
                            :max="moveRow?.currentFull ?? 0"
                            :label="$t('bins.show.move_full')"
                            :error="moveForm.errors.full_bags_change"
                        />
                    </div>
                    <div class="flex-1">
                        <AppInput
                            id="move-open"
                            v-model="moveForm.open_bags_change"
                            type="number"
                            min="0"
                            :max="moveRow?.currentOpen ?? 0"
                            :label="$t('bins.show.move_open')"
                            :error="moveForm.errors.open_bags_change"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <AppButton
                        variant="secondary"
                        type="button"
                        @click="closeModal"
                    >
                        {{ $t('bins.show.cancel') }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        type="submit"
                        :disabled="moveForm.processing"
                    >
                        {{ $t('bins.show.move_submit') }}
                    </AppButton>
                </div>
            </form>

            <!-- Edit bin -->
            <form
                v-else-if="modalType === 'edit'"
                class="flex flex-col gap-4 p-6"
                @submit.prevent="submitEdit"
            >
                <h2
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{ $t('bins.form.edit_bin_title') }}
                </h2>

                <div v-if="locations.length > 1" class="flex flex-col gap-1">
                    <label
                        for="edit-bin-location"
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.form.bin_location') }}
                    </label>
                    <select
                        id="edit-bin-location"
                        v-model="editForm.location_id"
                        class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    >
                        <option
                            v-for="loc in locations"
                            :key="loc.id"
                            :value="loc.id"
                        >
                            {{ loc.name }}
                        </option>
                    </select>
                    <p
                        v-if="editForm.errors.location_id"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ editForm.errors.location_id }}
                    </p>
                </div>

                <div class="flex gap-3">
                    <div class="flex-1">
                        <AppInput
                            id="edit-bin-name"
                            v-model="editForm.name"
                            :label="$t('bins.form.bin_name')"
                            :placeholder="$t('bins.form.bin_name_placeholder')"
                            :error="editForm.errors.name"
                            required
                        />
                    </div>
                    <div class="w-28">
                        <AppInput
                            id="edit-bin-number"
                            v-model="editForm.number"
                            type="number"
                            :label="$t('bins.form.bin_number')"
                            :placeholder="
                                $t('bins.form.bin_number_placeholder')
                            "
                            :error="editForm.errors.number"
                        />
                    </div>
                </div>

                <!-- Number lock -->
                <label
                    class="flex items-center gap-2 font-sans text-[13px] text-ink-primary"
                >
                    <input
                        v-model="editForm.number_locked"
                        type="checkbox"
                        class="h-4 w-4 rounded border-border-strong text-accent focus:ring-accent-soft"
                    />
                    <span>{{ $t('bins.lock.label') }}</span>
                    <span class="text-ink-tertiary">·</span>
                    <span class="text-ink-tertiary">{{
                        $t('bins.lock.hint')
                    }}</span>
                    <InfoButton :title="$t('bins.lock.info_title')">
                        <p>{{ $t('bins.lock.info_body') }}</p>
                    </InfoButton>
                </label>

                <div class="flex flex-col gap-1">
                    <label
                        for="edit-bin-description"
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.form.description') }}
                    </label>
                    <textarea
                        id="edit-bin-description"
                        v-model="editForm.description"
                        rows="2"
                        :placeholder="$t('bins.form.description_placeholder')"
                        class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    />
                    <p
                        v-if="editForm.errors.description"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ editForm.errors.description }}
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <AppButton
                        variant="secondary"
                        type="button"
                        @click="closeModal"
                    >
                        {{ $t('bins.form.cancel') }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        type="submit"
                        :disabled="editForm.processing"
                    >
                        {{ $t('bins.form.save') }}
                    </AppButton>
                </div>
            </form>

            <!-- Bin label -->
            <div
                v-else-if="modalType === 'label'"
                class="flex flex-col gap-4 p-6"
            >
                <h2
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{ $t('bins.label.view_title') }}
                </h2>

                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <label
                            for="label-size"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('bins.label.size') }}
                        </label>
                        <select
                            id="label-size"
                            v-model="sizeKey"
                            class="rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        >
                            <option
                                v-for="p in labelPresets"
                                :key="p.key"
                                :value="p.key"
                            >
                                {{ p.label }}
                            </option>
                            <option value="custom">
                                {{ $t('bins.label.custom') }}
                            </option>
                        </select>
                    </div>
                    <template v-if="sizeKey === 'custom'">
                        <div class="w-24">
                            <AppInput
                                v-model="customWidthIn"
                                type="number"
                                :label="$t('bins.label.width_in')"
                            />
                        </div>
                        <div class="w-24">
                            <AppInput
                                v-model="customHeightIn"
                                type="number"
                                :label="$t('bins.label.height_in')"
                            />
                        </div>
                    </template>
                </div>

                <div
                    class="flex justify-center rounded-md border border-border bg-background p-4"
                >
                    <div class="label-preview" v-html="previewSvg" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span
                        v-if="copyState === 'copied'"
                        class="mr-auto font-sans text-[13px] text-success"
                    >
                        {{ $t('bins.label.copied') }}
                    </span>
                    <span
                        v-else-if="copyState === 'error'"
                        class="mr-auto font-sans text-[13px] text-danger"
                    >
                        {{ $t('bins.label.copy_error') }}
                    </span>
                    <AppButton
                        variant="secondary"
                        size="sm"
                        @click="downloadLabelSvg"
                    >
                        {{ $t('bins.label.download_svg') }}
                    </AppButton>
                    <AppButton
                        variant="secondary"
                        size="sm"
                        @click="downloadLabelPng"
                    >
                        {{ $t('bins.label.download_png') }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="sm"
                        @click="copyLabelImage"
                    >
                        {{ $t('bins.label.copy') }}
                    </AppButton>
                </div>
                <div class="flex justify-end">
                    <AppButton variant="ghost" size="sm" @click="closeModal">
                        {{ $t('bins.show.cancel') }}
                    </AppButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.label-preview :deep(svg) {
    max-width: 100%;
    height: auto;
}
</style>
