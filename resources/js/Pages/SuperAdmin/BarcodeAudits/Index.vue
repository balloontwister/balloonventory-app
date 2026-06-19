<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    audits: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');

let debounce;
watch(search, (val) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('super-admin.barcode-audits.index'),
            { search: val || undefined },
            { preserveState: true, replace: true },
        );
    }, 350);
});

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
</script>

<template>
    <Head :title="$t('super_admin.dashboard.barcode_audits.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.barcode_audits.heading') }}
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
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('super_admin.dashboard.barcode_audits.subheading') }}
                    </p>
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="
                            $t(
                                'super_admin.dashboard.barcode_audits.search_placeholder',
                            )
                        "
                        class="mt-3 w-72 max-w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    />
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
                                            'super_admin.dashboard.barcode_audits.col_when',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.barcode_audits.col_user',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.barcode_audits.col_business',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.barcode_audits.col_product',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.barcode_audits.col_barcode',
                                        )
                                    }}
                                </th>
                                <th class="px-6 py-3 font-medium">
                                    {{
                                        $t(
                                            'super_admin.dashboard.barcode_audits.col_field',
                                        )
                                    }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="audits.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-10 text-center text-ink-tertiary"
                                >
                                    {{
                                        $t(
                                            'super_admin.dashboard.barcode_audits.empty',
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-for="audit in audits.data"
                                :key="audit.id"
                                class="text-ink-primary"
                            >
                                <td class="whitespace-nowrap px-6 py-3 text-ink-secondary">
                                    {{ formatDateTime(audit.created_at) }}
                                </td>
                                <td class="px-6 py-3">
                                    {{ audit.user?.name ?? '—' }}
                                </td>
                                <td class="px-6 py-3 text-ink-secondary">
                                    {{ audit.business?.name ?? '—' }}
                                </td>
                                <td class="px-6 py-3">
                                    <Link
                                        :href="
                                            route(
                                                'super-admin.catalog.skus.show',
                                                audit.sku_id,
                                            )
                                        "
                                        class="text-accent hover:underline"
                                    >
                                        {{ audit.sku_name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-3 font-mono">
                                    {{ audit.barcode }}
                                </td>
                                <td class="px-6 py-3 uppercase text-ink-secondary">
                                    {{ audit.field }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="audits.last_page > 1"
                    class="flex items-center justify-between border-t border-border px-6 py-3"
                >
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ audits.current_page }} / {{ audits.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="audits.prev_page_url"
                            :href="audits.prev_page_url"
                            preserve-state
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ‹
                        </Link>
                        <Link
                            v-if="audits.next_page_url"
                            :href="audits.next_page_url"
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
