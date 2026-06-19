<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    sku: { type: Object, default: null },
    brands: { type: Array, required: true },
    sizes: { type: Array, required: true },
    shapes: { type: Array, required: true },
    colorFamilies: { type: Array, required: true },
    themes: { type: Array, required: true },
    materials: { type: Array, required: true },
    balloonSizes: { type: Array, required: true },
    packagingTypes: { type: Array, required: true },
    priceCodes: { type: Array, required: true },
    printColors: { type: Array, required: true },
    printSides: { type: Array, required: true },
});

const isEdit = computed(() => !!props.sku);

// Shape is a local UI filter — not a form field sent to the server.
// On edit, derive the initial shape from the balloon size already selected.
const selectedShape = ref(
    props.sku?.balloon_size_id
        ? (props.balloonSizes.find((bs) => bs.id === props.sku.balloon_size_id)
              ?.shape_id ?? '')
        : '',
);

const form = useForm({
    name: props.sku?.name ?? '',
    description: props.sku?.description ?? '',
    brand_id: props.sku?.brand_id ?? '',
    material_id: props.sku?.material_id ?? '',
    balloon_size_id: props.sku?.balloon_size_id ?? '',
    color_id: props.sku?.color_id ?? '',
    is_printed: props.sku?.is_printed ?? false,
    default_count_per_bag: props.sku?.default_count_per_bag ?? '',
    warehouse_sku: props.sku?.warehouse_sku ?? '',
    upc: props.sku?.upc ?? '',
    ean: props.sku?.ean ?? '',
    asin: props.sku?.asin ?? '',
    mfg_no: props.sku?.mfg_no ?? '',
    packaging_id: props.sku?.packaging_id ?? '',
    single_image: null,
    single_image_clear: false,
    cluster_image: null,
    cluster_image_clear: false,
    price_code_id: props.sku?.price_code_id ?? '',
    is_active: props.sku?.is_active ?? true,
    discontinued_at: props.sku?.discontinued_at ?? '',
    product_version: props.sku?.product_version ?? '',
    theme_ids: props.sku?.themes?.map((t) => t.id) ?? [],
    print_color_ids: props.sku?.print_colors?.map((c) => c.id) ?? [],
    print_side_ids: props.sku?.print_sides?.map((s) => s.id) ?? [],
});

// When brand changes, clear every brand-scoped attribute whose previously
// selected value no longer matches the new brand.
watch(
    () => form.brand_id,
    (newBrand) => {
        const selectedColor = allColors.value.find(
            (c) => c.id === form.color_id,
        );
        if (
            selectedColor &&
            selectedColor.brand_id &&
            selectedColor.brand_id !== newBrand
        ) {
            form.color_id = '';
        }

        selectedShape.value = '';
        form.balloon_size_id = '';
        form.price_code_id = '';
    },
);

// When material changes, clear every material-scoped attribute.
watch(
    () => form.material_id,
    () => {
        selectedShape.value = '';
        form.balloon_size_id = '';
    },
);

// When shape filter changes, clear balloon size (it may no longer match the shape).
watch(selectedShape, () => {
    form.balloon_size_id = '';
});

// Flatten all colors for lookup.
const allColors = computed(() =>
    props.colorFamilies.flatMap((f) => f.colors ?? []),
);

// Filter colors to the selected brand + unbranded generics.
const filteredColorFamilies = computed(() => {
    const brandId = form.brand_id;
    return props.colorFamilies
        .map((family) => ({
            ...family,
            colors: (family.colors ?? []).filter(
                (c) => !c.brand_id || c.brand_id === brandId,
            ),
        }))
        .filter((family) => family.colors.length > 0);
});

// Shapes available for the current brand + material (derived from balloon sizes).
const availableShapes = computed(() => {
    const shapeIds = new Set(
        props.balloonSizes
            .filter((bs) => {
                if (form.brand_id && bs.brand_id !== form.brand_id)
                    return false;
                if (form.material_id && bs.material_id !== form.material_id)
                    return false;
                return true;
            })
            .map((bs) => bs.shape_id),
    );
    return props.shapes.filter((s) => shapeIds.has(s.id));
});

// Filter balloon sizes by brand + material + shape.
const filteredBalloonSizes = computed(() => {
    return props.balloonSizes.filter((bs) => {
        if (form.brand_id && bs.brand_id !== form.brand_id) return false;
        if (form.material_id && bs.material_id !== form.material_id)
            return false;
        if (selectedShape.value && bs.shape_id !== selectedShape.value)
            return false;
        return true;
    });
});

// Texture derived from the selected color — read-only display.
const derivedTexture = computed(() => {
    if (!form.color_id) return null;
    return allColors.value.find((c) => c.id === form.color_id)?.texture ?? null;
});

// Filter price codes by brand.
const filteredPriceCodes = computed(() => {
    if (!form.brand_id) return props.priceCodes;
    return props.priceCodes.filter((pc) => pc.brand_id === form.brand_id);
});

function toggleTheme(themeId) {
    const idx = form.theme_ids.indexOf(themeId);
    if (idx === -1) form.theme_ids.push(themeId);
    else form.theme_ids.splice(idx, 1);
}

function togglePrintColor(colorId) {
    const idx = form.print_color_ids.indexOf(colorId);
    if (idx === -1) form.print_color_ids.push(colorId);
    else form.print_color_ids.splice(idx, 1);
}

function togglePrintSide(sideId) {
    const idx = form.print_side_ids.indexOf(sideId);
    if (idx === -1) form.print_side_ids.push(sideId);
    else form.print_side_ids.splice(idx, 1);
}

function submit() {
    // forceFormData so file uploads are sent as multipart. Inertia v2 handles
    // the _method spoofing for PATCH internally when using useForm.
    if (isEdit.value) {
        form.patch(route('admin.catalog.skus.update', props.sku.id), {
            forceFormData: true,
        });
    } else {
        form.post(route('admin.catalog.skus.store'), {
            forceFormData: true,
        });
    }
}

// Color swatch preview
const selectedColorHex = computed(() => {
    if (!form.color_id) return null;
    return (
        allColors.value.find((c) => c.id === form.color_id)?.color_hex ?? null
    );
});

const selectClass =
    'w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:opacity-50';
</script>

<template>
    <Head
        :title="
            isEdit
                ? $t('catalog.sku_form.meta_title_edit', { name: sku.name })
                : $t('catalog.sku_form.meta_title_new')
        "
    />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="
                        isEdit
                            ? route('admin.catalog.skus.show', sku.id)
                            : route('admin.catalog.skus')
                    "
                    class="font-sans text-[14px] text-ink-secondary hover:text-ink-primary"
                >
                    {{
                        isEdit
                            ? sku.name
                            : $t('catalog.sku_form.back_to_catalog')
                    }}
                </Link>
                <span class="text-ink-tertiary">/</span>
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{
                        isEdit
                            ? $t('catalog.sku_form.heading_edit', {
                                  name: sku.name,
                              })
                            : $t('catalog.sku_form.heading_new')
                    }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left: main attributes (2/3 width) -->
                <div class="flex flex-col gap-6 lg:col-span-2">
                    <!-- Identity -->
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2
                            class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
                        >
                            {{ $t('catalog.sku_form.identity_heading') }}
                        </h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <AppInput
                                    :label="$t('catalog.sku_form.name_label')"
                                    id="name"
                                    v-model="form.name"
                                    :placeholder="
                                        $t('catalog.sku_form.name_placeholder')
                                    "
                                    required
                                    :error="form.errors.name"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.brand_label') }}
                                </label>
                                <select
                                    v-model="form.brand_id"
                                    required
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{
                                            $t(
                                                'catalog.sku_form.brand_placeholder',
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="b in brands"
                                        :key="b.id"
                                        :value="b.id"
                                    >
                                        {{ b.abbreviation }} — {{ b.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="form.errors.brand_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.brand_id }}
                                </p>
                            </div>

                            <div>
                                <AppInput
                                    :label="
                                        $t(
                                            'catalog.sku_form.warehouse_sku_label',
                                        )
                                    "
                                    id="warehouse_sku"
                                    v-model="form.warehouse_sku"
                                    :placeholder="
                                        $t(
                                            'catalog.sku_form.warehouse_sku_placeholder',
                                        )
                                    "
                                    :error="form.errors.warehouse_sku"
                                />
                            </div>

                            <!-- UPC / EAN / ASIN -->
                            <div>
                                <AppInput
                                    :label="$t('catalog.sku_form.upc_label')"
                                    id="upc"
                                    v-model="form.upc"
                                    :placeholder="
                                        $t('catalog.sku_form.upc_placeholder')
                                    "
                                    :error="form.errors.upc"
                                />
                            </div>
                            <div>
                                <AppInput
                                    :label="$t('catalog.sku_form.ean_label')"
                                    id="ean"
                                    v-model="form.ean"
                                    :placeholder="
                                        $t('catalog.sku_form.ean_placeholder')
                                    "
                                    :error="form.errors.ean"
                                />
                            </div>
                            <div>
                                <AppInput
                                    :label="$t('catalog.sku_form.asin_label')"
                                    id="asin"
                                    v-model="form.asin"
                                    :placeholder="
                                        $t('catalog.sku_form.asin_placeholder')
                                    "
                                    :error="form.errors.asin"
                                />
                            </div>
                            <div>
                                <AppInput
                                    :label="$t('catalog.sku_form.mfg_no_label')"
                                    id="mfg_no"
                                    v-model="form.mfg_no"
                                    :placeholder="
                                        $t(
                                            'catalog.sku_form.mfg_no_placeholder',
                                        )
                                    "
                                    :error="form.errors.mfg_no"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Physical attributes -->
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2
                            class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
                        >
                            {{ $t('catalog.sku_form.physical_heading') }}
                        </h2>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <!-- Material (first, because it filters shapes/textures/balloon sizes) -->
                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.material_label') }}
                                </label>
                                <select
                                    v-model="form.material_id"
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{ $t('catalog.sku_form.none_option') }}
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
                                    v-if="form.errors.material_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.material_id }}
                                </p>
                            </div>

                            <!-- Shape filter — narrows balloon sizes, not stored directly -->
                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.shape_label') }}
                                </label>
                                <select
                                    v-model="selectedShape"
                                    :disabled="
                                        !form.brand_id || !form.material_id
                                    "
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{ $t('catalog.sku_form.none_option') }}
                                    </option>
                                    <option
                                        v-for="s in availableShapes"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Balloon size (filtered by brand + material + shape) -->
                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{
                                        $t(
                                            'catalog.sku_form.balloon_size_label',
                                        )
                                    }}
                                </label>
                                <select
                                    v-model="form.balloon_size_id"
                                    :disabled="
                                        !form.brand_id || !form.material_id
                                    "
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{
                                            !form.brand_id || !form.material_id
                                                ? $t(
                                                      'catalog.sku_form.select_brand_and_material_first',
                                                  )
                                                : $t(
                                                      'catalog.sku_form.none_option',
                                                  )
                                        }}
                                    </option>
                                    <option
                                        v-for="bs in filteredBalloonSizes"
                                        :key="bs.id"
                                        :value="bs.id"
                                    >
                                        {{ bs.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="form.errors.balloon_size_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.balloon_size_id }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.color_label') }}
                                    <span
                                        v-if="!form.brand_id"
                                        class="normal-case tracking-normal text-ink-tertiary"
                                    >
                                        {{
                                            $t(
                                                'catalog.sku_form.color_select_brand_first',
                                            )
                                        }}
                                    </span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="form.color_id"
                                        :disabled="!form.brand_id"
                                        :class="selectClass"
                                    >
                                        <option value="">
                                            {{
                                                $t(
                                                    'catalog.sku_form.none_option',
                                                )
                                            }}
                                        </option>
                                        <optgroup
                                            v-for="family in filteredColorFamilies"
                                            :key="family.id"
                                            :label="family.name"
                                        >
                                            <option
                                                v-for="c in family.colors"
                                                :key="c.id"
                                                :value="c.id"
                                            >
                                                {{ c.name }}
                                            </option>
                                        </optgroup>
                                    </select>
                                    <span
                                        v-if="selectedColorHex"
                                        class="h-8 w-8 shrink-0 rounded-md ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor: selectedColorHex,
                                        }"
                                    />
                                </div>
                                <p
                                    v-if="form.errors.color_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.color_id }}
                                </p>
                            </div>

                            <!-- Texture derived from color — read-only -->
                            <div v-if="derivedTexture">
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.texture_label') }}
                                </label>
                                <div :class="selectClass" class="opacity-60">
                                    {{ derivedTexture.name }}
                                </div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label
                                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.printed_label') }}
                                </label>
                                <label
                                    class="flex cursor-pointer items-center gap-3 rounded-md border border-border px-3 py-[10px]"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="form.is_printed"
                                        class="h-4 w-4 accent-accent"
                                    />
                                    <span
                                        class="font-sans text-[14px] text-ink-primary"
                                    >
                                        {{
                                            $t(
                                                'catalog.sku_form.printed_checkbox',
                                            )
                                        }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Print details (conditional on is_printed) -->
                    <div
                        v-if="form.is_printed"
                        class="rounded-lg border border-border bg-surface p-5"
                    >
                        <h2
                            class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
                        >
                            {{ $t('catalog.sku_form.print_details_heading') }}
                        </h2>

                        <!-- Themes -->
                        <div class="mb-5">
                            <h3
                                class="mb-1 font-sans text-[13px] font-semibold text-ink-primary"
                            >
                                {{ $t('catalog.sku_form.themes_heading') }}
                            </h3>
                            <p
                                class="mb-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ $t('catalog.sku_form.themes_subheading') }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="theme in themes"
                                    :key="theme.id"
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 font-sans text-[13px] font-medium transition"
                                    :class="
                                        form.theme_ids.includes(theme.id)
                                            ? 'border-accent bg-accent-soft text-accent'
                                            : 'border-border bg-surface text-ink-secondary hover:border-border-strong'
                                    "
                                    @click="toggleTheme(theme.id)"
                                >
                                    {{ theme.name }}
                                </button>
                            </div>
                        </div>

                        <!-- Print colors -->
                        <div class="mb-5">
                            <h3
                                class="mb-1 font-sans text-[13px] font-semibold text-ink-primary"
                            >
                                {{
                                    $t('catalog.sku_form.print_colors_heading')
                                }}
                            </h3>
                            <p
                                class="mb-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{
                                    $t(
                                        'catalog.sku_form.print_colors_subheading',
                                    )
                                }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="color in printColors"
                                    :key="color.id"
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 font-sans text-[13px] font-medium transition"
                                    :class="
                                        form.print_color_ids.includes(color.id)
                                            ? 'border-accent bg-accent-soft text-accent'
                                            : 'border-border bg-surface text-ink-secondary hover:border-border-strong'
                                    "
                                    @click="togglePrintColor(color.id)"
                                >
                                    {{ color.name }}
                                </button>
                            </div>
                            <p
                                v-if="form.errors.print_color_ids"
                                class="mt-2 font-sans text-[13px] text-danger"
                            >
                                {{ form.errors.print_color_ids }}
                            </p>
                        </div>

                        <!-- Print sides -->
                        <div>
                            <h3
                                class="mb-1 font-sans text-[13px] font-semibold text-ink-primary"
                            >
                                {{ $t('catalog.sku_form.print_sides_heading') }}
                            </h3>
                            <p
                                class="mb-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{
                                    $t(
                                        'catalog.sku_form.print_sides_subheading',
                                    )
                                }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="side in printSides"
                                    :key="side.id"
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 font-sans text-[13px] font-medium transition"
                                    :class="
                                        form.print_side_ids.includes(side.id)
                                            ? 'border-accent bg-accent-soft text-accent'
                                            : 'border-border bg-surface text-ink-secondary hover:border-border-strong'
                                    "
                                    @click="togglePrintSide(side.id)"
                                >
                                    {{ side.name }}
                                </button>
                            </div>
                            <p
                                v-if="form.errors.print_side_ids"
                                class="mt-2 font-sans text-[13px] text-danger"
                            >
                                {{ form.errors.print_side_ids }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right: metadata (1/3 width) -->
                <div class="flex flex-col gap-6">
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2
                            class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
                        >
                            {{ $t('catalog.sku_form.metadata_heading') }}
                        </h2>
                        <div class="flex flex-col gap-4">
                            <!-- Price code -->
                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{
                                        $t('catalog.sku_form.price_code_label')
                                    }}
                                </label>
                                <select
                                    v-model="form.price_code_id"
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{ $t('catalog.sku_form.none_option') }}
                                    </option>
                                    <option
                                        v-for="pc in filteredPriceCodes"
                                        :key="pc.id"
                                        :value="pc.id"
                                    >
                                        {{ pc.code }}
                                    </option>
                                </select>
                                <p
                                    v-if="form.errors.price_code_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.price_code_id }}
                                </p>
                            </div>

                            <AppInput
                                :label="
                                    $t('catalog.sku_form.default_count_label')
                                "
                                id="default_count_per_bag"
                                type="number"
                                v-model="form.default_count_per_bag"
                                :placeholder="
                                    $t(
                                        'catalog.sku_form.default_count_placeholder',
                                    )
                                "
                                :error="form.errors.default_count_per_bag"
                            />

                            <!-- Packaging -->
                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.packaging_label') }}
                                </label>
                                <select
                                    v-model="form.packaging_id"
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{ $t('catalog.sku_form.none_option') }}
                                    </option>
                                    <option
                                        v-for="pt in packagingTypes"
                                        :key="pt.id"
                                        :value="pt.id"
                                    >
                                        {{ pt.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="form.errors.packaging_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.packaging_id }}
                                </p>
                            </div>

                            <!-- Product version -->
                            <AppInput
                                :label="
                                    $t('catalog.sku_form.product_version_label')
                                "
                                id="product_version"
                                v-model="form.product_version"
                                :placeholder="
                                    $t(
                                        'catalog.sku_form.product_version_placeholder',
                                    )
                                "
                                :error="form.errors.product_version"
                            />

                            <!-- Active toggle -->
                            <div class="flex flex-col gap-1">
                                <label
                                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.status_label') }}
                                </label>
                                <label
                                    class="flex cursor-pointer items-center gap-3 rounded-md border border-border px-3 py-[10px]"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="form.is_active"
                                        class="h-4 w-4 accent-accent"
                                    />
                                    <span
                                        class="font-sans text-[14px] text-ink-primary"
                                    >
                                        {{
                                            $t(
                                                'catalog.sku_form.active_checkbox',
                                            )
                                        }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2
                            class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
                        >
                            {{ $t('catalog.sku_form.description_heading') }}
                        </h2>
                        <div>
                            <label
                                class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                            >
                                {{ $t('catalog.sku_form.description_label') }}
                            </label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                :placeholder="
                                    $t(
                                        'catalog.sku_form.description_placeholder',
                                    )
                                "
                                :class="selectClass"
                            />
                            <p
                                v-if="form.errors.description"
                                class="mt-1 font-sans text-[13px] text-danger"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2
                            class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
                        >
                            {{ $t('catalog.sku_form.images_heading') }}
                        </h2>
                        <div class="flex flex-col gap-4">
                            <ImageUpload
                                :label="
                                    $t('catalog.sku_form.single_image_label')
                                "
                                v-model:file="form.single_image"
                                v-model:clear="form.single_image_clear"
                                :current-url="sku?.images?.single"
                                :error="form.errors.single_image"
                            />
                            <ImageUpload
                                :label="
                                    $t('catalog.sku_form.cluster_image_label')
                                "
                                v-model:file="form.cluster_image"
                                v-model:clear="form.cluster_image_clear"
                                :current-url="sku?.images?.cluster"
                                :error="form.errors.cluster_image"
                            />
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col gap-3">
                        <AppButton
                            type="submit"
                            variant="primary"
                            :disabled="form.processing"
                            class="w-full justify-center"
                        >
                            {{
                                form.processing
                                    ? $t('catalog.sku_form.submitting')
                                    : isEdit
                                      ? $t('catalog.sku_form.submit_edit')
                                      : $t('catalog.sku_form.submit_create')
                            }}
                        </AppButton>
                        <Link
                            :href="
                                isEdit
                                    ? route(
                                          'admin.catalog.skus.show',
                                          sku.id,
                                      )
                                    : route('admin.catalog.skus')
                            "
                        >
                            <AppButton
                                variant="secondary"
                                class="w-full justify-center"
                            >
                                {{ $t('catalog.sku_form.cancel') }}
                            </AppButton>
                        </Link>
                    </div>
                </div>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
