<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InvitationNotice from '@/Components/Dashboard/InvitationNotice.vue';
import KpiRow from '@/Components/Dashboard/KpiRow.vue';
import LowStockCard from '@/Components/Dashboard/LowStockCard.vue';
import QuickActionsCard from '@/Components/Dashboard/QuickActionsCard.vue';
import RecentActivityCard from '@/Components/Dashboard/RecentActivityCard.vue';
import SetupNudges from '@/Components/Dashboard/SetupNudges.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    kpis: { type: Object, required: true },
    lowStock: { type: Array, required: true },
    recentActivity: { type: Array, required: true },
    nudges: { type: Object, required: true },
    pendingInvitations: { type: Array, default: () => [] },
    can: { type: Object, required: true },
});

const page = usePage();
const userName = computed(() => {
    const full = page.props.auth?.user?.name ?? '';
    return full.split(' ')[0] || full;
});

const greeting = computed(() => {
    const h = new Date().getHours();
    const period = h < 12 ? 'morning' : h < 17 ? 'afternoon' : 'evening';
    return { key: `dashboard.greeting_${period}`, name: userName.value };
});

const hasInventory = computed(() => props.kpis.distinctSkus > 0);
</script>

<template>
    <Head :title="$t('dashboard.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-ink-primary">
                {{ $t('dashboard.heading') }}
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Personalized greeting -->
            <h1 class="font-display text-2xl font-semibold text-ink-primary">
                {{ $t(greeting.key, { name: greeting.name }) }}
            </h1>

            <!-- Pending invitations (top priority) -->
            <div v-if="pendingInvitations.length" class="flex flex-col gap-3">
                <InvitationNotice
                    v-for="invitation in pendingInvitations"
                    :key="invitation.token"
                    :invitation="invitation"
                />
            </div>

            <!-- Setup nudges -->
            <SetupNudges :nudges="nudges" :can="can" />

            <!-- Empty state: brand-new account with no inventory -->
            <div
                v-if="!hasInventory"
                class="rounded-lg border border-border bg-surface p-8 text-center"
            >
                <div
                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft text-accent"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-6 w-6"
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
                </div>
                <h2 class="mb-1 font-sans text-[16px] font-semibold text-ink-primary">
                    {{ $t('dashboard.empty_state.title') }}
                </h2>
                <p class="mb-5 font-sans text-[13px] text-ink-secondary">
                    {{ $t('dashboard.empty_state.body') }}
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <Link
                        v-if="can.addInventory"
                        :href="route('inventory.index')"
                        class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-white transition hover:bg-accent-hover"
                    >
                        {{ $t('dashboard.empty_state.add') }}
                    </Link>
                    <Link
                        v-if="can.checkIn"
                        :href="route('scan.index')"
                        class="rounded-md border border-border bg-background px-4 py-2 font-sans text-[14px] font-semibold text-ink-primary transition hover:border-border-strong"
                    >
                        {{ $t('dashboard.empty_state.scan') }}
                    </Link>
                </div>
            </div>

            <!-- Main dashboard grid -->
            <template v-else>
                <!-- Reserved: Upcoming jobs (uncomment once Jobs ships)
                <div v-if="can.jobView">
                    <UpcomingJobsCard />
                </div>
                -->

                <!-- Quick Actions + Low Stock -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <QuickActionsCard :can="can" />
                    <LowStockCard
                        v-if="can.viewCounts"
                        :items="lowStock"
                        :count="kpis.lowStockCount"
                        class="lg:col-span-2"
                    />
                </div>

                <!-- KPI row -->
                <KpiRow v-if="can.viewCounts" :kpis="kpis" />

                <!-- Recent activity -->
                <RecentActivityCard
                    v-if="can.viewCounts"
                    :activities="recentActivity"
                />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
