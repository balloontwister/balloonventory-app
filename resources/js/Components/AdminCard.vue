<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    href: { type: String, default: null },
    stat: { type: [String, Number], default: null },
    statLabel: { type: String, default: null },
    // Secondary one-line facts shown under the stat.
    lines: { type: Array, default: () => [] },
    // Renders a dimmed, non-clickable "coming soon" card.
    soon: { type: Boolean, default: false },
});

const clickable = computed(() => !!props.href && !props.soon);
const tag = computed(() => (clickable.value ? Link : 'div'));
</script>

<template>
    <component
        :is="tag"
        :href="clickable ? href : undefined"
        class="group flex flex-col rounded-lg border border-border bg-surface p-5 text-left transition"
        :class="
            clickable
                ? 'shadow-pop hover:border-border-strong hover:bg-background'
                : soon
                  ? 'opacity-70'
                  : ''
        "
    >
        <div class="flex items-center gap-2.5">
            <span
                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent"
            >
                <slot name="icon" />
            </span>
            <h2 class="font-sans text-[15px] font-semibold text-ink-primary">
                {{ title }}
            </h2>
            <span
                v-if="soon"
                class="ml-auto rounded-full bg-background px-2 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary ring-1 ring-inset ring-border"
            >
                {{ $t('super_admin.coming_soon.badge') }}
            </span>
            <svg
                v-else-if="clickable"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                class="ml-auto h-4 w-4 flex-shrink-0 text-ink-tertiary transition group-hover:text-ink-secondary"
            >
                <path
                    fill-rule="evenodd"
                    d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                    clip-rule="evenodd"
                />
            </svg>
        </div>

        <div class="mt-4 flex flex-1 flex-col">
            <div v-if="stat !== null" class="flex items-baseline gap-2">
                <span
                    class="font-display text-[26px] font-semibold tabular-nums text-ink-primary"
                >
                    {{ stat }}
                </span>
                <span
                    v-if="statLabel"
                    class="font-sans text-[13px] text-ink-secondary"
                >
                    {{ statLabel }}
                </span>
            </div>

            <ul v-if="lines.length" class="mt-1 flex flex-col gap-0.5">
                <li
                    v-for="(line, i) in lines"
                    :key="i"
                    class="font-sans text-[12px] text-ink-tertiary"
                >
                    {{ line }}
                </li>
            </ul>

            <p
                v-if="soon"
                class="mt-1 font-sans text-[13px] text-ink-tertiary"
            >
                <slot name="soon-text" />
            </p>
        </div>
    </component>
</template>
