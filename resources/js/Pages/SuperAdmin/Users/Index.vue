<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { pushToast } from '@/Composables/useToast';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const isSuperAdmin = page.props.auth?.isSuperAdmin ?? false;
const selfId = page.props.auth?.user?.id ?? null;

// ── Filters + search ──────────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const sortCol = ref(props.filters.sort ?? 'last_login_at');
const sortDir = ref(props.filters.dir ?? 'desc');
const perPage = ref(props.filters.per_page ?? '50');

const PER_PAGE_OPTIONS = ['25', '50', '100', 'all'];

const STATUS_FILTERS = [
    { value: '', label: 'super_admin.users.filter_all' },
    { value: 'active', label: 'super_admin.users.filter_active' },
    { value: 'frozen', label: 'super_admin.users.filter_frozen' },
    { value: 'deleted', label: 'super_admin.users.filter_deleted' },
    { value: 'admins', label: 'super_admin.users.filter_admins' },
];

// Columns whose natural first sort is descending (recent/biggest first).
const DESC_FIRST = [
    'created_at',
    'last_login_at',
    'inventory',
    'activity',
    'businesses',
];

function navigate() {
    router.get(
        route('admin.users.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            sort: sortCol.value,
            dir: sortDir.value,
            per_page: perPage.value !== '50' ? perPage.value : undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let debounce;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(navigate, 350);
});

watch([status, perPage], navigate);

// Page size persists across visits (like column choices). Save on change, and
// on a fresh visit (no ?per_page= in the URL) restore the saved preference.
const PERPAGE_KEY = 'users.table.perPage';
watch(perPage, (v) => localStorage.setItem(PERPAGE_KEY, v));

onMounted(() => {
    const urlHasPerPage = new URLSearchParams(window.location.search).has(
        'per_page',
    );
    if (urlHasPerPage) return;
    const saved = localStorage.getItem(PERPAGE_KEY);
    if (saved && PER_PAGE_OPTIONS.includes(saved) && saved !== perPage.value) {
        perPage.value = saved; // triggers navigate()
    }
});

// ── Column visibility (remembered per browser) ────────────────────────────────
const TOGGLEABLE = [
    'email',
    'businesses',
    'inventory',
    'activity',
    'created_at',
    'last_login_at',
];
const COL_DEFAULTS = {
    email: true,
    businesses: true,
    inventory: true,
    activity: false,
    created_at: false,
    last_login_at: true,
};
const COLS_KEY = 'users.table.cols';

function loadCols() {
    try {
        const saved = JSON.parse(localStorage.getItem(COLS_KEY));
        return { ...COL_DEFAULTS, ...(saved || {}) };
    } catch {
        return { ...COL_DEFAULTS };
    }
}

const visibleCols = ref(loadCols());
watch(
    visibleCols,
    (v) => localStorage.setItem(COLS_KEY, JSON.stringify(v)),
    { deep: true },
);

// name + status (admin_level) + actions are always shown.
function isColVisible(col) {
    if (col === 'name' || col === 'admin_level') return true;
    return !!visibleCols.value[col];
}

const columnCount = computed(
    () => 3 + TOGGLEABLE.filter((c) => visibleCols.value[c]).length,
);

const colsMenuOpen = ref(false);

function sortBy(col) {
    if (sortCol.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortCol.value = col;
        sortDir.value = DESC_FIRST.includes(col) ? 'desc' : 'asc';
    }
    navigate();
}

const SORTABLE = {
    name: 'super_admin.users.col_name',
    email: 'super_admin.users.col_email',
    businesses: 'super_admin.users.col_businesses',
    inventory: 'super_admin.users.col_inventory',
    activity: 'super_admin.users.col_activity',
    created_at: 'super_admin.users.col_registered',
    last_login_at: 'super_admin.users.col_last_login',
    admin_level: 'super_admin.users.col_status',
};

function formatDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(val) {
    if (!val) return '—';
    return new Date(val).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function numberFmt(n) {
    return new Intl.NumberFormat().format(n ?? 0);
}

// ── Per-row action menu (teleported so the table's overflow can't clip it) ─────
const openMenuId = ref(null);
const activeUser = ref(null);
const menuStyle = ref({});

function toggleMenu(user, event) {
    if (openMenuId.value === user.id) {
        closeMenu();
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    const width = 224;
    let left = rect.right - width;
    if (left < 8) left = 8;
    menuStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${left}px`,
        width: `${width}px`,
    };
    activeUser.value = user;
    openMenuId.value = user.id;
}

function closeMenu() {
    openMenuId.value = null;
    activeUser.value = null;
}

function onKey(e) {
    if (e.key === 'Escape') closeMenu();
}

onMounted(() => {
    document.addEventListener('keydown', onKey);
    window.addEventListener('scroll', closeMenu, true);
    window.addEventListener('resize', closeMenu);
});
onUnmounted(() => {
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('scroll', closeMenu, true);
    window.removeEventListener('resize', closeMenu);
});

// Which actions apply to the menu's active user.
const menu = computed(() => {
    const u = activeUser.value;
    if (!u) return {};
    const isSuper = u.admin_level === 'super_admin';
    const isSite = u.admin_level === 'site_admin';
    const isSelf = u.id === selfId;
    const deleted = !!u.deleted_at;
    const frozen = !!u.frozen_at;
    return {
        promote: isSuperAdmin && !u.admin_level && !deleted,
        demote: isSuperAdmin && isSite,
        freeze: !deleted && !isSuper && !isSelf && !frozen,
        thaw: !deleted && frozen,
        reset: !deleted,
        copy: true,
        delete: isSuperAdmin && !u.admin_level && !isSelf && !deleted,
    };
});

// ── Actions ───────────────────────────────────────────────────────────────────
function promote(user) {
    closeMenu();
    router.post(
        route('admin.users.promote', user.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function demote(user) {
    closeMenu();
    if (!window.confirm(trans('super_admin.users.demote_confirm', { name: user.name }))) {
        return;
    }
    router.delete(route('admin.users.demote', user.id), {
        preserveScroll: true,
        preserveState: true,
    });
}

function freeze(user) {
    closeMenu();
    if (!window.confirm(trans('super_admin.users.freeze_confirm', { name: user.name }))) {
        return;
    }
    router.post(
        route('admin.users.freeze', user.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function thaw(user) {
    closeMenu();
    router.delete(route('admin.users.thaw', user.id), {
        preserveScroll: true,
        preserveState: true,
    });
}

function sendReset(user) {
    closeMenu();
    router.post(
        route('admin.users.password-reset', user.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

async function copyEmail(user) {
    closeMenu();
    try {
        await navigator.clipboard.writeText(user.email);
        pushToast(trans('super_admin.users.copy_email_done'), 'info');
    } catch {
        /* clipboard unavailable — no-op */
    }
}

function destroyUser(user) {
    closeMenu();
    if (!window.confirm(trans('super_admin.users.delete_confirm', { name: user.name }))) {
        return;
    }
    router.delete(route('admin.users.destroy', user.id), {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head :title="$t('super_admin.users.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.users.heading') }}
                </h1>
                <AdminBackLink />
            </div>
        </template>

        <div class="py-2">
            <div class="rounded-lg border border-border bg-surface">
                <!-- Toolbar -->
                <div class="border-b border-border px-6 py-4">
                    <p class="font-sans text-[13px] text-ink-secondary">
                        {{ $t('super_admin.users.description') }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="
                                $t('super_admin.users.search_placeholder')
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

                        <!-- Columns visibility -->
                        <div class="relative ml-auto">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-secondary transition hover:bg-background"
                                @click="colsMenuOpen = !colsMenuOpen"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4"
                                >
                                    <path
                                        d="M3 4.5A1.5 1.5 0 014.5 3h11A1.5 1.5 0 0117 4.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 15.5v-11zM8 4.5v11h1.5v-11H8zm-1.5 0H4.5v11h2v-11z"
                                    />
                                </svg>
                                {{ $t('super_admin.users.columns_button') }}
                            </button>
                            <template v-if="colsMenuOpen">
                                <div
                                    class="fixed inset-0 z-40"
                                    @click="colsMenuOpen = false"
                                />
                                <div
                                    class="absolute right-0 z-50 mt-2 w-56 rounded-md border border-border bg-surface py-1 shadow-lg"
                                >
                                    <label
                                        v-for="col in TOGGLEABLE"
                                        :key="col"
                                        class="flex cursor-pointer items-center gap-2 px-4 py-2 font-sans text-[13px] text-ink-primary transition hover:bg-background"
                                    >
                                        <input
                                            v-model="visibleCols[col]"
                                            type="checkbox"
                                            class="rounded border-border-strong text-accent focus:ring-accent-soft"
                                        />
                                        {{ $t(SORTABLE[col]) }}
                                    </label>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full font-sans text-[13px]">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-ink-secondary"
                            >
                                <th
                                    v-for="(label, col) in SORTABLE"
                                    v-show="isColVisible(col)"
                                    :key="col"
                                    class="px-6 py-3 font-medium"
                                >
                                    <button
                                        type="button"
                                        class="group inline-flex items-center gap-1 transition hover:text-ink-primary"
                                        :class="{
                                            'text-ink-primary': sortCol === col,
                                        }"
                                        @click="sortBy(col)"
                                    >
                                        {{ $t(label) }}
                                        <span
                                            class="text-[10px]"
                                            :class="
                                                sortCol === col
                                                    ? 'opacity-100'
                                                    : 'opacity-0 group-hover:opacity-40'
                                            "
                                        >
                                            {{
                                                sortCol === col && sortDir === 'asc'
                                                    ? '▲'
                                                    : '▼'
                                            }}
                                        </span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-right font-medium">
                                    {{ $t('super_admin.users.col_actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            <tr v-if="users.data.length === 0">
                                <td
                                    :colspan="columnCount"
                                    class="px-6 py-10 text-center text-ink-tertiary"
                                >
                                    —
                                </td>
                            </tr>
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="text-ink-primary"
                                :class="{ 'opacity-60': user.deleted_at }"
                            >
                                <!-- Name -->
                                <td class="px-6 py-3">
                                    <span
                                        class="font-medium"
                                        :class="{
                                            'line-through': user.deleted_at,
                                        }"
                                    >
                                        {{ user.name }}
                                    </span>
                                </td>

                                <!-- Email -->
                                <td
                                    v-show="visibleCols.email"
                                    class="px-6 py-3 text-ink-secondary"
                                >
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <span
                                            v-if="!user.email_verified_at"
                                            class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-warning"
                                            :title="
                                                $t(
                                                    'super_admin.users.email_unverified',
                                                )
                                            "
                                        />
                                        <span
                                            :class="{
                                                'line-through': user.deleted_at,
                                            }"
                                        >
                                            {{ user.original_email ?? user.email }}
                                        </span>
                                    </span>
                                </td>

                                <!-- Businesses -->
                                <td v-show="visibleCols.businesses" class="px-6 py-3">
                                    <div
                                        v-if="user.businesses.length"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="b in user.businesses"
                                            :key="b.id"
                                            class="inline-flex rounded-full bg-background px-2 py-0.5 text-[11px] text-ink-secondary ring-1 ring-inset ring-border"
                                            :title="b.role"
                                        >
                                            {{ b.name }}
                                        </span>
                                    </div>
                                    <span v-else class="text-ink-tertiary">
                                        {{ $t('super_admin.users.businesses_none') }}
                                    </span>
                                </td>

                                <!-- Inventory -->
                                <td
                                    v-show="visibleCols.inventory"
                                    class="px-6 py-3 text-ink-secondary"
                                >
                                    <span v-if="user.inventory_skus_count > 0">
                                        {{
                                            $t('super_admin.users.inventory_value', {
                                                skus: numberFmt(
                                                    user.inventory_skus_count,
                                                ),
                                                bags: numberFmt(
                                                    user.inventory_bags_total,
                                                ),
                                            })
                                        }}
                                    </span>
                                    <span v-else class="text-ink-tertiary">
                                        {{ $t('super_admin.users.inventory_none') }}
                                    </span>
                                </td>

                                <!-- Activity -->
                                <td
                                    v-show="visibleCols.activity"
                                    class="px-6 py-3 text-ink-secondary"
                                >
                                    {{
                                        $t('super_admin.users.activity_value', {
                                            tickets: user.support_tickets_count,
                                            feedback: user.sku_feedback_count,
                                        })
                                    }}
                                </td>

                                <!-- Registered -->
                                <td
                                    v-show="visibleCols.created_at"
                                    class="px-6 py-3 text-ink-secondary"
                                >
                                    {{ formatDate(user.created_at) }}
                                </td>

                                <!-- Last login -->
                                <td
                                    v-show="visibleCols.last_login_at"
                                    class="px-6 py-3 text-ink-secondary"
                                >
                                    {{ formatDateTime(user.last_login_at) }}
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-3">
                                    <span
                                        v-if="user.deleted_at"
                                        class="inline-flex rounded-full bg-background px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                                    >
                                        {{ $t('super_admin.users.status_deleted') }}
                                    </span>
                                    <span
                                        v-else-if="user.frozen_at"
                                        class="inline-flex rounded-full bg-warning-soft px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-eyebrow text-warning"
                                    >
                                        {{ $t('super_admin.users.status_frozen') }}
                                    </span>
                                    <span
                                        v-else-if="user.admin_level === 'super_admin'"
                                        class="inline-flex rounded-full bg-accent-soft px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                                    >
                                        {{ $t('super_admin.users.level_super_admin') }}
                                    </span>
                                    <span
                                        v-else-if="user.admin_level === 'site_admin'"
                                        class="inline-flex rounded-full bg-success-soft px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-eyebrow text-success"
                                    >
                                        {{ $t('super_admin.users.level_site_admin') }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex rounded-full bg-background px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                    >
                                        {{ $t('super_admin.users.status_active') }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-3 text-right">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border-strong text-ink-secondary transition hover:bg-background"
                                        :aria-label="$t('super_admin.users.actions_menu')"
                                        @click="toggleMenu(user, $event)"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                            class="h-4 w-4"
                                        >
                                            <path
                                                d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"
                                            />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer: total + page size + pager -->
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-6 py-3"
                >
                    <div
                        class="flex items-center gap-3 font-sans text-[13px] text-ink-secondary"
                    >
                        <span>
                            {{
                                $t('super_admin.users.total_count', {
                                    count: users.total,
                                })
                            }}
                        </span>
                        <label class="flex items-center gap-1.5">
                            <span class="text-ink-tertiary">
                                {{ $t('super_admin.users.per_page_label') }}
                            </span>
                            <select
                                v-model="perPage"
                                class="rounded-md border border-border-strong bg-surface px-2 py-1 text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            >
                                <option
                                    v-for="opt in PER_PAGE_OPTIONS"
                                    :key="opt"
                                    :value="opt"
                                >
                                    {{
                                        opt === 'all'
                                            ? $t('super_admin.users.per_page_all')
                                            : opt
                                    }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div v-if="users.last_page > 1" class="flex items-center gap-2">
                        <span class="font-sans text-[13px] text-ink-secondary">
                            {{ users.current_page }} / {{ users.last_page }}
                        </span>
                        <Link
                            v-if="users.prev_page_url"
                            :href="users.prev_page_url"
                            preserve-state
                            preserve-scroll
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ‹
                        </Link>
                        <Link
                            v-if="users.next_page_url"
                            :href="users.next_page_url"
                            preserve-state
                            preserve-scroll
                            class="rounded-md border border-border-strong px-3 py-1.5 font-sans text-[13px] text-ink-secondary hover:bg-background"
                        >
                            ›
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teleported action menu -->
        <Teleport to="body">
            <template v-if="openMenuId && activeUser">
                <div class="fixed inset-0 z-40" @click="closeMenu" />
                <div
                    class="fixed z-50 overflow-hidden rounded-md border border-border bg-surface py-1 shadow-lg"
                    :style="menuStyle"
                >
                    <button
                        v-if="menu.promote"
                        type="button"
                        class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                        @click="promote(activeUser)"
                    >
                        {{ $t('super_admin.users.promote_button') }}
                    </button>
                    <button
                        v-if="menu.demote"
                        type="button"
                        class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                        @click="demote(activeUser)"
                    >
                        {{ $t('super_admin.users.demote_button') }}
                    </button>
                    <button
                        v-if="menu.freeze"
                        type="button"
                        class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                        @click="freeze(activeUser)"
                    >
                        {{ $t('super_admin.users.freeze_button') }}
                    </button>
                    <button
                        v-if="menu.thaw"
                        type="button"
                        class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                        @click="thaw(activeUser)"
                    >
                        {{ $t('super_admin.users.thaw_button') }}
                    </button>
                    <button
                        v-if="menu.reset"
                        type="button"
                        class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                        @click="sendReset(activeUser)"
                    >
                        {{ $t('super_admin.users.reset_button') }}
                    </button>
                    <button
                        v-if="menu.copy"
                        type="button"
                        class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                        @click="copyEmail(activeUser)"
                    >
                        {{ $t('super_admin.users.copy_email') }}
                    </button>
                    <button
                        v-if="menu.delete"
                        type="button"
                        class="block w-full border-t border-border px-4 py-2 text-left font-sans text-[13px] text-danger transition hover:bg-danger-soft"
                        @click="destroyUser(activeUser)"
                    >
                        {{ $t('super_admin.users.delete_button') }}
                    </button>
                </div>
            </template>
        </Teleport>
    </AuthenticatedLayout>
</template>
