<script setup>
import AdminBackLink from '@/Components/AdminBackLink.vue';
import AppButton from '@/Components/AppButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    references: { type: Object, required: true },
    pendingCount: { type: Number, default: 0 },
});

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

watch([statusFilter, confidenceFilter], applyFilters);

// ── Evidence expander ─────────────────────────────────────────────────────────
const expandedId = ref(null);

function toggleEvidence(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

// ── Edit modal ────────────────────────────────────────────────────────────────
const editingProposal = ref(null);
const editForm = ref({
    proposed_brand_id: null,
    proposed_balloon_size_id: null,
    proposed_color_id: null,
    proposed_count: null,
    proposed_warehouse_sku: '',
});
const editProcessing = ref(false);

function openEdit(proposal) {
    editingProposal.value = proposal;
    const guess = proposal.guess?.available ? proposal.guess : null;
    // Start from the manual mapping if one exists, otherwise from the matcher's
    // guess, so the admin confirms rather than fills from scratch.
    editForm.value = {
        proposed_brand_id: proposal.proposed_brand_id ?? guess?.brand?.selected?.id ?? null,
        proposed_balloon_size_id: proposal.proposed_balloon_size_id ?? guess?.balloon_size?.selected?.id ?? null,
        proposed_color_id: proposal.proposed_color_id ?? guess?.color?.selected?.id ?? null,
        proposed_count: proposal.proposed_count ?? guess?.count ?? null,
        proposed_warehouse_sku: proposal.proposed_warehouse_sku ?? '',
    };
}

function closeEdit() {
    editingProposal.value = null;
    editProcessing.value = false;
}

function submitEdit() {
    if (!editingProposal.value) return;
    editProcessing.value = true;
    router.patch(
        route('admin.distributors.proposals.update', editingProposal.value.id),
        editForm.value,
        {
            preserveScroll: true,
            onSuccess: () => closeEdit(),
            onError: () => { editProcessing.value = false; },
        },
    );
}

// Sizes + colors scoped to the selected brand in the edit modal.
const filteredSizes = computed(() => {
    if (!editForm.value.proposed_brand_id) return props.references.balloonSizes;
    return props.references.balloonSizes.filter(
        (s) => s.brand_id === editForm.value.proposed_brand_id,
    );
});

const filteredColors = computed(() => {
    if (!editForm.value.proposed_brand_id) return props.references.colors;
    return props.references.colors.filter(
        (c) => c.brand_id === editForm.value.proposed_brand_id,
    );
});

// When the brand changes, drop any size/colour that no longer belongs to it —
// but keep ones that are still valid (so a pre-filled guess survives opening).
watch(
    () => editForm.value.proposed_brand_id,
    () => {
        if (
            editForm.value.proposed_balloon_size_id &&
            !filteredSizes.value.some((s) => s.id === editForm.value.proposed_balloon_size_id)
        ) {
            editForm.value.proposed_balloon_size_id = null;
        }
        if (
            editForm.value.proposed_color_id &&
            !filteredColors.value.some((c) => c.id === editForm.value.proposed_color_id)
        ) {
            editForm.value.proposed_color_id = null;
        }
    },
);

// ── Actions ───────────────────────────────────────────────────────────────────
function approve(proposal) {
    router.post(route('admin.distributors.proposals.approve', proposal.id), {}, {
        preserveScroll: true,
    });
}

function reject(proposal) {
    if (!confirm(trans('super_admin.dashboard.distributors.proposals.reject_confirm'))) {
        return;
    }
    router.post(route('admin.distributors.proposals.reject', proposal.id), {}, {
        preserveScroll: true,
    });
}

// ── Mapping display (manual edit, else the matcher's guess) ────────────────────
const MANUAL_FIELD = {
    brand: 'brand_name',
    balloon_size: 'balloon_size_name',
    color: 'color_name',
};

function guessAttr(item, attr) {
    return item.guess?.available ? item.guess[attr] : null;
}

function manualName(item, attr) {
    return item[MANUAL_FIELD[attr]] ?? null;
}

function mappedName(item, attr) {
    return manualName(item, attr) ?? guessAttr(item, attr)?.selected?.name ?? null;
}

// 'manual' | 'exact' | 'fuzzy' | 'none' — drives the quality dot colour.
function mappedSource(item, attr) {
    if (manualName(item, attr)) return 'manual';
    const g = guessAttr(item, attr);
    return g?.selected ? g.quality : 'none';
}

function dotClass(source) {
    return {
        manual: 'bg-accent',
        exact: 'bg-success',
        fuzzy: 'bg-warning',
    }[source] ?? 'bg-border-strong';
}

function altCount(item, attr) {
    const g = guessAttr(item, attr);
    return g?.candidates ? Math.max(0, g.candidates.length - 1) : 0;
}

function displayCount(item) {
    return item.proposed_count ?? guessAttr(item, 'count') ?? null;
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
    return {
        pending: 'bg-warning-soft text-warning',
        auto_approved: 'bg-accent-soft text-accent',
        approved: 'bg-success-soft text-success',
        rejected: 'bg-background text-ink-tertiary',
    }[status] ?? 'bg-background text-ink-tertiary';
}

function confidenceClass(confidence) {
    return confidence === 'high'
        ? 'bg-success-soft text-success'
        : 'bg-warning-soft text-warning';
}

function formatPrice(price) {
    if (price == null) return '—';
    return `$${Number(price).toFixed(2)}`;
}
</script>

<template>
    <Head :title="$t('super_admin.dashboard.distributors.proposals.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <AdminBackLink
                        :href="route('admin.distributors.index')"
                        :label="$t('super_admin.dashboard.nav.distributors')"
                    />
                    <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">
                        {{ $t('super_admin.dashboard.distributors.proposals.heading') }}
                    </h1>
                    <span
                        v-if="pendingCount > 0"
                        class="rounded-full bg-accent px-2 py-0.5 font-sans text-[12px] font-semibold text-white"
                    >
                        {{ $t('super_admin.dashboard.distributors.proposals.pending_count', { count: pendingCount }) }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-2">
            <div class="rounded-lg border border-border bg-surface">
                <!-- Filters -->
                <div class="border-b border-border px-6 py-4">
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('super_admin.dashboard.distributors.proposals.subheading') }}
                    </p>
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
                                {{ $t(`super_admin.dashboard.distributors.proposals.${opt.labelKey}`) }}
                            </option>
                        </select>

                        <input
                            v-model="brandFilter"
                            type="search"
                            :placeholder="$t('super_admin.dashboard.distributors.proposals.filter_brand_placeholder')"
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
                                {{ $t(`super_admin.dashboard.distributors.proposals.${opt.labelKey}`) }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr class="border-b border-border text-left text-ink-secondary">
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_upc') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_name') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_brand') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_count') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_confidence') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_status') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_distributors') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_evidence') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ $t('super_admin.dashboard.distributors.proposals.col_actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="proposals.data.length === 0">
                                <td
                                    colspan="9"
                                    class="px-6 py-10 text-center text-ink-tertiary"
                                >
                                    {{ $t('super_admin.dashboard.distributors.proposals.empty') }}
                                </td>
                            </tr>

                            <template v-for="item in proposals.data" :key="item.id">
                                <!-- Main row -->
                                <tr
                                    class="align-top text-ink-primary"
                                    :class="{ 'opacity-60': item.status === 'rejected' }"
                                >
                                    <!-- UPC -->
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-[12px] text-ink-secondary">
                                        <Link
                                            v-if="item.resulting_sku_id"
                                            :href="route('admin.catalog.skus.show', item.resulting_sku_id)"
                                            class="text-accent hover:underline"
                                        >
                                            {{ item.upc ?? item.normalized_sku ?? '—' }}
                                        </Link>
                                        <span v-else>{{ item.upc ?? item.normalized_sku ?? '—' }}</span>
                                        <span
                                            v-if="item.resulting_sku_name"
                                            class="block text-[11px] text-ink-tertiary"
                                        >
                                            {{ item.resulting_sku_name }}
                                        </span>
                                    </td>

                                    <!-- Proposed name -->
                                    <td class="px-4 py-3 text-ink-primary">
                                        {{ item.proposed_name ?? '—' }}
                                    </td>

                                    <!-- Mapping (manual edit, else matcher guess) -->
                                    <td class="px-4 py-3 align-top">
                                        <div class="space-y-1">
                                            <div
                                                v-for="attr in ['brand', 'balloon_size', 'color']"
                                                :key="attr"
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 flex-shrink-0 rounded-full"
                                                    :class="dotClass(mappedSource(item, attr))"
                                                    :title="mappedSource(item, attr)"
                                                />
                                                <span
                                                    class="text-[12px]"
                                                    :class="mappedName(item, attr) ? 'text-ink-primary' : 'text-ink-tertiary'"
                                                >
                                                    {{ mappedName(item, attr) ?? '—' }}
                                                </span>
                                                <button
                                                    v-if="altCount(item, attr) > 0"
                                                    type="button"
                                                    class="text-[10px] text-accent hover:underline"
                                                    :title="$t('super_admin.dashboard.distributors.proposals.alt_candidates')"
                                                    @click="openEdit(item)"
                                                >
                                                    +{{ altCount(item, attr) }}
                                                </button>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Count -->
                                    <td class="whitespace-nowrap px-4 py-3 text-ink-secondary">
                                        {{ displayCount(item) ?? '—' }}
                                    </td>

                                    <!-- Confidence badge -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            v-if="item.confidence"
                                            class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                            :class="confidenceClass(item.confidence)"
                                        >
                                            {{ $t(`super_admin.dashboard.distributors.proposals.confidence_${item.confidence}`) }}
                                        </span>
                                        <span v-else class="text-ink-tertiary">—</span>
                                    </td>

                                    <!-- Status badge -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                            :class="statusClass(item.status)"
                                        >
                                            {{ $t(`super_admin.dashboard.distributors.proposals.status_${item.status}`) }}
                                        </span>
                                    </td>

                                    <!-- Distributor count -->
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-ink-secondary">
                                        {{ item.distributor_count }}
                                    </td>

                                    <!-- Evidence toggle -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <button
                                            v-if="item.evidence?.length"
                                            type="button"
                                            class="rounded-md border border-border-strong px-2 py-1 font-sans text-[12px] text-ink-secondary transition hover:bg-background"
                                            @click="toggleEvidence(item.id)"
                                        >
                                            {{
                                                expandedId === item.id
                                                    ? $t('super_admin.dashboard.distributors.proposals.evidence_toggle_hide')
                                                    : $t('super_admin.dashboard.distributors.proposals.evidence_toggle_show')
                                            }}
                                            ({{ item.evidence.length }})
                                        </button>
                                    </td>

                                    <!-- Actions -->
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div v-if="isActionable(item.status)" class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md bg-accent px-3 py-1.5 font-sans text-[12px] font-semibold text-white transition hover:opacity-90"
                                                @click="approve(item)"
                                            >
                                                {{ $t('super_admin.dashboard.distributors.proposals.action_approve') }}
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[12px] text-ink-secondary transition hover:bg-background"
                                                @click="openEdit(item)"
                                            >
                                                {{ $t('super_admin.dashboard.distributors.proposals.action_edit') }}
                                            </button>
                                            <button
                                                v-if="canReject(item.status)"
                                                type="button"
                                                class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[12px] text-danger transition hover:bg-background"
                                                @click="reject(item)"
                                            >
                                                {{ $t('super_admin.dashboard.distributors.proposals.action_reject') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Evidence slide-down row -->
                                <tr v-if="expandedId === item.id" class="bg-background/60">
                                    <td colspan="9" class="px-6 py-4">
                                        <div class="overflow-x-auto rounded-md border border-border">
                                            <table class="min-w-full divide-y divide-border text-[12px]">
                                                <thead class="bg-surface">
                                                    <tr class="text-left text-ink-tertiary">
                                                        <th class="px-3 py-2 font-medium">
                                                            {{ $t('super_admin.dashboard.distributors.proposals.evidence_col_distributor') }}
                                                        </th>
                                                        <th class="px-3 py-2 font-medium">
                                                            {{ $t('super_admin.dashboard.distributors.proposals.evidence_col_raw_sku') }}
                                                        </th>
                                                        <th class="px-3 py-2 font-medium">
                                                            {{ $t('super_admin.dashboard.distributors.proposals.evidence_col_title') }}
                                                        </th>
                                                        <th class="px-3 py-2 font-medium">
                                                            {{ $t('super_admin.dashboard.distributors.proposals.evidence_col_price') }}
                                                        </th>
                                                        <th class="px-3 py-2 font-medium">
                                                            {{ $t('super_admin.dashboard.distributors.proposals.evidence_col_stock') }}
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-border">
                                                    <tr
                                                        v-for="(ev, ei) in item.evidence"
                                                        :key="ei"
                                                        class="text-ink-secondary"
                                                    >
                                                        <td class="whitespace-nowrap px-3 py-2 font-medium text-ink-primary">
                                                            {{ ev.distributor_name ?? ev.distributor_id ?? '—' }}
                                                        </td>
                                                        <td class="whitespace-nowrap px-3 py-2 font-mono text-[11px]">
                                                            {{ ev.raw_sku ?? '—' }}
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <a
                                                                v-if="ev.url"
                                                                :href="ev.url"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="text-accent hover:underline"
                                                            >{{ ev.title ?? ev.url }}</a>
                                                            <span v-else>{{ ev.title ?? '—' }}</span>
                                                            <span
                                                                v-if="ev.inherited_upc"
                                                                class="ml-1 inline-flex rounded-full bg-warning-soft px-1.5 py-0.5 text-[10px] font-semibold text-warning"
                                                            >
                                                                {{ $t('super_admin.dashboard.distributors.proposals.evidence_inherited_upc') }}
                                                            </span>
                                                        </td>
                                                        <td class="whitespace-nowrap px-3 py-2">
                                                            {{ formatPrice(ev.price) }}
                                                        </td>
                                                        <td class="whitespace-nowrap px-3 py-2">
                                                            <span v-if="ev.stock != null">{{ ev.stock }}</span>
                                                            <span
                                                                v-else-if="ev.in_stock != null"
                                                                class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                                :class="ev.in_stock ? 'bg-success-soft text-success' : 'bg-background text-ink-tertiary'"
                                                            >
                                                                {{
                                                                    ev.in_stock
                                                                        ? $t('super_admin.dashboard.distributors.proposals.evidence_in_stock')
                                                                        : $t('super_admin.dashboard.distributors.proposals.evidence_out_of_stock')
                                                                }}
                                                            </span>
                                                            <span v-else>—</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
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

        <!-- Edit modal -->
        <Teleport to="body">
            <template v-if="editingProposal">
                <div
                    class="fixed inset-0 z-40 bg-black/40"
                    @click="closeEdit"
                />
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div
                        class="w-full max-w-md rounded-lg border border-border bg-surface shadow-xl"
                        @click.stop
                    >
                        <div class="border-b border-border px-6 py-4">
                            <h2 class="font-display text-[16px] font-semibold text-ink-primary">
                                {{ $t('super_admin.dashboard.distributors.proposals.edit_modal_title') }}
                            </h2>
                            <p class="mt-0.5 font-sans text-[13px] text-ink-tertiary">
                                {{ editingProposal.proposed_name }}
                            </p>
                        </div>

                        <div class="space-y-4 px-6 py-5">
                            <!-- Brand -->
                            <div>
                                <label class="block font-sans text-[13px] font-medium text-ink-primary">
                                    {{ $t('super_admin.dashboard.distributors.proposals.edit_brand') }}
                                </label>
                                <select
                                    v-model="editForm.proposed_brand_id"
                                    class="mt-1 block w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                >
                                    <option :value="null">
                                        {{ $t('super_admin.dashboard.distributors.proposals.edit_brand_placeholder') }}
                                    </option>
                                    <option
                                        v-for="brand in references.brands"
                                        :key="brand.id"
                                        :value="brand.id"
                                    >
                                        {{ brand.name }}
                                    </option>
                                </select>
                                <div
                                    v-if="editingProposal.guess?.brand?.candidates?.length"
                                    class="mt-1.5 flex flex-wrap items-center gap-1"
                                >
                                    <span class="text-[11px] text-ink-tertiary">
                                        {{ $t('super_admin.dashboard.distributors.proposals.suggested') }}
                                    </span>
                                    <button
                                        v-for="c in editingProposal.guess.brand.candidates"
                                        :key="c.id"
                                        type="button"
                                        class="rounded-full border px-2 py-0.5 text-[11px] transition"
                                        :class="editForm.proposed_brand_id === c.id ? 'border-accent bg-accent-soft text-accent' : 'border-border-strong text-ink-secondary hover:bg-background'"
                                        @click="editForm.proposed_brand_id = c.id"
                                    >
                                        {{ c.name }}
                                    </button>
                                </div>
                            </div>

                            <!-- Balloon size -->
                            <div>
                                <label class="block font-sans text-[13px] font-medium text-ink-primary">
                                    {{ $t('super_admin.dashboard.distributors.proposals.edit_size') }}
                                </label>
                                <select
                                    v-model="editForm.proposed_balloon_size_id"
                                    class="mt-1 block w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                >
                                    <option :value="null">
                                        {{ $t('super_admin.dashboard.distributors.proposals.edit_size_placeholder') }}
                                    </option>
                                    <option
                                        v-for="size in filteredSizes"
                                        :key="size.id"
                                        :value="size.id"
                                    >
                                        {{ size.name }}
                                    </option>
                                </select>
                                <div
                                    v-if="editingProposal.guess?.balloon_size?.candidates?.length"
                                    class="mt-1.5 flex flex-wrap items-center gap-1"
                                >
                                    <span class="text-[11px] text-ink-tertiary">
                                        {{ $t('super_admin.dashboard.distributors.proposals.suggested') }}
                                    </span>
                                    <button
                                        v-for="c in editingProposal.guess.balloon_size.candidates"
                                        :key="c.id"
                                        type="button"
                                        class="rounded-full border px-2 py-0.5 text-[11px] transition"
                                        :class="editForm.proposed_balloon_size_id === c.id ? 'border-accent bg-accent-soft text-accent' : 'border-border-strong text-ink-secondary hover:bg-background'"
                                        @click="editForm.proposed_balloon_size_id = c.id"
                                    >
                                        {{ c.name }}
                                    </button>
                                </div>
                            </div>

                            <!-- Color -->
                            <div>
                                <label class="block font-sans text-[13px] font-medium text-ink-primary">
                                    {{ $t('super_admin.dashboard.distributors.proposals.edit_color') }}
                                </label>
                                <select
                                    v-model="editForm.proposed_color_id"
                                    class="mt-1 block w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                >
                                    <option :value="null">
                                        {{ $t('super_admin.dashboard.distributors.proposals.edit_color_placeholder') }}
                                    </option>
                                    <option
                                        v-for="color in filteredColors"
                                        :key="color.id"
                                        :value="color.id"
                                    >
                                        {{ color.name }}
                                    </option>
                                </select>
                                <div
                                    v-if="editingProposal.guess?.color?.candidates?.length"
                                    class="mt-1.5 flex flex-wrap items-center gap-1"
                                >
                                    <span class="text-[11px] text-ink-tertiary">
                                        {{ $t('super_admin.dashboard.distributors.proposals.suggested') }}
                                    </span>
                                    <button
                                        v-for="c in editingProposal.guess.color.candidates"
                                        :key="c.id"
                                        type="button"
                                        class="rounded-full border px-2 py-0.5 text-[11px] transition"
                                        :class="editForm.proposed_color_id === c.id ? 'border-accent bg-accent-soft text-accent' : 'border-border-strong text-ink-secondary hover:bg-background'"
                                        @click="editForm.proposed_color_id = c.id"
                                    >
                                        {{ c.name }}
                                    </button>
                                </div>
                            </div>

                            <!-- Count + warehouse SKU -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-sans text-[13px] font-medium text-ink-primary">
                                        {{ $t('super_admin.dashboard.distributors.proposals.edit_count') }}
                                    </label>
                                    <input
                                        v-model.number="editForm.proposed_count"
                                        type="number"
                                        min="1"
                                        class="mt-1 block w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                    />
                                </div>
                                <div>
                                    <label class="block font-sans text-[13px] font-medium text-ink-primary">
                                        {{ $t('super_admin.dashboard.distributors.proposals.edit_warehouse_sku') }}
                                    </label>
                                    <input
                                        v-model="editForm.proposed_warehouse_sku"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
                            <button
                                type="button"
                                class="rounded-md border border-border-strong px-4 py-2 font-sans text-[13px] text-ink-secondary transition hover:bg-background"
                                :disabled="editProcessing"
                                @click="closeEdit"
                            >
                                {{ $t('super_admin.dashboard.distributors.proposals.action_edit_cancel') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                                :disabled="editProcessing"
                                @click="submitEdit"
                            >
                                {{
                                    editProcessing
                                        ? $t('super_admin.dashboard.distributors.proposals.action_edit_saving')
                                        : $t('super_admin.dashboard.distributors.proposals.action_edit_save')
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </Teleport>
    </AuthenticatedLayout>
</template>
