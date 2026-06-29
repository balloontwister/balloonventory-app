<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import BusinessActionMenu from '@/Components/BusinessActionMenu.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    business: { type: Object, required: true },
    members: { type: Array, required: true },
    members_count: { type: Number, required: true },
    pending_invitations: { type: Array, required: true },
    inventory_skus_count: { type: Number, required: true },
    inventory_bags_total: { type: Number, required: true },
    locations_count: { type: Number, required: true },
    bins_count: { type: Number, required: true },
    tickets: { type: Array, required: true },
});

function statusBadgeClass(business) {
    if (business.deleted_at) return 'badge-red';
    if (business.frozen_at) return 'badge-orange';
    return 'badge-green';
}

function statusLabel(business) {
    if (business.deleted_at) return 'super_admin.businesses.detail.badge_status_deleted';
    if (business.frozen_at) return 'super_admin.businesses.detail.badge_status_frozen';
    return 'super_admin.businesses.detail.badge_status_active';
}

function roleLabel(role) {
    return `super_admin.businesses.detail.role_${role}`;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="$t('super_admin.businesses.detail.meta_title', { name: business.name })" />

        <div class="min-h-full bg-gray-50 dark:bg-gray-900">
            <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <AdminBackLink />
                    <div class="mt-4 flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            <img
                                :src="business.logo_url"
                                :alt="business.name"
                                class="h-16 w-16 rounded-lg object-cover"
                            />
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ business.name }}
                                </h1>
                                <div class="mt-2 flex items-center gap-2">
                                    <code class="rounded bg-gray-100 px-2 py-1 text-sm font-mono text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ business.slug }}
                                    </code>
                                    <span
                                        :class="[
                                            'inline-block rounded-full px-3 py-1 text-xs font-medium',
                                            statusBadgeClass(business),
                                        ]"
                                    >
                                        {{ $t(statusLabel(business)) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <BusinessActionMenu :business="business" />
                    </div>
                </div>

                <!-- Identity Card -->
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('super_admin.businesses.detail.identity_section') }}
                    </h2>
                    <dl class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.type') }}
                            </dt>
                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ business.business_type || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.col_plan') }}
                            </dt>
                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $t(`super_admin.businesses.plan_${business.plan}`) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.created_at') }}
                            </dt>
                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ new Date(business.created_at).toLocaleString() }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.onboarded_at') }}
                            </dt>
                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{
                                    business.onboarding_completed_at
                                        ? new Date(business.onboarding_completed_at).toLocaleString()
                                        : '—'
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Contact Card -->
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('super_admin.businesses.detail.contact_section') }}
                    </h2>
                    <dl class="space-y-4">
                        <div
                            v-if="business.phone"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.phone') }}
                            </dt>
                            <dd class="text-gray-900 dark:text-white">{{ business.phone }}</dd>
                        </div>
                        <div
                            v-if="business.contact_email"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.contact_email') }}
                            </dt>
                            <dd class="text-gray-900 dark:text-white">{{ business.contact_email }}</dd>
                        </div>
                        <div
                            v-if="business.address_line1"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.address_line1') }}
                            </dt>
                            <dd class="text-right text-gray-900 dark:text-white">{{ business.address_line1 }}</dd>
                        </div>
                        <div
                            v-if="business.address_line2"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.address_line2') }}
                            </dt>
                            <dd class="text-right text-gray-900 dark:text-white">{{ business.address_line2 }}</dd>
                        </div>
                        <div
                            v-if="business.city"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.city') }}
                            </dt>
                            <dd class="text-gray-900 dark:text-white">{{ business.city }}</dd>
                        </div>
                        <div
                            v-if="business.state_region"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.state_region') }}
                            </dt>
                            <dd class="text-gray-900 dark:text-white">{{ business.state_region }}</dd>
                        </div>
                        <div
                            v-if="business.postal_code"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.postal_code') }}
                            </dt>
                            <dd class="text-gray-900 dark:text-white">{{ business.postal_code }}</dd>
                        </div>
                        <div
                            v-if="business.country"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.country') }}
                            </dt>
                            <dd class="text-gray-900 dark:text-white">{{ business.country }}</dd>
                        </div>
                        <div
                            v-if="business.website_url"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.website_url') }}
                            </dt>
                            <dd class="text-blue-600 dark:text-blue-400">
                                <a :href="business.website_url" target="_blank" rel="noopener noreferrer">
                                    {{ business.website_url }}
                                </a>
                            </dd>
                        </div>
                        <div
                            v-if="business.website_url_2"
                            class="flex justify-between"
                        >
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.website_url_2') }}
                            </dt>
                            <dd class="text-blue-600 dark:text-blue-400">
                                <a :href="business.website_url_2" target="_blank" rel="noopener noreferrer">
                                    {{ business.website_url_2 }}
                                </a>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Team Card -->
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('super_admin.businesses.detail.team_section') }}
                    </h2>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        {{
                            $t(
                                `super_admin.businesses.detail.member_count${members_count === 1 ? '' : '_plural'}`,
                                { count: members_count },
                            )
                        }}
                    </p>

                    <div
                        v-if="members.length > 0"
                        class="overflow-x-auto"
                    >
                        <table class="w-full">
                            <thead class="border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $t('super_admin.businesses.detail.col_member_name') }}
                                    </th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $t('super_admin.businesses.detail.col_member_email') }}
                                    </th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $t('super_admin.businesses.detail.col_member_role') }}
                                    </th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $t('super_admin.businesses.detail.col_member_joined') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="member in members"
                                    :key="member.id"
                                >
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="route('admin.users.show', member.user_id)"
                                            class="flex items-center gap-2 text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                        >
                                            <img
                                                :src="member.avatar_url"
                                                :alt="member.name"
                                                class="h-6 w-6 rounded-full"
                                            />
                                            <span class="font-medium">{{ member.name }}</span>
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ member.email }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span
                                            :class="[
                                                'inline-block rounded-full px-2 py-1 text-xs font-medium',
                                                member.role === 'owner'
                                                    ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100'
                                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100',
                                            ]"
                                        >
                                            {{ $t(roleLabel(member.role)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ new Date(member.joined_at).toLocaleDateString() }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-else
                        class="text-center text-sm text-gray-600 dark:text-gray-400"
                    >
                        {{ $t('super_admin.businesses.detail.members_empty') }}
                    </div>

                    <!-- Pending Invitations -->
                    <div
                        v-if="pending_invitations.length > 0"
                        class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700"
                    >
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
                            {{ $t('super_admin.businesses.detail.pending_invitations') }}
                        </h3>
                        <ul class="space-y-2">
                            <li
                                v-for="invite in pending_invitations"
                                :key="invite.id"
                                class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-700"
                            >
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ invite.invited_email }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $t(roleLabel(invite.role)) }}
                                    </p>
                                </div>
                                <div class="text-right text-sm text-gray-600 dark:text-gray-400">
                                    {{
                                        $t('super_admin.businesses.detail.invite_expires', {
                                            date: new Date(invite.expires_at).toLocaleDateString(),
                                        })
                                    }}
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Inventory Card -->
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('super_admin.businesses.detail.inventory_section') }}
                    </h2>
                    <dl class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.skus_count') }}
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ inventory_skus_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.bags_count') }}
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ inventory_bags_total }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.locations_count') }}
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ locations_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.detail.bins_count') }}
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ bins_count }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Activity Card -->
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('super_admin.businesses.detail.activity_section') }}
                    </h2>

                    <div class="mb-6">
                        <h3 class="mb-3 font-medium text-gray-900 dark:text-white">
                            {{
                                $t(
                                    `super_admin.businesses.detail.tickets_count${tickets.length === 1 ? '' : 's'}`,
                                    { count: tickets.length },
                                )
                            }}
                        </h3>
                        <div
                            v-if="tickets.length > 0"
                            class="space-y-2"
                        >
                            <div
                                v-for="ticket in tickets"
                                :key="ticket.id"
                                class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                            >
                                <p class="font-medium text-gray-900 dark:text-white">{{ ticket.subject }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $t('super_admin.businesses.detail.ticket_from', { name: ticket.user_name }) }}
                                    · {{ new Date(ticket.created_at).toLocaleString() }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-else
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{ $t('super_admin.businesses.detail.tickets_empty') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.badge-green {
    @apply bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100;
}

.badge-orange {
    @apply bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-100;
}

.badge-red {
    @apply bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100;
}
</style>
