<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import ImageGallery from '@/Components/ImageGallery.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
import { useScrollToHash } from '@/Composables/useScrollToHash';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, computed, watch, onUnmounted } from 'vue';

useScrollToHash();

const props = defineProps({
    colors: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    brands: { type: Array, required: true },
    materials: { type: Array, required: true },
    colorFamilies: { type: Array, required: true },
    textureFamilies: { type: Array, required: true },
    textures: { type: Array, required: true },
});

const brand = ref(props.filters.brand ?? '');
const material = ref(props.filters.material ?? '');
const colorFamily = ref(props.filters.color_family ?? '');
const textureFamily = ref(props.filters.texture_family ?? '');

let debounce;
function applyFilters() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('admin.catalog.colors'),
            {
                brand: brand.value || undefined,
                material: material.value || undefined,
                color_family: colorFamily.value || undefined,
                texture_family: textureFamily.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
}

watch([brand, material, colorFamily, textureFamily], applyFilters);

onUnmounted(() => clearTimeout(debounce));

const hasActiveFilters = computed(
    () =>
        !!(
            brand.value ||
            material.value ||
            colorFamily.value ||
            textureFamily.value
        ),
);

function resetFilters() {
    brand.value = '';
    material.value = '';
    colorFamily.value = '';
    textureFamily.value = '';
}

// ── Add form ──────────────────────────────────────────────────────────────────
const addForm = useForm({
    name: '',
    color_family_id: '',
    brand_id: '',
    texture_id: '',
    color_hex: '',
    sort_order: '',
    description: '',
    single_image: null,
    cluster_image: null,
});

function submitAdd() {
    addForm.post(route('admin.catalog.colors.store'), {
        forceFormData: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
}

const showAddForm = ref(false);

// ── Inline edit ───────────────────────────────────────────────────────────────
const editingId = ref(null);
const editForm = useForm({
    name: '',
    color_family_id: '',
    brand_id: '',
    texture_id: '',
    color_hex: '',
    sort_order: '',
    description: '',
    single_image: null,
    single_image_clear: false,
    cluster_image: null,
    cluster_image_clear: false,
    _method: 'patch',
});

function startEdit(color) {
    editingId.value = color.id;
    editForm.name = color.name;
    editForm.color_family_id = color.color_family_id;
    editForm.brand_id = color.brand_id ?? '';
    editForm.texture_id = color.texture_id ?? '';
    editForm.color_hex = color.color_hex ?? '';
    editForm.sort_order = color.sort_order ?? '';
    editForm.description = color.description ?? '';
    editForm.single_image = null;
    editForm.single_image_clear = false;
    editForm.cluster_image = null;
    editForm.cluster_image_clear = false;
}

function submitEdit(color) {
    // POST + _method spoofing because file uploads require multipart/form-data,
    // which PHP only parses from POST bodies.
    editForm.post(route('admin.catalog.colors.update', color.id), {
        forceFormData: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function cancelEdit() {
    editingId.value = null;
}

function destroy(color) {
    if (!confirm(trans('catalog.colors.delete_confirm', { name: color.name })))
        return;
    router.delete(route('admin.catalog.colors.destroy', color.id), {
        preserveScroll: true,
    });
}

// ── Texture filtering by brand ────────────────────────────────────────────────
const addFormTextures = computed(() =>
    addForm.brand_id
        ? props.textures.filter((t) => t.brand_id === addForm.brand_id)
        : [],
);
const editFormTextures = computed(() =>
    editForm.brand_id
        ? props.textures.filter((t) => t.brand_id === editForm.brand_id)
        : [],
);

// Textures are brand-scoped, so changing the brand invalidates the current
// texture. Tie this reset to the user-driven `change` event rather than a
// watcher: a watcher also fires when `startEdit()` programmatically repopulates
// the form, which would wipe the texture we just loaded for editing.
function onAddBrandChange() {
    addForm.texture_id = '';
}
function onEditBrandChange() {
    editForm.texture_id = '';
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const allColors = computed(() => props.colors.data);
const totalColors = computed(() => props.colors.total);

function brandAbbr(brandId) {
    if (!brandId) return null;
    return props.brands.find((b) => b.id === brandId)?.abbreviation ?? null;
}

const selectClass =
    'w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft';
</script>

<template>
    <Head :title="$t('catalog.colors.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('catalog.heading') }}
                </h1>
                <AdminBackLink />
            </div>
        </template>

        <!-- Catalog nav tabs (shared) -->
        <div class="mb-6 flex gap-1 border-b border-border">
            <Link
                :href="route('admin.catalog.skus')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.skus') }}
            </Link>
            <Link
                :href="route('admin.catalog.colors')"
                class="border-b-2 border-accent px-4 py-2.5 font-sans text-[14px] font-medium text-accent"
            >
                {{ $t('catalog.tabs.colors') }}
            </Link>
            <Link
                :href="route('admin.catalog.brands')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.brands') }}
            </Link>
            <Link
                :href="route('admin.catalog.reference')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.reference') }}
            </Link>
        </div>

        <!-- Filter bar -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <select
                v-model="brand"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">
                    {{ $t('catalog.skus.filter_all_brands') }}
                </option>
                <option v-for="b in brands" :key="b.id" :value="b.id">
                    {{ b.abbreviation }} — {{ b.name }}
                </option>
            </select>
            <select
                v-model="material"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">
                    {{ $t('catalog.skus.filter_all_materials') }}
                </option>
                <option v-for="m in materials" :key="m.id" :value="m.id">
                    {{ m.name }}
                </option>
            </select>
            <select
                v-model="colorFamily"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">
                    {{ $t('catalog.skus.filter_all_colors') }}
                </option>
                <option v-for="cf in colorFamilies" :key="cf.id" :value="cf.id">
                    {{ cf.name }}
                </option>
            </select>
            <select
                v-model="textureFamily"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">
                    {{ $t('catalog.skus.filter_all_textures') }}
                </option>
                <option
                    v-for="tf in textureFamilies"
                    :key="tf.id"
                    :value="tf.id"
                >
                    {{ tf.name }}
                </option>
            </select>
            <AppButton
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                @click="resetFilters"
            >
                {{ $t('catalog.colors.reset_filters') }}
            </AppButton>
        </div>

        <!-- Toolbar -->
        <div class="mb-4 flex items-center justify-between">
            <p class="font-sans text-[13px] text-ink-secondary">
                {{
                    totalColors === 1
                        ? $t('catalog.colors.count_singular', {
                              count: totalColors,
                          })
                        : $t('catalog.colors.count_plural', {
                              count: totalColors,
                          })
                }}
            </p>
            <AppButton variant="primary" @click="showAddForm = !showAddForm">
                {{
                    showAddForm
                        ? $t('catalog.actions.cancel')
                        : $t('catalog.colors.add_button')
                }}
            </AppButton>
        </div>

        <!-- Add form -->
        <div
            v-if="showAddForm"
            class="bg-accent-soft/30 mb-6 rounded-lg border border-accent p-5"
        >
            <h2
                class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
            >
                {{ $t('catalog.colors.new_heading') }}
            </h2>
            <form @submit.prevent="submitAdd">
                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
                >
                    <div class="sm:col-span-2">
                        <AppInput
                            :label="$t('catalog.colors.name_label')"
                            v-model="addForm.name"
                            :placeholder="$t('catalog.colors.name_placeholder')"
                            :error="addForm.errors.name"
                            required
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.family_label') }}
                        </label>
                        <select
                            v-model="addForm.color_family_id"
                            required
                            :class="selectClass"
                        >
                            <option value="">
                                {{ $t('catalog.colors.select_placeholder') }}
                            </option>
                            <option
                                v-for="f in colorFamilies"
                                :key="f.id"
                                :value="f.id"
                            >
                                {{ f.name }}
                            </option>
                        </select>
                        <p
                            v-if="addForm.errors.color_family_id"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ addForm.errors.color_family_id }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.brand_label') }}
                        </label>
                        <select
                            v-model="addForm.brand_id"
                            required
                            :class="selectClass"
                            @change="onAddBrandChange"
                        >
                            <option value="">
                                {{ $t('catalog.colors.select_placeholder') }}
                            </option>
                            <option
                                v-for="b in brands"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.abbreviation }}
                            </option>
                        </select>
                        <p
                            v-if="addForm.errors.brand_id"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ addForm.errors.brand_id }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.texture_label') }}
                        </label>
                        <select
                            v-model="addForm.texture_id"
                            required
                            :class="selectClass"
                        >
                            <option value="">
                                {{ $t('catalog.colors.select_placeholder') }}
                            </option>
                            <option
                                v-for="t in addFormTextures"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}
                            </option>
                        </select>
                        <p
                            v-if="addForm.errors.texture_id"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ addForm.errors.texture_id }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.hex_label') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                v-model="addForm.color_hex"
                                class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                            />
                            <input
                                type="text"
                                v-model="addForm.color_hex"
                                placeholder="#000000"
                                maxlength="7"
                                class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            />
                        </div>
                        <p
                            v-if="addForm.errors.color_hex"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ addForm.errors.color_hex }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <div class="w-72">
                        <ImageUpload
                            label="Single balloon image"
                            v-model:file="addForm.single_image"
                            :error="addForm.errors.single_image"
                        />
                    </div>
                    <div class="w-72">
                        <ImageUpload
                            label="Cluster image"
                            v-model:file="addForm.cluster_image"
                            :error="addForm.errors.cluster_image"
                        />
                    </div>
                    <AppButton
                        type="submit"
                        variant="primary"
                        :disabled="addForm.processing"
                    >
                        {{
                            addForm.processing
                                ? $t('catalog.actions.saving')
                                : $t('catalog.colors.submit_add')
                        }}
                    </AppButton>
                </div>
            </form>
        </div>

        <!-- Colors table -->
        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-background">
                        <th class="w-10 px-4 py-2.5"></th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_name') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_family') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_brand') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_hex') }}
                        </th>
                        <th class="px-3 py-2.5"></th>
                        <th class="w-24 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="colors.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center font-sans text-[14px] text-ink-tertiary"
                        >
                            {{ $t('catalog.colors.empty_family') }}
                        </td>
                    </tr>
                    <template v-for="color in colors.data" :key="color.id">
                        <!-- View row -->
                        <tr
                            v-if="editingId !== color.id"
                            :id="`color-${color.id}`"
                            class="hover:bg-accent-soft/30 group transition"
                        >
                            <td class="w-10 px-4 py-2.5">
                                <span
                                    v-if="color.color_hex"
                                    class="inline-block h-5 w-5 rounded-sm ring-1 ring-inset ring-black/10"
                                    :style="{
                                        backgroundColor: color.color_hex,
                                    }"
                                />
                                <span
                                    v-else
                                    class="inline-block h-5 w-5 rounded-sm border border-border bg-background"
                                />
                            </td>
                            <td class="px-3 py-2.5">
                                <Link
                                    :href="
                                        route(
                                            'admin.catalog.colors.show',
                                            color.id,
                                        )
                                    "
                                    class="font-sans text-[14px] text-ink-primary transition hover:text-accent hover:underline"
                                >
                                    {{ color.name }}
                                </Link>
                            </td>
                            <td class="px-3 py-2.5">
                                <span
                                    class="font-sans text-[13px] text-ink-secondary"
                                >
                                    {{ color.color_family?.name }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span
                                    v-if="brandAbbr(color.brand_id)"
                                    class="font-mono text-[12px] text-ink-secondary"
                                    >{{ brandAbbr(color.brand_id) }}</span
                                >
                                <span
                                    v-else
                                    class="font-sans text-[12px] text-ink-tertiary"
                                >
                                    {{ $t('catalog.colors.brand_generic') }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-2.5 font-mono text-[12px] text-ink-tertiary"
                            >
                                {{ color.color_hex ?? '—' }}
                            </td>
                            <td class="px-3 py-2.5">
                                <ImageGallery
                                    :urls="[
                                        color.single_image_url,
                                        color.cluster_image_url,
                                    ]"
                                    size="sm"
                                    :alt="color.name"
                                />
                            </td>
                            <td class="px-4 py-2.5">
                                <div
                                    class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100"
                                >
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        @click="startEdit(color)"
                                    >
                                        {{ $t('catalog.actions.edit') }}
                                    </AppButton>
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        class="text-danger hover:bg-danger-soft"
                                        @click="destroy(color)"
                                    >
                                        {{ $t('catalog.actions.delete') }}
                                    </AppButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit row -->
                        <tr v-else class="bg-accent-soft/20">
                            <td colspan="7" class="px-4 py-3">
                                <form @submit.prevent="submitEdit(color)">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div class="w-44">
                                            <AppInput
                                                :label="
                                                    $t(
                                                        'catalog.colors.edit_name_label',
                                                    )
                                                "
                                                v-model="editForm.name"
                                                :error="editForm.errors.name"
                                                required
                                            />
                                        </div>
                                        <div class="w-36">
                                            <label
                                                class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                            >
                                                {{
                                                    $t(
                                                        'catalog.colors.family_label',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                v-model="
                                                    editForm.color_family_id
                                                "
                                                required
                                                :class="selectClass"
                                            >
                                                <option
                                                    v-for="f in colorFamilies"
                                                    :key="f.id"
                                                    :value="f.id"
                                                >
                                                    {{ f.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="w-28">
                                            <label
                                                class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                            >
                                                {{
                                                    $t(
                                                        'catalog.colors.brand_label',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                v-model="editForm.brand_id"
                                                required
                                                :class="selectClass"
                                                @change="onEditBrandChange"
                                            >
                                                <option value="">
                                                    {{
                                                        $t(
                                                            'catalog.colors.select_placeholder',
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="b in brands"
                                                    :key="b.id"
                                                    :value="b.id"
                                                >
                                                    {{ b.abbreviation }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="w-28">
                                            <label
                                                class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                            >
                                                {{
                                                    $t(
                                                        'catalog.colors.texture_label',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                v-model="editForm.texture_id"
                                                required
                                                :class="selectClass"
                                            >
                                                <option value="">
                                                    {{
                                                        $t(
                                                            'catalog.colors.select_placeholder',
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="t in editFormTextures"
                                                    :key="t.id"
                                                    :value="t.id"
                                                >
                                                    {{ t.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="flex items-end gap-2">
                                            <input
                                                type="color"
                                                v-model="editForm.color_hex"
                                                class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                                            />
                                            <div class="w-28">
                                                <AppInput
                                                    :label="
                                                        $t(
                                                            'catalog.colors.hex_short_label',
                                                        )
                                                    "
                                                    v-model="editForm.color_hex"
                                                    placeholder="#000000"
                                                    :error="
                                                        editForm.errors
                                                            .color_hex
                                                    "
                                                />
                                            </div>
                                        </div>
                                        <div class="w-72">
                                            <ImageUpload
                                                label="Single balloon"
                                                v-model:file="
                                                    editForm.single_image
                                                "
                                                v-model:clear="
                                                    editForm.single_image_clear
                                                "
                                                :current-url="
                                                    color.single_image_url
                                                "
                                                :error="
                                                    editForm.errors.single_image
                                                "
                                            />
                                        </div>
                                        <div class="w-72">
                                            <ImageUpload
                                                label="Cluster"
                                                v-model:file="
                                                    editForm.cluster_image
                                                "
                                                v-model:clear="
                                                    editForm.cluster_image_clear
                                                "
                                                :current-url="
                                                    color.cluster_image_url
                                                "
                                                :error="
                                                    editForm.errors
                                                        .cluster_image
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
                                                {{
                                                    $t('catalog.actions.cancel')
                                                }}
                                            </AppButton>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="colors.last_page > 1"
            class="mt-4 flex items-center justify-between"
        >
            <p class="font-sans text-[13px] text-ink-secondary">
                {{
                    $t('catalog.skus.pagination_label', {
                        current: colors.current_page,
                        last: colors.last_page,
                    })
                }}
            </p>
            <div class="flex gap-2">
                <Link
                    v-if="colors.prev_page_url"
                    :href="colors.prev_page_url"
                    preserve-state
                >
                    <AppButton variant="secondary" size="sm">{{
                        $t('catalog.skus.pagination_prev')
                    }}</AppButton>
                </Link>
                <Link
                    v-if="colors.next_page_url"
                    :href="colors.next_page_url"
                    preserve-state
                >
                    <AppButton variant="secondary" size="sm">{{
                        $t('catalog.skus.pagination_next')
                    }}</AppButton>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
