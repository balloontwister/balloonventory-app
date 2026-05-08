<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BusinessBadge from '@/Components/BusinessBadge.vue';
import BusinessSwitcher from '@/Components/BusinessSwitcher.vue';
import { useBusiness } from '@/Composables/useBusiness';

const { business, businessColor } = useBusiness();
const page = usePage();

const nav = [
    { label: 'Inventory', icon: 'inventory', routeName: 'inventory.index' },
    { label: 'Jobs',      icon: 'jobs',      routeName: 'jobs.index' },
    { label: 'Reorder',  icon: 'reorder',   routeName: 'reorder.index' },
    { label: 'Settings', icon: 'settings',  routeName: 'settings.index' },
];

function isActive(routeName) {
    try { return route().current(routeName); } catch { return false; }
}
</script>

<template>
    <div class="min-h-screen bg-background">
        <!-- 2px BusinessBadge color bar pinned above everything -->
        <BusinessBadge :color="businessColor" />

        <!-- ─── DESKTOP LAYOUT (lg+) ─── -->
        <div class="hidden lg:flex min-h-screen pt-0.5">
            <!-- Sidebar 240px -->
            <aside class="fixed inset-y-0 left-0 z-20 flex w-60 flex-col border-r border-border bg-surface pt-0.5">
                <!-- logo area -->
                <div class="flex h-16 flex-shrink-0 items-center px-4">
                    <Link :href="route('dashboard')" class="font-display text-[18px] font-semibold text-ink-primary tracking-h3">
                        Balloonventory
                    </Link>
                </div>

                <!-- BusinessSwitcher -->
                <div class="px-2">
                    <BusinessSwitcher />
                </div>

                <!-- Nav -->
                <nav class="mt-6 flex flex-1 flex-col gap-1 overflow-y-auto px-2 pb-4">
                    <p class="mb-1 px-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary">
                        Main
                    </p>

                    <Link
                        v-for="item in nav"
                        :key="item.routeName"
                        :href="route(item.routeName)"
                        class="flex items-center gap-3 rounded-md px-3 py-2 font-sans text-[14px] transition"
                        :class="isActive(item.routeName)
                            ? 'bg-accent-soft text-accent font-semibold'
                            : 'text-ink-secondary hover:bg-background hover:text-ink-primary'"
                    >
                        <!-- inventory icon -->
                        <template v-if="item.icon === 'inventory'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0">
                                <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z" clip-rule="evenodd" />
                            </svg>
                        </template>

                        <!-- jobs icon -->
                        <template v-else-if="item.icon === 'jobs'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0">
                                <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
                            </svg>
                        </template>

                        <!-- reorder icon -->
                        <template v-else-if="item.icon === 'reorder'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0">
                                <path d="M10.75 6.75a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z" />
                                <path fill-rule="evenodd" d="M5.5 3.5A2 2 0 013.5 5.5H2v9A2.5 2.5 0 004.5 17H16a2 2 0 002-2V5a2 2 0 00-2-2H5.5zm0 1.5H16a.5.5 0 01.5.5v9.5a.5.5 0 01-.5.5H4.5A1 1 0 013.5 14.5v-9H3.5A.5.5 0 013 5a.5.5 0 01.5-.5H5V5z" clip-rule="evenodd" />
                            </svg>
                        </template>

                        <!-- settings icon -->
                        <template v-else-if="item.icon === 'settings'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0">
                                <path fill-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.993 6.993 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                            </svg>
                        </template>

                        {{ item.label }}
                    </Link>

                    <!-- user section -->
                    <div class="mt-auto border-t border-border pt-4">
                        <Link
                            :href="route('profile.edit')"
                            class="flex items-center gap-2 rounded-md px-3 py-2 font-sans text-[14px] text-ink-secondary transition hover:bg-background hover:text-ink-primary"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-shrink-0">
                                <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
                            </svg>
                            {{ $page.props.auth.user.name }}
                        </Link>
                    </div>
                </nav>
            </aside>

            <!-- Main content -->
            <div class="ml-60 flex min-h-screen w-full flex-col">
                <!-- Page header slot -->
                <header v-if="$slots.header" class="border-b border-border bg-surface px-8 py-5">
                    <slot name="header" />
                </header>

                <main class="flex-1 px-8 py-6 max-w-[1280px]">
                    <slot />
                </main>
            </div>
        </div>

        <!-- ─── MOBILE / TABLET LAYOUT (< lg) ─── -->
        <div class="flex flex-col min-h-screen pt-0.5 lg:hidden">
            <!-- Sticky BusinessSwitcher header -->
            <header class="sticky top-0.5 z-10 border-b border-border bg-surface">
                <div class="px-4 py-3">
                    <BusinessSwitcher />
                </div>

                <!-- Page sub-header slot -->
                <div v-if="$slots.header" class="border-t border-border px-4 py-3">
                    <slot name="header" />
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 px-4 py-4 pb-24">
                <slot />
            </main>

            <!-- Bottom tab bar (5 items) -->
            <nav class="fixed inset-x-0 bottom-0 z-20 flex h-14 items-stretch border-t border-border bg-surface">
                <!-- Inventory -->
                <Link :href="route('inventory.index')" class="flex flex-1 flex-col items-center justify-center gap-0.5 transition" :class="isActive('inventory.index') ? 'text-accent' : 'text-ink-tertiary'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                        <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-sans text-[10px] font-medium">Inventory</span>
                </Link>

                <!-- Jobs -->
                <Link :href="route('jobs.index')" class="flex flex-1 flex-col items-center justify-center gap-0.5 transition" :class="isActive('jobs.index') ? 'text-accent' : 'text-ink-tertiary'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                        <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-sans text-[10px] font-medium">Jobs</span>
                </Link>

                <!-- Center Scan button (elevated) -->
                <div class="relative flex flex-1 items-center justify-center">
                    <Link
                        :href="route('scan.index')"
                        class="absolute -top-4 flex h-14 w-14 items-center justify-center rounded-full bg-accent shadow-pop transition hover:bg-accent-hover"
                        aria-label="Scan"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                            <path d="M3 9V6a1 1 0 011-1h3M3 15v3a1 1 0 001 1h3M21 9V6a1 1 0 00-1-1h-3M21 15v3a1 1 0 01-1 1h-3" />
                            <line x1="7" y1="12" x2="17" y2="12" />
                            <line x1="7" y1="9" x2="7" y2="15" />
                            <line x1="11" y1="10" x2="11" y2="14" />
                            <line x1="15" y1="9" x2="15" y2="15" />
                            <line x1="17" y1="12" x2="17" y2="12" />
                        </svg>
                    </Link>
                </div>

                <!-- Reorder -->
                <Link :href="route('reorder.index')" class="flex flex-1 flex-col items-center justify-center gap-0.5 transition" :class="isActive('reorder.index') ? 'text-accent' : 'text-ink-tertiary'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-sans text-[10px] font-medium">Reorder</span>
                </Link>

                <!-- Settings -->
                <Link :href="route('settings.index')" class="flex flex-1 flex-col items-center justify-center gap-0.5 transition" :class="isActive('settings.index') ? 'text-accent' : 'text-ink-tertiary'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                        <path fill-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.993 6.993 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-sans text-[10px] font-medium">Settings</span>
                </Link>
            </nav>
        </div>
    </div>
</template>
