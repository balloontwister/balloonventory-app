<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
});
</script>

<template>
    <Link
        :href="href"
        class="inline-flex min-h-[44px] items-center gap-1 font-sans text-[14px] text-ink-secondary transition hover:text-ink-primary"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            class="h-4 w-4 flex-shrink-0"
        >
            <path
                fill-rule="evenodd"
                d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                clip-rule="evenodd"
            />
        </svg>
        {{ label }}
    </Link>
</template>
