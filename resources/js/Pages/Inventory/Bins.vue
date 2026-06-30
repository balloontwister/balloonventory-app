<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import InventoryTabs from '@/Components/InventoryTabs.vue';
import InfoButton from '@/Components/InfoButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    locations: { type: Array, required: true },
});

const allBins = computed(() =>
    props.locations.flatMap((location) =>
        location.bins.map((bin) => ({ ...bin, location_name: location.name })),
    ),
);

// ── Expandable bin contents ──────────────────────────────────────────────────
// binContents[binId] = { open: bool, loading: bool, items: array | null }
// A single card peek fetches just that bin; the "show all" / per-location
// toggles fetch every requested bin in ONE bulk request and cache it, so a
// 100-bin account never fires 100 requests. Once a bin's items are cached,
// every later toggle is an instant open/close flip.
const binContents = reactive({});
const contentsLoading = ref(false);

function isOpen(binId) {
    return !!binContents[binId]?.open;
}

function isLoaded(binId) {
    return Array.isArray(binContents[binId]?.items);
}

function setItems(binId, items) {
    const entry = binContents[binId];
    binContents[binId] = {
        open: entry?.open ?? false,
        loading: false,
        items: items ?? [],
    };
}

// Single-bin peek (the per-card "Show contents" button).
async function toggleBin(bin) {
    const entry = binContents[bin.id];

    if (entry?.open) {
        entry.open = false;
        return;
    }

    if (isLoaded(bin.id)) {
        binContents[bin.id].open = true;
        return;
    }

    binContents[bin.id] = { open: true, loading: true, items: null };

    try {
        const { data } = await window.axios.get(
            route('inventory.bins.contents', { bin: bin.id }),
        );
        binContents[bin.id] = { open: true, loading: false, items: data.items };
    } catch {
        binContents[bin.id] = { open: true, loading: false, items: [] };
    }
}

// Fetch contents for many bins at once (optionally one location), caching each.
async function loadContents(bins, locationId = null) {
    const missing = bins.filter((bin) => !isLoaded(bin.id));
    if (missing.length === 0) {
        return;
    }
    contentsLoading.value = true;
    try {
        const { data } = await window.axios.get(
            route('inventory.bins.bulk-contents'),
            locationId ? { params: { location: locationId } } : undefined,
        );
        for (const bin of bins) {
            setItems(bin.id, data.contents[bin.id] ?? []);
        }
    } catch {
        // Leave unloaded bins as-is; the per-card peek can retry individually.
    } finally {
        contentsLoading.value = false;
    }
}

function setOpen(bins, open) {
    for (const bin of bins) {
        if (binContents[bin.id]) {
            binContents[bin.id].open = open;
        } else if (open) {
            binContents[bin.id] = { open: true, loading: false, items: [] };
        }
    }
}

// ── Show / hide all contents (top toolbar) ────────────────────────────────────
const allBinsOpen = computed(
    () =>
        allBins.value.length > 0 &&
        allBins.value.every((bin) => isOpen(bin.id)),
);

async function toggleAllContents() {
    if (allBinsOpen.value) {
        setOpen(allBins.value, false);
        return;
    }
    await loadContents(allBins.value);
    setOpen(allBins.value, true);
}

// ── Show / hide a single location's contents (location header) ────────────────
function locationOpen(location) {
    return (
        location.bins.length > 0 && location.bins.every((bin) => isOpen(bin.id))
    );
}

async function toggleLocationContents(location) {
    if (locationOpen(location)) {
        setOpen(location.bins, false);
        return;
    }
    await loadContents(location.bins, location.id);
    setOpen(location.bins, true);
}

function binsCountLabel(location) {
    const count = location.bins.length;
    return count === 1
        ? trans('bins.location.bins_count_singular', { count })
        : trans('bins.location.bins_count_plural', { count });
}

function binSummaryLabel(bin) {
    const count = bin.sku_count ?? 0;
    if (count === 0) {
        return trans('bins.bin.summary_empty');
    }
    return count === 1
        ? trans('bins.bin.summary_singular', { count })
        : trans('bins.bin.summary_plural', { count });
}
</script>

<template>
    <Head :title="$t('bins.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3">
                <h1
                    class="font-display text-[22px] font-semibold text-ink-primary"
                >
                    {{ $t('bins.heading') }}
                </h1>
                <InventoryTabs active="bins" />
            </div>
        </template>

        <div class="flex flex-wrap items-center gap-2">
            <button
                v-if="allBins.length > 0"
                type="button"
                class="rounded-md px-2 py-1 font-sans text-[15px] font-medium text-accent hover:bg-accent-soft disabled:opacity-50"
                :disabled="contentsLoading"
                @click="toggleAllContents"
            >
                {{
                    allBinsOpen
                        ? $t('bins.contents_hide_all')
                        : $t('bins.contents_show_all')
                }}
            </button>

            <div class="ml-auto flex items-center gap-2">
                <InfoButton :title="$t('bins.info.title')">
                    <p>{{ $t('bins.info.body_location') }}</p>
                    <p>{{ $t('bins.info.body_bin') }}</p>
                    <p>{{ $t('bins.info.body_how') }}</p>
                </InfoButton>
                <AppButton
                    :href="route('inventory.storage')"
                    variant="secondary"
                    size="sm"
                >
                    {{ $t('bins.manage_storage') }}
                </AppButton>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="locations.length === 0"
            class="flex flex-col items-center gap-2 py-20 text-center"
        >
            <p class="font-sans text-[17px] font-semibold text-ink-primary">
                {{ $t('bins.empty.heading') }}
            </p>
            <p class="font-sans text-[14px] text-ink-secondary">
                {{ $t('bins.empty.body') }}
            </p>
        </div>

        <!-- Locations -->
        <div v-else class="mt-4 flex flex-col gap-8">
            <section
                v-for="location in locations"
                :key="location.id"
                class="flex flex-col gap-3"
            >
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h2
                        class="font-display text-[18px] font-semibold text-ink-primary"
                    >
                        {{ location.name }}
                    </h2>
                    <span
                        v-if="location.is_default"
                        class="rounded-pill bg-background px-2 py-0.5 font-sans text-[11px] font-medium uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('bins.default_badge') }}
                    </span>
                    <span class="font-mono text-[12px] text-ink-tertiary">
                        {{ binsCountLabel(location) }}
                    </span>
                    <button
                        v-if="location.bins.length > 0"
                        type="button"
                        class="ml-auto rounded-md px-2 py-1 font-sans text-[13px] font-medium text-accent hover:bg-accent-soft disabled:opacity-50"
                        :disabled="contentsLoading"
                        @click="toggleLocationContents(location)"
                    >
                        {{
                            locationOpen(location)
                                ? $t('bins.location.hide_contents')
                                : $t('bins.location.show_contents')
                        }}
                    </button>
                </div>

                <p
                    v-if="location.bins.length === 0"
                    class="font-sans text-[14px] text-ink-tertiary"
                >
                    {{ $t('bins.location.empty') }}
                </p>

                <div
                    v-else
                    class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4"
                >
                    <div
                        v-for="bin in location.bins"
                        :key="bin.id"
                        class="flex flex-col rounded-lg border border-border bg-surface"
                        :class="{ 'col-span-full': isOpen(bin.id) }"
                    >
                        <!-- Card header -->
                        <div class="flex items-start gap-2 p-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="bin.number != null"
                                        class="rounded-md bg-background px-1.5 py-0.5 font-mono text-[12px] font-semibold text-ink-secondary"
                                    >
                                        {{
                                            $t('bins.bin.number_prefix', {
                                                number: bin.number,
                                            })
                                        }}
                                    </span>
                                    <Link
                                        :href="
                                            route('inventory.bins.show', {
                                                bin: bin.id,
                                            })
                                        "
                                        class="truncate font-sans text-[15px] font-semibold text-ink-primary hover:text-accent hover:underline"
                                    >
                                        {{ bin.name }}
                                    </Link>
                                    <span
                                        v-if="bin.is_default"
                                        class="rounded-pill bg-background px-2 py-0.5 font-sans text-[10px] font-medium uppercase tracking-eyebrow text-ink-secondary"
                                    >
                                        {{ $t('bins.default_badge') }}
                                    </span>
                                </div>
                                <p
                                    class="mt-1 font-mono text-[12px] text-ink-tertiary"
                                >
                                    {{ binSummaryLabel(bin) }}
                                    <span
                                        v-if="
                                            bin.full_bags_total ||
                                            bin.open_bags_total
                                        "
                                    >
                                        ·
                                        {{
                                            $t('bins.bin.full_bags', {
                                                count: bin.full_bags_total ?? 0,
                                            })
                                        }}
                                        /
                                        {{
                                            $t('bins.bin.open_bags', {
                                                count: bin.open_bags_total ?? 0,
                                            })
                                        }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Expanded contents -->
                        <div
                            v-if="isOpen(bin.id)"
                            class="border-t border-border px-4 py-3"
                        >
                            <p
                                v-if="binContents[bin.id]?.loading"
                                class="animate-pulse font-sans text-[13px] text-ink-tertiary"
                            >
                                {{ $t('bins.bin.loading') }}
                            </p>
                            <p
                                v-else-if="!binContents[bin.id]?.items?.length"
                                class="font-sans text-[13px] text-ink-tertiary"
                            >
                                {{ $t('bins.bin.empty') }}
                            </p>
                            <ul
                                v-else
                                class="grid grid-cols-1 gap-x-8 gap-y-1.5 sm:grid-cols-2 xl:grid-cols-3"
                            >
                                <li
                                    v-for="item in binContents[bin.id].items"
                                    :key="item.sku_id"
                                    class="flex items-start gap-2"
                                >
                                    <span
                                        v-if="item.color_hex"
                                        class="mt-1 h-3 w-3 shrink-0 rounded-full border border-border"
                                        :style="{
                                            backgroundColor: item.color_hex,
                                        }"
                                    />
                                    <span
                                        class="min-w-0 flex-1 break-words font-sans text-[13px] text-ink-primary"
                                    >
                                        <span
                                            v-if="item.brand"
                                            class="text-ink-tertiary"
                                            >{{ item.brand }} </span
                                        >{{ item.name }}
                                    </span>
                                    <span
                                        class="shrink-0 font-mono text-[12px] text-ink-secondary"
                                    >
                                        {{ item.full_bags }}/{{
                                            item.open_bags
                                        }}
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- Card actions -->
                        <div
                            class="mt-auto flex flex-wrap items-center justify-between gap-x-1 gap-y-0.5 border-t border-border px-3 py-2"
                        >
                            <div class="flex items-center gap-x-1">
                                <Link
                                    :href="
                                        route('inventory.bins.show', {
                                            bin: bin.id,
                                        })
                                    "
                                    class="rounded-md px-2 py-1 font-sans text-[13px] font-medium text-accent hover:bg-accent-soft"
                                >
                                    {{ $t('bins.show.open') }}
                                </Link>
                                <Link
                                    :href="route('scan.index', { bin: bin.id })"
                                    class="flex h-7 w-7 items-center justify-center rounded-md text-ink-secondary transition hover:bg-background hover:text-accent"
                                    :title="$t('bins.scan_into')"
                                    :aria-label="$t('bins.scan_into')"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="h-[18px] w-[18px]"
                                    >
                                        <path
                                            d="M3 9V6a1 1 0 011-1h3M3 15v3a1 1 0 001 1h3M21 9V6a1 1 0 00-1-1h-3M21 15v3a1 1 0 01-1 1h-3"
                                        />
                                        <line x1="7" y1="12" x2="17" y2="12" />
                                        <line x1="7" y1="9" x2="7" y2="15" />
                                        <line x1="11" y1="10" x2="11" y2="14" />
                                        <line x1="15" y1="9" x2="15" y2="15" />
                                        <line x1="17" y1="12" x2="17" y2="12" />
                                    </svg>
                                </Link>
                            </div>
                            <button
                                type="button"
                                class="rounded-md px-2 py-1 font-sans text-[13px] text-ink-secondary hover:bg-background hover:text-ink-primary"
                                @click="toggleBin(bin)"
                            >
                                {{
                                    isOpen(bin.id)
                                        ? $t('bins.bin.collapse')
                                        : $t('bins.bin.expand')
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
