<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Jobs" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-[22px] font-semibold text-ink-primary">
                Jobs
            </h1>
        </template>

        <div class="flex items-center justify-center py-24">
            <p class="font-sans text-[15px] text-ink-tertiary">Coming soon</p>
        </div>
    </AuthenticatedLayout>
</template>
