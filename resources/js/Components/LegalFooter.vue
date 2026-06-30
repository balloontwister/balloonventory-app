<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// Shared footer row of legal links + copyright. Used on GuestLayout, the
// Welcome page, and the legal pages themselves.
const year = computed(() => new Date().getFullYear());
</script>

<template>
    <div class="flex flex-col items-center gap-2 text-center">
        <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
            <Link
                :href="route('legal.terms')"
                class="font-sans text-[13px] text-ink-tertiary transition hover:text-ink-primary"
            >
                {{ $t('legal.footer.terms') }}
            </Link>
            <Link
                :href="route('legal.privacy')"
                class="font-sans text-[13px] text-ink-tertiary transition hover:text-ink-primary"
            >
                {{ $t('legal.footer.privacy') }}
            </Link>
            <Link
                :href="route('legal.cookies')"
                class="font-sans text-[13px] text-ink-tertiary transition hover:text-ink-primary"
            >
                {{ $t('legal.footer.cookies') }}
            </Link>
        </nav>
        <p class="font-sans text-[12px] text-ink-tertiary">
            {{ $t('legal.footer.rights', { year }) }}
        </p>
    </div>
</template>
