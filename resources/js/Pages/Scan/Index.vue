<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head :title="$t('scan.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-[22px] font-semibold text-ink-primary">
                {{ $t('scan.heading') }}
            </h1>
        </template>

        <div class="flex items-center justify-center py-24">
            <p class="font-sans text-[15px] text-ink-tertiary">
                {{ $t('common.coming_soon') }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
