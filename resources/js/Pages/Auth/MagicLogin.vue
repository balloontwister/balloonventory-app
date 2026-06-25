<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    userName: { type: String, required: true },
});

const form = useForm({});

const submit = () => {
    form.post(route('magic-login.consume', props.token));
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('impersonation.landing.meta_title')" />

        <div class="flex flex-col gap-5 text-center">
            <div>
                <h1
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{
                        $t('impersonation.landing.heading', { name: userName })
                    }}
                </h1>
                <p class="mt-2 font-sans text-[13px] text-ink-secondary">
                    {{ $t('impersonation.landing.help') }}
                </p>
            </div>

            <form @submit.prevent="submit">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-accent py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                >
                    {{
                        form.processing
                            ? $t('impersonation.landing.signing_in')
                            : $t('impersonation.landing.button')
                    }}
                </button>
            </form>
        </div>
    </GuestLayout>
</template>
