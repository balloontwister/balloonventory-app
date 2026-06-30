<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import BackLink from '@/Components/BackLink.vue';
import InfoButton from '@/Components/InfoButton.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { useBusiness } from '@/Composables/useBusiness.js';
import {
    AVERY_FORMATS,
    LABEL_PRESETS,
    buildAverySheetHtml,
    buildLabelSvg,
    labelToPngBlob,
} from '@/Composables/useBinLabels';

const props = defineProps({
    locations: { type: Array, required: true },
});

const { can } = useBusiness();
const canManage = computed(() => can('inventory.manual_adjust'));

const allBins = computed(() =>
    props.locations.flatMap((location) =>
        location.bins.map((bin) => ({ ...bin, location_name: location.name })),
    ),
);

function labelText(bin, locationName) {
    const number = bin.number != null ? `#${bin.number} ` : '';
    const location = locationName ? `${locationName} · ` : '';
    return `${location}${number}${bin.name}`;
}

function binsCountLabel(location) {
    const count = location.bins.length;
    return count === 1
        ? trans('bins.location.bins_count_singular', { count })
        : trans('bins.location.bins_count_plural', { count });
}

// ── One dialog at a time (location / bin / label / auto-number) ────────────────
const modalType = ref(null);

function closeModal() {
    modalType.value = null;
}

// ── Location form ─────────────────────────────────────────────────────────────
const editingLocationId = ref(null);
const locationForm = useForm({ name: '', description: '' });

function openCreateLocation() {
    editingLocationId.value = null;
    locationForm.clearErrors();
    locationForm.name = '';
    locationForm.description = '';
    modalType.value = 'location';
}

function openEditLocation(location) {
    editingLocationId.value = location.id;
    locationForm.clearErrors();
    locationForm.name = location.name;
    locationForm.description = location.description ?? '';
    modalType.value = 'location';
}

function submitLocation() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editingLocationId.value) {
        locationForm.patch(
            route('inventory.locations.update', {
                location: editingLocationId.value,
            }),
            options,
        );
    } else {
        locationForm.post(route('inventory.locations.store'), options);
    }
}

function deleteLocation(location) {
    if (!window.confirm(trans('bins.delete.location_confirm'))) {
        return;
    }
    useForm({}).delete(
        route('inventory.locations.destroy', { location: location.id }),
        { preserveScroll: true },
    );
}

// ── Bin form (add / edit, incl. number lock) ──────────────────────────────────
const editingBinId = ref(null);
const binForm = useForm({
    location_id: '',
    name: '',
    number: '',
    number_locked: false,
    description: '',
});

function openCreateBin(location) {
    editingBinId.value = null;
    binForm.clearErrors();
    binForm.location_id = location.id;
    binForm.name = '';
    binForm.number = '';
    binForm.number_locked = false;
    binForm.description = '';
    modalType.value = 'bin';
}

function openEditBin(bin) {
    editingBinId.value = bin.id;
    binForm.clearErrors();
    binForm.location_id = bin.location_id;
    binForm.name = bin.name;
    binForm.number = bin.number ?? '';
    binForm.number_locked = !!bin.number_locked;
    binForm.description = bin.description ?? '';
    modalType.value = 'bin';
}

function submitBin() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editingBinId.value) {
        binForm.patch(
            route('inventory.bins.update', { bin: editingBinId.value }),
            options,
        );
    } else {
        binForm.post(route('inventory.bins.store'), options);
    }
}

// Delete the bin currently open in the edit form. Closes the dialog only on a
// confirmed, successful delete (the server redirects back to Manage storage).
function deleteEditingBin() {
    if (
        !editingBinId.value ||
        !window.confirm(trans('bins.delete.bin_confirm'))
    ) {
        return;
    }
    useForm({ from: 'manage' }).delete(
        route('inventory.bins.destroy', { bin: editingBinId.value }),
        { preserveScroll: true, onSuccess: closeModal },
    );
}

// ── Auto-number ───────────────────────────────────────────────────────────────
const autoNumberForm = useForm({ mode: 'fill' });

function autoNumber(mode) {
    if (
        mode === 'renumber' &&
        !window.confirm(trans('bins.manage.auto_number_renumber_confirm'))
    ) {
        return;
    }
    autoNumberForm.mode = mode;
    autoNumberForm.post(route('inventory.bins.auto-number'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
}

// ── Label viewer (single bin) ─────────────────────────────────────────────────
const labelPresets = LABEL_PRESETS;
const viewLabelBin = ref(null);
const sizeKey = ref(LABEL_PRESETS[0].key);
const customWidthIn = ref(2.625);
const customHeightIn = ref(1);
const copyState = ref('');

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
    viewLabelBin.value
        ? buildLabelSvg({
              name: viewLabelBin.value.name,
              code: viewLabelBin.value.code,
              widthIn: labelDims.value.widthIn,
              heightIn: labelDims.value.heightIn,
          })
        : '',
);

function openLabel(bin, locationName) {
    viewLabelBin.value = {
        name: labelText(bin, locationName),
        code: bin.scan_code,
    };
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
    downloadBlob(blob, `${viewLabelBin.value.code}.png`);
}

function downloadLabelSvg() {
    downloadBlob(
        new Blob([previewSvg.value], { type: 'image/svg+xml' }),
        `${viewLabelBin.value.code}.svg`,
    );
}

// ── Print all on an Avery sheet ───────────────────────────────────────────────
const averyFormats = AVERY_FORMATS;
const printFormatKey = ref(AVERY_FORMATS[0].key);

function printAll() {
    const format = AVERY_FORMATS.find((f) => f.key === printFormatKey.value);
    const labels = allBins.value
        .filter((bin) => bin.scan_code)
        .map((bin) => ({
            name: labelText(bin, bin.location_name),
            code: bin.scan_code,
        }));
    if (labels.length === 0) {
        return;
    }

    const html = buildAverySheetHtml(labels, format, trans('bins.print_title'));

    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    Object.assign(iframe.style, {
        position: 'fixed',
        right: '0',
        bottom: '0',
        width: '0',
        height: '0',
        border: '0',
    });
    document.body.appendChild(iframe);

    const win = iframe.contentWindow;
    const cleanup = () => iframe.remove();
    win.document.open();
    win.document.write(html);
    win.document.close();
    win.addEventListener('afterprint', cleanup);
    setTimeout(() => {
        win.focus();
        win.print();
    }, 150);
    setTimeout(cleanup, 60000);
}
</script>

<template>
    <Head :title="$t('bins.manage.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <BackLink
                :href="route('inventory.bins.index')"
                :label="$t('bins.manage.back')"
            />
        </template>

        <div class="mx-auto max-w-4xl">
            <!-- Heading -->
            <div class="mb-1 flex items-center gap-2">
                <h1
                    class="font-display text-[22px] font-semibold text-ink-primary"
                >
                    {{ $t('bins.manage.heading') }}
                </h1>
                <InfoButton :title="$t('bins.info.title')">
                    <p>{{ $t('bins.info.body_location') }}</p>
                    <p>{{ $t('bins.info.body_bin') }}</p>
                    <p>{{ $t('bins.info.body_how') }}</p>
                </InfoButton>
            </div>
            <p class="mb-5 font-sans text-[14px] text-ink-secondary">
                {{ $t('bins.manage.subtitle') }}
            </p>

            <!-- Top actions -->
            <div
                v-if="canManage"
                class="mb-6 flex flex-wrap items-center gap-2"
            >
                <AppButton
                    variant="primary"
                    size="sm"
                    @click="openCreateLocation"
                >
                    {{ $t('bins.add_location') }}
                </AppButton>
                <AppButton
                    v-if="allBins.length > 0"
                    variant="secondary"
                    size="sm"
                    @click="modalType = 'autonumber'"
                >
                    {{ $t('bins.manage.auto_number') }}
                </AppButton>

                <div
                    v-if="allBins.length > 0"
                    class="ml-auto flex flex-wrap items-center gap-2"
                >
                    <select
                        v-model="printFormatKey"
                        class="rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-primary focus:border-accent focus:outline-none"
                    >
                        <option
                            v-for="f in averyFormats"
                            :key="f.key"
                            :value="f.key"
                        >
                            {{ f.label }}
                        </option>
                    </select>
                    <AppButton variant="secondary" size="sm" @click="printAll">
                        {{ $t('bins.manage.print_all') }}
                    </AppButton>
                </div>
            </div>

            <!-- Locations + bins (condensed) -->
            <div class="flex flex-col gap-6">
                <section
                    v-for="location in locations"
                    :key="location.id"
                    class="flex flex-col gap-2"
                >
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h2
                            class="font-display text-[16px] font-semibold text-ink-primary"
                        >
                            {{ location.name }}
                        </h2>
                        <span
                            v-if="location.is_default"
                            class="rounded-pill bg-background px-2 py-0.5 font-sans text-[10px] font-medium uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('bins.default_badge') }}
                        </span>
                        <span class="font-mono text-[12px] text-ink-tertiary">
                            {{ binsCountLabel(location) }}
                        </span>

                        <div
                            v-if="canManage"
                            class="ml-auto flex items-center gap-1.5 font-sans text-[13px]"
                        >
                            <button
                                type="button"
                                class="text-ink-secondary hover:text-ink-primary hover:underline"
                                @click="openEditLocation(location)"
                            >
                                {{ $t('bins.form.edit') }}
                            </button>
                            <template v-if="!location.is_default">
                                <span class="text-ink-tertiary">|</span>
                                <button
                                    type="button"
                                    class="text-danger hover:underline"
                                    @click="deleteLocation(location)"
                                >
                                    {{ $t('bins.form.delete') }}
                                </button>
                            </template>
                            <span class="text-ink-tertiary">|</span>
                            <button
                                type="button"
                                class="text-accent hover:underline"
                                @click="openCreateBin(location)"
                            >
                                {{ $t('bins.add_bin') }}
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="location.bins.length === 0"
                        class="font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ $t('bins.manage.no_bins') }}
                    </p>

                    <div
                        v-else
                        class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="bin in location.bins"
                            :key="bin.id"
                            class="rounded-md border border-border bg-surface px-3 py-2"
                        >
                            <div class="flex items-center gap-1.5">
                                <span
                                    v-if="bin.number != null"
                                    class="shrink-0 rounded bg-background px-1.5 py-0.5 font-mono text-[12px] font-semibold text-ink-secondary"
                                >
                                    #{{ bin.number }}
                                </span>
                                <span
                                    class="min-w-0 flex-1 truncate font-sans text-[14px] font-medium text-ink-primary"
                                >
                                    {{ bin.name }}
                                </span>
                                <svg
                                    v-if="bin.number_locked"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-3.5 w-3.5 shrink-0 text-ink-tertiary"
                                    :aria-label="$t('bins.lock.label')"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <span
                                    v-if="bin.is_default"
                                    class="shrink-0 rounded-pill bg-background px-1.5 py-0.5 font-sans text-[9px] font-medium uppercase tracking-eyebrow text-ink-tertiary"
                                >
                                    {{ $t('bins.default_badge') }}
                                </span>
                            </div>
                            <div
                                class="mt-1.5 flex items-center gap-1.5 font-sans text-[12px]"
                            >
                                <Link
                                    :href="
                                        route('inventory.bins.show', {
                                            bin: bin.id,
                                            from: 'manage',
                                        })
                                    "
                                    class="text-accent hover:underline"
                                >
                                    {{ $t('bins.manage.open') }}
                                </Link>
                                <span class="text-ink-tertiary">|</span>
                                <button
                                    type="button"
                                    class="text-accent hover:underline"
                                    @click="openLabel(bin, location.name)"
                                >
                                    {{ $t('bins.manage.show_label') }}
                                </button>
                                <template v-if="canManage">
                                    <span class="text-ink-tertiary">|</span>
                                    <button
                                        type="button"
                                        class="text-accent hover:underline"
                                        @click="openEditBin(bin)"
                                    >
                                        {{ $t('bins.manage.edit') }}
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- One dialog switches between the management forms -->
        <Modal :show="modalType !== null" max-width="md" @close="closeModal">
            <!-- Location form -->
            <form
                v-if="modalType === 'location'"
                class="flex flex-col gap-4 p-6"
                @submit.prevent="submitLocation"
            >
                <h2
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{
                        editingLocationId
                            ? $t('bins.form.edit_location_title')
                            : $t('bins.form.create_location_title')
                    }}
                </h2>

                <AppInput
                    id="location-name"
                    v-model="locationForm.name"
                    :label="$t('bins.form.location_name')"
                    :placeholder="$t('bins.form.location_name_placeholder')"
                    :error="locationForm.errors.name"
                    required
                />

                <div class="flex flex-col gap-1">
                    <label
                        for="location-description"
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.form.description') }}
                    </label>
                    <textarea
                        id="location-description"
                        v-model="locationForm.description"
                        rows="2"
                        :placeholder="$t('bins.form.description_placeholder')"
                        class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    />
                    <p
                        v-if="locationForm.errors.description"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ locationForm.errors.description }}
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
                        :disabled="locationForm.processing"
                    >
                        {{
                            editingLocationId
                                ? $t('bins.form.save')
                                : $t('bins.form.create')
                        }}
                    </AppButton>
                </div>
            </form>

            <!-- Bin form -->
            <form
                v-else-if="modalType === 'bin'"
                class="flex flex-col gap-4 p-6"
                @submit.prevent="submitBin"
            >
                <h2
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{
                        editingBinId
                            ? $t('bins.form.edit_bin_title')
                            : $t('bins.form.create_bin_title')
                    }}
                </h2>

                <div class="flex flex-col gap-1">
                    <label
                        for="bin-location"
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.form.bin_location') }}
                    </label>
                    <select
                        id="bin-location"
                        v-model="binForm.location_id"
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
                        v-if="binForm.errors.location_id"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ binForm.errors.location_id }}
                    </p>
                </div>

                <div class="flex gap-3">
                    <div class="flex-1">
                        <AppInput
                            id="bin-name"
                            v-model="binForm.name"
                            :label="$t('bins.form.bin_name')"
                            :placeholder="$t('bins.form.bin_name_placeholder')"
                            :error="binForm.errors.name"
                            required
                        />
                    </div>
                    <div class="w-28">
                        <AppInput
                            id="bin-number"
                            v-model="binForm.number"
                            type="number"
                            :label="$t('bins.form.bin_number')"
                            :placeholder="
                                $t('bins.form.bin_number_placeholder')
                            "
                            :error="binForm.errors.number"
                        />
                    </div>
                </div>

                <!-- Number lock -->
                <label
                    class="flex items-center gap-2 font-sans text-[13px] text-ink-primary"
                >
                    <input
                        v-model="binForm.number_locked"
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
                        for="bin-description"
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.form.description') }}
                    </label>
                    <textarea
                        id="bin-description"
                        v-model="binForm.description"
                        rows="2"
                        :placeholder="$t('bins.form.description_placeholder')"
                        class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    />
                    <p
                        v-if="binForm.errors.description"
                        class="font-sans text-[13px] text-danger"
                    >
                        {{ binForm.errors.description }}
                    </p>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <button
                        v-if="editingBinId"
                        type="button"
                        class="font-sans text-[13px] text-danger hover:underline"
                        @click="deleteEditingBin"
                    >
                        {{ $t('bins.form.delete') }}
                    </button>
                    <div class="ml-auto flex gap-2">
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
                            :disabled="binForm.processing"
                        >
                            {{
                                editingBinId
                                    ? $t('bins.form.save')
                                    : $t('bins.form.create')
                            }}
                        </AppButton>
                    </div>
                </div>
            </form>

            <!-- Auto-number -->
            <div
                v-else-if="modalType === 'autonumber'"
                class="flex flex-col gap-4 p-6"
            >
                <h2
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{ $t('bins.manage.auto_number_title') }}
                </h2>
                <p class="font-sans text-[14px] text-ink-secondary">
                    {{ $t('bins.manage.auto_number_intro') }}
                </p>

                <button
                    type="button"
                    class="hover:bg-accent-soft/30 flex flex-col gap-1 rounded-md border border-border px-4 py-3 text-left transition hover:border-accent"
                    :disabled="autoNumberForm.processing"
                    @click="autoNumber('fill')"
                >
                    <span
                        class="font-sans text-[14px] font-semibold text-ink-primary"
                    >
                        {{ $t('bins.manage.auto_number_fill') }}
                    </span>
                    <span class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('bins.manage.auto_number_fill_hint') }}
                    </span>
                </button>

                <button
                    type="button"
                    class="hover:bg-accent-soft/30 flex flex-col gap-1 rounded-md border border-border px-4 py-3 text-left transition hover:border-accent"
                    :disabled="autoNumberForm.processing"
                    @click="autoNumber('renumber')"
                >
                    <span
                        class="font-sans text-[14px] font-semibold text-ink-primary"
                    >
                        {{ $t('bins.manage.auto_number_renumber') }}
                    </span>
                    <span class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('bins.manage.auto_number_renumber_hint') }}
                    </span>
                </button>

                <p class="font-sans text-[12px] text-ink-tertiary">
                    {{ $t('bins.manage.auto_number_locked_note') }}
                </p>

                <div class="flex justify-end">
                    <AppButton variant="ghost" size="sm" @click="closeModal">
                        {{ $t('bins.form.cancel') }}
                    </AppButton>
                </div>
            </div>

            <!-- Label viewer -->
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
                        {{ $t('bins.form.cancel') }}
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
