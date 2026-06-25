<script setup>
import AdminBackLink from '@/Components/AdminBackLink.vue';
import AppButton from '@/Components/AppButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    distributors: { type: Array, required: true },
});

const deleteForm = useForm({});

function confirmDelete(distributor) {
    if (!confirm(`Delete ${distributor.name}?`)) return;
    deleteForm.delete(route('admin.distributors.destroy', distributor.id));
}

function platformLabel(type) {
    return type === 'shopify' ? 'Shopify' : type === 'bigcommerce' ? 'BigCommerce' : type;
}

function healthClass(status) {
    return {
        healthy: 'bg-success-soft text-success',
        degraded: 'bg-warning-soft text-warning',
        broken: 'bg-danger-soft text-danger',
    }[status] ?? 'bg-background text-ink-tertiary';
}
</script>

<template>
    <Head title="Distributors" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <AdminBackLink :href="route('admin.dashboard')" :label="$t('super_admin.dashboard.back')" />
                    <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">
                        Distributors
                    </h1>
                </div>
                <div class="flex items-center gap-2">
                    <AppButton :href="route('admin.distributors.proposals.index')" variant="ghost" size="sm">
                        {{ $t('super_admin.dashboard.distributors.proposals.review_proposals_link') }}
                    </AppButton>
                    <AppButton :href="route('admin.distributors.create')" variant="primary" size="sm">
                        + Add Distributor
                    </AppButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="overflow-x-auto rounded-lg border border-border bg-surface shadow-pop">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-surface-secondary">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Name
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Platform
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Base URL
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Matched SKUs
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Active
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Health
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Last Synced
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-ink-secondary">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="d in distributors" :key="d.id" class="hover:bg-surface-hover">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-ink-primary">
                                <Link :href="route('admin.distributors.show', d.id)" class="text-accent hover:underline">
                                    {{ d.name }}
                                </Link>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="d.platform_type === 'shopify' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'"
                                >
                                    {{ platformLabel(d.platform_type) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-ink-secondary">
                                {{ d.base_url }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-ink-secondary">
                                {{ d.sku_urls_count ?? 0 }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <span
                                    class="inline-flex h-2 w-2 rounded-full"
                                    :class="d.is_active ? 'bg-green-500' : 'bg-gray-300'"
                                />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    v-if="d.health_status"
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                                    :class="healthClass(d.health_status)"
                                    :title="d.health_detail"
                                >
                                    {{ d.health_status }}
                                </span>
                                <span v-else class="text-sm text-ink-tertiary">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-ink-secondary">
                                {{ d.last_synced_at ? new Date(d.last_synced_at).toLocaleDateString() : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                <AppButton
                                    :href="route('admin.distributors.edit', d.id)"
                                    variant="ghost"
                                    size="xs"
                                    class="mr-1"
                                >
                                    Edit
                                </AppButton>
                                <AppButton
                                    variant="ghost"
                                    size="xs"
                                    class="text-red-600"
                                    @click="confirmDelete(d)"
                                >
                                    Delete
                                </AppButton>
                            </td>
                        </tr>
                        <tr v-if="distributors.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-ink-tertiary">
                                No distributors yet.
                                <Link :href="route('admin.distributors.create')" class="text-accent hover:underline">
                                    Add one
                                </Link>.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
