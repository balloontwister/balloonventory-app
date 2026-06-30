<script setup>
import MinimalLayout from '@/Layouts/MinimalLayout.vue';
import InvitationNotice from '@/Components/Dashboard/InvitationNotice.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    pendingInvitations: { type: Array, default: () => [] },
});

const page = usePage();
const email = computed(() => page.props.auth?.user?.email ?? '');
</script>

<template>
    <Head :title="$t('onboarding.welcome.meta_title')" />

    <MinimalLayout>
        <div class="mx-auto w-full max-w-xl">
            <h1
                class="font-display text-[24px] font-semibold tracking-h3 text-ink-primary"
            >
                {{ $t('onboarding.welcome.heading') }}
            </h1>
            <p
                class="mt-2 font-sans text-[14px] leading-relaxed text-ink-secondary"
            >
                {{ $t('onboarding.welcome.lead') }}
            </p>

            <!-- Pending team invitations -->
            <div v-if="pendingInvitations.length" class="mt-6">
                <h2
                    class="mb-2 font-sans text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-secondary"
                >
                    {{ $t('onboarding.welcome.invites_heading') }}
                </h2>
                <div class="flex flex-col gap-3">
                    <InvitationNotice
                        v-for="invitation in pendingInvitations"
                        :key="invitation.token"
                        :invitation="invitation"
                    />
                </div>
            </div>

            <!-- Create your own business -->
            <div
                class="mt-6 rounded-lg border border-border bg-surface p-5 shadow-pop"
            >
                <h2
                    class="font-sans text-[15px] font-semibold text-ink-primary"
                >
                    {{ $t('onboarding.welcome.create_heading') }}
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('onboarding.welcome.create_body') }}
                </p>
                <Link
                    :href="route('onboarding.create-business')"
                    class="mt-3 inline-block rounded-[10px] bg-accent px-4 py-2.5 font-sans text-[14px] font-medium text-white transition hover:bg-accent-hover"
                >
                    {{ $t('onboarding.welcome.create_cta') }}
                </Link>
            </div>

            <!-- Waiting-to-join explainer -->
            <p
                v-if="!pendingInvitations.length"
                class="mt-5 font-sans text-[13px] leading-relaxed text-ink-secondary"
            >
                {{ $t('onboarding.welcome.waiting_body') }}
                <span class="font-semibold text-ink-primary">{{ email }}</span>
            </p>
        </div>
    </MinimalLayout>
</template>
