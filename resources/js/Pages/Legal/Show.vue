<script setup>
import LegalLayout from '@/Layouts/LegalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    doc: { type: String, required: true },
    title: { type: String, required: true },
    // Server-rendered Markdown (raw HTML stripped). See v-html note below.
    html: { type: String, default: '' },
    updatedAt: { type: String, default: '' },
});

const formattedDate = computed(() => {
    if (!props.updatedAt) {
        return '';
    }
    // Append a time so the date-only string isn't shifted a day by UTC parsing.
    return new Date(`${props.updatedAt}T00:00:00`).toLocaleDateString(
        undefined,
        {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        },
    );
});
</script>

<template>
    <Head :title="title" />

    <LegalLayout>
        <Link
            :href="route('legal.index')"
            class="inline-flex items-center gap-1 font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
        >
            ← {{ $t('legal.all_policies') }}
        </Link>

        <h1
            class="mt-4 font-display text-[26px] font-semibold tracking-h2 text-ink-primary"
        >
            {{ title }}
        </h1>
        <p
            v-if="formattedDate"
            class="mt-1 font-sans text-[13px] text-ink-tertiary"
        >
            {{ $t('legal.last_updated', { date: formattedDate }) }}
        </p>

        <!--
            v-html is intentional and safe here: the source is our own
            author-controlled Markdown (resources/legal/**), rendered server-side
            with raw HTML stripped (Str::markdown html_input=strip). It is never
            user input. This is the one sanctioned v-html in the app.
        -->
        <div
            class="mt-8 font-sans text-[15px] leading-relaxed text-ink-secondary [&_a]:text-accent [&_a]:underline [&_blockquote]:my-4 [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:text-ink-tertiary [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-[18px] [&_h2]:font-semibold [&_h2]:tracking-h3 [&_h2]:text-ink-primary [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-ink-primary [&_li]:mt-1 [&_p]:mt-4 [&_strong]:text-ink-primary [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-6"
            v-html="html"
        ></div>
    </LegalLayout>
</template>
