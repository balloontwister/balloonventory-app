<script setup>
import Modal from '@/Components/Modal.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { pushToast } from '@/Composables/useToast';

const props = defineProps({
    user: { type: Object, required: true },
    showViewDetails: { type: Boolean, default: true },
});

const page = usePage();
const isSuperAdmin = page.props.auth?.isSuperAdmin ?? false;
const selfId = page.props.auth?.user?.id ?? null;

// ── Per-instance open state ───────────────────────────────────────────────────
const isOpen = ref(false);
const menuStyle = ref({});
const triggerRef = ref(null);

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
    const u = props.user;
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
        setPassword: !deleted && !isSelf && !u.admin_level,
        copy: true,
        delete: isSuperAdmin && !u.admin_level && !isSelf && !deleted,
    };
});

// ── Actions ───────────────────────────────────────────────────────────────────
function promote() {
    closeMenu();
    router.post(
        route('admin.users.promote', props.user.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function demote() {
    closeMenu();
    if (!window.confirm(trans('super_admin.users.demote_confirm', { name: props.user.name }))) {
        return;
    }
    router.delete(route('admin.users.demote', props.user.id), {
        preserveScroll: true,
        preserveState: true,
    });
}

function freeze() {
    closeMenu();
    if (!window.confirm(trans('super_admin.users.freeze_confirm', { name: props.user.name }))) {
        return;
    }
    router.post(
        route('admin.users.freeze', props.user.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function thaw() {
    closeMenu();
    router.delete(route('admin.users.thaw', props.user.id), {
        preserveScroll: true,
        preserveState: true,
    });
}

function sendReset() {
    closeMenu();
    router.post(
        route('admin.users.password-reset', props.user.id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

async function copyEmail() {
    closeMenu();
    try {
        await navigator.clipboard.writeText(props.user.email);
        pushToast(trans('super_admin.users.copy_email_done'), 'info');
    } catch {
        /* clipboard unavailable — no-op */
    }
}

function destroyUser() {
    closeMenu();
    if (!window.confirm(trans('super_admin.users.delete_confirm', { name: props.user.name }))) {
        return;
    }
    router.delete(route('admin.users.destroy', props.user.id), {
        preserveScroll: true,
        preserveState: true,
    });
}

// ── Set password modal ────────────────────────────────────────────────────────
const showPasswordModal = ref(false);
const showPassword = ref(false);

const pwForm = useForm({
    password: '',
    password_confirmation: '',
    notify: false,
    logout_sessions: false,
});

function openPasswordModal() {
    closeMenu();
    showPasswordModal.value = true;
}

function closePasswordModal() {
    showPasswordModal.value = false;
    showPassword.value = false;
    pwForm.reset();
    pwForm.clearErrors();
}

async function copyPassword() {
    if (!pwForm.password) return;
    try {
        await navigator.clipboard.writeText(pwForm.password);
        pushToast(trans('super_admin.users.set_password_copied'), 'info');
    } catch {
        /* clipboard unavailable — no-op */
    }
}

/** Fill both fields with a random strong password and reveal them. */
function generatePassword() {
    const chars =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    const length = 20;
    let password = '';
    const array = new Uint32Array(length);
    crypto.getRandomValues(array);
    for (let i = 0; i < length; i++) {
        password += chars[array[i] % chars.length];
    }
    pwForm.password = password;
    pwForm.password_confirmation = password;
    showPassword.value = true;
}

function submitPassword() {
    pwForm.post(route('admin.users.set-password', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            closePasswordModal();
        },
    });
}
</script>

<template>
    <!-- Kebab trigger -->
    <button
        ref="triggerRef"
        type="button"
        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border-strong text-ink-secondary transition hover:bg-background"
        :aria-label="$t('super_admin.users.actions_menu')"
        @click="toggleMenu($event)"
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

    <!-- Teleported dropdown (escapes overflow-x-auto containers) -->
    <Teleport to="body">
        <template v-if="isOpen">
            <div class="fixed inset-0 z-40" @click="closeMenu" />
            <div
                class="fixed z-50 overflow-hidden rounded-md border border-border bg-surface py-1 shadow-lg"
                :style="menuStyle"
            >
                <Link
                    v-if="showViewDetails"
                    :href="route('admin.users.show', user.id)"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] font-medium text-ink-primary transition hover:bg-background"
                    @click="closeMenu"
                >
                    {{ $t('super_admin.users.view_details') }}
                </Link>
                <Link
                    :href="route('admin.email-templates.index', { user: user.id })"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] font-medium text-ink-primary transition hover:bg-background"
                    :class="{ 'border-b border-border': !showViewDetails }"
                    @click="closeMenu"
                >
                    {{ $t('super_admin.users.email_user') }}
                </Link>
                <!-- Separator after email_user when view_details is shown -->
                <div v-if="showViewDetails" class="border-b border-border" />
                <button
                    v-if="menu.promote"
                    type="button"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                    @click="promote"
                >
                    {{ $t('super_admin.users.promote_button') }}
                </button>
                <button
                    v-if="menu.demote"
                    type="button"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                    @click="demote"
                >
                    {{ $t('super_admin.users.demote_button') }}
                </button>
                <button
                    v-if="menu.freeze"
                    type="button"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                    @click="freeze"
                >
                    {{ $t('super_admin.users.freeze_button') }}
                </button>
                <button
                    v-if="menu.thaw"
                    type="button"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                    @click="thaw"
                >
                    {{ $t('super_admin.users.thaw_button') }}
                </button>
                <button
                    v-if="menu.reset"
                    type="button"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                    @click="sendReset"
                >
                    {{ $t('super_admin.users.reset_button') }}
                </button>
                <button
                    v-if="menu.setPassword"
                    type="button"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                    @click="openPasswordModal"
                >
                    {{ $t('super_admin.users.set_password_button') }}
                </button>
                <button
                    v-if="menu.copy"
                    type="button"
                    class="block w-full px-4 py-2 text-left font-sans text-[13px] text-ink-primary transition hover:bg-background"
                    @click="copyEmail"
                >
                    {{ $t('super_admin.users.copy_email') }}
                </button>
                <button
                    v-if="menu.delete"
                    type="button"
                    class="block w-full border-t border-border px-4 py-2 text-left font-sans text-[13px] text-danger transition hover:bg-danger-soft"
                    @click="destroyUser"
                >
                    {{ $t('super_admin.users.delete_button') }}
                </button>
            </div>
        </template>
    </Teleport>

    <!-- Set password modal -->
    <Modal :show="showPasswordModal" max-width="md" @close="closePasswordModal">
        <div class="p-6">
            <h2 class="font-display text-[18px] font-semibold text-ink-primary">
                {{ $t('super_admin.users.set_password_title', { name: user.name }) }}
            </h2>
            <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                {{ $t('super_admin.users.set_password_help') }}
            </p>

            <div class="mt-5 space-y-4">
                <!-- New password -->
                <div>
                    <label
                        for="set-pw-password"
                        class="block font-sans text-[13px] font-medium text-ink-primary"
                    >
                        {{ $t('super_admin.users.set_password_field') }}
                    </label>
                    <div class="mt-1 flex gap-2">
                        <div class="relative flex-1">
                            <input
                                id="set-pw-password"
                                v-model="pwForm.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                class="w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-mono text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                :class="{ 'border-danger focus:border-danger focus:ring-danger/20': pwForm.errors.password }"
                            />
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[13px] text-ink-secondary transition hover:bg-background"
                            @click="showPassword = !showPassword"
                        >
                            {{
                                showPassword
                                    ? $t('super_admin.users.set_password_hide')
                                    : $t('super_admin.users.set_password_show')
                            }}
                        </button>
                        <button
                            type="button"
                            class="shrink-0 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[13px] text-ink-secondary transition hover:bg-background"
                            @click="generatePassword"
                        >
                            {{ $t('super_admin.users.set_password_generate') }}
                        </button>
                    </div>
                    <p
                        v-if="pwForm.errors.password"
                        class="mt-1 font-sans text-[12px] text-danger"
                    >
                        {{ pwForm.errors.password }}
                    </p>
                </div>

                <!-- Confirm password -->
                <div>
                    <label
                        for="set-pw-confirm"
                        class="block font-sans text-[13px] font-medium text-ink-primary"
                    >
                        {{ $t('super_admin.users.set_password_confirm_field') }}
                    </label>
                    <div class="mt-1">
                        <input
                            id="set-pw-confirm"
                            v-model="pwForm.password_confirmation"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            class="w-full rounded-md border border-border-strong bg-surface px-3 py-2 font-mono text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        />
                    </div>
                </div>

                <!-- Copy to clipboard -->
                <div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[13px] text-ink-secondary transition hover:bg-background disabled:opacity-40"
                        :disabled="!pwForm.password"
                        @click="copyPassword"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"
                            />
                            <path
                                d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"
                            />
                        </svg>
                        {{ $t('super_admin.users.set_password_copy') }}
                    </button>
                </div>

                <!-- Notify checkbox -->
                <div class="rounded-md border border-border bg-background px-4 py-3">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="pwForm.notify"
                            type="checkbox"
                            class="mt-0.5 rounded border-border-strong text-accent focus:ring-accent-soft"
                        />
                        <div>
                            <span class="font-sans text-[13px] font-medium text-ink-primary">
                                {{ $t('super_admin.users.set_password_notify') }}
                            </span>
                            <p class="mt-0.5 font-sans text-[12px] text-ink-secondary">
                                {{ $t('super_admin.users.set_password_notify_hint') }}
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Sign out everywhere checkbox -->
                <div class="rounded-md border border-border bg-background px-4 py-3">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="pwForm.logout_sessions"
                            type="checkbox"
                            class="mt-0.5 rounded border-border-strong text-accent focus:ring-accent-soft"
                        />
                        <div>
                            <span class="font-sans text-[13px] font-medium text-ink-primary">
                                {{ $t('super_admin.users.set_password_logout') }}
                            </span>
                            <p class="mt-0.5 font-sans text-[12px] text-ink-secondary">
                                {{ $t('super_admin.users.set_password_logout_hint') }}
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-md border border-border-strong bg-surface px-4 py-2 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                    @click="closePasswordModal"
                >
                    {{ $t('super_admin.users.confirm_cancel') }}
                </button>
                <button
                    type="button"
                    class="rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-medium text-white transition hover:bg-accent-hover disabled:opacity-50"
                    :disabled="pwForm.processing"
                    @click="submitPassword"
                >
                    {{ $t('super_admin.users.set_password_submit') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
