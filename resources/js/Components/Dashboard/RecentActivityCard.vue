<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    activities: { type: Array, required: true },
});

const page = usePage();
const locale = computed(() => page.props.locale ?? 'en');

function relativeTime(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime();
    const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' });
    const intervals = [
        ['year', 31536000000],
        ['month', 2592000000],
        ['week', 604800000],
        ['day', 86400000],
        ['hour', 3600000],
        ['minute', 60000],
    ];
    for (const [unit, ms] of intervals) {
        if (diff >= ms) {
            return rtf.format(-Math.floor(diff / ms), unit);
        }
    }
    return rtf.format(0, 'second');
}

function directionLabel(direction) {
    const key = `dashboard.activity.direction.${direction}`;
    return key;
}

function bagSummary(activity) {
    const parts = [];
    if (activity.full_bags_change !== 0) {
        const abs = Math.abs(activity.full_bags_change);
        parts.push(`${abs} ${abs === 1 ? 'bag' : 'bags'}`);
    }
    if (activity.open_bags_change !== 0) {
        const abs = Math.abs(activity.open_bags_change);
        parts.push(`${abs} open ${abs === 1 ? 'bag' : 'bags'}`);
    }
    return parts.join(', ');
}
</script>

<template>
    <div class="flex flex-col rounded-lg border border-border bg-surface p-5">
        <div class="flex items-center gap-2.5">
            <span
                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-accent-soft text-accent"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z"
                        clip-rule="evenodd"
                    />
                </svg>
            </span>
            <h2 class="font-sans text-[15px] font-semibold text-ink-primary">
                {{ $t('dashboard.activity.title') }}
            </h2>
        </div>

        <div class="mt-4 flex-1">
            <!-- Empty state -->
            <p
                v-if="!activities.length"
                class="font-sans text-[13px] text-ink-tertiary"
            >
                {{ $t('dashboard.activity.empty') }}
            </p>

            <!-- Activity feed -->
            <ul v-else class="flex flex-col divide-y divide-border">
                <li
                    v-for="activity in activities"
                    :key="activity.id"
                    class="flex items-start gap-3 py-2.5"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-sans text-[13px] text-ink-primary">
                            <span class="font-semibold">{{ activity.user_name }}</span>
                            {{ ' ' }}
                            <span class="text-ink-secondary">{{ $t(directionLabel(activity.direction)) }}</span>
                            {{ ' ' }}
                            <span>{{ bagSummary(activity) }}</span>
                            <span v-if="activity.sku_name" class="text-ink-secondary">
                                {{ ' · ' }}{{ activity.sku_name }}
                            </span>
                        </p>
                    </div>
                    <span class="flex-shrink-0 whitespace-nowrap font-sans text-[12px] text-ink-tertiary">
                        {{ relativeTime(activity.created_at) }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
