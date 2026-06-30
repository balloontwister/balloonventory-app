<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import CookieNotice from '@/Components/CookieNotice.vue';
import Dropdown from '@/Components/Dropdown.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import Toaster from '@/Components/Toaster.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Trimmed app shell for authenticated users without a current business
 * (the no-business welcome landing, profile, notifications, and — later —
 * the demo-business peek). Mirrors the real app's top bar (language,
 * notifications, account menu) minus anything that needs a business:
 * no business switcher, no business-scoped nav.
 */
const page = usePage();

const isImpersonating = computed(() => !!page.props.impersonating);
const avatarUrl = computed(() => page.props.auth?.avatarUrl);
const userName = computed(() => page.props.auth?.user?.name ?? '');

const logout = () => router.post(route('logout'));
</script>

<template>
    <div
        class="min-h-screen bg-background"
        :class="{ 'pt-9': isImpersonating }"
    >
        <ImpersonationBanner />
        <Toaster />

        <!-- Top bar -->
        <header class="border-b border-border bg-surface">
            <div
                class="mx-auto flex h-16 max-w-[1280px] items-center justify-between gap-4 px-4 sm:px-8"
            >
                <Link
                    :href="route('onboarding.welcome')"
                    class="flex items-center"
                    :title="userName"
                >
                    <ApplicationLogo class="h-9 w-auto" />
                </Link>

                <div class="flex items-center gap-1.5">
                    <LocaleSwitcher
                        button-class="flex h-9 w-9 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
                    />
                    <NotificationBell />

                    <Dropdown
                        align="right"
                        width="48"
                        content-classes="py-1 bg-surface border border-border"
                    >
                        <template #trigger>
                            <button
                                type="button"
                                :title="$t('nav.account')"
                                class="flex h-9 w-9 flex-shrink-0 items-center justify-center overflow-hidden rounded-full ring-1 ring-border transition hover:ring-accent"
                            >
                                <img
                                    v-if="avatarUrl"
                                    :src="avatarUrl"
                                    :alt="userName"
                                    class="h-9 w-9 rounded-full object-cover"
                                />
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-5 w-5 text-ink-tertiary"
                                >
                                    <path
                                        d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"
                                    />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <Link
                                :href="route('profile.edit')"
                                class="block px-4 py-2 font-sans text-[14px] text-ink-primary transition hover:bg-background"
                            >
                                {{ $t('nav.account') }}
                            </Link>
                            <button
                                type="button"
                                class="block w-full px-4 py-2 text-left font-sans text-[14px] text-danger transition hover:bg-background"
                                @click="logout"
                            >
                                {{ $t('account.rows.log_out.label') }}
                            </button>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </header>

        <!-- Optional page header slot (parity with AuthenticatedLayout) -->
        <header
            v-if="$slots.header"
            class="border-b border-border bg-surface px-4 py-5 sm:px-8"
        >
            <div class="mx-auto max-w-[1280px]">
                <slot name="header" />
            </div>
        </header>

        <main class="mx-auto max-w-[1280px] px-4 py-6 sm:px-8">
            <slot />
        </main>

        <CookieNotice />
    </div>
</template>
