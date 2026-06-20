<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AdminMenu from '@/Components/AdminMenu.vue';
import BusinessBadge from '@/Components/BusinessBadge.vue';
import BusinessSwitcher from '@/Components/BusinessSwitcher.vue';
import Toaster from '@/Components/Toaster.vue';
import { useBusiness } from '@/Composables/useBusiness';
import logoLight from '../../images/balloonventory-logo-light.png';
import logoDark from '../../images/balloonventory-logo-dark.png';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const { businessColor } = useBusiness();
const page = usePage();

// Render only the matching breakpoint's layout (Tailwind lg = 1024px). The page
// slot lives inside each wrapper, so rendering both would duplicate the page —
// and any modal in it — which breaks native <dialog> modals on wide screens.
const isDesktop = ref(
    typeof window !== 'undefined'
        ? window.matchMedia('(min-width: 1024px)').matches
        : true,
);
let mediaQuery;
function syncIsDesktop(event) {
    isDesktop.value = event.matches;
}
onMounted(() => {
    mediaQuery = window.matchMedia('(min-width: 1024px)');
    isDesktop.value = mediaQuery.matches;
    mediaQuery.addEventListener('change', syncIsDesktop);
});
onUnmounted(() => mediaQuery?.removeEventListener('change', syncIsDesktop));

const isSuperAdmin = page.props.auth?.isAnyAdmin ?? false;
const isSuperOnly = page.props.auth?.isSuperAdmin ?? false;

// Desktop sidebar collapse — remembered per browser.
const sidebarCollapsed = ref(
    typeof localStorage !== 'undefined' &&
        localStorage.getItem('sidebar.collapsed') === '1',
);
function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    try {
        localStorage.setItem(
            'sidebar.collapsed',
            sidebarCollapsed.value ? '1' : '0',
        );
    } catch {
        /* ignore storage errors */
    }
}

const nav = [
    {
        labelKey: 'nav.inventory',
        icon: 'inventory',
        routeName: 'inventory.index',
    },
    { labelKey: 'nav.jobs', icon: 'jobs', routeName: 'jobs.index' },
    { labelKey: 'nav.scan', icon: 'scan', routeName: 'scan.index' },
    { labelKey: 'nav.reorder', icon: 'reorder', routeName: 'reorder.index' },
    { labelKey: 'nav.account', icon: 'account', routeName: 'account.index' },
];

function isActive(routeName) {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
}

// When the sidebar is collapsed, the main nav moves to a top bar. Account is
// omitted there — the avatar in the header already links to it.
const topNavItems = computed(() =>
    nav.filter((item) => item.routeName !== 'account.index'),
);
</script>

<template>
    <div class="min-h-screen bg-background">
        <!-- 2px BusinessBadge color bar pinned above everything -->
        <BusinessBadge :color="businessColor" />

        <!-- App-wide toast stack (teleports to body) -->
        <Toaster />

        <!-- ─── DESKTOP LAYOUT (lg+) ─── -->
        <div v-if="isDesktop" class="hidden min-h-screen pt-0.5 lg:flex">
            <!-- Sidebar 240px -->
            <aside
                v-show="!sidebarCollapsed"
                class="fixed inset-y-0 left-0 z-20 flex w-60 flex-col border-r border-border bg-surface pt-0.5"
            >
                <!-- logo area -->
                <div
                    class="flex h-16 flex-shrink-0 items-center justify-between px-4"
                >
                    <Link :href="route('dashboard')" class="block">
                        <img
                            :src="logoLight"
                            alt="Balloonventory"
                            class="h-7 w-auto dark:hidden"
                        />
                        <img
                            :src="logoDark"
                            alt="Balloonventory"
                            class="hidden h-7 w-auto dark:block"
                        />
                    </Link>
                    <button
                        type="button"
                        :title="$t('nav.collapse_sidebar')"
                        :aria-label="$t('nav.collapse_sidebar')"
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
                        @click="toggleSidebar"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M2 4.75A2.75 2.75 0 014.75 2h10.5A2.75 2.75 0 0118 4.75v10.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25V4.75zm6 0v10.5h7.25a1.25 1.25 0 001.25-1.25V6a1.25 1.25 0 00-1.25-1.25H8zm-1.5 0H4.75A1.25 1.25 0 003.5 6v8a1.25 1.25 0 001.25 1.25H6.5V4.75z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                </div>

                <!-- BusinessSwitcher -->
                <div class="px-2">
                    <BusinessSwitcher />
                </div>

                <!-- Nav -->
                <nav
                    class="mt-6 flex flex-1 flex-col gap-1 overflow-y-auto px-2 pb-4"
                >
                    <p
                        class="mb-1 px-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                    >
                        {{ $t('nav.main') }}
                    </p>

                    <Link
                        v-for="item in nav"
                        :key="item.routeName"
                        :href="route(item.routeName)"
                        class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                        :class="
                            isActive(item.routeName)
                                ? 'bg-accent-soft font-semibold text-accent'
                                : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                        "
                    >
                        <!-- inventory icon (stacked boxes) -->
                        <template v-if="item.icon === 'inventory'">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    d="M10.75 1.66a1.5 1.5 0 0 0-1.5 0L1.6 6.04a.75.75 0 0 0 0 1.32l7.65 4.37a1.5 1.5 0 0 0 1.5 0l7.65-4.37a.75.75 0 0 0 0-1.32l-7.65-4.37Z"
                                />
                                <path
                                    d="m2.69 9.21-1.09.62a.75.75 0 0 0 0 1.32l7.65 4.37a1.5 1.5 0 0 0 1.5 0l7.65-4.37a.75.75 0 0 0 0-1.32l-1.09-.62-5.81 3.32a3 3 0 0 1-3 0L2.69 9.21Z"
                                />
                                <path
                                    d="m2.69 13.21-1.09.62a.75.75 0 0 0 0 1.32l7.65 4.37a1.5 1.5 0 0 0 1.5 0l7.65-4.37a.75.75 0 0 0 0-1.32l-1.09-.62-5.81 3.32a3 3 0 0 1-3 0l-5.81-3.32Z"
                                />
                            </svg>
                        </template>

                        <!-- jobs icon -->
                        <template v-else-if="item.icon === 'jobs'">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </template>

                        <!-- scan icon -->
                        <template v-else-if="item.icon === 'scan'">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    d="M3 9V6a1 1 0 011-1h3M3 15v3a1 1 0 001 1h3M21 9V6a1 1 0 00-1-1h-3M21 15v3a1 1 0 01-1 1h-3"
                                />
                                <line x1="7" y1="12" x2="17" y2="12" />
                                <line x1="7" y1="9" x2="7" y2="15" />
                                <line x1="11" y1="10" x2="11" y2="14" />
                                <line x1="15" y1="9" x2="15" y2="15" />
                                <line x1="17" y1="12" x2="17" y2="12" />
                            </svg>
                        </template>

                        <!-- reorder icon -->
                        <template v-else-if="item.icon === 'reorder'">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    d="M10.75 6.75a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z"
                                />
                                <path
                                    fill-rule="evenodd"
                                    d="M5.5 3.5A2 2 0 013.5 5.5H2v9A2.5 2.5 0 004.5 17H16a2 2 0 002-2V5a2 2 0 00-2-2H5.5zm0 1.5H16a.5.5 0 01.5.5v9.5a.5.5 0 01-.5.5H4.5A1 1 0 013.5 14.5v-9H3.5A.5.5 0 013 5a.5.5 0 01.5-.5H5V5z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </template>

                        <!-- account icon (avatar if available) -->
                        <template v-else-if="item.icon === 'account'">
                            <img
                                v-if="$page.props.auth.avatarUrl"
                                :src="$page.props.auth.avatarUrl"
                                :alt="$page.props.auth.user.name"
                                class="h-4 w-4 flex-shrink-0 rounded-full object-cover"
                            />
                            <svg
                                v-else
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"
                                />
                            </svg>
                        </template>

                        {{ $t(item.labelKey) }}
                    </Link>

                    <!-- Admin section -->
                    <template v-if="isSuperAdmin">
                        <p
                            class="mb-1 mt-4 px-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('nav.admin') }}
                        </p>

                        <Link
                            :href="route('admin.dashboard')"
                            class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                            :class="
                                route().current('admin.dashboard')
                                    ? 'bg-accent-soft font-semibold text-accent'
                                    : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                            "
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M4.25 2A2.25 2.25 0 002 4.25v2.5A2.25 2.25 0 004.25 9h2.5A2.25 2.25 0 009 6.75v-2.5A2.25 2.25 0 006.75 2h-2.5zm0 9A2.25 2.25 0 002 13.25v2.5A2.25 2.25 0 004.25 18h2.5A2.25 2.25 0 009 15.75v-2.5A2.25 2.25 0 006.75 11h-2.5zm6.5-9A2.25 2.25 0 008.5 4.25v2.5A2.25 2.25 0 0010.75 9h2.5A2.25 2.25 0 0015.5 6.75v-2.5A2.25 2.25 0 0013.25 2h-2.5zm0 9a2.25 2.25 0 00-2.25 2.25v2.5A2.25 2.25 0 0010.75 18h2.5A2.25 2.25 0 0015.5 15.75v-2.5A2.25 2.25 0 0013.25 11h-2.5z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t('super_admin.dashboard.nav.overview') }}
                        </Link>

                        <Link
                            :href="route('admin.tickets.index')"
                            class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                            :class="
                                route().current('admin.tickets.*')
                                    ? 'bg-accent-soft font-semibold text-accent'
                                    : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                            "
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M2 10c0-3.967 3.69-7 8-7 4.31 0 8 3.033 8 7s-3.69 7-8 7a9.165 9.165 0 01-2.628-.39A1 1 0 006 18.1l-2.7-.9A.75.75 0 012.5 16.5v-1.75A6.967 6.967 0 012 10z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t('super_admin.dashboard.nav.support_tickets') }}
                        </Link>

                        <Link
                            :href="route('admin.email-templates.index')"
                            class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                            :class="
                                route().current('admin.email-templates.*')
                                    ? 'bg-accent-soft font-semibold text-accent'
                                    : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                            "
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z"
                                />
                                <path
                                    d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z"
                                />
                            </svg>
                            {{ $t('super_admin.dashboard.nav.email') }}
                        </Link>

                        <Link
                            :href="route('admin.catalog.skus')"
                            class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                            :class="
                                route().current('admin.catalog.*')
                                    ? 'bg-accent-soft font-semibold text-accent'
                                    : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                            "
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    d="M10.75 16.82A7.462 7.462 0 0115 15.5c.71 0 1.396.098 2.046.282A.75.75 0 0018 15.06v-11a.75.75 0 00-.546-.721A9.006 9.006 0 0015 3a8.963 8.963 0 00-4.25 1.065V16.82zM9.25 4.065A8.963 8.963 0 005 3c-.85 0-1.673.118-2.454.339A.75.75 0 002 4.06v11a.75.75 0 00.954.721A7.506 7.506 0 015 15.5c1.579 0 3.042.487 4.25 1.32V4.065z"
                                />
                            </svg>
                            {{ $t('super_admin.dashboard.nav.catalog') }}
                        </Link>

                        <Link
                            :href="route('admin.users.index')"
                            class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                            :class="
                                route().current('admin.users.*')
                                    ? 'bg-accent-soft font-semibold text-accent'
                                    : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                            "
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 17a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z"
                                />
                            </svg>
                            {{ $t('super_admin.dashboard.nav.users') }}
                        </Link>

                        <Link
                            :href="route('admin.barcode-audits.index')"
                            class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                            :class="
                                route().current('admin.barcode-audits.*')
                                    ? 'bg-accent-soft font-semibold text-accent'
                                    : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                            "
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    d="M3 4.75A.75.75 0 0 1 3.75 4h.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-.5a.75.75 0 0 1-.75-.75V4.75ZM6 4.75A.75.75 0 0 1 6.75 4h.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-.5A.75.75 0 0 1 6 15.25V4.75ZM9.5 4a.75.75 0 0 0-.75.75v10.5c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75V4.75A.75.75 0 0 0 10 4h-.5ZM12.5 4.75a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V4.75ZM16.5 4a.75.75 0 0 0-.75.75v10.5c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75V4.75A.75.75 0 0 0 17 4h-.5Z"
                                />
                            </svg>
                            {{ $t('super_admin.dashboard.nav.barcode_log') }}
                        </Link>

                        <Link
                            :href="route('admin.feedback.index')"
                            class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                            :class="
                                route().current('admin.feedback.*')
                                    ? 'bg-accent-soft font-semibold text-accent'
                                    : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                            "
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4 flex-shrink-0"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M18 5v8a2 2 0 0 1-2 2h-5l-5 4v-4H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2ZM7 8H5v2h2V8Zm2 0h2v2H9V8Zm6 0h-2v2h2V8Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t('super_admin.dashboard.nav.feedback') }}
                        </Link>

                        <!-- Super-Admin-only areas -->
                        <template v-if="isSuperOnly">
                            <Link
                                :href="route('admin.backups.index')"
                                class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                                :class="
                                    route().current('admin.backups.*')
                                        ? 'bg-accent-soft font-semibold text-accent'
                                        : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                                "
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4 flex-shrink-0"
                                >
                                    <path
                                        d="M3 12.75a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3 8.25a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 8.25ZM3 3.75A.75.75 0 0 1 3.75 3h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 3.75Z"
                                    />
                                </svg>
                                {{ $t('super_admin.dashboard.nav.backups') }}
                            </Link>

                            <Link
                                :href="route('admin.subscriptions.index')"
                                class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                                :class="
                                    route().current('admin.subscriptions.*')
                                        ? 'bg-accent-soft font-semibold text-accent'
                                        : 'text-ink-tertiary hover:bg-background hover:text-ink-primary'
                                "
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4 flex-shrink-0"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v.316a3.78 3.78 0 00-1.653.713c-.426.33-.744.74-.925 1.2a2.6 2.6 0 000 1.962c.18.46.499.87.925 1.2.42.326.94.55 1.653.713V12.5a2.2 2.2 0 01-.5-.105.75.75 0 10-.45 1.43c.305.096.625.155.95.175v.316a.75.75 0 001.5 0v-.316c.66-.084 1.22-.323 1.653-.713.426-.33.744-.74.925-1.2a2.6 2.6 0 000-1.962c-.18-.46-.499-.87-.925-1.2-.42-.326-.94-.55-1.653-.713V7.5c.176.027.343.063.5.105a.75.75 0 10.45-1.43 4.3 4.3 0 00-.95-.175V5z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                {{ $t('super_admin.dashboard.nav.subscriptions') }}
                            </Link>

                            <Link
                                :href="route('admin.payments.index')"
                                class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                                :class="
                                    route().current('admin.payments.*')
                                        ? 'bg-accent-soft font-semibold text-accent'
                                        : 'text-ink-tertiary hover:bg-background hover:text-ink-primary'
                                "
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4 flex-shrink-0"
                                >
                                    <path
                                        d="M1 4.25C1 3.56 1.56 3 2.25 3h15.5c.69 0 1.25.56 1.25 1.25v.5H1v-.5z"
                                    />
                                    <path
                                        fill-rule="evenodd"
                                        d="M1 6.5v9.25C1 16.44 1.56 17 2.25 17h15.5c.69 0 1.25-.56 1.25-1.25V6.5H1zm3 6.5a.75.75 0 01.75-.75h3a.75.75 0 010 1.5h-3A.75.75 0 014 13z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                {{ $t('super_admin.dashboard.nav.payments') }}
                            </Link>

                            <Link
                                :href="route('admin.affiliates.index')"
                                class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                                :class="
                                    route().current('admin.affiliates.*')
                                        ? 'bg-accent-soft font-semibold text-accent'
                                        : 'text-ink-tertiary hover:bg-background hover:text-ink-primary'
                                "
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4 flex-shrink-0"
                                >
                                    <path
                                        d="M11 5a3 3 0 11-6 0 3 3 0 016 0zM2.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 018 18a9.953 9.953 0 01-5.385-1.572zM16.25 5.75a.75.75 0 00-1.5 0v2h-2a.75.75 0 000 1.5h2v2a.75.75 0 001.5 0v-2h2a.75.75 0 000-1.5h-2v-2z"
                                    />
                                </svg>
                                {{ $t('super_admin.dashboard.nav.affiliates') }}
                            </Link>
                        </template>
                    </template>
                </nav>
            </aside>

            <!-- Main content -->
            <div
                class="flex min-h-screen w-full flex-col"
                :class="sidebarCollapsed ? 'ml-0' : 'ml-60'"
            >
                <!-- Top navigation bar — shown when the sidebar is collapsed, so
                     the main nav stays reachable from the top instead. -->
                <nav
                    v-if="sidebarCollapsed"
                    class="flex items-center gap-1 border-b border-border bg-surface px-8 py-2.5"
                >
                    <button
                        type="button"
                        :title="$t('nav.expand_sidebar')"
                        :aria-label="$t('nav.expand_sidebar')"
                        class="mr-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
                        @click="toggleSidebar"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M2 4.75A2.75 2.75 0 014.75 2h10.5A2.75 2.75 0 0118 4.75v10.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25V4.75zm6 0v10.5h7.25a1.25 1.25 0 001.25-1.25V6a1.25 1.25 0 00-1.25-1.25H8zm-1.5 0H4.75A1.25 1.25 0 003.5 6v8a1.25 1.25 0 001.25 1.25H6.5V4.75z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                    <Link
                        v-for="item in topNavItems"
                        :key="item.routeName"
                        :href="route(item.routeName)"
                        class="rounded-md px-3 py-1.5 font-sans text-[14px] transition"
                        :class="
                            isActive(item.routeName)
                                ? 'bg-accent-soft font-semibold text-accent'
                                : 'text-ink-secondary hover:bg-background hover:text-ink-primary'
                        "
                    >
                        {{ $t(item.labelKey) }}
                    </Link>
                </nav>

                <!-- Page header slot -->
                <header
                    v-if="$slots.header || sidebarCollapsed"
                    class="border-b border-border bg-surface px-8 py-5"
                >
                    <div class="flex items-center gap-4">
                        <div class="min-w-0 flex-1">
                            <slot name="header" />
                        </div>
                        <AdminMenu v-if="isSuperAdmin" />
                        <Link
                            :href="route('account.index')"
                            :title="$t('nav.account')"
                            class="flex h-9 w-9 flex-shrink-0 items-center justify-center overflow-hidden rounded-full transition"
                            :class="
                                route().current('account.*')
                                    ? 'ring-2 ring-accent'
                                    : 'ring-1 ring-border hover:ring-accent'
                            "
                        >
                            <img
                                v-if="$page.props.auth.avatarUrl"
                                :src="$page.props.auth.avatarUrl"
                                :alt="$page.props.auth.user.name"
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
                        </Link>
                    </div>
                </header>

                <main class="max-w-[1280px] flex-1 px-8 py-6">
                    <slot />
                </main>
            </div>
        </div>

        <!-- ─── MOBILE / TABLET LAYOUT (< lg) ─── -->
        <div v-else class="flex min-h-screen flex-col pt-0.5 lg:hidden">
            <!-- Sticky BusinessSwitcher header -->
            <header
                class="sticky top-0.5 z-10 border-b border-border bg-surface"
            >
                <div class="flex items-center gap-2 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <BusinessSwitcher />
                    </div>
                    <AdminMenu v-if="isSuperAdmin" compact />
                    <Link
                        :href="route('account.index')"
                        :title="$t('nav.account')"
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center overflow-hidden rounded-full text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
                        :class="
                            route().current('account.index')
                                ? 'ring-2 ring-accent'
                                : ''
                        "
                    >
                        <img
                            v-if="$page.props.auth.avatarUrl"
                            :src="$page.props.auth.avatarUrl"
                            :alt="$page.props.auth.user.name"
                            class="h-8 w-8 rounded-full object-cover"
                        />
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"
                            />
                        </svg>
                    </Link>
                </div>

                <!-- Page sub-header slot -->
                <div
                    v-if="$slots.header"
                    class="border-t border-border px-4 py-3"
                >
                    <slot name="header" />
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 px-4 py-4 pb-24">
                <slot />
            </main>

            <!-- Bottom tab bar (5 items) -->
            <nav
                class="fixed inset-x-0 bottom-0 z-20 flex h-14 items-stretch border-t border-border bg-surface"
            >
                <!-- Inventory -->
                <Link
                    :href="route('inventory.index')"
                    class="flex flex-1 flex-col items-center justify-center gap-0.5 transition"
                    :class="
                        isActive('inventory.index')
                            ? 'text-accent'
                            : 'text-ink-tertiary'
                    "
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-5 w-5"
                    >
                        <path
                            d="M10.75 1.66a1.5 1.5 0 0 0-1.5 0L1.6 6.04a.75.75 0 0 0 0 1.32l7.65 4.37a1.5 1.5 0 0 0 1.5 0l7.65-4.37a.75.75 0 0 0 0-1.32l-7.65-4.37Z"
                        />
                        <path
                            d="m2.69 9.21-1.09.62a.75.75 0 0 0 0 1.32l7.65 4.37a1.5 1.5 0 0 0 1.5 0l7.65-4.37a.75.75 0 0 0 0-1.32l-1.09-.62-5.81 3.32a3 3 0 0 1-3 0L2.69 9.21Z"
                        />
                        <path
                            d="m2.69 13.21-1.09.62a.75.75 0 0 0 0 1.32l7.65 4.37a1.5 1.5 0 0 0 1.5 0l7.65-4.37a.75.75 0 0 0 0-1.32l-1.09-.62-5.81 3.32a3 3 0 0 1-3 0l-5.81-3.32Z"
                        />
                    </svg>
                    <span class="font-sans text-[10px] font-medium">{{
                        $t('nav.inventory')
                    }}</span>
                </Link>

                <!-- Jobs -->
                <Link
                    :href="route('jobs.index')"
                    class="flex flex-1 flex-col items-center justify-center gap-0.5 transition"
                    :class="
                        isActive('jobs.index')
                            ? 'text-accent'
                            : 'text-ink-tertiary'
                    "
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-5 w-5"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <span class="font-sans text-[10px] font-medium">{{
                        $t('nav.jobs')
                    }}</span>
                </Link>

                <!-- Center Scan button (elevated) -->
                <div class="relative flex flex-1 items-center justify-center">
                    <Link
                        :href="route('scan.index')"
                        class="absolute -top-4 flex h-14 w-14 items-center justify-center rounded-full bg-accent shadow-pop transition hover:bg-accent-hover"
                        :aria-label="$t('nav.scan')"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="white"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-6 w-6"
                        >
                            <path
                                d="M3 9V6a1 1 0 011-1h3M3 15v3a1 1 0 001 1h3M21 9V6a1 1 0 00-1-1h-3M21 15v3a1 1 0 01-1 1h-3"
                            />
                            <line x1="7" y1="12" x2="17" y2="12" />
                            <line x1="7" y1="9" x2="7" y2="15" />
                            <line x1="11" y1="10" x2="11" y2="14" />
                            <line x1="15" y1="9" x2="15" y2="15" />
                            <line x1="17" y1="12" x2="17" y2="12" />
                        </svg>
                    </Link>
                </div>

                <!-- Reorder -->
                <Link
                    :href="route('reorder.index')"
                    class="flex flex-1 flex-col items-center justify-center gap-0.5 transition"
                    :class="
                        isActive('reorder.index')
                            ? 'text-accent'
                            : 'text-ink-tertiary'
                    "
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-5 w-5"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <span class="font-sans text-[10px] font-medium">{{
                        $t('nav.reorder')
                    }}</span>
                </Link>

                <!-- Account -->
                <Link
                    :href="route('account.index')"
                    class="flex flex-1 flex-col items-center justify-center gap-0.5 transition"
                    :class="
                        isActive('account.index')
                            ? 'text-accent'
                            : 'text-ink-tertiary'
                    "
                >
                    <img
                        v-if="$page.props.auth.avatarUrl"
                        :src="$page.props.auth.avatarUrl"
                        :alt="$page.props.auth.user.name"
                        class="h-5 w-5 rounded-full object-cover"
                    />
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-5 w-5"
                    >
                        <path
                            d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"
                        />
                    </svg>
                    <span class="font-sans text-[10px] font-medium">{{
                        $t('nav.account')
                    }}</span>
                </Link>
            </nav>
        </div>
    </div>
</template>
