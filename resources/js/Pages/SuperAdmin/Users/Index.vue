<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: { type: Array, required: true },
});

const page = usePage();
const isSuperAdmin = page.props.auth?.isSuperAdmin ?? false;

const confirmingDemote = ref(null);

function formatDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(val) {
    if (!val) return '—';
    return new Date(val).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function promote(user) {
    router.post(
        route('super-admin.users.promote', user.id),
        {},
        { preserveScroll: true },
    );
}

function confirmDemote(userId) {
    confirmingDemote.value = userId;
}

function cancelDemote() {
    confirmingDemote.value = null;
}

function demote(user) {
    router.delete(
        route('super-admin.users.demote', user.id),
        { preserveScroll: true },
    );
    confirmingDemote.value = null;
}

function adminLevelLabel(level) {
    if (level === 'super_admin') return 'Super Admin';
    if (level === 'site_admin') return 'Site Admin';
    return null;
}
</script>

<template>
    <Head :title="$t('super_admin.users.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.users.heading') }}
                </h1>
                <Link
                    :href="route('super-admin.dashboard')"
                    class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
                >
                    {{ $t('super_admin.users.back') }}
                </Link>
            </div>
        </template>

        <div class="py-2">
            <div class="rounded-lg border border-border bg-surface">
                <!-- Legend / description -->
                <div class="border-b border-border px-6 py-4">
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('super_admin.users.description') }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-ink-secondary"
                            >
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.users.col_name') }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.users.col_email') }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.users.col_admin_level') }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.users.col_registered') }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{ $t('super_admin.users.col_last_login') }}
                                </th>
                                <th
                                    v-if="isSuperAdmin"
                                    class="px-6 py-3 font-medium"
                                >
                                    {{ $t('super_admin.users.col_actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in users"
                                :key="user.id"
                                class="border-b border-border/50 last:border-0"
                                :class="{ 'opacity-50': user.deleted_at }"
                            >
                                <td class="px-6 py-3 text-ink-primary">
                                    <span
                                        :class="{
                                            'line-through': user.deleted_at,
                                        }"
                                    >
                                        {{ user.name }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-ink-secondary">
                                    <span
                                        :class="{
                                            'line-through': user.deleted_at,
                                        }"
                                    >
                                        {{ user.original_email ?? user.email }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <span
                                        v-if="user.admin_level === 'super_admin'"
                                        class="inline-flex rounded-full bg-accent-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                                    >
                                        {{
                                            adminLevelLabel(user.admin_level)
                                        }}
                                    </span>
                                    <span
                                        v-else-if="
                                            user.admin_level === 'site_admin'
                                        "
                                        class="inline-flex rounded-full bg-success-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-success"
                                    >
                                        {{
                                            adminLevelLabel(user.admin_level)
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-ink-tertiary"
                                    >
                                        {{
                                            $t('super_admin.users.level_none')
                                        }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-ink-secondary">
                                    {{ formatDate(user.created_at) }}
                                </td>
                                <td class="px-6 py-3 text-ink-secondary">
                                    {{ formatDateTime(user.last_login_at) }}
                                </td>

                                <!-- Actions (Super Admin only) -->
                                <td
                                    v-if="isSuperAdmin"
                                    class="px-6 py-3"
                                >
                                    <!-- Super Admin: no action -->
                                    <span
                                        v-if="user.admin_level === 'super_admin'"
                                        class="text-ink-tertiary"
                                    >
                                        —
                                    </span>

                                    <!-- Site Admin: demote confirm -->
                                    <template
                                        v-else-if="
                                            user.admin_level === 'site_admin'
                                        "
                                    >
                                        <div
                                            v-if="confirmingDemote === user.id"
                                            class="flex items-center gap-2"
                                        >
                                            <span
                                                class="font-sans text-[12px] text-ink-secondary"
                                            >
                                                {{
                                                    $t(
                                                        'super_admin.users.demote_confirm',
                                                    )
                                                }}
                                            </span>
                                            <button
                                                type="button"
                                                class="rounded-md bg-danger px-3 py-1 font-sans text-[12px] font-semibold text-white transition hover:bg-red-700"
                                                @click="demote(user)"
                                            >
                                                {{
                                                    $t(
                                                        'super_admin.users.demote_yes',
                                                    )
                                                }}
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md border border-border-strong px-3 py-1 font-sans text-[12px] font-medium text-ink-secondary transition hover:bg-background"
                                                @click="cancelDemote"
                                            >
                                                {{
                                                    $t(
                                                        'super_admin.users.demote_cancel',
                                                    )
                                                }}
                                            </button>
                                        </div>
                                        <button
                                            v-else
                                            type="button"
                                            class="rounded-md border border-border-strong px-3 py-1 font-sans text-[12px] font-medium text-danger transition hover:bg-danger-soft"
                                            @click="confirmDemote(user.id)"
                                        >
                                            {{
                                                $t(
                                                    'super_admin.users.demote_button',
                                                )
                                            }}
                                        </button>
                                    </template>

                                    <!-- Regular user: promote -->
                                    <button
                                        v-else-if="!user.deleted_at"
                                        type="button"
                                        class="rounded-md bg-accent px-3 py-1 font-sans text-[12px] font-semibold text-accent-on transition hover:bg-accent-hover"
                                        @click="promote(user)"
                                    >
                                        {{
                                            $t(
                                                'super_admin.users.promote_button',
                                            )
                                        }}
                                    </button>

                                    <!-- Deleted user: no action -->
                                    <span v-else class="text-ink-tertiary">
                                        —
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
