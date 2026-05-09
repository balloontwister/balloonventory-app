<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-background px-4 py-16 sm:justify-center sm:py-0"
    >
        <div class="flex w-full flex-col items-center gap-8 sm:max-w-md">
            <Link href="/">
                <ApplicationLogo class="h-10 w-auto" />
            </Link>

            <div
                class="w-full rounded-lg border border-border bg-surface px-6 py-6 shadow-pop"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
