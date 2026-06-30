<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import InventoryTabs from '@/Components/InventoryTabs.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref } from 'vue';
import { useBusiness } from '@/Composables/useBusiness.js';
import { AVERY_FORMATS, buildAverySheetHtml } from '@/Composables/useBinLabels';

const props = defineProps({
    locations: { type: Array, required: true },
});

const { can } = useBusiness();
const canManageBins = computed(() => can('inventory.manual_adjust'));

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

// ── Bulk print on a standard Avery sheet ───────────────────────────────────────
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

// ── Expandable bin contents (lazy-loaded per card) ───────────────────────────
// binContents[binId] = { loading: bool, items: array | null }
const binContents = reactive({});

async function toggleBin(bin) {
    const entry = binContents[bin.id];

    if (entry && entry.open) {
        entry.open = false;
        return;
    }

    if (entry && entry.items !== null) {
        entry.open = true;
        return;
    }

    binContents[bin.id] = { open: true, loading: true, items: null };

    try {
        const { data } = await window.axios.get(
            route('inventory.bins.contents', { bin: bin.id }),
        );
        binContents[bin.id] = {
            open: true,
            loading: false,
            items: data.items,
        };
    } catch {
        binContents[bin.id] = { open: true, loading: false, items: [] };
    }
}

function isOpen(binId) {
    return !!binContents[binId]?.open;
}

// ── Add/edit modal ────────────────────────────────────────────────────────────
// A single Modal instance switches between the location and bin forms. Two
// native <dialog> modals on one page stack their backdrops and trap clicks, so
// the page keeps exactly one dialog at a time.
const modalType = ref(null); // null | 'location' | 'bin'
// (Single-bin label viewing/export now lives on the bin detail page;
// the wall keeps only the bulk "Print all labels" action.)

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
    locationForm.reset();
    locationForm.clearErrors();
    locationForm.name = location.name;
    locationForm.description = location.description ?? '';
    modalType.value = 'location';
}

function submitLocation() {
    const options = {
        preserveScroll: true,
        onSuccess: closeModal,
    };

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

// ── Bin form ──────────────────────────────────────────────────────────────────
const editingBinId = ref(null);
const binForm = useForm({
    location_id: '',
    name: '',
    number: '',
    description: '',
});

function openCreateBin(location) {
    editingBinId.value = null;
    binForm.clearErrors();
    binForm.location_id = location.id;
    binForm.name = '';
    binForm.number = '';
    binForm.description = '';
    modalType.value = 'bin';
}

function submitBin() {
    const options = {
        preserveScroll: true,
        onSuccess: closeModal,
    };

    if (editingBinId.value) {
        binForm.patch(
            route('inventory.bins.update', { bin: editingBinId.value }),
            options,
        );
    } else {
        binForm.post(route('inventory.bins.store'), options);
    }
}

function binsCountLabel(location) {
    const count = location.bins.length;
    return count === 1
        ? trans('bins.location.bins_count_singular', { count })
        : trans('bins.location.bins_count_plural', { count });
}

function binSummaryLabel(bin) {
    const count = bin.sku_count ?? 0;
    if (count === 0) {
        return trans('bins.bin.summary_empty');
    }
    return count === 1
        ? trans('bins.bin.summary_singular', { count })
        : trans('bins.bin.summary_plural', { count });
}
</script>

<template>
    <Head :title="$t('bins.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3">
                <h1
                    class="font-display text-[22px] font-semibold text-ink-primary"
                >
                    {{ $t('bins.heading') }}
                </h1>
                <InventoryTabs active="bins" />
            </div>
        </template>

        <div class="flex items-center justify-end gap-2">
            <select
                v-if="allBins.length > 0"
                v-model="printFormatKey"
                class="rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-primary focus:border-accent focus:outline-none"
            >
                <option v-for="f in averyFormats" :key="f.key" :value="f.key">
                    {{ f.label }}
                </option>
            </select>
            <AppButton
                v-if="allBins.length > 0"
                variant="secondary"
                size="sm"
                @click="printAll"
            >
                {{ $t('bins.print_all') }}
            </AppButton>
            <AppButton
                v-if="canManageBins"
                variant="primary"
                size="sm"
                @click="openCreateLocation"
            >
                {{ $t('bins.add_location') }}
            </AppButton>
        </div>

        <!-- Empty state -->
        <div
            v-if="locations.length === 0"
            class="flex flex-col items-center gap-2 py-20 text-center"
        >
            <p class="font-sans text-[17px] font-semibold text-ink-primary">
                {{ $t('bins.empty.heading') }}
            </p>
            <p class="font-sans text-[14px] text-ink-secondary">
                {{ $t('bins.empty.body') }}
            </p>
        </div>

        <!-- Locations -->
        <div v-else class="mt-4 flex flex-col gap-8">
            <section
                v-for="location in locations"
                :key="location.id"
                class="flex flex-col gap-3"
            >
                <div class="flex items-center gap-3">
                    <h2
                        class="font-display text-[18px] font-semibold text-ink-primary"
                    >
                        {{ location.name }}
                    </h2>
                    <span
                        v-if="location.is_default"
                        class="rounded-pill bg-background px-2 py-0.5 font-sans text-[11px] font-medium uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.default_badge') }}
                    </span>
                    <span class="font-mono text-[12px] text-ink-tertiary">
                        {{ binsCountLabel(location) }}
                    </span>

                    <div
                        v-if="canManageBins"
                        class="ml-auto flex items-center gap-1"
                    >
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 font-sans text-[13px] text-ink-secondary hover:bg-background hover:text-ink-primary"
                            @click="openEditLocation(location)"
                        >
                            {{ $t('bins.form.edit') }}
                        </button>
                        <button
                            v-if="!location.is_default"
                            type="button"
                            class="rounded-md px-2 py-1 font-sans text-[13px] text-danger hover:bg-danger-soft"
                            @click="deleteLocation(location)"
                        >
                            {{ $t('bins.form.delete') }}
                        </button>
                        <AppButton
                            variant="secondary"
                            size="sm"
                            @click="openCreateBin(location)"
                        >
                            {{ $t('bins.add_bin') }}
                        </AppButton>
                    </div>
                </div>

                <p
                    v-if="location.bins.length === 0"
                    class="font-sans text-[14px] text-ink-tertiary"
                >
                    {{ $t('bins.location.empty') }}
                </p>

                <div
                    v-else
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="bin in location.bins"
                        :key="bin.id"
                        class="flex flex-col rounded-lg border border-border bg-surface"
                    >
                        <!-- Card header -->
                        <div class="flex items-start gap-2 p-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="bin.number != null"
                                        class="rounded-md bg-background px-1.5 py-0.5 font-mono text-[12px] font-semibold text-ink-secondary"
                                    >
                                        {{
                                            $t('bins.bin.number_prefix', {
                                                number: bin.number,
                                            })
                                        }}
                                    </span>
                                    <Link
                                        :href="
                                            route('inventory.bins.show', {
                                                bin: bin.id,
                                            })
                                        "
                                        class="truncate font-sans text-[15px] font-semibold text-ink-primary hover:text-accent hover:underline"
                                    >
                                        {{ bin.name }}
                                    </Link>
                                    <span
                                        v-if="bin.is_default"
                                        class="rounded-pill bg-background px-2 py-0.5 font-sans text-[10px] font-medium uppercase tracking-eyebrow text-ink-secondary"
                                    >
                                        {{ $t('bins.default_badge') }}
                                    </span>
                                </div>
                                <p
                                    class="mt-1 font-mono text-[12px] text-ink-tertiary"
                                >
                                    {{ binSummaryLabel(bin) }}
                                    <span
                                        v-if="
                                            bin.full_bags_total ||
                                            bin.open_bags_total
                                        "
                                    >
                                        ·
                                        {{
                                            $t('bins.bin.full_bags', {
                                                count: bin.full_bags_total ?? 0,
                                            })
                                        }}
                                        /
                                        {{
                                            $t('bins.bin.open_bags', {
                                                count: bin.open_bags_total ?? 0,
                                            })
                                        }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Expanded contents -->
                        <div
                            v-if="isOpen(bin.id)"
                            class="border-t border-border px-4 py-3"
                        >
                            <p
                                v-if="binContents[bin.id]?.loading"
                                class="animate-pulse font-sans text-[13px] text-ink-tertiary"
                            >
                                {{ $t('bins.bin.loading') }}
                            </p>
                            <p
                                v-else-if="!binContents[bin.id]?.items?.length"
                                class="font-sans text-[13px] text-ink-tertiary"
                            >
                                {{ $t('bins.bin.empty') }}
                            </p>
                            <ul v-else class="flex flex-col gap-1.5">
                                <li
                                    v-for="item in binContents[bin.id].items"
                                    :key="item.sku_id"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        v-if="item.color_hex"
                                        class="h-3 w-3 shrink-0 rounded-full border border-border"
                                        :style="{
                                            backgroundColor: item.color_hex,
                                        }"
                                    />
                                    <span
                                        class="min-w-0 flex-1 truncate font-sans text-[13px] text-ink-primary"
                                    >
                                        <span
                                            v-if="item.brand"
                                            class="text-ink-tertiary"
                                            >{{ item.brand }} </span
                                        >{{ item.name }}
                                    </span>
                                    <span
                                        class="shrink-0 font-mono text-[12px] text-ink-secondary"
                                    >
                                        {{ item.full_bags }}/{{
                                            item.open_bags
                                        }}
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- Card actions -->
                        <div
                            class="mt-auto flex items-center gap-1 border-t border-border px-3 py-2"
                        >
                            <Link
                                :href="
                                    route('inventory.bins.show', {
                                        bin: bin.id,
                                    })
                                "
                                class="rounded-md px-2 py-1 font-sans text-[13px] font-medium text-accent hover:bg-accent-soft"
                            >
                                {{ $t('bins.show.open') }}
                            </Link>
                            <button
                                type="button"
                                class="rounded-md px-2 py-1 font-sans text-[13px] text-ink-secondary hover:bg-background hover:text-ink-primary"
                                @click="toggleBin(bin)"
                            >
                                {{
                                    isOpen(bin.id)
                                        ? $t('bins.bin.collapse')
                                        : $t('bins.bin.expand')
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Add/edit modal — a single dialog switches between the two forms -->
        <Modal :show="modalType !== null" max-width="md" @close="closeModal">
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
                            v-for="location in locations"
                            :key="location.id"
                            :value="location.id"
                        >
                            {{ location.name }}
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
                        :disabled="binForm.processing"
                    >
                        {{
                            editingBinId
                                ? $t('bins.form.save')
                                : $t('bins.form.create')
                        }}
                    </AppButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
