<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    sku:           { type: Object, default: null },
    brands:        { type: Array, required: true },
    sizes:         { type: Array, required: true },
    shapes:        { type: Array, required: true },
    textures:      { type: Array, required: true },
    colorFamilies: { type: Array, required: true },
    themes:        { type: Array, required: true },
    materials:     { type: Array, required: true },
});

const isEdit = computed(() => !!props.sku);

const form = useForm({
    name:                  props.sku?.name ?? '',
    brand_id:              props.sku?.brand_id ?? '',
    size_id:               props.sku?.size_id ?? '',
    shape_id:              props.sku?.shape_id ?? '',
    texture_id:            props.sku?.texture_id ?? '',
    color_id:              props.sku?.color_id ?? '',
    material_id:           props.sku?.material_id ?? '',
    is_printed:            props.sku?.is_printed ?? false,
    default_count_per_bag: props.sku?.default_count_per_bag ?? '',
    manufacturer_sku:      props.sku?.manufacturer_sku ?? '',
    price_code:            props.sku?.price_code ?? '',
    theme_ids:             props.sku?.themes?.map(t => t.id) ?? [],
});

// When brand changes, clear color selection if the color belongs to a different brand.
watch(() => form.brand_id, (newBrand) => {
    if (!form.color_id) return;
    const selectedColor = allColors.value.find(c => c.id === form.color_id);
    if (selectedColor && selectedColor.brand_id && selectedColor.brand_id !== newBrand) {
        form.color_id = '';
    }
});

// Flatten all colors for lookup.
const allColors = computed(() =>
    props.colorFamilies.flatMap(f => f.colors ?? [])
);

// Filter colors to the selected brand + unbranded generics.
const filteredColorFamilies = computed(() => {
    const brandId = form.brand_id;
    return props.colorFamilies
        .map(family => ({
            ...family,
            colors: (family.colors ?? []).filter(
                c => !c.brand_id || c.brand_id === brandId
            ),
        }))
        .filter(family => family.colors.length > 0);
});

// Group sizes by category for optgroup display.
const sizeGroups = computed(() => {
    const groups = {};
    const labels = {
        small: 'Small (round)',
        medium: 'Medium (round)',
        large: 'Large (round)',
        giant: 'Giant (round)',
        small_modeling: 'Small Modeling',
        large_modeling: 'Large Modeling',
    };
    for (const s of props.sizes) {
        const label = labels[s.size_category] ?? s.size_category;
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
    return allColors.value.find(c => c.id === form.color_id)?.color_hex ?? null;
});

const selectClass = 'w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:opacity-50';
</script>

<template>
    <Head :title="isEdit ? `Edit SKU — ${sku.name}` : 'New SKU'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('super-admin.catalog.skus')" class="font-sans text-[14px] text-ink-secondary hover:text-ink-primary">
                    ← Catalog
                </Link>
                <span class="text-ink-tertiary">/</span>
                <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">
                    {{ isEdit ? `Edit — ${sku.name}` : 'New SKU' }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- ── Left: main attributes (2/3 width) ───────────────────── -->
                <div class="flex flex-col gap-6 lg:col-span-2">

                    <!-- Identity -->
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2 class="mb-4 font-sans text-[15px] font-semibold text-ink-primary">Identity</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <AppInput
                                    label="Name *"
                                    id="name"
                                    v-model="form.name"
                                    placeholder="e.g. Onyx Black"
                                    required
                                    :error="form.errors.name"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                                    Brand *
                                </label>
                                <select v-model="form.brand_id" required :class="selectClass">
                                    <option value="">Select brand…</option>
                                    <option v-for="b in brands" :key="b.id" :value="b.id">
                                        {{ b.abbreviation }} — {{ b.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.brand_id" class="mt-1 font-sans text-[13px] text-danger">{{ form.errors.brand_id }}</p>
                            </div>

                            <div>
                                <AppInput
                                    label="Manufacturer SKU"
                                    id="manufacturer_sku"
                                    v-model="form.manufacturer_sku"
                                    placeholder="e.g. 43734"
                                    :error="form.errors.manufacturer_sku"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Physical attributes -->
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2 class="mb-4 font-sans text-[15px] font-semibold text-ink-primary">Physical attributes</h2>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">

                            <div>
                                <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Size</label>
                                <select v-model="form.size_id" :class="selectClass">
                                    <option value="">— none —</option>
                                    <optgroup v-for="(group, label) in sizeGroups" :key="label" :label="label">
                                        <option v-for="s in group" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </optgroup>
                                </select>
                                <p v-if="form.errors.size_id" class="mt-1 font-sans text-[13px] text-danger">{{ form.errors.size_id }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Shape</label>
                                <select v-model="form.shape_id" :class="selectClass">
                                    <option value="">— none —</option>
                                    <option v-for="s in shapes" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <p v-if="form.errors.shape_id" class="mt-1 font-sans text-[13px] text-danger">{{ form.errors.shape_id }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Material</label>
                                <select v-model="form.material_id" :class="selectClass">
                                    <option value="">— none —</option>
                                    <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                                <p v-if="form.errors.material_id" class="mt-1 font-sans text-[13px] text-danger">{{ form.errors.material_id }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Texture</label>
                                <select v-model="form.texture_id" :class="selectClass">
                                    <option value="">— none —</option>
                                    <optgroup v-for="(group, family) in textureGroups" :key="family" :label="family">
                                        <option v-for="t in group" :key="t.id" :value="t.id">{{ t.name }}</option>
                                    </optgroup>
                                </select>
                                <p v-if="form.errors.texture_id" class="mt-1 font-sans text-[13px] text-danger">{{ form.errors.texture_id }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                                    Color
                                    <span v-if="!form.brand_id" class="normal-case tracking-normal text-ink-tertiary"> (select brand first)</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <select v-model="form.color_id" :disabled="!form.brand_id" :class="selectClass">
                                        <option value="">— none —</option>
                                        <optgroup v-for="family in filteredColorFamilies" :key="family.id" :label="family.name">
                                            <option v-for="c in family.colors" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </optgroup>
                                    </select>
                                    <!-- Live color swatch -->
                                    <span
                                        v-if="selectedColorHex"
                                        class="h-8 w-8 shrink-0 rounded-md ring-1 ring-inset ring-black/10"
                                        :style="{ backgroundColor: selectedColorHex }"
                                    />
                                </div>
                                <p v-if="form.errors.color_id" class="mt-1 font-sans text-[13px] text-danger">{{ form.errors.color_id }}</p>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Printed</label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-md border border-border px-3 py-[10px]">
                                    <input type="checkbox" v-model="form.is_printed" class="h-4 w-4 accent-accent" />
                                    <span class="font-sans text-[14px] text-ink-primary">This is a printed balloon</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Themes (multi-select) -->
                    <div v-if="form.is_printed" class="rounded-lg border border-border bg-surface p-5">
                        <h2 class="mb-1 font-sans text-[15px] font-semibold text-ink-primary">Themes</h2>
                        <p class="mb-4 font-sans text-[13px] text-ink-secondary">Select all that apply.</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="theme in themes"
                                :key="theme.id"
                                type="button"
                                class="rounded-full border px-3 py-1.5 font-sans text-[13px] font-medium transition"
                                :class="form.theme_ids.includes(theme.id)
                                    ? 'border-accent bg-accent-soft text-accent'
                                    : 'border-border bg-surface text-ink-secondary hover:border-border-strong'"
                                @click="toggleTheme(theme.id)"
                            >
                                {{ theme.name }}
                            </button>
                        </div>
                        <p v-if="form.errors.theme_ids" class="mt-2 font-sans text-[13px] text-danger">{{ form.errors.theme_ids }}</p>
                    </div>
                </div>

                <!-- ── Right: metadata (1/3 width) ─────────────────────────── -->
                <div class="flex flex-col gap-6">
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h2 class="mb-4 font-sans text-[15px] font-semibold text-ink-primary">Catalog metadata</h2>
                        <div class="flex flex-col gap-4">
                            <AppInput
                                label="Price code"
                                id="price_code"
                                v-model="form.price_code"
                                placeholder="e.g. STD-11"
                                :error="form.errors.price_code"
                            />
                            <AppInput
                                label="Default count per bag"
                                id="default_count_per_bag"
                                type="number"
                                v-model="form.default_count_per_bag"
                                placeholder="e.g. 100"
                                :error="form.errors.default_count_per_bag"
                            />
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col gap-3">
                        <AppButton type="submit" variant="primary" :disabled="form.processing" class="w-full justify-center">
                            {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create SKU') }}
                        </AppButton>
                        <Link :href="route('super-admin.catalog.skus')">
                            <AppButton variant="secondary" class="w-full justify-center">Cancel</AppButton>
                        </Link>
                    </div>
                </div>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
