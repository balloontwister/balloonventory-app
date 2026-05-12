<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.forgot_password.meta_title')" />

        <div class="flex flex-col gap-4">
            <p class="font-sans text-[13px] text-ink-secondary">
                {{ $t('auth.forgot_password.lead') }}
            </p>

            <div
                v-if="status"
                class="rounded-md border border-success bg-success-soft px-3 py-2 font-sans text-[13px] text-ink-primary"
            >
                {{ status }}
            </div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div>
                    <InputLabel
                        for="email"
                        :value="$t('auth.forgot_password.email_label')"
                    />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-accent py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                >
                    {{ $t('auth.forgot_password.submit') }}
                </button>
            </form>

            <p class="text-center font-sans text-[13px] text-ink-secondary">
                {{ $t('auth.forgot_password.remember_question') }}
                <Link
                    :href="route('login')"
                    class="font-semibold text-accent hover:underline"
                >
                    {{ $t('auth.forgot_password.log_in') }}
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
