<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    active: { type: String, default: 'items' }, // items | bins
});
</script>

<template>
    <div class="inline-flex items-center rounded-pill bg-background p-1">
        <Link
            :href="route('inventory.index')"
            class="relative rounded-pill px-4 py-1.5 font-sans text-[14px] font-medium transition-colors"
            :class="
                active === 'items'
                    ? 'bg-accent-soft font-semibold text-accent'
                    : 'text-ink-secondary hover:text-ink-primary'
            "
        >
            {{ $t('bins.tabs.by_item') }}
        </Link>
        <Link
            :href="route('inventory.bins.index')"
            class="relative rounded-pill px-4 py-1.5 font-sans text-[14px] font-medium transition-colors"
            :class="
                active === 'bins'
                    ? 'bg-accent-soft font-semibold text-accent'
                    : 'text-ink-secondary hover:text-ink-primary'
            "
        >
            {{ $t('bins.tabs.by_bin') }}
        </Link>
    </div>
</template>
