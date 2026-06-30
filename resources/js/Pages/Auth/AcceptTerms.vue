<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    termsUrl: { type: String, required: true },
    privacyUrl: { type: String, required: true },
    previouslyAccepted: { type: Boolean, default: false },
});

const form = useForm({ terms: false });

const submit = () => form.post(route('terms.accept'));

const logout = () => useForm({}).post(route('logout'));
</script>

<template>
    <GuestLayout>
        <Head :title="$t('legal.accept.meta_title')" />

        <h1
            class="font-display text-[20px] font-semibold tracking-h3 text-ink-primary"
        >
            {{ $t('legal.accept.heading') }}
        </h1>
        <p
            class="mt-2 font-sans text-[14px] leading-relaxed text-ink-secondary"
        >
            {{
                previouslyAccepted
                    ? $t('legal.accept.intro_updated')
                    : $t('legal.accept.intro_new')
            }}
        </p>

        <form class="mt-5" @submit.prevent="submit">
            <label class="flex items-start gap-2">
                <Checkbox v-model:checked="form.terms" class="mt-0.5" />
                <span class="text-sm text-ink-secondary">
                    {{ $t('legal.consent.prefix') }}
                    <a
                        :href="termsUrl"
                        target="_blank"
                        class="text-accent underline"
                    >
                        {{ $t('legal.consent.terms_link') }}
                    </a>
                    {{ $t('legal.consent.and') }}
                    <a
                        :href="privacyUrl"
                        target="_blank"
                        class="text-accent underline"
                    >
                        {{ $t('legal.consent.privacy_link') }}</a
                    >.
                </span>
            </label>
            <InputError class="mt-2" :message="form.errors.terms" />

            <div class="mt-6 flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="font-sans text-[13px] text-ink-tertiary underline hover:text-ink-secondary"
                    @click="logout"
                >
                    {{ $t('legal.accept.logout') }}
                </button>
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('legal.accept.submit') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
