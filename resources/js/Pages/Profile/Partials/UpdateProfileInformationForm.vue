<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section class="flex flex-col gap-5">
        <div>
            <h2
                class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
            >
                {{ $t('profile.information.heading') }}
            </h2>
            <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                {{ $t('profile.information.subheading') }}
            </p>
        </div>

        <form
            class="flex flex-col gap-4"
            @submit.prevent="form.patch(route('profile.update'))"
        >
            <div>
                <InputLabel
                    for="name"
                    :value="$t('profile.information.name_label')"
                />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full max-w-sm"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel
                    for="email"
                    :value="$t('profile.information.email_label')"
                />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full max-w-sm"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                >
                    {{ $t('profile.information.submit') }}
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
                        {{ $t('profile.information.saved') }}
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>
