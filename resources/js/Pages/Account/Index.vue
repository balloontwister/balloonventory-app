<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ContactSupportModal from '@/Components/ContactSupportModal.vue';

const page = usePage();

const showSupportModal = ref(false);

const user = computed(() => page.props.auth?.user ?? {});
const avatarUrl = computed(() => page.props.auth?.avatarUrl ?? null);
const business = computed(() => page.props.business ?? null);
const isAnyAdmin = computed(() => page.props.auth?.isAnyAdmin ?? false);
const isFrozen = computed(() => page.props.auth?.isFrozen ?? false);

const canManageBusiness = computed(() => {
    const perms = page.props.permissions ?? [];
    return (
        perms.includes('business.edit_settings') ||
        perms.includes('business.manage_logo')
    );
});

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head :title="$t('account.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('account.heading') }}
            </h1>
        </template>

        <ContactSupportModal
            :show="showSupportModal"
            @close="showSupportModal = false"
        />

        <div class="flex flex-col gap-4 py-2">
            <!-- ── Frozen-account banner ─────────────────────────────────── -->
            <div
                v-if="isFrozen"
                class="rounded-lg border border-warning bg-warning-soft p-4"
            >
                <p class="font-sans text-[14px] font-semibold text-ink-primary">
                    {{ $t('account.frozen_banner.title') }}
                </p>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('account.frozen_banner.body') }}
                </p>
                <button
                    type="button"
                    class="mt-2 font-sans text-[13px] font-semibold text-accent hover:underline"
                    @click="showSupportModal = true"
                >
                    {{ $t('account.rows.support.label') }}
                </button>
            </div>

            <!-- ── Identity card ─────────────────────────────────────────── -->
            <Link
                :href="route('profile.edit')"
                class="flex items-center gap-4 rounded-lg border border-border bg-surface p-4 shadow-pop transition hover:bg-background"
            >
                <img
                    v-if="avatarUrl"
                    :src="avatarUrl"
                    :alt="user.name"
                    class="h-14 w-14 flex-shrink-0 rounded-full object-cover ring-2 ring-border"
                />
                <div
                    v-else
                    class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent ring-2 ring-border"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-7 w-7"
                    >
                        <path
                            d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="truncate font-display text-[17px] font-semibold text-ink-primary"
                    >
                        {{ user.name }}
                    </p>
                    <p
                        class="truncate font-sans text-[13px] text-ink-secondary"
                    >
                        {{ user.email }}
                    </p>
                </div>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4 flex-shrink-0 text-ink-tertiary"
                >
                    <path
                        fill-rule="evenodd"
                        d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                        clip-rule="evenodd"
                    />
                </svg>
            </Link>

            <!-- ── Rows ──────────────────────────────────────────────────── -->
            <div
                class="overflow-hidden rounded-lg border border-border bg-surface shadow-pop"
            >
                <!-- Profile -->
                <Link
                    :href="route('profile.edit')"
                    class="flex items-center gap-3 px-4 py-3 transition hover:bg-background"
                >
                    <span
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-sans text-[14px] font-medium text-ink-primary"
                        >
                            {{ $t('account.rows.profile.label') }}
                        </p>
                        <p
                            class="truncate font-sans text-[12px] text-ink-tertiary"
                        >
                            {{ $t('account.rows.profile.subtext') }}
                        </p>
                    </div>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4 flex-shrink-0 text-ink-tertiary"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </Link>

                <!-- Business (gated) -->
                <Link
                    v-if="canManageBusiness"
                    :href="route('settings.businesses')"
                    class="flex items-center gap-3 border-t border-border px-4 py-3 transition hover:bg-background"
                >
                    <span
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M4 16.5v-13h-.25a.75.75 0 010-1.5h12.5a.75.75 0 010 1.5H16v13h.25a.75.75 0 010 1.5h-3.5a.75.75 0 01-.75-.75v-2.5a.75.75 0 00-.75-.75h-2.5a.75.75 0 00-.75.75v2.5a.75.75 0 01-.75.75h-3.5a.75.75 0 010-1.5H4zM7.25 6a.75.75 0 00-.75.75v.5c0 .414.336.75.75.75h.5a.75.75 0 00.75-.75v-.5A.75.75 0 007.75 6h-.5zm5 0a.75.75 0 00-.75.75v.5c0 .414.336.75.75.75h.5a.75.75 0 00.75-.75v-.5a.75.75 0 00-.75-.75h-.5zm-5 3a.75.75 0 00-.75.75v.5c0 .414.336.75.75.75h.5a.75.75 0 00.75-.75v-.5A.75.75 0 007.75 9h-.5zm5 0a.75.75 0 00-.75.75v.5c0 .414.336.75.75.75h.5a.75.75 0 00.75-.75v-.5a.75.75 0 00-.75-.75h-.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-sans text-[14px] font-medium text-ink-primary"
                        >
                            {{ $t('account.rows.business.label') }}
                        </p>
                        <p
                            class="truncate font-sans text-[12px] text-ink-tertiary"
                        >
                            {{
                                business?.name ??
                                $t('account.rows.business.subtext_fallback')
                            }}
                        </p>
                    </div>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4 flex-shrink-0 text-ink-tertiary"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </Link>

                <!-- Preferences -->
                <Link
                    :href="route('settings.index')"
                    class="flex items-center gap-3 border-t border-border px-4 py-3 transition hover:bg-background"
                >
                    <span
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.993 6.993 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-sans text-[14px] font-medium text-ink-primary"
                        >
                            {{ $t('account.rows.preferences.label') }}
                        </p>
                        <p
                            class="truncate font-sans text-[12px] text-ink-tertiary"
                        >
                            {{ $t('account.rows.preferences.subtext') }}
                        </p>
                    </div>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4 flex-shrink-0 text-ink-tertiary"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </Link>

                <!-- Help & Support -->
                <button
                    type="button"
                    class="flex w-full items-center gap-3 border-t border-border px-4 py-3 text-left transition hover:bg-background"
                    @click="showSupportModal = true"
                >
                    <span
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.94 6.94a.75.75 0 11-1.061-1.061 3 3 0 112.871 5.026v.345a.75.75 0 01-1.5 0v-.5c0-.72.57-1.172 1.081-1.287A1.5 1.5 0 108.94 6.94zM10 15a1 1 0 100-2 1 1 0 000 2z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-sans text-[14px] font-medium text-ink-primary"
                        >
                            {{ $t('account.rows.support.label') }}
                        </p>
                        <p
                            class="truncate font-sans text-[12px] text-ink-tertiary"
                        >
                            {{ $t('account.rows.support.subtext') }}
                        </p>
                    </div>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4 flex-shrink-0 text-ink-tertiary"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <!-- Super Admin (gated) -->
                <Link
                    v-if="isAnyAdmin"
                    :href="route('admin.dashboard')"
                    class="flex items-center gap-3 border-t border-border px-4 py-3 transition hover:bg-background"
                >
                    <span
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M9.661 2.237a.531.531 0 01.678 0 11.947 11.947 0 007.078 2.749.533.533 0 01.479.533c0 5.448-3.299 10.116-8 11.932a.535.535 0 01-.372 0c-4.701-1.816-8-6.484-8-11.932a.533.533 0 01.479-.533 11.947 11.947 0 007.078-2.749z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-sans text-[14px] font-medium text-ink-primary"
                        >
                            {{ $t('account.rows.super_admin.label') }}
                        </p>
                        <p
                            class="truncate font-sans text-[12px] text-ink-tertiary"
                        >
                            {{ $t('account.rows.super_admin.subtext') }}
                        </p>
                    </div>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4 flex-shrink-0 text-ink-tertiary"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </Link>
            </div>

            <!-- ── Log out ───────────────────────────────────────────────── -->
            <button
                type="button"
                class="flex w-full items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3 text-left shadow-pop transition hover:bg-background"
                @click="logout"
            >
                <span
                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-danger-soft text-danger"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z"
                            clip-rule="evenodd"
                        />
                        <path
                            fill-rule="evenodd"
                            d="M19 10a.75.75 0 00-.75-.75H8.704l1.048-1.08a.75.75 0 10-1.004-1.115l-2.5 2.569a.75.75 0 000 1.052l2.5 2.569a.75.75 0 101.004-1.115l-1.048-1.08h9.546A.75.75 0 0019 10z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </span>
                <span class="font-sans text-[14px] font-medium text-danger">
                    {{ $t('account.rows.log_out.label') }}
                </span>
            </button>
        </div>
    </AuthenticatedLayout>
</template>
