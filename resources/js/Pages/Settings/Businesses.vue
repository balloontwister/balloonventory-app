<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    business: { type: Object, required: true },
});

const page = usePage();
const canEditSettings = computed(() => page.props.auth?.permissions?.includes('business.edit_settings'));

const form = useForm({
    name: props.business.name,
});

const submit = () => form.patch(route('settings.businesses.update'));
</script>

<template>
    <Head title="Manage Business" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">
                Manage Business
            </h1>
        </template>

        <div class="flex flex-col gap-6 py-2">

            <!-- ── Business name ──────────────────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Business name
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    This name appears throughout the app and in shared views.
                </p>

                <form class="mt-5 flex flex-col gap-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full max-w-sm"
                            required
                            :disabled="!canEditSettings"
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            :disabled="form.processing || !canEditSettings"
                            class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                        >
                            Save changes
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
                                Name updated.
                            </span>
                        </Transition>
                    </div>

                    <p v-if="!canEditSettings" class="font-sans text-[12px] text-ink-tertiary">
                        Only the business owner can change the business name.
                    </p>
                </form>
            </div>

            <!-- ── Business logo ─────────────────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Business logo
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    Upload a logo to personalise your business. Shown on reports and shared views.
                </p>

                <div class="mt-5 flex items-center gap-4">
                    <!-- Logo preview placeholder -->
                    <div class="flex h-16 w-16 items-center justify-center rounded-lg border-2 border-dashed border-border bg-background text-ink-tertiary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <button
                            type="button"
                            disabled
                            class="rounded-md border border-border px-4 py-2 font-sans text-[14px] font-medium text-ink-secondary opacity-40"
                        >
                            Upload logo
                        </button>
                        <p class="mt-1 font-sans text-[12px] text-ink-tertiary">Coming soon — PNG or SVG, max 2 MB.</p>
                    </div>
                </div>
            </div>

            <!-- ── Team members ──────────────────────────────────────────── -->
            <!--
                TODO: Invite team members — requires membership invite flow (Phase 2)

                This section will show:
                - A list of current members with their roles
                - "Invite Artist" button — visible to owner + manager (membership.invite_staff permission)
                - "Invite Guest" button — visible to owner + manager (membership.invite_guest permission)

                Both invite flows send an email with a sign-up link pre-linked to this business.

                <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                    <h2>Team members</h2>
                    <button v-if="can('membership.invite_staff')">Invite Artist</button>
                    <button v-if="can('membership.invite_guest')">Invite Guest</button>
                </div>
            -->

            <!-- ── Subscription ──────────────────────────────────────────── -->
            <div class="rounded-lg border border-border bg-surface p-6 shadow-pop">
                <h2 class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary">
                    Subscription
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    Manage your Balloonventory plan and billing.
                </p>
                <div class="mt-4 inline-flex items-center gap-2 rounded-md bg-background px-3 py-2">
                    <span class="h-2 w-2 rounded-full bg-success"></span>
                    <span class="font-sans text-[13px] text-ink-primary">Free beta</span>
                </div>
                <p class="mt-3 font-sans text-[12px] text-ink-tertiary">
                    Billing and plan management coming soon.
                </p>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
