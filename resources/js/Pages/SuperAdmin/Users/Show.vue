<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, required: true },
});

// Sections to be filled in next — each becomes a real data panel later.
const sections = [
    'login_history',
    'emails',
    'feedback',
    'tickets',
    'ledger',
    'businesses',
];

function formatDateTime(val) {
    if (!val) return '—';
    return new Date(val).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="user.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-2">
                    <Link
                        :href="route('admin.users.index')"
                        class="font-sans text-[14px] text-ink-secondary transition hover:text-ink-primary"
                    >
                        {{ $t('super_admin.dashboard.nav.users') }}
                    </Link>
                    <span class="text-ink-tertiary">/</span>
                    <h1
                        class="truncate font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                    >
                        {{ user.name }}
                    </h1>
                </div>
                <AdminBackLink />
            </div>
        </template>

        <div class="mx-auto flex max-w-5xl flex-col gap-6 py-2">
            <!-- Identity summary -->
            <div class="rounded-lg border border-border bg-surface p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p
                            class="font-display text-[18px] font-semibold text-ink-primary"
                        >
                            {{ user.name }}
                        </p>
                        <p class="mt-0.5 font-sans text-[13px] text-ink-secondary">
                            {{ user.original_email ?? user.email }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="user.deleted_at"
                            class="rounded-full bg-background px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.users.status_deleted') }}
                        </span>
                        <span
                            v-else-if="user.frozen_at"
                            class="rounded-full bg-warning-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-warning"
                        >
                            {{ $t('super_admin.users.status_frozen') }}
                        </span>
                        <span
                            v-if="user.admin_level === 'super_admin'"
                            class="rounded-full bg-accent-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                        >
                            {{ $t('super_admin.users.level_super_admin') }}
                        </span>
                        <span
                            v-else-if="user.admin_level === 'site_admin'"
                            class="rounded-full bg-success-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-success"
                        >
                            {{ $t('super_admin.users.level_site_admin') }}
                        </span>
                    </div>
                </div>

                <dl
                    class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3"
                >
                    <div>
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.users.col_registered') }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-primary">
                            {{ formatDateTime(user.created_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.users.col_last_login') }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-primary">
                            {{ formatDateTime(user.last_login_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('super_admin.user_detail.email_verified') }}
                        </dt>
                        <dd class="mt-0.5 font-sans text-[13px] text-ink-primary">
                            {{ formatDateTime(user.email_verified_at) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Placeholder sections — wired up next -->
            <section
                v-for="key in sections"
                :key="key"
                class="rounded-lg border border-border bg-surface p-5"
            >
                <h2
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t(`super_admin.user_detail.sections.${key}`) }}
                </h2>
                <p
                    class="mt-3 rounded-md border border-dashed border-border px-3 py-6 text-center font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('super_admin.user_detail.coming_soon') }}
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
