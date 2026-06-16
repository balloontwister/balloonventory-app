<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import InventoryTabs from '@/Components/InventoryTabs.vue';
import StockBadge from '@/Components/StockBadge.vue';
import Modal from '@/Components/Modal.vue';
import FavoriteStar from '@/Components/FavoriteStar.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    skus: { type: Object, required: true },
    catalogSkus: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    brands: { type: Array, required: true },
    sizes: { type: Array, required: true },
    shapes: { type: Array, required: true },
    textureFamilies: { type: Array, required: true },
    colorFamilies: { type: Array, required: true },
    materials: { type: Array, required: true },
    lists: { type: Array, required: true },
    favoritesListId: { type: String, default: null },
});

const search = ref(props.filters.search ?? '');
const brand = ref(props.filters.brand ?? '');
const size = ref(props.filters.size ?? '');
const shape = ref(props.filters.shape ?? '');
const textureFamily = ref(props.filters.texture_family ?? '');
const colorFamily = ref(props.filters.color_family ?? '');
const material = ref(props.filters.material ?? '');
const sort = ref(props.filters.sort ?? 'recent');

let debounce;
function applyFilters() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('inventory.index'),
            {
                search: search.value || undefined,
                brand: brand.value || undefined,
                size: size.value || undefined,
                shape: shape.value || undefined,
                texture_family: textureFamily.value || undefined,
                color_family: colorFamily.value || undefined,
                material: material.value || undefined,
                sort: sort.value !== 'recent' ? sort.value : undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
}

watch(
    [search, brand, size, shape, textureFamily, colorFamily, material, sort],
    applyFilters,
);

const hasActiveFilters = computed(
    () =>
        !!(
            search.value ||
            brand.value ||
            size.value ||
            shape.value ||
            textureFamily.value ||
            colorFamily.value ||
            material.value
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
}

// ── Add to inventory (from catalog fallback) ──────────────────────────────────

function addToInventory(sku) {
    router.post(
        route('inventory.sku.store'),
        { sku_id: sku.id },
        { preserveScroll: true },
    );
}

// ── Remove from inventory ─────────────────────────────────────────────────────

function removeSku(sku) {
    if (!confirm(`Remove "${sku.name}" from your inventory?`)) return;
    router.delete(route('inventory.sku.destroy', sku.id), {
        preserveScroll: true,
    });
}

// ── Add to list modal ─────────────────────────────────────────────────────────

const addToListSku = ref(null);
const showAddToListModal = computed(() => addToListSku.value !== null);

const addToListForm = useForm({ list_id: '' });

const nonFavoriteLists = computed(() =>
    props.lists.filter((l) => !l.is_business_favorites),
);

function openAddToList(sku) {
    addToListSku.value = sku;
    addToListForm.reset();
    addToListForm.clearErrors();
    if (nonFavoriteLists.value.length > 0) {
        addToListForm.list_id = nonFavoriteLists.value[0].id;
    }
}

function closeAddToListModal() {
    addToListSku.value = null;
}

function submitAddToList() {
    addToListForm.post(
        route('inventory.sku.add-to-list', addToListSku.value.id),
        {
            preserveScroll: true,
            onSuccess: () => closeAddToListModal(),
        },
    );
}

// ── Bag count display ─────────────────────────────────────────────────────────

function listTooltip(sku) {
    if (!sku.lists?.length) return '';
    return sku.lists.map((l) => l.name).join(', ');
}

function isFavorite(sku) {
    return sku.lists?.some((l) => l.is_favorites) ?? false;
}
</script>

<template>
    <Head :title="$t('inventory.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3">
                <h1
                    class="font-display text-[22px] font-semibold text-ink-primary"
                >
                    {{ $t('inventory.heading') }}
                </h1>
                <InventoryTabs active="items" />
            </div>
        </template>

        <!-- Empty state: no inventory yet -->
        <template v-if="skus.total === 0 && !hasActiveFilters && !search">
            <div class="flex flex-col items-center gap-4 py-20 text-center">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    class="h-12 w-12 text-ink-tertiary"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-.375c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v.375c0 .621.504 1.125 1.125 1.125z"
                    />
                </svg>
                <div>
                    <p
                        class="font-sans text-[17px] font-semibold text-ink-primary"
                    >
                        {{ $t('inventory.empty_heading') }}
                    </p>
                    <p class="mt-1 font-sans text-[14px] text-ink-secondary">
                        {{ $t('inventory.empty_body') }}
                    </p>
                </div>
                <input
                    v-model="search"
                    type="search"
                    :placeholder="$t('inventory.empty_search_placeholder')"
                    class="w-72 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                />
            </div>
        </template>

        <!-- Inventory table view -->
        <template v-else>
            <!-- Toolbar -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="search"
                    :placeholder="$t('inventory.toolbar.search_placeholder')"
                    class="w-56 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                />

                <select
                    v-model="brand"
                    class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                >
                    <option value="">
                        {{ $t('inventory.toolbar.filter_all_brands') }}
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
                        {{ $t('inventory.toolbar.filter_all_sizes') }}
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
                        {{ $t('inventory.toolbar.filter_all_shapes') }}
                    </option>
                    <option v-for="sh in shapes" :key="sh.id" :value="sh.id">
                        {{ sh.name }}
                    </option>
                </select>

                <select
                    v-model="textureFamily"
                    class="hidden rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft lg:block"
                >
                    <option value="">
                        {{ $t('inventory.toolbar.filter_all_textures') }}
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
                    class="hidden rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft lg:block"
                >
                    <option value="">
                        {{ $t('inventory.toolbar.filter_all_colors') }}
                    </option>
                    <option
                        v-for="cf in colorFamilies"
                        :key="cf.id"
                        :value="cf.id"
                    >
                        {{ cf.name }}
                    </option>
                </select>

                <select
                    v-model="material"
                    class="hidden rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft xl:block"
                >
                    <option value="">
                        {{ $t('inventory.toolbar.filter_all_materials') }}
                    </option>
                    <option v-for="m in materials" :key="m.id" :value="m.id">
                        {{ m.name }}
                    </option>
                </select>

                <AppButton
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    @click="resetFilters"
                >
                    {{ $t('inventory.toolbar.reset_filters') }}
                </AppButton>

                <div class="ml-auto flex items-center gap-2">
                    <label class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('inventory.toolbar.sort_label') }}:
                    </label>
                    <select
                        v-model="sort"
                        class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    >
                        <option value="recent">
                            {{ $t('inventory.toolbar.sort_recent') }}
                        </option>
                        <option value="name">
                            {{ $t('inventory.toolbar.sort_name') }}
                        </option>
                        <option value="color_family">
                            {{ $t('inventory.toolbar.sort_color_family') }}
                        </option>
                        <option value="shape">
                            {{ $t('inventory.toolbar.sort_shape') }}
                        </option>
                        <option value="size">
                            {{ $t('inventory.toolbar.sort_size') }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Count -->
            <p class="mb-3 font-sans text-[13px] text-ink-secondary">
                {{
                    skus.total === 1
                        ? $t('inventory.count_singular', {
                              count: skus.total.toLocaleString(),
                          })
                        : $t('inventory.count_plural', {
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
                                {{ $t('inventory.col_name') }}
                            </th>
                            <th
                                class="hidden px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary sm:table-cell"
                            >
                                {{ $t('inventory.col_brand') }}
                            </th>
                            <th
                                class="hidden px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary md:table-cell"
                            >
                                {{ $t('inventory.col_size') }}
                            </th>
                            <th
                                class="px-3 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                            >
                                {{ $t('inventory.col_bags') }}
                            </th>
                            <th class="w-28 px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <!-- Empty search result -->
                        <tr
                            v-if="
                                skus.data.length === 0 &&
                                catalogSkus.length === 0
                            "
                        >
                            <td
                                colspan="5"
                                class="px-4 py-10 text-center font-sans text-[14px] text-ink-tertiary"
                            >
                                No balloons match your search.
                            </td>
                        </tr>

                        <!-- Inventory rows -->
                        <tr
                            v-for="sku in skus.data"
                            :key="sku.id"
                            :id="`sku-${sku.id}`"
                            class="hover:bg-accent-soft/40 group transition"
                        >
                            <td class="px-3 py-3">
                                <div class="flex min-w-0 items-center gap-2">
                                    <!-- Color swatch -->
                                    <span
                                        v-if="sku.color?.color_hex"
                                        class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor:
                                                sku.color.color_hex,
                                        }"
                                    />
                                    <Link
                                        :href="
                                            route('inventory.sku.show', sku.id)
                                        "
                                        class="min-w-0 truncate font-sans text-[14px] font-medium text-ink-primary hover:underline"
                                    >
                                        {{ sku.name }}
                                    </Link>
                                    <!-- List dot indicator -->
                                    <div
                                        v-if="sku.lists?.length"
                                        class="group/dot relative shrink-0"
                                    >
                                        <span
                                            class="inline-block h-2 w-2 cursor-default rounded-full bg-accent"
                                            :title="listTooltip(sku)"
                                        />
                                        <div
                                            class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-ink-primary px-2.5 py-1 font-sans text-[12px] text-white shadow-lg group-hover/dot:block"
                                        >
                                            {{ listTooltip(sku) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-3 py-3 sm:table-cell">
                                <span
                                    class="font-mono text-[13px] text-ink-secondary"
                                    >{{ sku.brand?.abbreviation }}</span
                                >
                            </td>
                            <td
                                class="hidden px-3 py-3 font-mono text-[13px] text-ink-secondary md:table-cell"
                            >
                                {{ sku.balloon_size?.name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <StockBadge
                                    :full-bags="sku.full_bags_total ?? 0"
                                    :open-bags="sku.open_bags_total ?? 0"
                                />
                            </td>
                            <td class="px-3 py-3">
                                <div
                                    class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100"
                                >
                                    <FavoriteStar
                                        v-if="favoritesListId"
                                        :sku-id="sku.id"
                                        :is-favorite="isFavorite(sku)"
                                        :favorite-list-id="favoritesListId"
                                    />
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        @click="openAddToList(sku)"
                                    >
                                        {{ $t('inventory.action_add_to_list') }}
                                    </AppButton>
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        class="text-danger hover:bg-danger-soft"
                                        @click="removeSku(sku)"
                                    >
                                        {{ $t('inventory.action_remove') }}
                                    </AppButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Catalog fallback divider -->
                        <tr v-if="catalogSkus.length > 0">
                            <td
                                colspan="5"
                                class="bg-background px-4 py-2 text-center font-sans text-[12px] font-medium uppercase tracking-eyebrow text-ink-tertiary"
                            >
                                {{ $t('inventory.catalog_fallback_divider') }}
                            </td>
                        </tr>

                        <!-- Catalog fallback rows -->
                        <tr
                            v-for="sku in catalogSkus"
                            :key="`cat-${sku.id}`"
                            class="hover:bg-accent-soft/40 group transition"
                        >
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="sku.color?.color_hex"
                                        class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor:
                                                sku.color.color_hex,
                                        }"
                                    />
                                    <span
                                        class="font-sans text-[14px] text-ink-secondary"
                                        >{{ sku.name }}</span
                                    >
                                </div>
                            </td>
                            <td class="hidden px-3 py-3 sm:table-cell">
                                <span
                                    class="font-mono text-[13px] text-ink-tertiary"
                                    >{{ sku.brand?.abbreviation }}</span
                                >
                            </td>
                            <td
                                class="hidden px-3 py-3 font-mono text-[13px] text-ink-tertiary md:table-cell"
                            >
                                {{ sku.balloon_size?.name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <span
                                    class="font-sans text-[13px] text-ink-tertiary"
                                    >—</span
                                >
                            </td>
                            <td class="px-3 py-3">
                                <div
                                    class="flex justify-end opacity-0 transition group-hover:opacity-100"
                                >
                                    <AppButton
                                        variant="secondary"
                                        size="sm"
                                        @click="addToInventory(sku)"
                                    >
                                        {{
                                            $t(
                                                'inventory.action_add_to_inventory',
                                            )
                                        }}
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
                        $t('inventory.pagination_label', {
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
                            $t('inventory.pagination_prev')
                        }}</AppButton>
                    </Link>
                    <Link
                        v-if="skus.next_page_url"
                        :href="skus.next_page_url"
                        preserve-state
                    >
                        <AppButton variant="secondary" size="sm">{{
                            $t('inventory.pagination_next')
                        }}</AppButton>
                    </Link>
                </div>
            </div>
        </template>

        <!-- Add to list modal -->
        <Modal
            :show="showAddToListModal"
            max-width="sm"
            @close="closeAddToListModal"
        >
            <div class="p-6">
                <div class="mb-4 flex items-start justify-between">
                    <h2
                        class="font-sans text-[17px] font-semibold text-ink-primary"
                    >
                        {{ $t('inventory.add_to_list_heading') }}
                    </h2>
                    <button
                        type="button"
                        class="ml-4 flex h-7 w-7 items-center justify-center rounded text-ink-tertiary hover:bg-background hover:text-ink-primary"
                        @click="closeAddToListModal"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                    </button>
                </div>

                <p
                    v-if="nonFavoriteLists.length === 0"
                    class="font-sans text-[14px] text-ink-secondary"
                >
                    {{ $t('inventory.add_to_list_no_lists') }}
                </p>

                <form
                    v-else
                    @submit.prevent="submitAddToList"
                    class="flex flex-col gap-4"
                >
                    <div class="flex flex-col gap-2">
                        <label
                            v-for="list in nonFavoriteLists"
                            :key="list.id"
                            class="flex cursor-pointer items-center gap-3 rounded-md border border-border p-3 transition"
                            :class="
                                addToListForm.list_id === list.id
                                    ? 'bg-accent-soft/30 border-accent'
                                    : 'hover:bg-background'
                            "
                        >
                            <input
                                type="radio"
                                :value="list.id"
                                v-model="addToListForm.list_id"
                                class="accent-accent"
                            />
                            <span
                                class="font-sans text-[14px] text-ink-primary"
                                >{{ list.name }}</span
                            >
                        </label>
                    </div>

                    <div class="flex justify-end gap-2">
                        <AppButton
                            variant="secondary"
                            type="button"
                            @click="closeAddToListModal"
                        >
                            {{ $t('inventory.add_to_list_cancel') }}
                        </AppButton>
                        <AppButton
                            variant="primary"
                            type="submit"
                            :disabled="
                                !addToListForm.list_id ||
                                addToListForm.processing
                            "
                        >
                            {{ $t('inventory.add_to_list_confirm') }}
                        </AppButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
