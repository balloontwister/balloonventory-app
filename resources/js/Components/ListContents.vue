<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import StockBadge from '@/Components/StockBadge.vue';

const props = defineProps({
    list: { type: Object, required: true },
    // { id, name, is_business_favorites, notes, items: [...], can: { editItems, ... } }
});

const items = computed(() => props.list.items ?? []);
const isFavorites = computed(() => !!props.list.is_business_favorites);
const canEdit = computed(() => !!props.list.can?.editItems);

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
                                    :href="
                                        route('inventory.sku.show', {
                                            sku: item.sku_id,
                                        })
                                    "
                                    class="min-w-0 truncate font-sans text-[14px] font-medium text-ink-primary hover:underline"
                                >
                                    {{ item.name }}
                                </Link>
                            </div>
                        </td>
                        <!-- Brand -->
                        <td class="hidden px-3 py-3 sm:table-cell">
                            <span class="font-mono text-[13px] text-ink-secondary">
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
