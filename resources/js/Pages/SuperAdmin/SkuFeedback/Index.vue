<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    feedback: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    openCount: { type: Number, default: 0 },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

const STATUS_FILTERS = [
    { value: '', label: 'super_admin.dashboard.feedback.filter_all' },
    { value: 'open', label: 'super_admin.dashboard.feedback.filter_open' },
    { value: 'resolved', label: 'super_admin.dashboard.feedback.filter_resolved' },
    {
        value: 'dismissed',
        label: 'super_admin.dashboard.feedback.filter_dismissed',
    },
];

function applyFilters() {
    router.get(
        route('super-admin.feedback.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

let debounce;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});

watch(status, applyFilters);

function formatDateTime(val) {
    if (!val) return '—';
    return new Date(val).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function updateStatus(item, newStatus) {
    router.patch(
        route('super-admin.feedback.update-status', item.id),
        { status: newStatus },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="$t('super_admin.dashboard.feedback.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.feedback.heading') }}
                </h1>
                <Link
                    :href="route('super-admin.dashboard')"
                    class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.nav.overview') }}
                </Link>
            </div>
        </template>

        <div class="py-2">
            <div class="rounded-lg border border-border bg-surface">
                <div class="border-b border-border px-6 py-4">
                    <div class="flex items-center gap-3">
                        <p class="font-sans text-[13px] text-ink-secondary">
                            {{ $t('super_admin.dashboard.feedback.subheading') }}
                        </p>
                        <span
                            v-if="openCount > 0"
                            class="rounded-full bg-accent-soft px-2 py-0.5 font-sans text-[12px] font-semibold text-accent"
                        >
                            {{
                                $t('super_admin.dashboard.feedback.open_count', {
                                    count: openCount,
                                })
                            }}
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="
                                $t(
                                    'super_admin.dashboard.feedback.search_placeholder',
                                )
                            "
                            class="w-72 max-w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        />
                        <select
                            v-model="status"
                            class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        >
                            <option
                                v-for="opt in STATUS_FILTERS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ $t(opt.label) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-ink-secondary"
                            >
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.feedback.col_when',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.feedback.col_user',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.feedback.col_product',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.feedback.col_field',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.feedback.col_report',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.feedback.col_status',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="feedback.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-6 py-10 text-center text-ink-tertiary"
                                >
                                    {{
                                        $t('super_admin.dashboard.feedback.empty')
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-for="item in feedback.data"
                                :key="item.id"
                                class="align-top text-ink-primary"
                                :class="{ 'opacity-60': item.status !== 'open' }"
                            >
                                <td
                                    class="whitespace-nowrap px-6 py-3 text-ink-secondary"
                                >
                                    {{ formatDateTime(item.created_at) }}
                                </td>
                                <td class="px-6 py-3">
                                    {{ item.user?.name ?? '—' }}
                                    <span
                                        v-if="item.business"
                                        class="block text-[12px] text-ink-tertiary"
                                    >
                                        {{ item.business.name }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <Link
                                        :href="
                                            route(
                                                'super-admin.catalog.skus.show',
                                                item.sku_id,
                                            )
                                        "
                                        class="text-accent hover:underline"
                                    >
                                        {{ item.sku_name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-3 text-ink-secondary">
                                    {{
                                        $t(
                                            `inventory.show.feedback_field_${item.field}`,
                                        )
                                    }}
                                </td>
                                <td class="px-6 py-3 text-ink-secondary">
                                    <p v-if="item.suggested_value">
                                        <span
                                            v-if="item.current_value"
                                            class="text-ink-tertiary line-through"
                                            >{{ item.current_value }}</span
                                        >
                                        <span class="text-ink-tertiary">
                                            {{
                                                $t(
                                                    'super_admin.dashboard.feedback.report_says',
                                                )
                                            }}
                                        </span>
                                        <span class="font-medium text-ink-primary">
                                            {{ item.suggested_value }}
                                        </span>
                                    </p>
                                    <p
                                        v-if="item.note"
                                        class="mt-0.5 text-[12px] text-ink-tertiary"
                                    >
                                        {{ item.note }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3">
                                    {{
                                        $t(
                                            `super_admin.dashboard.feedback.status_${item.status}`,
                                        )
                                    }}
                                    <span
                                        v-if="
                                            item.status !== 'open' &&
                                            item.resolved_by
                                        "
                                        class="block text-[12px] text-ink-tertiary"
                                    >
                                        {{
                                            $t(
                                                'super_admin.dashboard.feedback.reviewed_by',
                                                {
                                                    name: item.resolved_by.name,
                                                },
                                            )
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-3 text-right"
                                >
                                    <div class="flex justify-end gap-2">
                                        <template v-if="item.status === 'open'">
                                            <button
                                                type="button"
                                                class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-accent transition hover:bg-accent-soft"
                                                @click="
                                                    updateStatus(item, 'resolved')
                                                "
                                            >
                                                {{
                                                    $t(
                                                        'super_admin.dashboard.feedback.action_resolve',
                                                    )
                                                }}
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary transition hover:bg-background"
                                                @click="
                                                    updateStatus(
                                                        item,
                                                        'dismissed',
                                                    )
                                                "
                                            >
                                                {{
                                                    $t(
                                                        'super_admin.dashboard.feedback.action_dismiss',
                                                    )
                                                }}
                                            </button>
                                        </template>
                                        <button
                                            v-else
                                            type="button"
                                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary transition hover:bg-background"
                                            @click="updateStatus(item, 'open')"
                                        >
                                            {{
                                                $t(
                                                    'super_admin.dashboard.feedback.action_reopen',
                                                )
                                            }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="feedback.last_page > 1"
                    class="flex items-center justify-between border-t border-border px-6 py-3"
                >
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ feedback.current_page }} / {{ feedback.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="feedback.prev_page_url"
                            :href="feedback.prev_page_url"
                            preserve-state
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ‹
                        </Link>
                        <Link
                            v-if="feedback.next_page_url"
                            :href="feedback.next_page_url"
                            preserve-state
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ›
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
