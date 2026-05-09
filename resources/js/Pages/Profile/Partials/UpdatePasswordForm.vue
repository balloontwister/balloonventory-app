<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="flex flex-col gap-5">
        <div>
            <h2
                class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
            >
                Update password
            </h2>
            <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                Use a long, random password to keep your account secure.
            </p>
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="updatePassword">
            <div>
                <InputLabel for="current_password" value="Current password" />
                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full max-w-sm"
                    autocomplete="current-password"
                />
                <InputError
                    :message="form.errors.current_password"
                    class="mt-1"
                />
            </div>

            <div>
                <InputLabel for="password" value="New password" />
                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full max-w-sm"
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password" class="mt-1" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirm new password"
                />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full max-w-sm"
                    autocomplete="new-password"
                />
                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-1"
                />
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                >
                    Update password
                </button>

                <Transition
                    enter-active-class="transition-opacity duration-200"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity duration-200"
                    leave-to-class="opacity-0"
                >
                    <span
                        v-if="form.recentlySuccessful"
                        class="rounded-md border border-success bg-success-soft px-3 py-1.5 font-sans text-[13px] text-ink-primary"
                    >
                        Password updated.
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>
