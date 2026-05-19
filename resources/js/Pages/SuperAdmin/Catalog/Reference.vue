<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import ImageGallery from '@/Components/ImageGallery.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    sizes: { type: Array, required: true },
    balloonSizes: { type: Array, required: true },
    shapes: { type: Array, required: true },
    textures: { type: Array, required: true },
    colorFamilies: { type: Array, required: true },
    themes: { type: Array, required: true },
    materials: { type: Array, required: true },
    textureFamilies: { type: Array, required: true },
    brands: { type: Array, required: true },
});

const activeTab = ref('sizes');

const tabKeys = [
    'sizes',
    'balloon-sizes',
    'shapes',
    'textures',
    'color-families',
    'themes',
    'materials',
];

// Map tab key → props key + image slots. Slots correspond to keys configured
// in ImageAttachmentService (single/cluster for dual-image entities, 'image' for
// single-image entities, empty array for themes).
const tabConfig = {
    sizes: { items: () => props.sizes, imageSlots: ['single', 'cluster'] },
    'balloon-sizes': {
        items: () => props.balloonSizes,
        imageSlots: ['single', 'cluster'],
    },
    shapes: { items: () => props.shapes, imageSlots: ['image'] },
    textures: { items: () => props.textures, imageSlots: ['image'] },
    'color-families': {
        items: () => props.colorFamilies,
        imageSlots: ['single', 'cluster'],
    },
    themes: { items: () => props.themes, imageSlots: [] },
    materials: { items: () => props.materials, imageSlots: ['image'] },
};

const activeSlots = computed(() => tabConfig[activeTab.value].imageSlots);
const hasSingle = computed(() => activeSlots.value.includes('single'));
const hasCluster = computed(() => activeSlots.value.includes('cluster'));
const hasImage = computed(() => activeSlots.value.includes('image'));

// ── Add form ──────────────────────────────────────────────────────────────────
const showAdd = ref(false);
const addForm = useForm({
    name: '',
    alt_imperial_name: '',
    diameter_cm: '',
    size_id: '',
    texture_family_id: '',
    material_id: '',
    brand_id: '',
    fallback_color_hex: '',
    sort_order: '',
    single_image: null,
    cluster_image: null,
    image: null,
});

function submitAdd() {
    addForm.post(
        route('super-admin.catalog.reference.store', activeTab.value),
        {
            forceFormData: true,
            onSuccess: () => {
                addForm.reset();
                showAdd.value = false;
            },
        },
    );
}

// ── Inline edit ───────────────────────────────────────────────────────────────
const editingId = ref(null);
const editForm = useForm({
    name: '',
    alt_imperial_name: '',
    diameter_cm: '',
    size_id: '',
    texture_family_id: '',
    material_id: '',
    brand_id: '',
    fallback_color_hex: '',
    sort_order: '',
    single_image: null,
    single_image_clear: false,
    cluster_image: null,
    cluster_image_clear: false,
    image: null,
    image_clear: false,
    _method: 'patch',
});

function startEdit(item) {
    editingId.value = item.id;
    editForm.name = item.name;
    editForm.alt_imperial_name = item.alt_imperial_name ?? '';
    editForm.diameter_cm = item.diameter_cm ?? '';
    editForm.size_id = item.size_id ?? '';
    editForm.texture_family_id = item.texture_family_id ?? '';
    editForm.material_id = item.material_id ?? '';
    editForm.brand_id = item.brand_id ?? '';
    editForm.fallback_color_hex = item.fallback_color_hex ?? '';
    editForm.sort_order = item.sort_order ?? '';
    editForm.single_image = null;
    editForm.single_image_clear = false;
    editForm.cluster_image = null;
    editForm.cluster_image_clear = false;
    editForm.image = null;
    editForm.image_clear = false;
}

function submitEdit(item) {
    // POST + _method spoofing so multipart uploads survive the PATCH route.
    editForm.post(
        route('super-admin.catalog.reference.update', {
            table: activeTab.value,
            item: item.id,
        }),
        {
            forceFormData: true,
            onSuccess: () => {
                editingId.value = null;
            },
        },
    );
}

function itemThumbnails(item) {
    const slots = activeSlots.value;
    if (!item.images) return [];
    return slots.map((slot) => item.images[slot]).filter(Boolean);
}

function cancelEdit() {
    editingId.value = null;
}

function destroy(item) {
    if (
        !confirm(trans('catalog.reference.delete_confirm', { name: item.name }))
    )
        return;
    router.delete(
        route('super-admin.catalog.reference.destroy', {
            table: activeTab.value,
            item: item.id,
        }),
        { preserveScroll: true },
    );
}

function switchTab(key) {
    activeTab.value = key;
    editingId.value = null;
    showAdd.value = false;
    addForm.reset();
}

const selectClass =
    'w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft';
</script>

<template>
    <Head :title="$t('catalog.reference.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('catalog.heading') }}
            </h1>
        </template>

        <!-- Catalog nav tabs -->
        <div class="mb-6 flex gap-1 border-b border-border">
            <Link
                :href="route('super-admin.catalog.skus')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.skus') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.colors')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.colors') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.brands')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.brands') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.reference')"
                class="border-b-2 border-accent px-4 py-2.5 font-sans text-[14px] font-medium text-accent"
            >
                {{ $t('catalog.tabs.reference') }}
            </Link>
        </div>

        <!-- Inner sub-tabs -->
        <div class="mb-4 flex flex-wrap gap-1">
            <button
                v-for="key in tabKeys"
                :key="key"
                type="button"
                class="rounded-md px-3 py-1.5 font-sans text-[13px] font-medium transition"
                :class="
                    activeTab === key
                        ? 'bg-accent text-accent-on'
                        : 'bg-background text-ink-secondary hover:bg-border'
                "
                @click="switchTab(key)"
            >
                {{ $t(`catalog.reference.tabs.${key}`) }}
                <span class="ml-1 font-mono text-[11px] opacity-70">{{
                    tabConfig[key].items().length
                }}</span>
            </button>
        </div>

        <!-- Add button + form -->
        <div class="mb-4 flex items-center justify-end">
            <AppButton variant="primary" @click="showAdd = !showAdd">
                {{
                    showAdd
                        ? $t('catalog.actions.cancel')
                        : $t('catalog.reference.add_button')
                }}
            </AppButton>
        </div>

        <div
            v-if="showAdd"
            class="bg-accent-soft/30 mb-5 rounded-lg border border-accent p-4"
        >
            <form
                @submit.prevent="submitAdd"
                class="flex flex-wrap items-end gap-3"
            >
                <div class="w-52">
                    <AppInput
                        :label="$t('catalog.reference.name_label')"
                        v-model="addForm.name"
                        :error="addForm.errors.name"
                        required
                    />
                </div>

                <div v-if="activeTab === 'sizes'" class="w-44">
                    <AppInput
                        :label="$t('catalog.reference.alt_imperial_label')"
                        v-model="addForm.alt_imperial_name"
                        :error="addForm.errors.alt_imperial_name"
                    />
                </div>

                <div v-if="activeTab === 'sizes'" class="w-32">
                    <AppInput
                        :label="$t('catalog.reference.diameter_label')"
                        type="number"
                        v-model="addForm.diameter_cm"
                        :error="addForm.errors.diameter_cm"
                    />
                </div>

                <div v-if="activeTab === 'balloon-sizes'" class="w-44">
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.reference.size_family_label') }}
                    </label>
                    <select
                        v-model="addForm.size_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">
                            {{ $t('catalog.reference.select_placeholder') }}
                        </option>
                        <option v-for="s in sizes" :key="s.id" :value="s.id">
                            {{ s.name }}
                        </option>
                    </select>
                    <p
                        v-if="addForm.errors.size_id"
                        class="mt-1 font-sans text-[13px] text-danger"
                    >
                        {{ addForm.errors.size_id }}
                    </p>
                </div>

                <div v-if="activeTab === 'balloon-sizes'" class="w-44">
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.reference.brand_label') }}
                    </label>
                    <select
                        v-model="addForm.brand_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">
                            {{ $t('catalog.reference.select_placeholder') }}
                        </option>
                        <option v-for="b in brands" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                    <p
                        v-if="addForm.errors.brand_id"
                        class="mt-1 font-sans text-[13px] text-danger"
                    >
                        {{ addForm.errors.brand_id }}
                    </p>
                </div>

                <div v-if="activeTab === 'balloon-sizes'" class="w-44">
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.reference.material_label') }}
                    </label>
                    <select
                        v-model="addForm.material_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">
                            {{ $t('catalog.reference.select_placeholder') }}
                        </option>
                        <option
                            v-for="m in materials"
                            :key="m.id"
                            :value="m.id"
                        >
                            {{ m.name }}
                        </option>
                    </select>
                    <p
                        v-if="addForm.errors.material_id"
                        class="mt-1 font-sans text-[13px] text-danger"
                    >
                        {{ addForm.errors.material_id }}
                    </p>
                </div>

                <div v-if="activeTab === 'textures'" class="w-44">
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.reference.family_label') }}
                    </label>
                    <select
                        v-model="addForm.texture_family_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">
                            {{ $t('catalog.reference.select_placeholder') }}
                        </option>
                        <option
                            v-for="f in textureFamilies"
                            :key="f.id"
                            :value="f.id"
                        >
                            {{ f.name }}
                        </option>
                    </select>
                    <p
                        v-if="addForm.errors.texture_family_id"
                        class="mt-1 font-sans text-[13px] text-danger"
                    >
                        {{ addForm.errors.texture_family_id }}
                    </p>
                </div>

                <div v-if="activeTab === 'textures'" class="w-44">
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.reference.material_label') }}
                    </label>
                    <select v-model="addForm.material_id" :class="selectClass">
                        <option value="">
                            {{ $t('catalog.reference.none_option') }}
                        </option>
                        <option
                            v-for="m in materials"
                            :key="m.id"
                            :value="m.id"
                        >
                            {{ m.name }}
                        </option>
                    </select>
                    <p
                        v-if="addForm.errors.material_id"
                        class="mt-1 font-sans text-[13px] text-danger"
                    >
                        {{ addForm.errors.material_id }}
                    </p>
                </div>

                <div v-if="activeTab === 'textures'" class="w-44">
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.reference.brand_label') }}
                    </label>
                    <select v-model="addForm.brand_id" :class="selectClass">
                        <option value="">
                            {{ $t('catalog.reference.none_option') }}
                        </option>
                        <option v-for="b in brands" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                    <p
                        v-if="addForm.errors.brand_id"
                        class="mt-1 font-sans text-[13px] text-danger"
                    >
                        {{ addForm.errors.brand_id }}
                    </p>
                </div>

                <div
                    v-if="activeTab === 'color-families'"
                    class="flex items-end gap-2"
                >
                    <input
                        type="color"
                        v-model="addForm.fallback_color_hex"
                        class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                    />
                    <div class="w-28">
                        <AppInput
                            :label="$t('catalog.reference.hex_label')"
                            v-model="addForm.fallback_color_hex"
                            placeholder="#000000"
                            :error="addForm.errors.fallback_color_hex"
                        />
                    </div>
                </div>

                <div class="w-24">
                    <AppInput
                        :label="$t('catalog.reference.sort_order_label')"
                        type="number"
                        v-model="addForm.sort_order"
                    />
                </div>

                <div v-if="hasImage" class="w-72">
                    <ImageUpload
                        label="Image"
                        v-model:file="addForm.image"
                        :error="addForm.errors.image"
                    />
                </div>
                <div v-if="hasSingle" class="w-72">
                    <ImageUpload
                        label="Single balloon"
                        v-model:file="addForm.single_image"
                        :error="addForm.errors.single_image"
                    />
                </div>
                <div v-if="hasCluster" class="w-72">
                    <ImageUpload
                        label="Cluster"
                        v-model:file="addForm.cluster_image"
                        :error="addForm.errors.cluster_image"
                    />
                </div>

                <AppButton
                    type="submit"
                    variant="primary"
                    :disabled="addForm.processing"
                >
                    {{ $t('catalog.actions.add') }}
                </AppButton>
            </form>
        </div>

        <!-- Items table -->
        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-background">
                        <th
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_name') }}
                        </th>
                        <th
                            v-if="activeTab === 'sizes'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_alt_imperial') }}
                        </th>
                        <th
                            v-if="activeTab === 'sizes'"
                            class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_diameter') }}
                        </th>
                        <th
                            v-if="activeTab === 'balloon-sizes'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_size_family') }}
                        </th>
                        <th
                            v-if="activeTab === 'balloon-sizes'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_brand') }}
                        </th>
                        <th
                            v-if="activeTab === 'balloon-sizes'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_material') }}
                        </th>
                        <th
                            v-if="activeTab === 'textures'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_family') }}
                        </th>
                        <th
                            v-if="activeTab === 'textures'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_material') }}
                        </th>
                        <th
                            v-if="activeTab === 'textures'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_brand') }}
                        </th>
                        <th
                            v-if="activeTab === 'color-families'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_hex') }}
                        </th>
                        <th
                            v-if="activeSlots.length"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            Images
                        </th>
                        <th
                            class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_sort') }}
                        </th>
                        <th class="w-28 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="tabConfig[activeTab].items().length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-8 text-center font-sans text-[14px] text-ink-tertiary"
                        >
                            {{ $t('catalog.reference.empty') }}
                        </td>
                    </tr>
                    <template
                        v-for="item in tabConfig[activeTab].items()"
                        :key="item.id"
                    >
                        <!-- View row -->
                        <tr
                            v-if="editingId !== item.id"
                            class="hover:bg-accent-soft/40 group transition"
                        >
                            <td
                                class="px-4 py-3 font-sans text-[14px] text-ink-primary"
                            >
                                {{ item.name }}
                            </td>
                            <td
                                v-if="activeTab === 'sizes'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.alt_imperial_name ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'sizes'"
                                class="px-4 py-3 text-right font-mono text-[13px] text-ink-tertiary"
                            >
                                {{ item.diameter_cm ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'balloon-sizes'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.size?.name ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'balloon-sizes'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.brand?.name ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'balloon-sizes'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.material?.name ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'textures'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.texture_family?.name ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'textures'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.material?.name ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'textures'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.brand?.name ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'color-families'"
                                class="px-4 py-3"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="item.fallback_color_hex"
                                        class="h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor:
                                                item.fallback_color_hex,
                                        }"
                                    />
                                    <span
                                        class="font-mono text-[12px] text-ink-tertiary"
                                        >{{
                                            item.fallback_color_hex ?? '—'
                                        }}</span
                                    >
                                </div>
                            </td>
                            <td v-if="activeSlots.length" class="px-4 py-3">
                                <ImageGallery
                                    :urls="itemThumbnails(item)"
                                    size="sm"
                                    :alt="item.name"
                                />
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[13px] text-ink-tertiary"
                            >
                                {{ item.sort_order }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex justify-end gap-1 opacity-0 transition group-hover:opacity-100"
                                >
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        @click="startEdit(item)"
                                    >
                                        {{ $t('catalog.actions.edit') }}
                                    </AppButton>
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        class="text-danger hover:bg-danger-soft"
                                        @click="destroy(item)"
                                    >
                                        {{ $t('catalog.actions.delete') }}
                                    </AppButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit row -->
                        <tr v-else class="bg-accent-soft/20">
                            <td colspan="7" class="px-4 py-3">
                                <form
                                    @submit.prevent="submitEdit(item)"
                                    class="flex flex-wrap items-end gap-3"
                                >
                                    <div class="w-52">
                                        <AppInput
                                            :label="
                                                $t(
                                                    'catalog.reference.name_label',
                                                )
                                            "
                                            v-model="editForm.name"
                                            :error="editForm.errors.name"
                                            required
                                        />
                                    </div>
                                    <div
                                        v-if="activeTab === 'sizes'"
                                        class="w-44"
                                    >
                                        <AppInput
                                            :label="
                                                $t(
                                                    'catalog.reference.alt_imperial_label',
                                                )
                                            "
                                            v-model="editForm.alt_imperial_name"
                                            :error="
                                                editForm.errors.alt_imperial_name
                                            "
                                        />
                                    </div>
                                    <div
                                        v-if="activeTab === 'sizes'"
                                        class="w-32"
                                    >
                                        <AppInput
                                            :label="
                                                $t(
                                                    'catalog.reference.diameter_label',
                                                )
                                            "
                                            type="number"
                                            v-model="editForm.diameter_cm"
                                            :error="editForm.errors.diameter_cm"
                                        />
                                    </div>
                                    <div
                                        v-if="activeTab === 'balloon-sizes'"
                                        class="w-44"
                                    >
                                        <label
                                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'catalog.reference.size_family_label',
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editForm.size_id"
                                            required
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                {{
                                                    $t(
                                                        'catalog.reference.select_placeholder',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="s in sizes"
                                                :key="s.id"
                                                :value="s.id"
                                            >
                                                {{ s.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="editForm.errors.size_id"
                                            class="mt-1 font-sans text-[13px] text-danger"
                                        >
                                            {{ editForm.errors.size_id }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="activeTab === 'balloon-sizes'"
                                        class="w-44"
                                    >
                                        <label
                                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'catalog.reference.brand_label',
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editForm.brand_id"
                                            required
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                {{
                                                    $t(
                                                        'catalog.reference.select_placeholder',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="b in brands"
                                                :key="b.id"
                                                :value="b.id"
                                            >
                                                {{ b.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="editForm.errors.brand_id"
                                            class="mt-1 font-sans text-[13px] text-danger"
                                        >
                                            {{ editForm.errors.brand_id }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="activeTab === 'balloon-sizes'"
                                        class="w-44"
                                    >
                                        <label
                                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'catalog.reference.material_label',
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editForm.material_id"
                                            required
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                {{
                                                    $t(
                                                        'catalog.reference.select_placeholder',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="m in materials"
                                                :key="m.id"
                                                :value="m.id"
                                            >
                                                {{ m.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="editForm.errors.material_id"
                                            class="mt-1 font-sans text-[13px] text-danger"
                                        >
                                            {{ editForm.errors.material_id }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="activeTab === 'textures'"
                                        class="w-44"
                                    >
                                        <label
                                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'catalog.reference.family_label',
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editForm.texture_family_id"
                                            required
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                {{
                                                    $t(
                                                        'catalog.reference.select_placeholder',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="f in textureFamilies"
                                                :key="f.id"
                                                :value="f.id"
                                            >
                                                {{ f.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                editForm.errors.texture_family_id
                                            "
                                            class="mt-1 font-sans text-[13px] text-danger"
                                        >
                                            {{
                                                editForm.errors.texture_family_id
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="activeTab === 'textures'"
                                        class="w-44"
                                    >
                                        <label
                                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'catalog.reference.material_label',
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editForm.material_id"
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                {{
                                                    $t(
                                                        'catalog.reference.none_option',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="m in materials"
                                                :key="m.id"
                                                :value="m.id"
                                            >
                                                {{ m.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="editForm.errors.material_id"
                                            class="mt-1 font-sans text-[13px] text-danger"
                                        >
                                            {{ editForm.errors.material_id }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="activeTab === 'textures'"
                                        class="w-44"
                                    >
                                        <label
                                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'catalog.reference.brand_label',
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editForm.brand_id"
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                {{
                                                    $t(
                                                        'catalog.reference.none_option',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="b in brands"
                                                :key="b.id"
                                                :value="b.id"
                                            >
                                                {{ b.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="editForm.errors.brand_id"
                                            class="mt-1 font-sans text-[13px] text-danger"
                                        >
                                            {{ editForm.errors.brand_id }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="activeTab === 'color-families'"
                                        class="flex items-end gap-2"
                                    >
                                        <input
                                            type="color"
                                            v-model="
                                                editForm.fallback_color_hex
                                            "
                                            class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                                        />
                                        <div class="w-28">
                                            <AppInput
                                                :label="
                                                    $t(
                                                        'catalog.reference.hex_label',
                                                    )
                                                "
                                                v-model="
                                                    editForm.fallback_color_hex
                                                "
                                                placeholder="#000000"
                                                :error="
                                                    editForm.errors
                                                        .fallback_color_hex
                                                "
                                            />
                                        </div>
                                    </div>
                                    <div class="w-24">
                                        <AppInput
                                            :label="
                                                $t(
                                                    'catalog.reference.sort_order_label',
                                                )
                                            "
                                            type="number"
                                            v-model="editForm.sort_order"
                                        />
                                    </div>
                                    <div v-if="hasImage" class="w-72">
                                        <ImageUpload
                                            label="Image"
                                            v-model:file="editForm.image"
                                            v-model:clear="editForm.image_clear"
                                            :current-url="item.images?.image"
                                            :error="editForm.errors.image"
                                        />
                                    </div>
                                    <div v-if="hasSingle" class="w-72">
                                        <ImageUpload
                                            label="Single balloon"
                                            v-model:file="editForm.single_image"
                                            v-model:clear="
                                                editForm.single_image_clear
                                            "
                                            :current-url="item.images?.single"
                                            :error="
                                                editForm.errors.single_image
                                            "
                                        />
                                    </div>
                                    <div v-if="hasCluster" class="w-72">
                                        <ImageUpload
                                            label="Cluster"
                                            v-model:file="editForm.cluster_image"
                                            v-model:clear="
                                                editForm.cluster_image_clear
                                            "
                                            :current-url="item.images?.cluster"
                                            :error="
                                                editForm.errors.cluster_image
                                            "
                                        />
                                    </div>
                                    <div class="flex gap-2">
                                        <AppButton
                                            type="submit"
                                            variant="primary"
                                            size="sm"
                                            :disabled="editForm.processing"
                                        >
                                            {{ $t('catalog.actions.save') }}
                                        </AppButton>
                                        <AppButton
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            @click="cancelEdit"
                                        >
                                            {{ $t('catalog.actions.cancel') }}
                                        </AppButton>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
