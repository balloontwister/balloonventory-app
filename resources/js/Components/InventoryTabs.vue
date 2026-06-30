<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useInventoryView } from '@/Composables/useInventoryView.js';

const props = defineProps({
    active: { type: String, default: 'items' }, // items | bins | lists
});

// Remember this as the last-opened Inventory view so the nav returns here.
const { remember } = useInventoryView();
onMounted(() => remember(props.active));
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
        <Link
            :href="route('inventory.lists.index')"
            class="relative rounded-pill px-4 py-1.5 font-sans text-[14px] font-medium transition-colors"
            :class="
                active === 'lists'
                    ? 'bg-accent-soft font-semibold text-accent'
                    : 'text-ink-secondary hover:text-ink-primary'
            "
        >
            {{ $t('bins.tabs.by_list') }}
        </Link>
    </div>
</template>
