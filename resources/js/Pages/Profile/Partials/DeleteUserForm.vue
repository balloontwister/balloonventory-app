<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({ password: '' });

const openConfirm = () => {
    confirmingDeletion.value = true;
    nextTick(() => passwordInput.value.focus());
};

const closeConfirm = () => {
    confirmingDeletion.value = false;
    form.clearErrors();
    form.reset();
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeConfirm(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <section class="flex flex-col gap-5">
        <div>
            <h2
                class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
            >
                Delete account
            </h2>
            <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                Permanently delete your account and all associated data. This
                cannot be undone.
            </p>
        </div>

        <div>
            <button
                type="button"
                class="rounded-md border border-danger px-4 py-2 font-sans text-[14px] font-semibold text-danger transition hover:bg-danger-soft"
                @click="openConfirm"
            >
                Delete account
            </button>
        </div>

        <!-- Confirmation overlay -->
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="confirmingDeletion"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
                @click.self="closeConfirm"
            >
                <div
                    class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-modal"
                >
                    <h3
                        class="font-display text-[18px] font-semibold tracking-h3 text-ink-primary"
                    >
                        Are you sure?
                    </h3>
                    <p class="mt-2 font-sans text-[13px] text-ink-secondary">
                        This will permanently delete your account and all your
                        data. Enter your password to confirm.
                    </p>

                    <div class="mt-5">
                        <InputLabel
                            for="delete-password"
                            value="Password"
                            class="sr-only"
                        />
                        <TextInput
                            id="delete-password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="block w-full"
                            placeholder="Your password"
                            @keyup.enter="deleteUser"
                        />
                        <InputError
                            :message="form.errors.password"
                            class="mt-1"
                        />
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-md border border-border px-4 py-2 font-sans text-[14px] font-medium text-ink-secondary transition hover:bg-background"
                            @click="closeConfirm"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="rounded-md bg-danger px-4 py-2 font-sans text-[14px] font-semibold text-white transition hover:opacity-90 disabled:opacity-40"
                            @click="deleteUser"
                        >
                            Delete my account
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </section>
</template>
