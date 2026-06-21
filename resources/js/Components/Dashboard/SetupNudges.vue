<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    nudges: { type: Object, required: true },
    can: { type: Object, required: true },
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Verify email -->
        <div
            v-if="!nudges.emailVerified"
            class="flex items-center justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950"
        >
            <p class="font-sans text-[13px] text-amber-800 dark:text-amber-200">
                {{ $t('dashboard.nudges.verify_email') }}
            </p>
            <Link
                :href="route('verification.notice')"
                class="flex-shrink-0 font-sans text-[13px] font-semibold text-amber-800 underline hover:text-amber-900 dark:text-amber-200 dark:hover:text-amber-100"
            >
                {{ $t('dashboard.nudges.verify_email_action') }}
            </Link>
        </div>

        <!-- Sample stock -->
        <div
            v-if="nudges.hasSampleStock && can.manageBusiness"
            class="flex items-center justify-between gap-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-950"
        >
            <p class="font-sans text-[13px] text-blue-800 dark:text-blue-200">
                {{ $t('dashboard.nudges.clear_samples') }}
            </p>
            <Link
                :href="route('onboarding.samples.clear')"
                method="post"
                as="button"
                class="flex-shrink-0 font-sans text-[13px] font-semibold text-blue-800 underline hover:text-blue-900 dark:text-blue-200 dark:hover:text-blue-100"
            >
                {{ $t('dashboard.nudges.clear_samples_action') }}
            </Link>
        </div>

        <!-- User contact incomplete -->
        <div
            v-if="nudges.userContactIncomplete"
            class="flex items-center justify-between gap-4 rounded-lg border border-border bg-surface px-4 py-3"
        >
            <p class="font-sans text-[13px] text-ink-secondary">
                {{ $t('dashboard.nudges.user_contact') }}
            </p>
            <Link
                :href="route('account.index')"
                class="flex-shrink-0 font-sans text-[13px] font-semibold text-accent hover:underline"
            >
                {{ $t('dashboard.nudges.user_contact_action') }}
            </Link>
        </div>

        <!-- Business contact incomplete -->
        <div
            v-if="nudges.businessContactIncomplete && can.manageBusiness"
            class="flex items-center justify-between gap-4 rounded-lg border border-border bg-surface px-4 py-3"
        >
            <p class="font-sans text-[13px] text-ink-secondary">
                {{ $t('dashboard.nudges.business_contact') }}
            </p>
            <Link
                :href="route('account.index')"
                class="flex-shrink-0 font-sans text-[13px] font-semibold text-accent hover:underline"
            >
                {{ $t('dashboard.nudges.business_contact_action') }}
            </Link>
        </div>

        <!-- Finish onboarding -->
        <div
            v-if="!nudges.onboardingComplete && can.manageBusiness"
            class="flex items-center justify-between gap-4 rounded-lg border border-border bg-surface px-4 py-3"
        >
            <p class="font-sans text-[13px] text-ink-secondary">
                {{ $t('dashboard.nudges.onboarding') }}
            </p>
            <Link
                :href="route('onboarding.wizard')"
                class="flex-shrink-0 font-sans text-[13px] font-semibold text-accent hover:underline"
            >
                {{ $t('dashboard.nudges.onboarding_action') }}
            </Link>
        </div>
    </div>
</template>
