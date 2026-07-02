<script setup>
import AdminBackLink from '@/Components/AdminBackLink.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    references: { type: Object, required: true },
    gaps: {
        type: Object,
        default: () => ({ brands: [], sizes: [], colors: [] }),
    },
    facets: { type: Object, default: () => ({ brands: [], states: {} }) },
    pendingCount: { type: Number, default: 0 },
});

const NO_BRAND = '__none__';

// ── Missing reference data (matcher gap report) ────────────────────────────────
const showGaps = ref(false);
const gapTotal = computed(
    () =>
        props.gaps.brands.length +
        props.gaps.sizes.length +
        props.gaps.colors.length,
);

// ── Filters ───────────────────────────────────────────────────────────────────
const statusFilter = ref(props.filters.status ?? '');
const brandFilter = ref(props.filters.brand ?? '');
const confidenceFilter = ref(props.filters.confidence ?? '');

const STATUS_FILTERS = [
    { value: '', labelKey: 'filter_status_all' },
    { value: 'pending', labelKey: 'filter_status_pending' },
    { value: 'auto_approved', labelKey: 'filter_status_auto_approved' },
    { value: 'approved', labelKey: 'filter_status_approved' },
    { value: 'rejected', labelKey: 'filter_status_rejected' },
];

const CONFIDENCE_FILTERS = [
    { value: '', labelKey: 'filter_confidence_all' },
    { value: 'high', labelKey: 'filter_confidence_high' },
    { value: 'low', labelKey: 'filter_confidence_low' },
];

function applyFilters() {
    router.get(
        route('admin.distributors.proposals.index'),
        {
            status: statusFilter.value || undefined,
            brand: brandFilter.value || undefined,
            confidence: confidenceFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

let brandDebounce;
watch(brandFilter, () => {
    clearTimeout(brandDebounce);
    brandDebounce = setTimeout(applyFilters, 350);
});

// Brand facet click: jump straight to that brand's group (no debounce wait).
function filterByBrand(name) {
    brandFilter.value = name;
    clearTimeout(brandDebounce);
    applyFilters();
}

watch([statusFilter, confidenceFilter], applyFilters);

// ── Per-card inline editing ─────────────────────────────────────────────────────
// One editable form per visible proposal, seeded from the manual mapping if
// present, else the matcher's guess — so the reviewer confirms rather than fills
// from scratch. A row's Save enables once its form differs from that seed.
const forms = reactive({});
const snapshots = reactive({}); // id → JSON of the pristine form, for dirty detection
const savingId = ref(null);

function blankForm(item) {
    const g = item.guess?.available ? item.guess : null;
    return {
        proposed_name: item.proposed_name ?? '',
        proposed_brand_id:
            item.proposed_brand_id ?? g?.brand?.selected?.id ?? null,
        proposed_balloon_size_id:
            item.proposed_balloon_size_id ??
            g?.balloon_size?.selected?.id ??
            null,
        proposed_color_id:
            item.proposed_color_id ?? g?.color?.selected?.id ?? null,
        proposed_packaging_id:
            item.proposed_packaging_id ?? g?.packaging?.selected?.id ?? null,
        proposed_count: item.proposed_count ?? g?.count ?? null,
        proposed_warehouse_sku: item.proposed_warehouse_sku ?? '',
        note: item.note ?? '',
    };
}

function syncForms() {
    const ids = new Set(props.proposals.data.map((i) => i.id));
    Object.keys(forms).forEach((id) => {
        if (!ids.has(id)) {
            delete forms[id];
            delete snapshots[id];
        }
    });
    for (const item of props.proposals.data) {
        const fresh = blankForm(item);
        forms[item.id] = fresh;
        snapshots[item.id] = JSON.stringify(fresh);
    }
}

syncForms();
// Rebuild after every prop refresh (save, filter, pagination) so snapshots reset.
watch(() => props.proposals.data, syncForms);

function isDirty(id) {
    return !!forms[id] && snapshots[id] !== JSON.stringify(forms[id]);
}

// Sizes / colours scoped to the row's chosen brand.
function sizesFor(brandId) {
    return brandId
        ? props.references.balloonSizes.filter((s) => s.brand_id === brandId)
        : props.references.balloonSizes;
}
function colorsFor(brandId) {
    return brandId
        ? props.references.colors.filter((c) => c.brand_id === brandId)
        : props.references.colors;
}

// Changing the brand drops a size/colour that no longer belongs to it.
function onBrandChange(id) {
    const f = forms[id];
    if (
        f.proposed_balloon_size_id &&
        !sizesFor(f.proposed_brand_id).some(
            (s) => s.id === f.proposed_balloon_size_id,
        )
    ) {
        f.proposed_balloon_size_id = null;
    }
    if (
        f.proposed_color_id &&
        !colorsFor(f.proposed_brand_id).some(
            (c) => c.id === f.proposed_color_id,
        )
    ) {
        f.proposed_color_id = null;
    }
}

// ── Actions ───────────────────────────────────────────────────────────────────
function save(item) {
    if (!isDirty(item.id) || savingId.value) return;
    savingId.value = item.id;
    router.patch(
        route('admin.distributors.proposals.update', item.id),
        forms[item.id],
        {
            preserveScroll: true,
            onSuccess: () => {
                savingId.value = null;
            },
            onError: () => {
                savingId.value = null;
            },
        },
    );
}

function approve(item) {
    if (savingId.value) return;
    // Persist any unsaved edits first, then approve — so one click applies the row
    // exactly as shown rather than the last-saved state.
    if (isDirty(item.id)) {
        savingId.value = item.id;
        router.patch(
            route('admin.distributors.proposals.update', item.id),
            forms[item.id],
            {
                preserveScroll: true,
                onSuccess: () => {
                    savingId.value = null;
                    router.post(
                        route('admin.distributors.proposals.approve', item.id),
                        {},
                        { preserveScroll: true },
                    );
                },
                onError: () => {
                    savingId.value = null;
                },
            },
        );
        return;
    }
    router.post(
        route('admin.distributors.proposals.approve', item.id),
        {},
        { preserveScroll: true },
    );
}

function reject(item) {
    if (
        !confirm(
            trans(
                'super_admin.dashboard.distributors.proposals.reject_confirm',
            ),
        )
    ) {
        return;
    }
    router.post(
        route('admin.distributors.proposals.reject', item.id),
        {},
        { preserveScroll: true },
    );
}

// Map to the existing barcode-less SKU instead of creating a duplicate.
function mapToExisting(item) {
    const exact = item.catalog_match?.available
        ? item.catalog_match.exact
        : null;
    if (!exact || savingId.value) return;
    savingId.value = item.id;
    router.post(
        route('admin.distributors.proposals.map-to-existing', item.id),
        { sku_id: exact.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                savingId.value = null;
            },
            onError: () => {
                savingId.value = null;
            },
        },
    );
}

// ── Match-quality dot (how confident the matcher's guess was) ──────────────────
function guessAttr(item, attr) {
    return item.guess?.available ? item.guess[attr] : null;
}

// 'exact' | 'fuzzy' | 'title' | 'none' — colours the dot beside each field so the
// reviewer sees at a glance which guesses to trust vs check. 'title' means the
// shade came from the product title rather than the structured field.
function attrQuality(item, attr) {
    const g = guessAttr(item, attr);
    if (!g?.selected) return 'none';
    return g.source === 'title' ? 'title' : g.quality;
}

function dotClass(quality) {
    return (
        {
            exact: 'bg-success',
            fuzzy: 'bg-warning',
            title: 'bg-warning',
        }[quality] ?? 'bg-border-strong'
    );
}

// ── Catalog match (what approving does against the existing catalog) ───────────
function catalogMatch(item) {
    return item.catalog_match?.available ? item.catalog_match : null;
}

function siblingCount(item) {
    return catalogMatch(item)?.siblings?.length ?? 0;
}

function exactNoBarcode(item) {
    const cm = catalogMatch(item);
    return cm?.exact && !cm.exact.has_barcode ? cm.exact : null;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function isActionable(status) {
    return status === 'pending' || status === 'auto_approved';
}

// Reject is only offered for not-yet-materialised proposals. An auto_approved
// proposal already owns a catalog SKU; rejecting it here would not unwind that
// SKU, so removal goes through normal catalog deletion instead.
function canReject(status) {
    return status === 'pending';
}

function statusClass(status) {
    return (
        {
            pending: 'bg-warning-soft text-warning',
            auto_approved: 'bg-accent-soft text-accent',
            approved: 'bg-success-soft text-success',
            rejected: 'bg-background text-ink-tertiary',
        }[status] ?? 'bg-background text-ink-tertiary'
    );
}

function confidenceClass(confidence) {
    return confidence === 'high'
        ? 'bg-success-soft text-success'
        : 'bg-warning-soft text-warning';
}
</script>

<template>
    <Head
        :title="$t('super_admin.dashboard.distributors.proposals.meta_title')"
    />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <AdminBackLink
                        :href="route('admin.distributors.index')"
                        :label="$t('super_admin.dashboard.nav.distributors')"
                    />
                    <h1
                        class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                    >
                        {{
                            $t(
                                'super_admin.dashboard.distributors.proposals.heading',
                            )
                        }}
                    </h1>
                    <span
                        v-if="pendingCount > 0"
                        class="rounded-full bg-accent px-2 py-0.5 font-sans text-[12px] font-semibold text-white"
                    >
                        {{
                            $t(
                                'super_admin.dashboard.distributors.proposals.pending_count',
                                { count: pendingCount },
                            )
                        }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-2">
            <!-- Missing reference data — what the matcher couldn't resolve -->
            <div
                v-if="gapTotal > 0"
                class="mb-3 rounded-lg border border-border bg-warning-soft"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-left"
                    @click="showGaps = !showGaps"
                >
                    <span
                        class="font-sans text-[13px] font-medium text-ink-primary"
                    >
                        {{
                            $t(
                                'super_admin.dashboard.distributors.proposals.gaps_heading',
                                { count: gapTotal },
                            )
                        }}
                    </span>
                    <span class="font-sans text-[12px] text-ink-tertiary">{{
                        showGaps ? '▲' : '▼'
                    }}</span>
                </button>
                <div
                    v-if="showGaps"
                    class="space-y-3 border-t border-border px-4 py-3"
                >
                    <p class="font-sans text-[12px] text-ink-secondary">
                        {{
                            $t(
                                'super_admin.dashboard.distributors.proposals.gaps_hint',
                            )
                        }}
                    </p>
                    <div v-if="gaps.brands.length">
                        <p
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{
                                $t(
                                    'super_admin.dashboard.distributors.proposals.gaps_brands',
                                )
                            }}
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span
                                v-for="g in gaps.brands"
                                :key="g.value"
                                class="rounded-md border border-border-strong bg-surface px-2 py-0.5 font-sans text-[12px] text-ink-secondary"
                            >
                                {{ g.value }}
                                <span class="text-ink-tertiary"
                                    >×{{ g.count }}</span
                                >
                            </span>
                        </div>
                    </div>
                    <div v-if="gaps.sizes.length">
                        <p
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{
                                $t(
                                    'super_admin.dashboard.distributors.proposals.gaps_sizes',
                                )
                            }}
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span
                                v-for="g in gaps.sizes"
                                :key="g.brand + g.value"
                                class="rounded-md border border-border-strong bg-surface px-2 py-0.5 font-sans text-[12px] text-ink-secondary"
                            >
                                {{ g.value }}
                                <span class="text-ink-tertiary"
                                    >· {{ g.brand }} ×{{ g.count }}</span
                                >
                            </span>
                        </div>
                    </div>
                    <div v-if="gaps.colors.length">
                        <p
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{
                                $t(
                                    'super_admin.dashboard.distributors.proposals.gaps_colors',
                                )
                            }}
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span
                                v-for="g in gaps.colors"
                                :key="g.brand + g.value"
                                class="rounded-md border border-border-strong bg-surface px-2 py-0.5 font-sans text-[12px] text-ink-secondary"
                            >
                                {{ g.value }}
                                <span class="text-ink-tertiary"
                                    >· {{ g.brand }} ×{{ g.count }}</span
                                >
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface">
                <!-- Filters -->
                <div class="border-b border-border px-6 py-4">
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{
                            $t(
                                'super_admin.dashboard.distributors.proposals.subheading',
                            )
                        }}
                    </p>

                    <!-- Brand facet: jump to a brand's group; counts come from the
                         resolution stamped at cluster time. -->
                    <div
                        v-if="facets.brands.length"
                        class="mt-3 flex flex-wrap items-center gap-1.5"
                    >
                        <span
                            class="mr-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{
                                $t(
                                    'super_admin.dashboard.distributors.proposals.facet_brand',
                                )
                            }}
                        </span>
                        <button
                            type="button"
                            class="rounded-full px-2.5 py-1 font-sans text-[12px]"
                            :class="
                                !brandFilter
                                    ? 'bg-accent text-white'
                                    : 'border border-border-strong bg-surface text-ink-secondary hover:border-accent'
                            "
                            @click="filterByBrand('')"
                        >
                            {{
                                $t(
                                    'super_admin.dashboard.distributors.proposals.facet_all',
                                )
                            }}
                        </button>
                        <button
                            v-for="b in facets.brands"
                            :key="b.name ?? NO_BRAND"
                            type="button"
                            class="rounded-full px-2.5 py-1 font-sans text-[12px]"
                            :class="
                                brandFilter === (b.name ?? NO_BRAND)
                                    ? 'bg-accent text-white'
                                    : 'border border-border-strong bg-surface text-ink-secondary hover:border-accent'
                            "
                            @click="filterByBrand(b.name ?? NO_BRAND)"
                        >
                            {{
                                b.name ??
                                $t(
                                    'super_admin.dashboard.distributors.proposals.facet_no_brand',
                                )
                            }}
                            <span
                                :class="
                                    brandFilter === (b.name ?? NO_BRAND)
                                        ? 'text-white/75'
                                        : 'text-ink-tertiary'
                                "
                                >{{ b.count }}</span
                            >
                        </button>
                    </div>

                    <!-- Resolution split: how many of the pending are one-click vs need work. -->
                    <div
                        v-if="facets.brands.length"
                        class="mt-2 flex flex-wrap items-center gap-1.5"
                    >
                        <span
                            v-if="facets.states.full"
                            class="rounded-full bg-success-soft px-2 py-0.5 font-sans text-[11px] font-semibold text-success"
                        >
                            {{ facets.states.full }}
                            {{
                                $t(
                                    'super_admin.dashboard.distributors.proposals.state_full',
                                )
                            }}
                        </span>
                        <span
                            v-if="facets.states.partial"
                            class="rounded-full bg-warning-soft px-2 py-0.5 font-sans text-[11px] font-semibold text-warning"
                        >
                            {{ facets.states.partial }}
                            {{
                                $t(
                                    'super_admin.dashboard.distributors.proposals.state_partial',
                                )
                            }}
                        </span>
                        <span
                            v-if="facets.states.no_brand"
                            class="rounded-full border border-border-strong bg-surface px-2 py-0.5 font-sans text-[11px] font-semibold text-ink-tertiary"
                        >
                            {{ facets.states.no_brand }}
                            {{
                                $t(
                                    'super_admin.dashboard.distributors.proposals.state_no_brand',
                                )
                            }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <select
                            v-model="statusFilter"
                            class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        >
                            <option
                                v-for="opt in STATUS_FILTERS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{
                                    $t(
                                        `super_admin.dashboard.distributors.proposals.${opt.labelKey}`,
                                    )
                                }}
                            </option>
                        </select>

                        <input
                            v-model="brandFilter"
                            type="search"
                            :placeholder="
                                $t(
                                    'super_admin.dashboard.distributors.proposals.filter_brand_placeholder',
                                )
                            "
                            class="w-64 max-w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        />

                        <select
                            v-model="confidenceFilter"
                            class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        >
                            <option
                                v-for="opt in CONFIDENCE_FILTERS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{
                                    $t(
                                        `super_admin.dashboard.distributors.proposals.${opt.labelKey}`,
                                    )
                                }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Proposal cards -->
                <div class="divide-y divide-border">
                    <div
                        v-if="proposals.data.length === 0"
                        class="px-6 py-10 text-center text-ink-tertiary"
                    >
                        {{
                            $t(
                                'super_admin.dashboard.distributors.proposals.empty',
                            )
                        }}
                    </div>

                    <div
                        v-for="item in proposals.data"
                        :key="item.id"
                        class="rounded-md px-4 py-4 transition focus-within:bg-background focus-within:shadow-sm focus-within:ring-1 focus-within:ring-border-strong hover:bg-background hover:shadow-sm hover:ring-1 hover:ring-border-strong sm:px-6"
                        :class="{ 'opacity-60': item.status === 'rejected' }"
                    >
                        <!-- Header: editable name + identity + badges -->
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <input
                                    v-model="forms[item.id].proposed_name"
                                    type="text"
                                    :placeholder="
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.col_name',
                                        )
                                    "
                                    class="w-full rounded-md border border-transparent bg-transparent px-1.5 py-1 font-sans text-[15px] font-semibold text-ink-primary hover:border-border-strong focus:border-accent focus:bg-surface focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                />
                                <div
                                    class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 px-1.5 font-mono text-[11px] text-ink-tertiary"
                                >
                                    <Link
                                        v-if="item.resulting_sku_id"
                                        :href="
                                            route(
                                                'admin.catalog.skus.show',
                                                item.resulting_sku_id,
                                            )
                                        "
                                        class="text-accent hover:underline"
                                    >
                                        {{
                                            item.upc ??
                                            item.normalized_sku ??
                                            '—'
                                        }}
                                    </Link>
                                    <span v-else>{{
                                        item.upc ?? item.normalized_sku ?? '—'
                                    }}</span>
                                    <span v-if="item.resulting_sku_name"
                                        >· {{ item.resulting_sku_name }}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="flex flex-shrink-0 flex-col items-end gap-1"
                            >
                                <span
                                    v-if="item.confidence"
                                    class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="confidenceClass(item.confidence)"
                                >
                                    {{
                                        $t(
                                            `super_admin.dashboard.distributors.proposals.confidence_${item.confidence}`,
                                        )
                                    }}
                                </span>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="statusClass(item.status)"
                                >
                                    {{
                                        $t(
                                            `super_admin.dashboard.distributors.proposals.status_${item.status}`,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Evidence: SKU + link per distributor (opens new tab) -->
                        <div
                            v-if="item.evidence?.length"
                            class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 px-1.5 text-[12px]"
                        >
                            <template
                                v-for="(ev, ei) in item.evidence"
                                :key="ei"
                            >
                                <a
                                    v-if="ev.url"
                                    :href="ev.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group relative inline-flex items-center gap-1.5 text-ink-secondary transition hover:text-accent"
                                >
                                    <span
                                        class="font-medium text-ink-primary"
                                        >{{
                                            ev.distributor_name ??
                                            ev.distributor_id
                                        }}</span
                                    >
                                    <span class="font-mono text-[11px]">{{
                                        ev.raw_sku ?? '—'
                                    }}</span>
                                    <span aria-hidden="true">↗</span>
                                    <span
                                        class="pointer-events-none absolute left-0 top-full z-20 mt-1 hidden whitespace-nowrap rounded-md border border-border bg-surface px-2 py-1 font-mono text-[11px] text-ink-secondary shadow-md group-hover:block"
                                        >{{ ev.url }}</span
                                    >
                                </a>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 text-ink-secondary"
                                >
                                    <span
                                        class="font-medium text-ink-primary"
                                        >{{
                                            ev.distributor_name ??
                                            ev.distributor_id
                                        }}</span
                                    >
                                    <span class="font-mono text-[11px]">{{
                                        ev.raw_sku ?? '—'
                                    }}</span>
                                </span>
                            </template>
                        </div>

                        <!-- Dense editable attributes -->
                        <div
                            class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6"
                        >
                            <div>
                                <div
                                    class="mb-0.5 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-ink-tertiary"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            dotClass(attrQuality(item, 'brand'))
                                        "
                                    />
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.col_brand',
                                        )
                                    }}
                                </div>
                                <select
                                    v-model="forms[item.id].proposed_brand_id"
                                    class="w-full rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                    @change="onBrandChange(item.id)"
                                >
                                    <option :value="null">—</option>
                                    <option
                                        v-for="b in references.brands"
                                        :key="b.id"
                                        :value="b.id"
                                    >
                                        {{ b.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <div
                                    class="mb-0.5 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-ink-tertiary"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            dotClass(
                                                attrQuality(
                                                    item,
                                                    'balloon_size',
                                                ),
                                            )
                                        "
                                    />
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.edit_size',
                                        )
                                    }}
                                </div>
                                <select
                                    v-model="
                                        forms[item.id].proposed_balloon_size_id
                                    "
                                    class="w-full rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                >
                                    <option :value="null">—</option>
                                    <option
                                        v-for="s in sizesFor(
                                            forms[item.id].proposed_brand_id,
                                        )"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <div
                                    class="mb-0.5 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-ink-tertiary"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            dotClass(attrQuality(item, 'color'))
                                        "
                                    />
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.edit_color',
                                        )
                                    }}
                                </div>
                                <select
                                    v-model="forms[item.id].proposed_color_id"
                                    class="w-full rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                >
                                    <option :value="null">—</option>
                                    <option
                                        v-for="c in colorsFor(
                                            forms[item.id].proposed_brand_id,
                                        )"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <div
                                    class="mb-0.5 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-ink-tertiary"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            dotClass(
                                                attrQuality(item, 'packaging'),
                                            )
                                        "
                                    />
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.edit_packaging',
                                        )
                                    }}
                                </div>
                                <select
                                    v-model="
                                        forms[item.id].proposed_packaging_id
                                    "
                                    class="w-full rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                >
                                    <option :value="null">—</option>
                                    <option
                                        v-for="p in references.packagingTypes"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <div
                                    class="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-tertiary"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.col_count',
                                        )
                                    }}
                                </div>
                                <input
                                    v-model.number="
                                        forms[item.id].proposed_count
                                    "
                                    type="number"
                                    min="1"
                                    class="w-full rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                />
                            </div>
                            <div>
                                <div
                                    class="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-tertiary"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.edit_warehouse_sku',
                                        )
                                    }}
                                </div>
                                <input
                                    v-model="
                                        forms[item.id].proposed_warehouse_sku
                                    "
                                    type="text"
                                    class="w-full rounded-md border border-border-strong bg-surface px-2 py-1.5 font-mono text-[12px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                />
                            </div>
                        </div>

                        <!-- Catalog-match hint -->
                        <div
                            v-if="exactNoBarcode(item) || siblingCount(item)"
                            class="mt-2 flex flex-wrap items-center gap-2 px-1.5 text-[12px]"
                        >
                            <span
                                v-if="siblingCount(item)"
                                class="inline-flex items-center rounded-full bg-accent-soft px-2 py-0.5 text-[11px] font-semibold text-accent"
                                :title="
                                    $t(
                                        'super_admin.dashboard.distributors.proposals.links_pack_sizes',
                                    )
                                "
                            >
                                ↔ {{ siblingCount(item) }}
                            </span>
                            <template v-if="exactNoBarcode(item)">
                                <span class="text-warning">
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.maybe_exists_detail',
                                            { name: exactNoBarcode(item).name },
                                        )
                                    }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-md border border-warning px-2 py-0.5 font-sans text-[11px] font-semibold text-warning transition hover:bg-warning-soft disabled:opacity-50"
                                    :disabled="savingId === item.id"
                                    @click="mapToExisting(item)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.map_to_existing',
                                        )
                                    }}
                                </button>
                            </template>
                        </div>

                        <!-- Note + actions -->
                        <div
                            class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <input
                                v-model="forms[item.id].note"
                                type="text"
                                :placeholder="
                                    $t(
                                        'super_admin.dashboard.distributors.proposals.edit_note',
                                    )
                                "
                                class="min-w-0 flex-1 rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[12px] text-ink-secondary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            />
                            <div
                                v-if="isActionable(item.status)"
                                class="flex flex-shrink-0 gap-2"
                            >
                                <button
                                    type="button"
                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[12px] font-semibold text-ink-secondary transition hover:bg-background disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="
                                        !isDirty(item.id) ||
                                        savingId === item.id
                                    "
                                    @click="save(item)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.action_edit_save',
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md bg-accent px-3 py-1.5 font-sans text-[12px] font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                                    :disabled="savingId === item.id"
                                    @click="approve(item)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.action_approve',
                                        )
                                    }}
                                </button>
                                <button
                                    v-if="canReject(item.status)"
                                    type="button"
                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[12px] text-danger transition hover:bg-background"
                                    @click="reject(item)"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.distributors.proposals.action_reject',
                                        )
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="proposals.last_page > 1"
                    class="flex items-center justify-between border-t border-border px-6 py-3"
                >
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ proposals.current_page }} / {{ proposals.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="proposals.prev_page_url"
                            :href="proposals.prev_page_url"
                            preserve-state
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ‹
                        </Link>
                        <Link
                            v-if="proposals.next_page_url"
                            :href="proposals.next_page_url"
                            preserve-state
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ›
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
