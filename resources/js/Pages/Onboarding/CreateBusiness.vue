<script setup>
import { useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    hasExistingBusiness: { type: Boolean, default: false },
});

const form = useForm({
    name: '',
});

function submit() {
    form.post(route('onboarding.store-business'));
}
</script>

<template>
    <Head :title="$t('onboarding.meta_title')" />

    <GuestLayout>
        <div class="mx-auto w-full max-w-md px-4 py-12">
            <!-- Logo / wordmark -->
            <div class="mb-8 text-center">
                <p
                    class="font-display text-[28px] font-semibold tracking-tight text-ink-primary"
                >
                    {{ $t('onboarding.wordmark') }}
                </p>
                <p class="mt-2 font-sans text-[15px] text-ink-secondary">
                    {{
                        hasExistingBusiness
                            ? $t('onboarding.lead_additional')
                            : $t('onboarding.lead')
                    }}
                </p>
            </div>

            <div class="rounded-lg border border-border bg-surface p-8">
                <h1
                    class="mb-1 font-display text-[22px] font-semibold text-ink-primary"
                >
                    {{
                        hasExistingBusiness
                            ? $t('onboarding.heading_additional')
                            : $t('onboarding.heading')
                    }}
                </h1>
                <p class="mb-6 font-sans text-[14px] text-ink-secondary">
                    {{
                        hasExistingBusiness
                            ? $t('onboarding.subheading_additional')
                            : $t('onboarding.subheading')
                    }}
                </p>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label
                            for="name"
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-secondary"
                        >
                            {{ $t('onboarding.name_label') }}
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            autofocus
                            :placeholder="$t('onboarding.name_placeholder')"
                            class="focus:ring-3 w-full rounded-[10px] border border-border-strong bg-surface px-3 py-2.5 font-sans text-[15px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-accent-soft"
                        />
                        <p
                            v-if="form.errors.name"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.name.trim()"
                        class="w-full rounded-[10px] bg-accent px-4 py-2.5 font-sans text-[14px] font-medium text-white transition hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? $t('onboarding.submitting')
                                : $t('onboarding.submit')
                        }}
                    </button>
                </form>
            </div>
        </div>
    </GuestLayout>
</template>
