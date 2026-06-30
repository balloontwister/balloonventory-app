<script setup>
import LegalLayout from '@/Layouts/LegalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    // [{ doc, title, summary, url }]
    documents: { type: Array, default: () => [] },
    updatedAt: { type: String, default: '' },
});
</script>

<template>
    <Head :title="$t('legal.hub.meta_title')" />

    <LegalLayout>
        <h1
            class="font-display text-[26px] font-semibold tracking-h2 text-ink-primary"
        >
            {{ $t('legal.hub.heading') }}
        </h1>
        <p
            class="mt-2 font-sans text-[15px] leading-relaxed text-ink-secondary"
        >
            {{ $t('legal.hub.subheading') }}
        </p>

        <ul class="mt-8 flex flex-col gap-3">
            <li v-for="document in documents" :key="document.doc">
                <Link
                    :href="document.url"
                    class="block rounded-lg border border-border bg-surface px-5 py-4 shadow-pop transition hover:border-accent"
                >
                    <span
                        class="font-display text-[16px] font-semibold text-ink-primary"
                    >
                        {{ document.title }}
                    </span>
                    <span
                        class="mt-1 block font-sans text-[13px] text-ink-secondary"
                    >
                        {{ document.summary }}
                    </span>
                </Link>
            </li>
        </ul>
    </LegalLayout>
</template>
