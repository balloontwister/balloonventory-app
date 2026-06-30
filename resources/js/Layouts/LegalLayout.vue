<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// Minimal standalone layout for the public legal/policy pages — wider than
// GuestLayout (which is a narrow auth card) so long prose reads comfortably.
// Renders for guests and logged-in users alike; no business context required.
const year = computed(() => new Date().getFullYear());
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <ImpersonationBanner />

        <header
            class="flex items-center justify-between border-b border-border px-4 py-4 sm:px-6"
        >
            <Link href="/" class="flex items-center gap-2">
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
            <div
                class="mx-auto flex w-full max-w-3xl flex-col items-center gap-3 text-center sm:flex-row sm:justify-between sm:text-left"
            >
                <nav class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <Link
                        :href="route('legal.terms')"
                        class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
                    >
                        {{ $t('legal.footer.terms') }}
                    </Link>
                    <Link
                        :href="route('legal.privacy')"
                        class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
                    >
                        {{ $t('legal.footer.privacy') }}
                    </Link>
                    <Link
                        :href="route('legal.cookies')"
                        class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
                    >
                        {{ $t('legal.footer.cookies') }}
                    </Link>
                </nav>
                <p class="font-sans text-[12px] text-ink-tertiary">
                    {{ $t('legal.footer.rights', { year }) }}
                </p>
            </div>
        </footer>
    </div>
</template>
