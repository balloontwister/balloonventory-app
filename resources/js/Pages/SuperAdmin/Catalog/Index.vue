<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import BrandTag from '@/Components/BrandTag.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    skus:      { type: Object, required: true },
    filters:   { type: Object, default: () => ({}) },
    brands:    { type: Array, required: true },
    sizes:     { type: Array, required: true },
    textures:  { type: Array, required: true },
    materials: { type: Array, required: true },
});

const search   = ref(props.filters.search   ?? '');
const brand    = ref(props.filters.brand    ?? '');
const size     = ref(props.filters.size     ?? '');
const texture  = ref(props.filters.texture  ?? '');
const material = ref(props.filters.material ?? '');

let debounce;
function applyFilters() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('super-admin.catalog.skus'), {
            search:   search.value   || undefined,
            brand:    brand.value    || undefined,
            size:     size.value     || undefined,
            texture:  texture.value  || undefined,
            material: material.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
}

watch([search, brand, size, texture, material], applyFilters);

function destroy(sku) {
    if (!confirm(`Delete "${sku.name}"? This cannot be undone.`)) return;
    router.delete(route('super-admin.catalog.skus.destroy', sku.id), { preserveScroll: true });
}

const sizeCategories = {
    small: 'Small', medium: 'Medium', large: 'Large', giant: 'Giant',
    small_modeling: 'Small Modeling', large_modeling: 'Large Modeling',
};
</script>

<template>
    <Head title="Catalog — SKUs" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">
                Catalog
            </h1>
        </template>

        <!-- Catalog nav tabs -->
        <div class="mb-6 flex gap-1 border-b border-border">
            <Link
                :href="route('super-admin.catalog.skus')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium transition"
                :class="$page.component === 'SuperAdmin/Catalog/Index'
                    ? 'border-b-2 border-accent text-accent'
                    : 'text-ink-secondary hover:text-ink-primary'"
            >
                SKUs
            </Link>
            <Link
                :href="route('super-admin.catalog.colors')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium transition"
                :class="$page.component === 'SuperAdmin/Catalog/Colors'
                    ? 'border-b-2 border-accent text-accent'
                    : 'text-ink-secondary hover:text-ink-primary'"
            >
                Colors
            </Link>
            <Link
                :href="route('super-admin.catalog.brands')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium transition"
                :class="$page.component === 'SuperAdmin/Catalog/Brands'
                    ? 'border-b-2 border-accent text-accent'
                    : 'text-ink-secondary hover:text-ink-primary'"
            >
                Brands
            </Link>
            <Link
                :href="route('super-admin.catalog.reference')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium transition"
                :class="$page.component === 'SuperAdmin/Catalog/Reference'
                    ? 'border-b-2 border-accent text-accent'
                    : 'text-ink-secondary hover:text-ink-primary'"
            >
                Reference Data
            </Link>
        </div>

        <!-- Toolbar -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <input
                v-model="search"
                type="search"
                placeholder="Search name, mfr SKU, price code…"
                class="w-56 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            />

            <select
                v-model="brand"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">All brands</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.abbreviation }} — {{ b.name }}</option>
            </select>

            <select
                v-model="size"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">All sizes</option>
                <option v-for="s in sizes" :key="s.id" :value="s.id">
                    {{ s.name }} ({{ sizeCategories[s.size_category] }})
                </option>
            </select>

            <select
                v-model="texture"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">All textures</option>
                <option v-for="t in textures" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>

            <div class="ml-auto">
                <Link :href="route('super-admin.catalog.skus.create')">
                    <AppButton variant="primary">+ New SKU</AppButton>
                </Link>
            </div>
        </div>

        <!-- Count -->
        <p class="mb-3 font-sans text-[13px] text-ink-secondary">
            {{ skus.total.toLocaleString() }} shared SKU{{ skus.total !== 1 ? 's' : '' }}
        </p>

        <!-- Table -->
        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-background">
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Name</th>
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Brand</th>
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Size</th>
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Color</th>
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Texture</th>
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Material</th>
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Mfr SKU</th>
                        <th class="w-24 px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="skus.data.length === 0">
                        <td colspan="8" class="px-4 py-10 text-center font-sans text-[14px] text-ink-tertiary">
                            No SKUs yet. <Link :href="route('super-admin.catalog.skus.create')" class="text-accent underline">Add the first one.</Link>
                        </td>
                    </tr>
                    <tr
                        v-for="sku in skus.data"
                        :key="sku.id"
                        class="group transition hover:bg-accent-soft/40"
                    >
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                <!-- Color swatch -->
                                <span
                                    v-if="sku.color?.color_hex"
                                    class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                    :style="{ backgroundColor: sku.color.color_hex }"
                                />
                                <span class="font-sans text-[14px] font-medium text-ink-primary">{{ sku.name }}</span>
                                <span v-if="sku.is_printed" class="rounded bg-accent-soft px-1.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent">Printed</span>
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <span class="font-mono text-[13px] text-ink-secondary">{{ sku.brand?.abbreviation }}</span>
                        </td>
                        <td class="px-3 py-3 font-mono text-[13px] text-ink-secondary">{{ sku.size?.name ?? '—' }}</td>
                        <td class="px-3 py-3 font-sans text-[13px] text-ink-secondary">{{ sku.color?.name ?? '—' }}</td>
                        <td class="px-3 py-3 font-sans text-[13px] text-ink-secondary">{{ sku.texture?.name ?? '—' }}</td>
                        <td class="px-3 py-3 font-sans text-[13px] text-ink-secondary">{{ sku.material?.name ?? '—' }}</td>
                        <td class="px-3 py-3 font-mono text-[13px] text-ink-secondary">{{ sku.manufacturer_sku ?? '—' }}</td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                <Link :href="route('super-admin.catalog.skus.edit', sku.id)">
                                    <AppButton variant="ghost" size="sm">Edit</AppButton>
                                </Link>
                                <AppButton variant="ghost" size="sm" class="text-danger hover:bg-danger-soft" @click="destroy(sku)">
                                    Delete
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="skus.last_page > 1" class="mt-4 flex items-center justify-between">
            <p class="font-sans text-[13px] text-ink-secondary">
                Page {{ skus.current_page }} of {{ skus.last_page }}
            </p>
            <div class="flex gap-2">
                <Link
                    v-if="skus.prev_page_url"
                    :href="skus.prev_page_url"
                    preserve-state
                >
                    <AppButton variant="secondary" size="sm">← Previous</AppButton>
                </Link>
                <Link
                    v-if="skus.next_page_url"
                    :href="skus.next_page_url"
                    preserve-state
                >
                    <AppButton variant="secondary" size="sm">Next →</AppButton>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
