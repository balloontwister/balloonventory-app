<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import logoLight from '../../images/balloonventory-logo-light.png';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
});
</script>

<template>
    <Head :title="$t('welcome.meta_title')" />

    <div class="flex min-h-screen flex-col bg-background">
        <!-- Nav -->
        <header class="flex items-center justify-between px-6 py-5 sm:px-10">
            <img :src="logoLight" alt="Balloonventory" class="h-8 w-auto" />

            <div class="flex items-center gap-3">
                <LocaleSwitcher
                    button-class="flex h-8 w-8 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
                />
                <nav v-if="canLogin" class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-md px-4 py-2 font-sans text-[14px] font-medium text-ink-primary transition hover:text-ink-secondary"
                >
                    {{ $t('welcome.nav.dashboard') }}
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="rounded-md px-4 py-2 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
                    >
                        {{ $t('welcome.nav.log_in') }}
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-medium text-accent-on transition hover:bg-accent-hover"
                    >
                        {{ $t('welcome.nav.get_started') }}
                    </Link>
                </template>
            </nav>
            </div>
        </header>

        <!-- Hero -->
        <main
            class="flex flex-1 flex-col items-center justify-center px-6 pb-24 pt-16 text-center sm:px-10"
        >
            <h1
                class="font-display text-[40px] font-semibold leading-[1.1] tracking-h1 text-ink-primary sm:text-[56px]"
            >
                {{ $t('welcome.hero.title_line_1') }}<br />{{
                    $t('welcome.hero.title_line_2')
                }}
            </h1>

            <p
                class="mt-6 max-w-[480px] font-sans text-[17px] leading-relaxed text-ink-secondary"
            >
                {{ $t('welcome.hero.subtitle') }}
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="rounded-md bg-accent px-6 py-3 font-sans text-[15px] font-semibold text-accent-on shadow-pop transition hover:bg-accent-hover"
                >
                    {{ $t('welcome.cta.start_for_free') }}
                </Link>

                <Link
                    v-if="canLogin"
                    :href="route('login')"
                    class="rounded-md border border-border bg-surface px-6 py-3 font-sans text-[15px] font-semibold text-ink-primary shadow-pop transition hover:border-border-strong"
                >
                    {{ $t('welcome.cta.log_in') }}
                </Link>
            </div>

            <!-- Feature pills -->
            <div class="mt-16 flex flex-wrap items-center justify-center gap-2">
                <span
                    v-for="featureKey in [
                        'upc_barcode_scanning',
                        'multi_business_accounts',
                        'job_planning',
                        'favorites_and_lists',
                        'reorder_alerts',
                    ]"
                    :key="featureKey"
                    class="rounded-pill border border-border bg-surface px-3 py-1 font-sans text-[13px] text-ink-secondary"
                >
                    {{ $t(`welcome.features.${featureKey}`) }}
                </span>
            </div>
        </main>

        <footer
            class="py-6 text-center font-sans text-[13px] text-ink-tertiary"
        >
            &copy; {{ new Date().getFullYear() }} Balloonventory
        </footer>
    </div>
</template>
