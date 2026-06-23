<script setup>
import AppButton from '@/Components/AppButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    skus: { type: Array, required: true },
    distributors: { type: Array, required: true },
    hasFavorites: { type: Boolean, default: false },
});

function openUrl(url) {
    window.open(url, '_blank');
}
</script>

<template>
    <Head :title="$t('reorder.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-[22px] font-semibold text-ink-primary">
                {{ $t('reorder.heading') }}
            </h1>
        </template>

        <!-- Empty: no favorites -->
        <div v-if="!hasFavorites" class="flex items-center justify-center py-24">
            <div class="text-center space-y-4">
                <p class="font-sans text-[15px] text-ink-tertiary">
                    Star items as favorites and set a reorder quantity to see them here.
                </p>
                <AppButton :href="route('inventory.index')" variant="secondary">
                    Go to Inventory
                </AppButton>
            </div>
        </div>

        <!-- Empty: no distributors configured -->
        <div v-else-if="distributors.length === 0" class="mx-auto max-w-4xl px-4 py-12 text-center">
            <p class="font-sans text-[15px] text-ink-tertiary mb-4">
                You haven't selected any preferred distributors yet.
            </p>
            <AppButton :href="route('settings.businesses')" variant="primary">
                Pick your preferred distributors
            </AppButton>
        </div>

        <!-- Reorder table -->
        <div v-else class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="overflow-x-auto rounded-lg border border-border bg-surface shadow-pop">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-surface-secondary">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Product
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                On Hand
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Reorder At
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Needed
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Buy From
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="sku in skus"
                            :key="sku.id"
                            :class="sku.needed > 0 ? 'bg-amber-50' : ''"
                        >
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-ink-primary">
                                    {{ sku.computed_name || sku.name }}
                                </div>
                                <div class="text-xs text-ink-tertiary font-mono">
                                    {{ sku.warehouse_sku || sku.upc || sku.ean || '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-ink-secondary">
                                {{ sku.on_hand }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-ink-secondary">
                                {{ sku.planned_quantity }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="sku.needed > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'"
                                >
                                    {{ sku.needed > 0 ? sku.needed : '✓' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="link in sku.distributor_urls"
                                        :key="link.distributor.slug"
                                        @click="openUrl(link.url)"
                                        class="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-1 text-xs font-medium text-ink-primary hover:bg-surface-hover transition"
                                        :title="link.price ? '$' + parseFloat(link.price).toFixed(2) + (link.currency || ' USD') : ''"
                                    >
                                        {{ link.distributor.name }}
                                        <span v-if="link.in_stock === true" class="h-1.5 w-1.5 rounded-full bg-green-500" />
                                        <span v-else-if="link.in_stock === false" class="h-1.5 w-1.5 rounded-full bg-red-500" />
                                    </button>
                                    <span
                                        v-if="sku.distributor_urls.length === 0"
                                        class="text-xs text-ink-tertiary"
                                    >
                                        No links yet
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
