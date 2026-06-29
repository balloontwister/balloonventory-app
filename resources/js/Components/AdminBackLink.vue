<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // Override the destination/label; defaults to the Admin Dashboard so existing
    // callers are unchanged.
    href: { type: String, default: null },
    label: { type: String, default: null },
});

const target = computed(() => props.href ?? route('admin.dashboard'));
</script>

<template>
    <Link
        :href="target"
        class="inline-flex items-center gap-1 font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
    >
        <!-- icon: chevron-left -->
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            class="h-3.5 w-3.5"
            aria-hidden="true"
        >
            <path
                fill-rule="evenodd"
                d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                clip-rule="evenodd"
            />
        </svg>
        {{ label ?? $t('super_admin.dashboard.back') }}
    </Link>
</template>
