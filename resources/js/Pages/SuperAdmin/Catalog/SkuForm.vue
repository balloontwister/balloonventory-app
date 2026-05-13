<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

const props = defineProps({
    sku: { type: Object, default: null },
    brands: { type: Array, required: true },
    sizes: { type: Array, required: true },
    shapes: { type: Array, required: true },
    textures: { type: Array, required: true },
    colorFamilies: { type: Array, required: true },
    themes: { type: Array, required: true },
    materials: { type: Array, required: true },
});

const isEdit = computed(() => !!props.sku);

const form = useForm({
    name: props.sku?.name ?? '',
    brand_id: props.sku?.brand_id ?? '',
    size_id: props.sku?.size_id ?? '',
    shape_id: props.sku?.shape_id ?? '',
    texture_id: props.sku?.texture_id ?? '',
    color_id: props.sku?.color_id ?? '',
    material_id: props.sku?.material_id ?? '',
    is_printed: props.sku?.is_printed ?? false,
    default_count_per_bag: props.sku?.default_count_per_bag ?? '',
    manufacturer_sku: props.sku?.manufacturer_sku ?? '',
    price_code: props.sku?.price_code ?? '',
    theme_ids: props.sku?.themes?.map((t) => t.id) ?? [],
});

// When brand changes, clear color selection if the color belongs to a different brand.
watch(
    () => form.brand_id,
    (newBrand) => {
        if (!form.color_id) return;
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
    },
);

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

// Group sizes by category for optgroup display.
const sizeGroups = computed(() => {
    const groups = {};
    for (const s of props.sizes) {
        const label = s.size_category
            ? trans(`catalog.sku_form.size_group_labels.${s.size_category}`)
            : '';
        if (!groups[label]) groups[label] = [];
        groups[label].push(s);
    }
    return groups;
});

// Group textures by family for optgroup display.
const textureGroups = computed(() => {
    const groups = {};
    for (const t of props.textures) {
        if (!groups[t.texture_family]) groups[t.texture_family] = [];
        groups[t.texture_family].push(t);
    }
    return groups;
});

function toggleTheme(themeId) {
    const idx = form.theme_ids.indexOf(themeId);
    if (idx === -1) form.theme_ids.push(themeId);
    else form.theme_ids.splice(idx, 1);
}

function submit() {
    if (isEdit.value) {
        form.patch(route('super-admin.catalog.skus.update', props.sku.id));
    } else {
        form.post(route('super-admin.catalog.skus.store'));
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
                    :href="route('super-admin.catalog.skus')"
                    class="font-sans text-[14px] text-ink-secondary hover:text-ink-primary"
                >
                    {{ $t('catalog.sku_form.back_to_catalog') }}
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
                <!-- ── Left: main attributes (2/3 width) ───────────────────── -->
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
                                            'catalog.sku_form.manufacturer_sku_label',
                                        )
                                    "
                                    id="manufacturer_sku"
                                    v-model="form.manufacturer_sku"
                                    :placeholder="
                                        $t(
                                            'catalog.sku_form.manufacturer_sku_placeholder',
                                        )
                                    "
                                    :error="form.errors.manufacturer_sku"
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
                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.size_label') }}
                                </label>
                                <select
                                    v-model="form.size_id"
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{ $t('catalog.sku_form.none_option') }}
                                    </option>
                                    <optgroup
                                        v-for="(group, label) in sizeGroups"
                                        :key="label"
                                        :label="label"
                                    >
                                        <option
                                            v-for="s in group"
                                            :key="s.id"
                                            :value="s.id"
                                        >
                                            {{ s.name }}
                                        </option>
                                    </optgroup>
                                </select>
                                <p
                                    v-if="form.errors.size_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.size_id }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.shape_label') }}
                                </label>
                                <select
                                    v-model="form.shape_id"
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{ $t('catalog.sku_form.none_option') }}
                                    </option>
                                    <option
                                        v-for="s in shapes"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="form.errors.shape_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.shape_id }}
                                </p>
                            </div>

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

                            <div>
                                <label
                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                >
                                    {{ $t('catalog.sku_form.texture_label') }}
                                </label>
                                <select
                                    v-model="form.texture_id"
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{ $t('catalog.sku_form.none_option') }}
                                    </option>
                                    <optgroup
                                        v-for="(group, family) in textureGroups"
                                        :key="family"
                                        :label="family"
                                    >
                                        <option
                                            v-for="t in group"
                                            :key="t.id"
                                            :value="t.id"
                                        >
                                            {{ t.name }}
                                        </option>
                                    </optgroup>
                                </select>
                                <p
                                    v-if="form.errors.texture_id"
                                    class="mt-1 font-sans text-[13px] text-danger"
                                >
                                    {{ form.errors.texture_id }}
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
                                    <!-- Live color swatch -->
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

                    <!-- Themes (multi-select) -->
                    <div
                        v-if="form.is_printed"
                        class="rounded-lg border border-border bg-surface p-5"
                    >
                        <h2
                            class="mb-1 font-sans text-[15px] font-semibold text-ink-primary"
                        >
                            {{ $t('catalog.sku_form.themes_heading') }}
                        </h2>
                        <p
                            class="mb-4 font-sans text-[13px] text-ink-secondary"
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
                        <p
                            v-if="form.errors.theme_ids"
                            class="mt-2 font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.theme_ids }}
                        </p>
                    </div>
                </div>

                <!-- ── Right: metadata (1/3 width) ─────────────────────────── -->
                <div class="flex flex-col gap-6">
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2
                            class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
                        >
                            {{ $t('catalog.sku_form.metadata_heading') }}
                        </h2>
                        <div class="flex flex-col gap-4">
                            <AppInput
                                :label="$t('catalog.sku_form.price_code_label')"
                                id="price_code"
                                v-model="form.price_code"
                                :placeholder="
                                    $t(
                                        'catalog.sku_form.price_code_placeholder',
                                    )
                                "
                                :error="form.errors.price_code"
                            />
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
                        <Link :href="route('super-admin.catalog.skus')">
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
