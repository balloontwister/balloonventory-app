<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import { useScrollToHash } from '@/Composables/useScrollToHash';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, computed, watch, onUnmounted } from 'vue';

useScrollToHash();

const props = defineProps({
    skus: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    brands: { type: Array, required: true },
    sizes: { type: Array, required: true },
    shapes: { type: Array, required: true },
    textureFamilies: { type: Array, required: true },
    colorFamilies: { type: Array, required: true },
    materials: { type: Array, required: true },
    themes: { type: Array, required: true },
});

const search = ref(props.filters.search ?? '');
const brand = ref(props.filters.brand ?? '');
const size = ref(props.filters.size ?? '');
const shape = ref(props.filters.shape ?? '');
const textureFamily = ref(props.filters.texture_family ?? '');
const colorFamily = ref(props.filters.color_family ?? '');
const material = ref(props.filters.material ?? '');
const theme = ref(props.filters.theme ?? '');
const printed = ref(props.filters.printed ?? '');

let debounce;
function applyFilters() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('admin.catalog.skus'),
            {
                search: search.value || undefined,
                brand: brand.value || undefined,
                size: size.value || undefined,
                shape: shape.value || undefined,
                texture_family: textureFamily.value || undefined,
                color_family: colorFamily.value || undefined,
                material: material.value || undefined,
                theme: theme.value || undefined,
                printed: printed.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
}

watch(
    [
        search,
        brand,
        size,
        shape,
        textureFamily,
        colorFamily,
        material,
        theme,
        printed,
    ],
    applyFilters,
);

onUnmounted(() => clearTimeout(debounce));

const hasActiveFilters = computed(
    () =>
        !!(
            search.value ||
            brand.value ||
            size.value ||
            shape.value ||
            textureFamily.value ||
            colorFamily.value ||
            material.value ||
            theme.value ||
            printed.value
        ),
);

function resetFilters() {
    search.value = '';
    brand.value = '';
    size.value = '';
    shape.value = '';
    textureFamily.value = '';
    colorFamily.value = '';
    material.value = '';
    theme.value = '';
    printed.value = '';
}

const page = usePage();

// Carry the list's current filters/page into the show link so the show page's
// back link can restore them (and scroll to the originating row).
function showUrl(skuId) {
    const base = route('admin.catalog.skus.show', skuId);
    const queryStart = page.url.indexOf('?');
    const query = queryStart === -1 ? '' : page.url.slice(queryStart);
    return query ? `${base}?return=${encodeURIComponent(query)}` : base;
}

function destroy(sku) {
    if (!confirm(trans('catalog.skus.delete_confirm', { name: sku.name })))
        return;
    router.delete(route('admin.catalog.skus.destroy', sku.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="$t('catalog.skus.meta_title')" />

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
                :href="route('admin.catalog.skus')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium transition"
                :class="
                    $page.component === 'SuperAdmin/Catalog/Index'
                        ? 'border-b-2 border-accent text-accent'
                        : 'text-ink-secondary hover:text-ink-primary'
                "
            >
                {{ $t('catalog.tabs.skus') }}
            </Link>
            <Link
                :href="route('admin.catalog.colors')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium transition"
                :class="
                    $page.component === 'SuperAdmin/Catalog/Colors'
                        ? 'border-b-2 border-accent text-accent'
                        : 'text-ink-secondary hover:text-ink-primary'
                "
            >
                {{ $t('catalog.tabs.colors') }}
            </Link>
            <Link
                :href="route('admin.catalog.brands')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium transition"
                :class="
                    $page.component === 'SuperAdmin/Catalog/Brands'
                        ? 'border-b-2 border-accent text-accent'
                        : 'text-ink-secondary hover:text-ink-primary'
                "
            >
                {{ $t('catalog.tabs.brands') }}
            </Link>
            <Link
                :href="route('admin.catalog.reference')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium transition"
                :class="
                    $page.component === 'SuperAdmin/Catalog/Reference'
                        ? 'border-b-2 border-accent text-accent'
                        : 'text-ink-secondary hover:text-ink-primary'
                "
            >
                {{ $t('catalog.tabs.reference') }}
            </Link>
        </div>

        <!-- Toolbar -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <input
                v-model="search"
                type="search"
                :placeholder="$t('catalog.skus.search_placeholder')"
                class="w-56 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            />

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
                v-model="size"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">
                    {{ $t('catalog.skus.filter_all_sizes') }}
                </option>
                <option v-for="s in sizes" :key="s.id" :value="s.id">
                    {{ s.name }}
                </option>
            </select>

            <select
                v-model="shape"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">
                    {{ $t('catalog.skus.filter_all_shapes') }}
                </option>
                <option v-for="sh in shapes" :key="sh.id" :value="sh.id">
                    {{ sh.name }}
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
                v-model="theme"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">
                    {{ $t('catalog.skus.filter_all_themes') }}
                </option>
                <option v-for="th in themes" :key="th.id" :value="th.id">
                    {{ th.name }}
                </option>
            </select>

            <select
                v-model="printed"
                class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            >
                <option value="">
                    {{ $t('catalog.skus.filter_printed_all') }}
                </option>
                <option value="0">
                    {{ $t('catalog.skus.filter_solid_only') }}
                </option>
                <option value="1">
                    {{ $t('catalog.skus.filter_printed_only') }}
                </option>
            </select>

            <AppButton
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                @click="resetFilters"
            >
                {{ $t('catalog.skus.reset_filters') }}
            </AppButton>

            <div class="ml-auto">
                <Link :href="route('admin.catalog.skus.create')">
                    <AppButton variant="primary">{{
                        $t('catalog.skus.new_button')
                    }}</AppButton>
                </Link>
            </div>
        </div>

        <!-- Count -->
        <p class="mb-3 font-sans text-[13px] text-ink-secondary">
            {{
                skus.total === 1
                    ? $t('catalog.skus.count_singular', {
                          count: skus.total.toLocaleString(),
                      })
                    : $t('catalog.skus.count_plural', {
                          count: skus.total.toLocaleString(),
                      })
            }}
        </p>

        <!-- Table -->
        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-background">
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.skus.col_name') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.skus.col_brand') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.skus.col_size') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.skus.col_color') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.skus.col_texture') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.skus.col_material') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.skus.col_warehouse_sku') }}
                        </th>
                        <th class="w-24 px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="skus.data.length === 0">
                        <td
                            colspan="8"
                            class="px-4 py-10 text-center font-sans text-[14px] text-ink-tertiary"
                        >
                            {{ $t('catalog.skus.empty_lead') }}
                            <Link
                                :href="route('admin.catalog.skus.create')"
                                class="text-accent underline"
                            >
                                {{ $t('catalog.skus.empty_cta') }}
                            </Link>
                        </td>
                    </tr>
                    <tr
                        v-for="sku in skus.data"
                        :key="sku.id"
                        :id="`sku-${sku.id}`"
                        class="hover:bg-accent-soft/40 group transition"
                    >
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                <!-- Color swatch -->
                                <span
                                    v-if="sku.color?.color_hex"
                                    class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                    :style="{
                                        backgroundColor: sku.color.color_hex,
                                    }"
                                />
                                <Link
                                    :href="showUrl(sku.id)"
                                    class="font-sans text-[14px] font-medium text-ink-primary hover:underline"
                                    >{{ sku.name }}</Link
                                >
                                <span
                                    v-if="sku.is_printed"
                                    class="rounded bg-accent-soft px-1.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                                >
                                    {{ $t('catalog.skus.printed_badge') }}
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <span
                                class="font-mono text-[13px] text-ink-secondary"
                                >{{ sku.brand?.abbreviation }}</span
                            >
                        </td>
                        <td
                            class="px-3 py-3 font-mono text-[13px] text-ink-secondary"
                        >
                            {{ sku.balloon_size?.name ?? '—' }}
                        </td>
                        <td
                            class="px-3 py-3 font-sans text-[13px] text-ink-secondary"
                        >
                            {{ sku.color?.name ?? '—' }}
                        </td>
                        <td
                            class="px-3 py-3 font-sans text-[13px] text-ink-secondary"
                        >
                            {{ sku.color?.texture?.name ?? '—' }}
                        </td>
                        <td
                            class="px-3 py-3 font-sans text-[13px] text-ink-secondary"
                        >
                            {{ sku.material?.name ?? '—' }}
                        </td>
                        <td
                            class="px-3 py-3 font-mono text-[13px] text-ink-secondary"
                        >
                            {{ sku.warehouse_sku ?? '—' }}
                        </td>
                        <td class="px-3 py-3">
                            <div
                                class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100"
                            >
                                <Link
                                    :href="
                                        route(
                                            'admin.catalog.skus.edit',
                                            sku.id,
                                        )
                                    "
                                >
                                    <AppButton variant="ghost" size="sm">{{
                                        $t('catalog.actions.edit')
                                    }}</AppButton>
                                </Link>
                                <AppButton
                                    variant="ghost"
                                    size="sm"
                                    class="text-danger hover:bg-danger-soft"
                                    @click="destroy(sku)"
                                >
                                    {{ $t('catalog.actions.delete') }}
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="skus.last_page > 1"
            class="mt-4 flex items-center justify-between"
        >
            <p class="font-sans text-[13px] text-ink-secondary">
                {{
                    $t('catalog.skus.pagination_label', {
                        current: skus.current_page,
                        last: skus.last_page,
                    })
                }}
            </p>
            <div class="flex gap-2">
                <Link
                    v-if="skus.prev_page_url"
                    :href="skus.prev_page_url"
                    preserve-state
                >
                    <AppButton variant="secondary" size="sm">{{
                        $t('catalog.skus.pagination_prev')
                    }}</AppButton>
                </Link>
                <Link
                    v-if="skus.next_page_url"
                    :href="skus.next_page_url"
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
