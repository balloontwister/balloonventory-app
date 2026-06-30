<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import CookieNotice from '@/Components/CookieNotice.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import LegalFooter from '@/Components/LegalFooter.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
// Clear the fixed banner (h-9) when impersonating spills onto a guest page.
const isImpersonating = computed(() => !!page.props.impersonating);
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center bg-background px-4 py-16 sm:justify-center sm:py-0"
        :class="{ 'pt-12': isImpersonating }"
    >
        <ImpersonationBanner />
        <div class="absolute right-4 top-4 z-50">
            <LocaleSwitcher
                button-class="flex h-8 w-8 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
            />
        </div>

        <div class="flex w-full flex-col items-center gap-8 sm:max-w-md">
            <Link href="/">
                <ApplicationLogo class="h-10 w-auto" />
            </Link>

            <div
                class="w-full rounded-lg border border-border bg-surface px-6 py-6 shadow-pop"
            >
                <slot />
            </div>

            <LegalFooter />
        </div>

        <CookieNotice />
    </div>
</template>
