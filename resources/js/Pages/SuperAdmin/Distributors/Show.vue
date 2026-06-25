<script setup>
import AdminBackLink from '@/Components/AdminBackLink.vue';
import AppButton from '@/Components/AppButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    distributor: { type: Object, required: true },
    stagedTotal: { type: Number, default: 0 },
    stagedWithUpc: { type: Number, default: 0 },
    probe: { type: Object, default: null },
});

const syncing = ref(false);

// ── Probe (test-fetch one URL, see how it maps — no DB writes) ─────────────────
const probeUrl = ref('');
const probing = ref(false);

function runProbe() {
    if (!probeUrl.value) return;
    probing.value = true;
    router.post(
        route('admin.distributors.probe', props.distributor.id),
        { probe_url: probeUrl.value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { probing.value = false; },
        },
    );
}

function qualityDot(quality) {
    return {
        exact: 'bg-success',
        fuzzy: 'bg-warning',
    }[quality] ?? 'bg-border-strong';
}

function platformLabel(type) {
    return type === 'shopify' ? 'Shopify' : type === 'bigcommerce' ? 'BigCommerce' : type;
}

function syncLabel(type) {
    return type === 'shopify' ? 'Sync now' : 'Crawl more';
}

function formatDate(date) {
    if (!date) return 'Never';
    return new Date(date).toLocaleString();
}

function sync() {
    syncing.value = true;
    router.post(
        route('admin.distributors.sync', props.distributor.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => { syncing.value = false; },
        },
    );
}
</script>

<template>
    <Head :title="distributor.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <AdminBackLink :href="route('admin.distributors.index')" label="Distributors" />
                    <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">
                        {{ distributor.name }}
                    </h1>
                </div>
                <div class="flex items-center gap-2">
                    <AppButton
                        variant="secondary"
                        size="sm"
                        :disabled="syncing"
                        @click="sync"
                    >
                        {{ syncing ? 'Starting…' : syncLabel(distributor.platform_type) }}
                    </AppButton>
                    <AppButton :href="route('admin.distributors.edit', distributor.id)" variant="ghost" size="sm">
                        Edit
                    </AppButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <!-- Info card -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="text-lg font-semibold text-ink-primary mb-4">Details</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-ink-tertiary">Platform</dt>
                        <dd class="text-ink-primary font-medium">{{ platformLabel(distributor.platform_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-tertiary">Base URL</dt>
                        <dd class="text-ink-primary font-medium">{{ distributor.base_url }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-tertiary">Slug</dt>
                        <dd class="text-ink-primary font-medium">{{ distributor.slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-tertiary">Active</dt>
                        <dd class="text-ink-primary font-medium">{{ distributor.is_active ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-tertiary">Matched SKUs</dt>
                        <dd class="text-ink-primary font-medium">{{ distributor.sku_urls_count ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-tertiary">Catalog Gaps</dt>
                        <dd class="text-ink-primary font-medium">{{ distributor.catalog_gaps_count ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-tertiary">Last Synced</dt>
                        <dd class="text-ink-primary font-medium">{{ formatDate(distributor.last_synced_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-tertiary">Sitemap URL</dt>
                        <dd class="text-ink-primary font-medium">{{ distributor.sitemap_url || 'Auto-detected' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-tertiary">Staged products</dt>
                        <dd class="text-ink-primary font-medium">
                            {{ stagedTotal.toLocaleString() }}
                            <span class="text-ink-tertiary font-normal">
                                ({{ stagedWithUpc.toLocaleString() }} with barcodes)
                            </span>
                        </dd>
                    </div>
                </dl>
                <div v-if="distributor.description" class="mt-4">
                    <dt class="text-sm text-ink-tertiary">Description</dt>
                    <dd class="text-sm text-ink-primary mt-1">{{ distributor.description }}</dd>
                </div>
                <div class="mt-4 pt-4 border-t border-border">
                    <Link
                        :href="route('admin.distributors.proposals.index')"
                        class="font-sans text-sm text-accent hover:underline"
                    >
                        {{ $t('super_admin.dashboard.distributors.proposals.review_proposals_link') }} →
                    </Link>
                </div>
            </div>

            <!-- Probe (verify-before-crawl) -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="text-lg font-semibold text-ink-primary">Test fetch (Probe)</h2>
                <p class="mt-1 text-sm text-ink-tertiary">
                    Fetch one product URL and see how this site's page maps to our catalog — nothing is saved.
                    Use it to confirm the extraction recipe before crawling.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <input
                        v-model="probeUrl"
                        type="url"
                        placeholder="https://…/a-product-page"
                        class="w-96 max-w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        @keyup.enter="runProbe"
                    />
                    <AppButton variant="secondary" size="sm" :disabled="probing || !probeUrl" @click="runProbe">
                        {{ probing ? 'Fetching…' : 'Test fetch' }}
                    </AppButton>
                </div>

                <!-- Result -->
                <div v-if="probe" class="mt-4 border-t border-border pt-4">
                    <p v-if="!probe.fetched" class="text-sm text-danger">
                        Couldn't fetch the page{{ probe.http_status ? ` (HTTP ${probe.http_status})` : '' }}{{ probe.error ? `: ${probe.error}` : '' }}.
                    </p>
                    <template v-else>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span
                                class="rounded-full px-2 py-0.5 text-[12px] font-semibold"
                                :class="probe.extraction.ok ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'"
                            >
                                {{ probe.extraction.ok ? 'Recipe matched' : 'Recipe did NOT match' }}
                            </span>
                            <span class="text-ink-tertiary">
                                {{ probe.extraction.row_count }} attribute rows · type: {{ probe.product_type }}
                            </span>
                            <span v-if="probe.extraction.missing_required.length" class="text-warning">
                                missing: {{ probe.extraction.missing_required.join(', ') }}
                            </span>
                        </div>
                        <p v-if="probe.title" class="mt-2 text-sm text-ink-primary">{{ probe.title }}</p>
                        <p class="text-[12px] text-ink-tertiary">
                            sku: {{ probe.raw_sku ?? '—' }} · upc: {{ probe.upc ?? '—' }}
                        </p>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <!-- Matched attributes -->
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary">Resolved to our catalog</p>
                                <div class="mt-1 space-y-1">
                                    <div
                                        v-for="attr in ['brand', 'balloon_size', 'color', 'packaging']"
                                        :key="attr"
                                        class="flex items-center gap-1.5 text-sm"
                                    >
                                        <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full" :class="qualityDot(probe.match[attr].quality)" />
                                        <span class="capitalize text-ink-tertiary">{{ attr.replace('_', ' ') }}:</span>
                                        <span :class="probe.match[attr].matched ? 'text-ink-primary' : 'text-ink-tertiary'">
                                            {{ probe.match[attr].matched ?? '—' }}
                                        </span>
                                        <span v-if="probe.match[attr].value" class="text-[11px] text-ink-tertiary">
                                            (“{{ probe.match[attr].value }}”)
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-sm">
                                        <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-border-strong" />
                                        <span class="text-ink-tertiary">count:</span>
                                        <span class="text-ink-primary">{{ probe.match.count ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Raw distributor table -->
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary">Distributor's attribute table</p>
                                <dl class="mt-1 space-y-0.5 text-[12px]">
                                    <div v-for="row in probe.attributes" :key="row.label" class="flex gap-2">
                                        <dt class="min-w-[120px] text-ink-tertiary">{{ row.label }}</dt>
                                        <dd class="text-ink-secondary">{{ row.value }}</dd>
                                    </div>
                                    <p v-if="!probe.attributes.length" class="text-ink-tertiary">No attribute table found on this page.</p>
                                </dl>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Catalog gaps -->
            <div v-if="distributor.catalog_gaps?.length" class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="text-lg font-semibold text-ink-primary mb-4">
                    Catalog Gaps ({{ distributor.catalog_gaps_count ?? distributor.catalog_gaps.length }})
                </h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-surface-secondary">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-ink-secondary">Product</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-ink-secondary">Identifier</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-ink-secondary">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="gap in distributor.catalog_gaps" :key="gap.id">
                                <td class="px-3 py-2">
                                    <a :href="gap.product_url" target="_blank" class="text-accent hover:underline">
                                        {{ gap.product_name }}
                                    </a>
                                </td>
                                <td class="px-3 py-2 text-ink-secondary font-mono text-xs">{{ gap.external_identifier }}</td>
                                <td class="px-3 py-2 text-ink-tertiary">{{ gap.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-else-if="(distributor.catalog_gaps_count ?? 0) > 0" class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <p class="text-sm text-ink-tertiary">{{ distributor.catalog_gaps_count }} catalog gaps. Run a sync to populate.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
