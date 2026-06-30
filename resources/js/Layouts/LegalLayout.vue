<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import BackLink from '@/Components/BackLink.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import LegalFooter from '@/Components/LegalFooter.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Minimal standalone layout for the public legal/policy pages — wider than
// GuestLayout (which is a narrow auth card) so long prose reads comfortably.
// Renders for guests and logged-in users alike; no business context required.
const page = usePage();

// Logged-in visitors get a clear way back into the app; guests see the brand
// logo linking to the marketing home.
const isAuthenticated = computed(() => !!page.props.auth?.user);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <ImpersonationBanner />

        <header
            class="flex items-center justify-between border-b border-border px-4 py-4 sm:px-6"
        >
            <BackLink
                v-if="isAuthenticated"
                :href="route('dashboard')"
                :label="$t('nav.go_to_dashboard')"
            />
            <Link v-else href="/" class="flex items-center gap-2">
                <ApplicationLogo class="h-8 w-auto" />
            </Link>
            <LocaleSwitcher
                button-class="flex h-8 w-8 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-surface hover:text-ink-primary"
            />
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-10 sm:px-6">
            <slot />
        </main>

        <footer class="border-t border-border px-4 py-6 sm:px-6">
            <LegalFooter />
        </footer>
    </div>
</template>
