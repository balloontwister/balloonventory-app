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
        route('admin.feedback.index'),
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
        route('admin.feedback.update-status', item.id),
        { status: newStatus },
        { preserveScroll: true },
    );
}

// ── Reply to the reporting user ───────────────────────────────────────────────
const replyingTo = ref(null);
const replyBody = ref('');
const replyProcessing = ref(false);

function openReply(item) {
    replyingTo.value = item.id;
    replyBody.value = '';
}

function cancelReply() {
    replyingTo.value = null;
    replyBody.value = '';
}

function submitReply(item) {
    replyProcessing.value = true;
    router.post(
        route('admin.feedback.reply', item.id),
        { body: replyBody.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                replyingTo.value = null;
                replyBody.value = '';
                replyProcessing.value = false;
            },
            onError: () => {
                replyProcessing.value = false;
            },
        },
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
                    :href="route('admin.dashboard')"
                    class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.back') }}
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
                            <template
                                v-for="item in feedback.data"
                                :key="item.id"
                            >
                            <tr
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
                                                'admin.catalog.skus.show',
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
                                    <span
                                        v-if="item.replies && item.replies.length"
                                        class="mt-1 inline-block rounded-full bg-accent-soft px-2 py-0.5 text-[11px] font-semibold text-accent"
                                    >
                                        {{
                                            $t(
                                                'super_admin.dashboard.feedback.replied_badge',
                                            )
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-3 text-right"
                                >
                                    <div class="flex justify-end gap-2">
                                        <button
                                            v-if="item.user?.email"
                                            type="button"
                                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-primary transition hover:bg-background"
                                            @click="openReply(item)"
                                        >
                                            {{
                                                $t(
                                                    'super_admin.dashboard.feedback.reply_button',
                                                )
                                            }}
                                        </button>
                                        <span
                                            v-else
                                            class="self-center font-sans text-[12px] text-ink-tertiary"
                                        >
                                            {{
                                                $t(
                                                    'super_admin.dashboard.feedback.reply_no_email_hint',
                                                )
                                            }}
                                        </span>
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

                            <!-- Existing replies + compose box -->
                            <tr
                                v-if="
                                    replyingTo === item.id ||
                                    (item.replies && item.replies.length)
                                "
                                class="bg-background/60"
                            >
                                <td colspan="7" class="px-6 py-4">
                                    <!-- Past replies -->
                                    <div
                                        v-if="item.replies && item.replies.length"
                                        class="mb-3 space-y-2"
                                    >
                                        <p
                                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                                        >
                                            {{
                                                $t(
                                                    'super_admin.dashboard.feedback.replies_heading',
                                                )
                                            }}
                                        </p>
                                        <div
                                            v-for="reply in item.replies"
                                            :key="reply.id"
                                            class="rounded-md border border-border bg-surface px-3 py-2"
                                        >
                                            <p
                                                class="font-sans text-[12px] text-ink-tertiary"
                                            >
                                                {{
                                                    $t(
                                                        'super_admin.dashboard.feedback.reply_meta',
                                                        {
                                                            date: formatDateTime(
                                                                reply.created_at,
                                                            ),
                                                        },
                                                    )
                                                }}
                                                <template v-if="reply.user">
                                                    ·
                                                    {{ reply.user.name }}
                                                </template>
                                            </p>
                                            <p
                                                class="mt-1 whitespace-pre-wrap font-sans text-[14px] text-ink-primary"
                                            >
                                                {{ reply.body }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Compose -->
                                    <div v-if="replyingTo === item.id">
                                        <textarea
                                            v-model="replyBody"
                                            rows="4"
                                            :placeholder="
                                                $t(
                                                    'super_admin.dashboard.feedback.reply_placeholder',
                                                )
                                            "
                                            :disabled="replyProcessing"
                                            class="w-full resize-y rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:opacity-50"
                                        />
                                        <div
                                            class="mt-2 flex items-center justify-between gap-3"
                                        >
                                            <p
                                                class="font-sans text-[12px] text-ink-tertiary"
                                            >
                                                {{
                                                    $t(
                                                        'super_admin.dashboard.feedback.reply_footnote',
                                                        {
                                                            email:
                                                                item.user
                                                                    ?.email ?? '',
                                                        },
                                                    )
                                                }}
                                            </p>
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary transition hover:bg-background"
                                                    :disabled="replyProcessing"
                                                    @click="cancelReply"
                                                >
                                                    {{
                                                        $t(
                                                            'super_admin.dashboard.feedback.reply_cancel',
                                                        )
                                                    }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-md bg-accent px-3 py-1.5 font-sans text-[13px] font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
                                                    :disabled="
                                                        replyProcessing ||
                                                        !replyBody.trim()
                                                    "
                                                    @click="submitReply(item)"
                                                >
                                                    {{
                                                        replyProcessing
                                                            ? $t(
                                                                  'super_admin.dashboard.feedback.reply_submitting',
                                                              )
                                                            : $t(
                                                                  'super_admin.dashboard.feedback.reply_submit',
                                                              )
                                                    }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
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
