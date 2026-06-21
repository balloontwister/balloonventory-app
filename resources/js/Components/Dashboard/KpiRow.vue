<script setup>
import AdminCard from '@/Components/AdminCard.vue';

defineProps({
    kpis: { type: Object, required: true },
});
</script>

<template>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <!-- SKUs -->
        <AdminCard
            :title="$t('dashboard.kpis.distinct_skus')"
            :href="route('inventory.index')"
            :stat="kpis.distinctSkus"
        >
            <template #icon>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        d="M10.75 1.66a1.5 1.5 0 00-1.5 0L1.6 6.04a.75.75 0 000 1.32l7.65 4.37a1.5 1.5 0 001.5 0l7.65-4.37a.75.75 0 000-1.32l-7.65-4.37z"
                    />
                    <path
                        d="m2.69 9.21-1.09.62a.75.75 0 000 1.32l7.65 4.37a1.5 1.5 0 001.5 0l7.65-4.37a.75.75 0 000-1.32l-1.09-.62-5.81 3.32a3 3 0 01-3 0L2.69 9.21z"
                    />
                    <path
                        d="m2.69 13.21-1.09.62a.75.75 0 000 1.32l7.65 4.37a1.5 1.5 0 001.5 0l7.65-4.37a.75.75 0 000-1.32l-1.09-.62-5.81 3.32a3 3 0 01-3 0l-5.81-3.32z"
                    />
                </svg>
            </template>
        </AdminCard>

        <!-- Total Bags -->
        <AdminCard
            :title="$t('dashboard.kpis.total_bags')"
            :href="route('inventory.index')"
            :stat="kpis.totalBags"
        >
            <template #icon>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M6 5v1H4.667a1.75 1.75 0 00-1.743 1.598l-.826 9.5A1.75 1.75 0 003.84 19H16.16a1.75 1.75 0 001.743-1.902l-.826-9.5A1.75 1.75 0 0015.333 6H14V5a4 4 0 00-8 0zm4-2.5A2.5 2.5 0 007.5 5v1h5V5A2.5 2.5 0 0010 2.5zM7.5 10a2.5 2.5 0 005 0V8.75a.75.75 0 011.5 0V10a4 4 0 01-8 0V8.75a.75.75 0 011.5 0V10z"
                        clip-rule="evenodd"
                    />
                </svg>
            </template>
        </AdminCard>

        <!-- Bins -->
        <AdminCard
            :title="$t('dashboard.kpis.bins')"
            :href="route('inventory.bins.index')"
            :stat="kpis.binCount"
        >
            <template #icon>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        d="M2 3a1 1 0 00-1 1v1a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1H2z"
                    />
                    <path
                        fill-rule="evenodd"
                        d="M2 7.5h16l-.811 7.71a2 2 0 01-1.99 1.79H4.802a2 2 0 01-1.99-1.79L2 7.5zM7 11a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z"
                        clip-rule="evenodd"
                    />
                </svg>
            </template>
        </AdminCard>

        <!-- Low Stock -->
        <AdminCard
            :title="$t('dashboard.kpis.low_stock')"
            :href="route('reorder.index')"
            :stat="kpis.lowStockCount"
        >
            <template #icon>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                        clip-rule="evenodd"
                    />
                </svg>
            </template>
        </AdminCard>
    </div>
</template>
