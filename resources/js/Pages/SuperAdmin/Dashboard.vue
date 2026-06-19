<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminCard from '@/Components/AdminCard.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    summary: { type: Object, required: true },
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.isSuperAdmin ?? false);

const s = props.summary;

const usersLines = computed(() => {
    const lines = [
        trans('super_admin.dashboard.cards.users_new', {
            count: s.users.new_7d,
        }),
    ];
    if (s.users.frozen > 0) {
        lines.push(
            trans('super_admin.dashboard.cards.users_frozen', {
                count: s.users.frozen,
            }),
        );
    }
    return lines;
});

const barcodeLines = computed(() => [
    trans('super_admin.dashboard.cards.barcode_recent', {
        count: s.barcode.recent,
    }),
]);

const emailLines = computed(() => [
    trans('super_admin.dashboard.cards.email_today', { count: s.email.today }),
]);
</script>

<template>
    <Head :title="$t('super_admin.dashboard.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('super_admin.dashboard.heading') }}
            </h1>
        </template>

        <div class="py-2">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <!-- Users -->
                <AdminCard
                    :title="$t('super_admin.dashboard.cards.users_title')"
                    :href="route('admin.users.index')"
                    :stat="s.users.total"
                    :stat-label="$t('super_admin.dashboard.cards.users_label')"
                    :lines="usersLines"
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 17a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z" />
                        </svg>
                    </template>
                </AdminCard>

                <!-- Catalog -->
                <AdminCard
                    :title="$t('super_admin.dashboard.cards.catalog_title')"
                    :href="route('admin.catalog.skus')"
                    :stat="s.catalog.skus"
                    :stat-label="$t('super_admin.dashboard.cards.catalog_label')"
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M10.362 1.093a.75.75 0 00-.724 0L2.523 5.018 10 9.143l7.477-4.125-7.115-3.925zM18 6.443l-7.25 4v8.25l6.862-3.786A.75.75 0 0018 14.25V6.443zM9.25 18.693v-8.25l-7.25-4v7.807a.75.75 0 00.388.657l6.862 3.786z" />
                        </svg>
                    </template>
                </AdminCard>

                <!-- Item feedback -->
                <AdminCard
                    :title="$t('super_admin.dashboard.cards.feedback_title')"
                    :href="route('admin.feedback.index')"
                    :stat="s.feedback.open"
                    :stat-label="$t('super_admin.dashboard.cards.open_label')"
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path fill-rule="evenodd" d="M18 5v8a2 2 0 0 1-2 2h-5l-5 4v-4H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2ZM7 8H5v2h2V8Zm2 0h2v2H9V8Zm6 0h-2v2h2V8Z" clip-rule="evenodd" />
                        </svg>
                    </template>
                </AdminCard>

                <!-- Support tickets -->
                <AdminCard
                    :title="$t('super_admin.dashboard.cards.tickets_title')"
                    :href="route('admin.tickets.index')"
                    :stat="s.tickets.open"
                    :stat-label="$t('super_admin.dashboard.cards.open_label')"
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path fill-rule="evenodd" d="M10 2c-4.418 0-8 3.134-8 7 0 1.76.743 3.37 1.97 4.6a6.6 6.6 0 01-1.07 2.34.75.75 0 00.72 1.18 8.7 8.7 0 003.3-1.06A9.6 9.6 0 0010 16c4.418 0 8-3.134 8-7s-3.582-7-8-7z" clip-rule="evenodd" />
                        </svg>
                    </template>
                </AdminCard>

                <!-- Barcode log -->
                <AdminCard
                    :title="$t('super_admin.dashboard.cards.barcode_title')"
                    :href="route('admin.barcode-audits.index')"
                    :stat="s.barcode.total"
                    :stat-label="$t('super_admin.dashboard.cards.barcode_label')"
                    :lines="barcodeLines"
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M3 4.75A.75.75 0 0 1 3.75 4h.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-.5a.75.75 0 0 1-.75-.75V4.75ZM6 4.75A.75.75 0 0 1 6.75 4h.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-.5A.75.75 0 0 1 6 15.25V4.75ZM9.5 4a.75.75 0 0 0-.75.75v10.5c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75V4.75A.75.75 0 0 0 10 4h-.5ZM12.5 4.75a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75ZM16.5 4a.75.75 0 0 0-.75.75v10.5c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75V4.75A.75.75 0 0 0 17 4h-.5Z" />
                        </svg>
                    </template>
                </AdminCard>

                <!-- Email -->
                <AdminCard
                    :title="$t('super_admin.dashboard.cards.email_title')"
                    :href="route('admin.email-templates.index')"
                    :stat="s.email.sent_30d"
                    :stat-label="$t('super_admin.dashboard.cards.email_label')"
                    :lines="emailLines"
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                            <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                        </svg>
                    </template>
                </AdminCard>

                <!-- Backups (Super Admin only) -->
                <AdminCard
                    v-if="isSuperAdmin"
                    :title="$t('super_admin.dashboard.cards.backups_title')"
                    :href="route('admin.backups.index')"
                    :lines="[$t('super_admin.dashboard.cards.backups_desc')]"
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M10 1c-3.866 0-7 1.343-7 3s3.134 3 7 3 7-1.343 7-3-3.134-3-7-3z" />
                            <path d="M3 6.519C3 8.176 6.134 9.519 10 9.519s7-1.343 7-3V10c0 1.657-3.134 3-7 3s-7-1.343-7-3V6.519z" />
                            <path d="M3 11.519C3 13.176 6.134 14.519 10 14.519s7-1.343 7-3V15c0 1.657-3.134 3-7 3s-7-1.343-7-3v-3.481z" />
                        </svg>
                    </template>
                </AdminCard>

                <!-- Future-growth stubs (Super Admin only) -->
                <AdminCard
                    v-if="isSuperAdmin"
                    :title="$t('super_admin.dashboard.nav.subscriptions')"
                    :href="route('admin.subscriptions.index')"
                    soon
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v.316a3.78 3.78 0 00-1.653.713c-.426.33-.744.74-.925 1.2a2.6 2.6 0 000 1.962c.18.46.499.87.925 1.2.42.326.94.55 1.653.713V12.5a2.2 2.2 0 01-.5-.105.75.75 0 10-.45 1.43c.305.096.625.155.95.175v.316a.75.75 0 001.5 0v-.316c.66-.084 1.22-.323 1.653-.713.426-.33.744-.74.925-1.2a2.6 2.6 0 000-1.962c-.18-.46-.499-.87-.925-1.2-.42-.326-.94-.55-1.653-.713V7.5c.176.027.343.063.5.105a.75.75 0 10.45-1.43 4.3 4.3 0 00-.95-.175V5z" clip-rule="evenodd" />
                        </svg>
                    </template>
                    <template #soon-text>
                        {{ $t('super_admin.coming_soon.subscriptions') }}
                    </template>
                </AdminCard>

                <AdminCard
                    v-if="isSuperAdmin"
                    :title="$t('super_admin.dashboard.nav.payments')"
                    :href="route('admin.payments.index')"
                    soon
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M1 4.25C1 3.56 1.56 3 2.25 3h15.5c.69 0 1.25.56 1.25 1.25v.5H1v-.5z" />
                            <path fill-rule="evenodd" d="M1 6.5v9.25C1 16.44 1.56 17 2.25 17h15.5c.69 0 1.25-.56 1.25-1.25V6.5H1zm3 6.5a.75.75 0 01.75-.75h3a.75.75 0 010 1.5h-3A.75.75 0 014 13z" clip-rule="evenodd" />
                        </svg>
                    </template>
                    <template #soon-text>
                        {{ $t('super_admin.coming_soon.payments') }}
                    </template>
                </AdminCard>

                <AdminCard
                    v-if="isSuperAdmin"
                    :title="$t('super_admin.dashboard.nav.affiliates')"
                    :href="route('admin.affiliates.index')"
                    soon
                >
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M11 5a3 3 0 11-6 0 3 3 0 016 0zM2.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 018 18a9.953 9.953 0 01-5.385-1.572zM16.25 5.75a.75.75 0 00-1.5 0v2h-2a.75.75 0 000 1.5h2v2a.75.75 0 001.5 0v-2h2a.75.75 0 000-1.5h-2v-2z" />
                        </svg>
                    </template>
                    <template #soon-text>
                        {{ $t('super_admin.coming_soon.affiliates') }}
                    </template>
                </AdminCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
