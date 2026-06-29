<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { pushToast } from '@/Composables/useToast';

const props = defineProps({
    business: { type: Object, required: true },
    showViewDetails: { type: Boolean, default: true },
});

const page = usePage();
const isSuperAdmin = page.props.auth?.isSuperAdmin ?? false;

// ── Per-instance open state ───────────────────────────────────────────────────
const isOpen = ref(false);
const menuStyle = ref({});
const confirmDialog = ref(null);
const pendingAction = ref(null);

function toggleMenu(event) {
    if (isOpen.value) {
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
    isOpen.value = true;
}

function closeMenu() {
    isOpen.value = false;
}

function onKey(e) {
    if (e.key === 'Escape') {
        closeMenu();
    }
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

// ── Menu visibility ───────────────────────────────────────────────────────────
const menu = computed(() => {
    const b = props.business;
    const deleted = !!b.deleted_at;
    const frozen = !!b.frozen_at;
    const hasOwner = !!b.owner_id;
    return {
        viewDetails: props.showViewDetails && !deleted,
        emailOwner: !deleted && hasOwner,
        impersonateOwner: !deleted && !frozen && hasOwner,
        suspend: !deleted && !frozen,
        unsuspend: !deleted && frozen,
        delete: isSuperAdmin && !deleted,
    };
});

const emailOwnerHref = computed(() =>
    route('admin.email-templates.index', { user: props.business.owner_id }),
);

function impersonateOwner() {
    closeMenu();
    router.post(
        route('admin.users.impersonate', props.business.owner_id),
        {},
        { onError: () => pushToast(trans('super_admin.businesses.action_failed'), 'error') },
    );
}

// ── Actions ───────────────────────────────────────────────────────────────────
function confirmAction(action) {
    pendingAction.value = action;
    confirmDialog.value?.showModal();
    closeMenu();
}

function executeAction() {
    const action = pendingAction.value;
    if (!action) return;

    if (action.type === 'suspend') {
        router.post(
            route('admin.businesses.suspend', props.business.id),
            {},
            {
                onError: () => pushToast(trans('super_admin.businesses.action_failed'), 'error'),
            },
        );
    } else if (action.type === 'unsuspend') {
        router.delete(
            route('admin.businesses.thaw', props.business.id),
            {
                onError: () => pushToast(trans('super_admin.businesses.action_failed'), 'error'),
            },
        );
    } else if (action.type === 'delete') {
        router.delete(
            route('admin.businesses.destroy', props.business.id),
            {
                onError: () => pushToast(trans('super_admin.businesses.action_failed'), 'error'),
            },
        );
    }

    confirmDialog.value?.close();
    pendingAction.value = null;
}

function cancelAction() {
    confirmDialog.value?.close();
    pendingAction.value = null;
}
</script>

<template>
    <div>
        <!-- Trigger Button -->
        <button
            ref="triggerRef"
            @click="toggleMenu"
            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
        >
            {{ __('super_admin.businesses.actions_menu') }}
        </button>

        <!-- Teleported Fixed Menu -->
        <Teleport to="body">
            <div
                v-if="isOpen"
                :style="menuStyle"
                class="fixed z-50 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
                <!-- View Details -->
                <Link
                    v-if="menu.viewDetails"
                    :href="route('admin.businesses.show', business.id)"
                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="closeMenu"
                >
                    {{ __('super_admin.businesses.view_details') }}
                </Link>

                <!-- Email Owner -->
                <Link
                    v-if="menu.emailOwner"
                    :href="emailOwnerHref"
                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="closeMenu"
                >
                    {{ __('super_admin.businesses.email_owner') }}
                </Link>

                <!-- Impersonate Owner -->
                <button
                    v-if="menu.impersonateOwner"
                    type="button"
                    class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="impersonateOwner"
                >
                    {{ __('super_admin.businesses.impersonate_owner') }}
                </button>

                <!-- Divider -->
                <div
                    v-if="menu.suspend || menu.unsuspend || menu.delete"
                    class="border-t border-gray-200 dark:border-gray-700"
                />

                <!-- Suspend -->
                <button
                    v-if="menu.suspend"
                    type="button"
                    class="w-full px-4 py-2 text-left text-sm text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-900/20"
                    @click="confirmAction({ type: 'suspend' })"
                >
                    {{ __('super_admin.businesses.suspend_button') }}
                </button>

                <!-- Unsuspend -->
                <button
                    v-if="menu.unsuspend"
                    type="button"
                    class="w-full px-4 py-2 text-left text-sm text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20"
                    @click="confirmAction({ type: 'unsuspend' })"
                >
                    {{ __('super_admin.businesses.unsuspend_button') }}
                </button>

                <!-- Delete -->
                <button
                    v-if="menu.delete"
                    type="button"
                    class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                    @click="confirmAction({ type: 'delete' })"
                >
                    {{ __('super_admin.businesses.delete_button') }}
                </button>
            </div>
        </Teleport>

        <!-- Confirmation Dialog -->
        <dialog
            ref="confirmDialog"
            class="rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <template v-if="pendingAction?.type === 'suspend'">
                        {{ __('super_admin.businesses.suspend_button') }}
                    </template>
                    <template v-else-if="pendingAction?.type === 'unsuspend'">
                        {{ __('super_admin.businesses.unsuspend_button') }}
                    </template>
                    <template v-else-if="pendingAction?.type === 'delete'">
                        {{ __('super_admin.businesses.delete_button') }}
                    </template>
                </h3>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    <template v-if="pendingAction?.type === 'suspend'">
                        {{ __('super_admin.businesses.suspend_confirm', { name: business.name }) }}
                    </template>
                    <template v-else-if="pendingAction?.type === 'unsuspend'">
                        {{ __('super_admin.businesses.unsuspend_confirm', { name: business.name }) }}
                    </template>
                    <template v-else-if="pendingAction?.type === 'delete'">
                        {{ __('super_admin.businesses.delete_confirm', { name: business.name }) }}
                    </template>
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="cancelAction"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        {{ __('super_admin.businesses.confirm_cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="executeAction"
                        :class="[
                            'rounded-lg px-4 py-2 text-sm font-medium text-white transition',
                            pendingAction?.type === 'suspend'
                                ? 'bg-orange-600 hover:bg-orange-700'
                                : pendingAction?.type === 'unsuspend'
                                  ? 'bg-green-600 hover:bg-green-700'
                                  : 'bg-red-600 hover:bg-red-700',
                        ]"
                    >
                        {{ __('super_admin.businesses.confirm_yes') }}
                    </button>
                </div>
            </div>
        </dialog>
    </div>
</template>
