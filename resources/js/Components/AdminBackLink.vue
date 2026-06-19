<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link
        :href="route('admin.dashboard')"
        class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
    >
        {{ $t('super_admin.dashboard.back') }}
    </Link>
</template>
