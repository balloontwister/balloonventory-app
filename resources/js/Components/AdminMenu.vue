<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

// Shield-icon dropdown that lists the admin areas the current admin can reach.
// Used in both the desktop and mobile headers. Super-Admin-only areas are hidden
// from Site Admins.
const props = defineProps({
    compact: { type: Boolean, default: false },
});

const page = usePage();
const isSuper = computed(() => page.props.auth?.isSuperAdmin ?? false);
const pendingProposalsCount = computed(() => page.props.pendingProposalsCount ?? 0);

const LINKS = [
    { key: 'overview', route: 'admin.dashboard', match: 'admin.dashboard' },
    { key: 'catalog', route: 'admin.catalog.skus', match: 'admin.catalog.*' },
    { key: 'distributors', route: 'admin.distributors.index', match: 'admin.distributors.*' },
    { key: 'proposals', route: 'admin.distributors.proposals.index', match: 'admin.distributors.proposals.*', superOnly: true },
    { key: 'users', route: 'admin.users.index', match: 'admin.users.*' },
    { key: 'feedback', route: 'admin.feedback.index', match: 'admin.feedback.*' },
    { key: 'support_tickets', route: 'admin.tickets.index', match: 'admin.tickets.*' },
    { key: 'barcode_log', route: 'admin.barcode-audits.index', match: 'admin.barcode-audits.*' },
    { key: 'login_log', route: 'admin.login-log.index', match: 'admin.login-log.*' },
    { key: 'email', route: 'admin.email-templates.index', match: 'admin.email-templates.*' },
    { key: 'backups', route: 'admin.backups.index', match: 'admin.backups.*', superOnly: true },
    { key: 'subscriptions', route: 'admin.subscriptions.index', match: 'admin.subscriptions.*', superOnly: true },
    { key: 'payments', route: 'admin.payments.index', match: 'admin.payments.*', superOnly: true },
    { key: 'affiliates', route: 'admin.affiliates.index', match: 'admin.affiliates.*', superOnly: true },
];

const visibleLinks = computed(() =>
    LINKS.filter((l) => !l.superOnly || isSuper.value),
);

const routeActive = computed(() => route().current('admin.*'));

const open = ref(false);
const menuStyle = ref({});

function toggle(e) {
    if (open.value) {
        open.value = false;
        return;
    }
    const r = e.currentTarget.getBoundingClientRect();
    const width = 224;
    let left = r.right - width;
    if (left < 8) left = 8;
    menuStyle.value = {
        top: `${r.bottom + 6}px`,
        left: `${left}px`,
        width: `${width}px`,
    };
    open.value = true;
}

function close() {
    open.value = false;
}

function onKey(e) {
    if (e.key === 'Escape') close();
}

onMounted(() => {
    document.addEventListener('keydown', onKey);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
});
onUnmounted(() => {
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
});
</script>

<template>
    <div>
        <button
            type="button"
            :title="$t('nav.super_admin_section')"
            :aria-label="$t('nav.super_admin_section')"
            class="flex flex-shrink-0 items-center justify-center rounded-md transition"
            :class="[
                compact ? 'h-8 w-8' : 'h-9 w-9 ring-1 ring-border hover:ring-accent',
                routeActive
                    ? 'text-accent'
                    : 'text-ink-tertiary hover:bg-background hover:text-ink-primary',
            ]"
            @click="toggle"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-4 w-4"
            >
                <path
                    fill-rule="evenodd"
                    d="M9.661 2.237a.531.531 0 01.678 0 11.947 11.947 0 007.078 2.749.533.533 0 01.479.533c0 5.448-3.299 10.116-8 11.932a.535.535 0 01-.372 0c-4.701-1.816-8-6.484-8-11.932a.533.533 0 01.479-.533 11.947 11.947 0 007.078-2.749z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <Teleport to="body">
            <template v-if="open">
                <div class="fixed inset-0 z-[55]" @click="close" />
                <div
                    class="fixed z-[60] overflow-hidden rounded-md border border-border bg-surface py-1 shadow-lg"
                    :style="menuStyle"
                >
                    <p
                        class="px-4 pb-1 pt-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                    >
                        {{ $t('nav.admin') }}
                    </p>
                    <Link
                        v-for="l in visibleLinks"
                        :key="l.key"
                        :href="route(l.route)"
                        class="flex items-center justify-between px-4 py-2 font-sans text-[13px] transition hover:bg-background"
                        :class="[
                            route().current(l.match)
                                ? 'font-semibold text-accent'
                                : 'text-ink-primary',
                            l.key === 'proposals' ? 'pl-7' : '',
                        ]"
                        @click="close"
                    >
                        <span>{{ $t(`super_admin.dashboard.nav.${l.key}`) }}</span>
                        <span
                            v-if="l.key === 'proposals' && pendingProposalsCount > 0"
                            class="ml-2 rounded-full bg-accent px-1.5 py-0.5 font-sans text-[11px] font-semibold text-white"
                        >
                            {{ pendingProposalsCount }}
                        </span>
                    </Link>
                </div>
            </template>
        </Teleport>
    </div>
</template>
