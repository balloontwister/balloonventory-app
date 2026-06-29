<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminBackLink from '@/Components/AdminBackLink.vue';
import BusinessActionMenu from '@/Components/BusinessActionMenu.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    // Named businessList (not "businesses") so it doesn't clobber the globally
    // shared "businesses" prop (the user's memberships) used by BusinessSwitcher.
    businessList: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

// ── Filters + search ──────────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const plan = ref(props.filters.plan ?? '');
const sortCol = ref(props.filters.sort ?? 'created_at');
const sortDir = ref(props.filters.dir ?? 'desc');
const perPage = ref(props.filters.per_page ?? '50');
const viewMode = ref('list');

const PER_PAGE_OPTIONS = ['25', '50', '100', 'all'];

const STATUS_FILTERS = [
    { value: '', label: 'super_admin.businesses.filter_all' },
    { value: 'active', label: 'super_admin.businesses.filter_active' },
    { value: 'frozen', label: 'super_admin.businesses.filter_frozen' },
    { value: 'deleted', label: 'super_admin.businesses.filter_deleted' },
    { value: 'onboarded', label: 'super_admin.businesses.filter_onboarded' },
];

const PLAN_FILTERS = [
    { value: '', label: 'super_admin.businesses.filter_all' },
    { value: 'solo', label: 'super_admin.businesses.plan_solo' },
    { value: 'store', label: 'super_admin.businesses.plan_store' },
    { value: 'enterprise', label: 'super_admin.businesses.plan_enterprise' },
];

// Sortable list-view columns (key must match the controller's sort whitelist).
const SORTABLE_COLUMNS = [
    { key: 'name', label: 'super_admin.businesses.col_name' },
    { key: 'plan', label: 'super_admin.businesses.col_plan' },
    { key: 'members', label: 'super_admin.businesses.col_members' },
    { key: 'inventory_skus', label: 'super_admin.businesses.col_inventory' },
    { key: 'created_at', label: 'super_admin.businesses.col_created' },
];

// Columns whose natural first sort is descending (recent/biggest first).
const DESC_FIRST = [
    'created_at',
    'members',
    'inventory_skus',
    'inventory_bags',
];

function navigate() {
    router.get(
        route('admin.businesses.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            plan: plan.value || undefined,
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

watch([status, plan, perPage], navigate);

// Page size persists across visits (like column choices). Save on change, and
// on a fresh visit (no ?per_page= in the URL) restore the saved preference.
const PERPAGE_KEY = 'businesses.table.perPage';
watch(perPage, (v) => localStorage.setItem(PERPAGE_KEY, v));

const VIEW_MODE_KEY = 'businesses.view.mode';
watch(viewMode, (v) => localStorage.setItem(VIEW_MODE_KEY, v));

onMounted(() => {
    const urlHasPerPage = new URLSearchParams(window.location.search).has('per_page');
    if (!urlHasPerPage) {
        const saved = localStorage.getItem(PERPAGE_KEY);
        if (saved && PER_PAGE_OPTIONS.includes(saved) && saved !== perPage.value) {
            perPage.value = saved;
        }
    }

    const savedView = localStorage.getItem(VIEW_MODE_KEY);
    if (savedView && ['list', 'card'].includes(savedView)) {
        viewMode.value = savedView;
    }
});

function toggleSort(col) {
    if (sortCol.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortCol.value = col;
        sortDir.value = DESC_FIRST.includes(col) ? 'desc' : 'asc';
    }
    navigate();
}

function statusBadgeClass(business) {
    if (business.deleted_at) return 'badge-red';
    if (business.frozen_at) return 'badge-orange';
    return 'badge-green';
}

function statusLabel(business) {
    if (business.deleted_at) return 'super_admin.businesses.status_deleted';
    if (business.frozen_at) return 'super_admin.businesses.status_frozen';
    return 'super_admin.businesses.status_active';
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="$t('super_admin.businesses.meta_title')" />

        <div class="min-h-full bg-gray-50 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <AdminBackLink />
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ $t('super_admin.businesses.heading') }}
                            </h1>
                            <p class="mt-1 text-gray-600 dark:text-gray-400">
                                {{ $t('super_admin.businesses.description') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="mb-6 space-y-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <!-- Search + Filters -->
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('super_admin.businesses.search_placeholder')"
                            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />

                        <select
                            v-model="status"
                            class="rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">{{ $t('super_admin.businesses.filter_status') }}</option>
                            <option value="active">{{ $t('super_admin.businesses.filter_active') }}</option>
                            <option value="frozen">{{ $t('super_admin.businesses.filter_frozen') }}</option>
                            <option value="deleted">{{ $t('super_admin.businesses.filter_deleted') }}</option>
                            <option value="onboarded">{{ $t('super_admin.businesses.filter_onboarded') }}</option>
                        </select>

                        <select
                            v-model="plan"
                            class="rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">{{ $t('super_admin.businesses.filter_plan') }}</option>
                            <option value="solo">{{ $t('super_admin.businesses.plan_solo') }}</option>
                            <option value="store">{{ $t('super_admin.businesses.plan_store') }}</option>
                            <option value="enterprise">{{ $t('super_admin.businesses.plan_enterprise') }}</option>
                        </select>

                        <div class="flex gap-2">
                            <button
                                @click="viewMode = 'list'"
                                :class="[
                                    'rounded-lg border px-3 py-2 text-sm font-medium transition',
                                    viewMode === 'list'
                                        ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900 dark:text-blue-100'
                                        : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                ]"
                            >
                                {{ $t('super_admin.businesses.view_list') }}
                            </button>
                            <button
                                @click="viewMode = 'card'"
                                :class="[
                                    'rounded-lg border px-3 py-2 text-sm font-medium transition',
                                    viewMode === 'card'
                                        ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900 dark:text-blue-100'
                                        : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                ]"
                            >
                                {{ $t('super_admin.businesses.view_card') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- List View -->
                <div v-if="viewMode === 'list'" class="space-y-4">
                    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <table class="w-full">
                            <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-700 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                <tr>
                                    <th
                                        v-for="col in SORTABLE_COLUMNS"
                                        :key="col.key"
                                        class="px-6 py-3 text-left"
                                    >
                                        <button
                                            type="button"
                                            @click="toggleSort(col.key)"
                                            class="group inline-flex items-center gap-1 font-medium transition hover:text-gray-900 dark:hover:text-white"
                                            :class="{ 'text-gray-900 dark:text-white': sortCol === col.key }"
                                        >
                                            {{ $t(col.label) }}
                                            <span
                                                class="text-[10px]"
                                                :class="
                                                    sortCol === col.key
                                                        ? 'opacity-100'
                                                        : 'opacity-0 group-hover:opacity-40'
                                                "
                                            >
                                                {{ sortCol === col.key && sortDir === 'asc' ? '▲' : '▼' }}
                                            </span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-left">
                                        <span class="font-medium">
                                            {{ $t('super_admin.businesses.col_status') }}
                                        </span>
                                    </th>
                                    <th class="px-6 py-3 text-right">
                                        <span class="font-medium">
                                            {{ $t('super_admin.businesses.col_actions') }}
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="business in businessList.data"
                                    :key="business.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    <td class="px-6 py-4">
                                        <Link
                                            :href="route('admin.businesses.show', business.id)"
                                            class="flex items-center gap-3 text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                        >
                                            <img
                                                :src="business.logo_url"
                                                :alt="business.name"
                                                class="h-8 w-8 rounded object-cover"
                                            />
                                            <span class="font-medium">{{ business.name }}</span>
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $t(`super_admin.businesses.plan_${business.plan}`) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ business.members_count }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <template v-if="business.inventory_skus_count > 0">
                                            {{
                                                $t(
                                                    'super_admin.businesses.inventory_value',
                                                    {
                                                        skus: business.inventory_skus_count,
                                                        bags: business.inventory_bags_total,
                                                    },
                                                )
                                            }}
                                        </template>
                                        <template v-else>
                                            {{ $t('super_admin.businesses.inventory_none') }}
                                        </template>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ new Date(business.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="[
                                                'inline-block rounded-full px-2 py-1 text-xs font-medium',
                                                statusBadgeClass(business),
                                            ]"
                                        >
                                            {{ $t(statusLabel(business)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <BusinessActionMenu :business="business" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $t('super_admin.businesses.total_count', { count: businessList.total }) }}
                        </div>
                        <div class="flex gap-2">
                            <select
                                v-model="perPage"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option v-for="option in PER_PAGE_OPTIONS" :key="option" :value="option">
                                    {{ option === 'all' ? $t('super_admin.businesses.per_page_all') : option }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Pagination Links -->
                    <div v-if="businessList.links.length > 3" class="flex justify-center gap-1">
                        <template v-for="link in businessList.links">
                            <Link
                                v-if="!link.url"
                                :key="link.label"
                                :href="link.url"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :key="link.label"
                                :href="link.url"
                                :class="[
                                    'rounded-lg border px-3 py-2 text-sm transition',
                                    link.active
                                        ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900 dark:text-blue-100'
                                        : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700',
                                ]"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>

                <!-- Card View -->
                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="business in businessList.data"
                        :key="business.id"
                        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="mb-4 flex items-start justify-between">
                            <Link
                                :href="route('admin.businesses.show', business.id)"
                                class="flex items-center gap-3 flex-1"
                            >
                                <img
                                    :src="business.logo_url"
                                    :alt="business.name"
                                    class="h-12 w-12 rounded object-cover"
                                />
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                        {{ business.name }}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ business.slug }}</p>
                                </div>
                            </Link>
                            <span
                                :class="[
                                    'ml-2 inline-block rounded-full px-2 py-1 text-xs font-medium flex-shrink-0',
                                    statusBadgeClass(business),
                                ]"
                            >
                                {{ $t(statusLabel(business)) }}
                            </span>
                        </div>

                        <div class="mb-4 space-y-2 text-sm">
                            <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                <span>{{ $t('super_admin.businesses.col_plan') }}</span>
                                <span class="font-medium">{{ $t(`super_admin.businesses.plan_${business.plan}`) }}</span>
                            </div>
                            <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                <span>{{ $t('super_admin.businesses.col_members') }}</span>
                                <span class="font-medium">{{ business.members_count }}</span>
                            </div>
                            <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                <span>{{ $t('super_admin.businesses.col_inventory') }}</span>
                                <span class="font-medium">
                                    <template v-if="business.inventory_skus_count > 0">
                                        {{
                                            $t(
                                                'super_admin.businesses.inventory_value',
                                                {
                                                    skus: business.inventory_skus_count,
                                                    bags: business.inventory_bags_total,
                                                },
                                            )
                                        }}
                                    </template>
                                    <template v-else>
                                        {{ $t('super_admin.businesses.inventory_none') }}
                                    </template>
                                </span>
                            </div>
                            <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                <span>{{ $t('super_admin.businesses.col_created') }}</span>
                                <span class="font-medium">{{ new Date(business.created_at).toLocaleDateString() }}</span>
                            </div>
                        </div>

                        <Link
                            :href="route('admin.businesses.show', business.id)"
                            class="mt-4 block text-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-900 dark:text-blue-100"
                        >
                            {{ $t('super_admin.businesses.view_details') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.badge-green {
    @apply bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100;
}

.badge-orange {
    @apply bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-100;
}

.badge-red {
    @apply bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100;
}
</style>
