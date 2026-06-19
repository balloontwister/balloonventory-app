<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    blurb: { type: String, default: '' },
});
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ title }}
                </h1>
                <Link
                    :href="route('admin.dashboard')"
                    class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.back') }}
                </Link>
            </div>
        </template>

        <div class="py-2">
            <div
                class="flex flex-col items-center gap-3 rounded-lg border border-dashed border-border-strong bg-surface px-6 py-16 text-center"
            >
                <span
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft text-accent"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-6 w-6"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-12a.75.75 0 00-1.5 0v4c0 .414.336.75.75.75h3a.75.75 0 000-1.5h-2.25V6z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </span>
                <p class="font-display text-[18px] font-semibold text-ink-primary">
                    {{ $t('super_admin.coming_soon.heading') }}
                </p>
                <p
                    v-if="blurb"
                    class="max-w-md font-sans text-[14px] text-ink-secondary"
                >
                    {{ blurb }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
