<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import StockBadge from '@/Components/StockBadge.vue';

const props = defineProps({
    list: { type: Object, required: true },
    // { id, name, is_business_favorites, notes, items: [...], can: { editItems, ... } }
    // Where this list is being viewed, so an item's SKU page can link back here:
    // 'list-detail' (Lists/Show) or 'inventory-list' (the By-list tab).
    backContext: { type: String, default: null },
});

// SKU detail link that remembers this list as the origin, so "back" on the SKU
// page returns to this list instead of the inventory list.
function itemHref(skuId) {
    const params = { sku: skuId };
    if (props.backContext) {
        params.from = props.backContext;
        params.list = props.list.id;
    }
    return route('inventory.sku.show', params);
}

const items = computed(() => props.list.items ?? []);
const isFavorites = computed(() => !!props.list.is_business_favorites);
const canEdit = computed(() => !!props.list.can?.editItems);

// ── Add items by search (reuses the shared scan.search-skus typeahead) ──────────
const query = ref('');
const results = ref([]);
const searching = ref(false);
const open = ref(false);
const adding = ref(null); // sku_id currently being added
let searchTimer = null;

const existingSkuIds = computed(
    () => new Set(items.value.map((item) => item.sku_id)),
);

watch(query, (q) => {
    clearTimeout(searchTimer);
    const term = (q ?? '').trim();
    if (term.length < 2) {
        results.value = [];
        searching.value = false;
        open.value = false;
        return;
    }
    searching.value = true;
    open.value = true;
    searchTimer = setTimeout(async () => {
        try {
            const res = await window.axios.get(route('scan.search-skus'), {
                params: { q: term },
            });
            results.value = res.data.skus ?? [];
        } catch {
            results.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
});

function addSku(sku) {
    if (existingSkuIds.value.has(sku.id) || adding.value) {
        return;
    }
    adding.value = sku.id;
    router.post(
        route('lists.items.store', { list: props.list.id }),
        { sku_id: sku.id },
        {
            preserveScroll: true,
            // Full reload so the new row appears; local state resets afterward.
            onFinish: () => {
                adding.value = null;
            },
        },
    );
}

function closeSearch() {
    open.value = false;
}

onBeforeUnmount(() => clearTimeout(searchTimer));

// On the Favorites list, planned_quantity is the reorder threshold.
const quantityLabelKey = computed(() =>
    isFavorites.value
        ? 'lists.items.reorder_threshold'
        : 'lists.items.planned_quantity',
);

function commitQuantity(item, event) {
    const raw = event.target.value;
    const value = raw === '' ? null : Number(raw);

    if (value === item.planned_quantity) {
        return;
    }

    router.patch(
        route('lists.items.update', { list: props.list.id, item: item.id }),
        { planned_quantity: value },
        { preserveScroll: true, preserveState: false },
    );
}

function removeItem(item) {
    router.delete(
        route('lists.items.destroy', { list: props.list.id, item: item.id }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <div>
        <!-- Add items by search -->
        <div v-if="canEdit" class="border-b border-border p-3">
            <div class="relative">
                <input
                    v-model="query"
                    type="search"
                    :placeholder="$t('lists.detail.add_placeholder')"
                    class="w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    @focus="query.trim().length >= 2 && (open = true)"
                />

                <!-- Results dropdown -->
                <div
                    v-if="open"
                    class="absolute left-0 right-0 top-full z-40 mt-1 max-h-80 overflow-y-auto rounded-md border border-border bg-surface shadow-pop"
                >
                    <p
                        v-if="searching"
                        class="px-3 py-2.5 font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ $t('lists.detail.add_searching') }}
                    </p>
                    <p
                        v-else-if="results.length === 0"
                        class="px-3 py-2.5 font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ $t('lists.detail.add_no_results') }}
                    </p>
                    <button
                        v-for="sku in results"
                        v-else
                        :key="sku.id"
                        type="button"
                        :disabled="
                            existingSkuIds.has(sku.id) || adding === sku.id
                        "
                        class="flex w-full items-center gap-2 px-3 py-2 text-left transition hover:bg-background disabled:cursor-default disabled:opacity-60 disabled:hover:bg-transparent"
                        @click="addSku(sku)"
                    >
                        <span
                            v-if="sku.color?.color_hex"
                            class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: sku.color.color_hex }"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate font-sans text-[14px] text-ink-primary"
                                >{{ sku.name }}</span
                            >
                            <span
                                class="block truncate font-mono text-[12px] text-ink-tertiary"
                            >
                                {{ sku.brand?.abbreviation
                                }}<template v-if="sku.balloon_size?.name">
                                    · {{ sku.balloon_size.name }}</template
                                >
                            </span>
                        </span>
                        <span
                            v-if="existingSkuIds.has(sku.id)"
                            class="shrink-0 font-sans text-[12px] font-medium text-ink-tertiary"
                            >{{ $t('lists.detail.add_already') }}</span
                        >
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 16 16"
                            fill="currentColor"
                            class="h-4 w-4 shrink-0 text-accent"
                        >
                            <path
                                d="M8.75 3.75a.75.75 0 00-1.5 0v3.5h-3.5a.75.75 0 000 1.5h3.5v3.5a.75.75 0 001.5 0v-3.5h3.5a.75.75 0 000-1.5h-3.5v-3.5z"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Click-away overlay -->
                <div
                    v-if="open"
                    class="fixed inset-0 z-30"
                    @click="closeSearch"
                />
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="items.length === 0"
            class="flex flex-col items-center gap-2 py-16 text-center"
        >
            <p class="font-sans text-[16px] font-semibold text-ink-primary">
                {{ $t('lists.detail.empty_title') }}
            </p>
            <p class="max-w-sm font-sans text-[14px] text-ink-secondary">
                {{ $t('lists.detail.empty_hint') }}
            </p>
            <Link
                :href="route('inventory.index')"
                class="mt-2 rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-medium text-accent-on transition hover:bg-accent-hover"
            >
                {{ $t('lists.detail.browse_inventory') }}
            </Link>
        </div>

        <!-- Items table -->
        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border">
                        <th
                            class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('lists.detail.col_item') }}
                        </th>
                        <th
                            class="hidden px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary sm:table-cell"
                        >
                            {{ $t('lists.detail.col_brand') }}
                        </th>
                        <th
                            class="hidden px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary md:table-cell"
                        >
                            {{ $t('lists.detail.col_size') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('lists.detail.col_stock') }}
                        </th>
                        <th
                            class="px-3 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t(quantityLabelKey) }}
                        </th>
                        <th
                            v-if="canEdit"
                            class="w-10 px-3 py-2.5"
                            aria-hidden="true"
                        />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-border last:border-0"
                    >
                        <!-- Item -->
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="item.color_hex"
                                    class="inline-block h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                    :style="{ backgroundColor: item.color_hex }"
                                />
                                <Link
                                    :href="itemHref(item.sku_id)"
                                    class="min-w-0 truncate font-sans text-[14px] font-medium text-ink-primary hover:underline"
                                >
                                    {{ item.name }}
                                </Link>
                            </div>
                        </td>
                        <!-- Brand -->
                        <td class="hidden px-3 py-3 sm:table-cell">
                            <span
                                class="font-mono text-[13px] text-ink-secondary"
                            >
                                {{ item.brand ?? '—' }}
                            </span>
                        </td>
                        <!-- Size -->
                        <td
                            class="hidden px-3 py-3 font-mono text-[13px] text-ink-secondary md:table-cell"
                        >
                            {{ item.size ?? '—' }}
                        </td>
                        <!-- Stock -->
                        <td class="px-3 py-3 text-right">
                            <StockBadge
                                :full-bags="item.full_bags ?? 0"
                                :open-bags="item.open_bags ?? 0"
                            />
                        </td>
                        <!-- Planned qty / reorder threshold -->
                        <td class="px-3 py-3 text-right">
                            <input
                                v-if="canEdit"
                                type="number"
                                min="0"
                                inputmode="numeric"
                                :value="item.planned_quantity ?? ''"
                                :placeholder="$t('lists.items.none')"
                                class="w-20 rounded-md border border-border-strong bg-surface px-2 py-1 text-right font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                @change="commitQuantity(item, $event)"
                            />
                            <span
                                v-else
                                class="font-mono text-[13px] text-ink-secondary"
                            >
                                {{ item.planned_quantity ?? '—' }}
                            </span>
                        </td>
                        <!-- Remove -->
                        <td v-if="canEdit" class="px-3 py-3 text-right">
                            <button
                                type="button"
                                class="rounded-md p-1.5 text-ink-tertiary transition hover:bg-danger-soft hover:text-danger"
                                :title="$t('lists.detail.remove_item')"
                                @click="removeItem(item)"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
