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
});

const syncing = ref(false);

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
