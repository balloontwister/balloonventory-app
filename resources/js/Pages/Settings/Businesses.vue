<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import CountrySelect from '@/Components/CountrySelect.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    business: { type: Object, required: true },
    countries: { type: Object, default: () => ({}) },
});

const page = usePage();
const canEditSettings = computed(() =>
    page.props.permissions?.includes('business.edit_settings'),
);
const canManageLogo = computed(() =>
    page.props.permissions?.includes('business.manage_logo'),
);

const form = useForm({
    name: props.business.name,
    phone: props.business.phone ?? '',
    address_line1: props.business.address_line1 ?? '',
    address_line2: props.business.address_line2 ?? '',
    city: props.business.city ?? '',
    state_region: props.business.state_region ?? '',
    postal_code: props.business.postal_code ?? '',
    country: props.business.country ?? 'US',
    website_url: props.business.website_url ?? '',
    website_url_2: props.business.website_url_2 ?? '',
    contact_email: props.business.contact_email ?? '',
});
const submit = () => form.patch(route('settings.businesses.update'));

const logoForm = useForm({ logo: null, logo_clear: false });
const submitLogo = () =>
    logoForm.post(route('settings.businesses.logo.update'), {
        forceFormData: true,
    });
</script>

<template>
    <Head :title="$t('settings.businesses.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <BackLink
                :href="route('account.index')"
                :label="$t('nav.account')"
            />
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('settings.businesses.heading') }}
            </h1>
        </template>

        <div class="flex flex-col gap-6 py-2">
            <!-- ── Business name ──────────────────────────────────────────── -->
            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <h2
                    class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('settings.businesses.name.heading') }}
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('settings.businesses.name.subheading') }}
                </p>

                <form class="mt-5 flex flex-col gap-4" @submit.prevent="submit">
                    <div>
                        <InputLabel
                            for="name"
                            :value="$t('settings.businesses.name.label')"
                        />
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
                            {{ $t('settings.businesses.name.submit') }}
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
                                {{ $t('settings.businesses.name.saved') }}
                            </span>
                        </Transition>
                    </div>

                    <p
                        v-if="!canEditSettings"
                        class="font-sans text-[12px] text-ink-tertiary"
                    >
                        {{ $t('settings.businesses.name.no_permission') }}
                    </p>
                </form>
            </div>

            <!-- ── Business contact ──────────────────────────────────────── -->
            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <h2
                    class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('settings.businesses.contact.heading') }}
                </h2>
                <p class="mt-1 font-sans text-[12px] text-ink-tertiary">
                    {{ $t('settings.businesses.contact.privacy_note') }}
                </p>

                <div class="mt-5 flex flex-col gap-4">
                    <div>
                        <InputLabel
                            for="biz_contact_email"
                            :value="$t('settings.businesses.contact.contact_email')"
                        />
                        <TextInput
                            id="biz_contact_email"
                            v-model="form.contact_email"
                            type="email"
                            class="mt-1 block w-full max-w-sm"
                            :disabled="!canEditSettings"
                        />
                        <InputError class="mt-1" :message="form.errors.contact_email" />
                    </div>

                    <div>
                        <InputLabel
                            for="biz_phone"
                            :value="$t('settings.businesses.contact.phone')"
                        />
                        <TextInput
                            id="biz_phone"
                            v-model="form.phone"
                            type="text"
                            class="mt-1 block w-full max-w-sm"
                            :disabled="!canEditSettings"
                        />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>

                    <div>
                        <InputLabel
                            for="biz_address_line1"
                            :value="$t('settings.businesses.contact.address_line1')"
                        />
                        <TextInput
                            id="biz_address_line1"
                            v-model="form.address_line1"
                            type="text"
                            class="mt-1 block w-full max-w-sm"
                            :disabled="!canEditSettings"
                        />
                        <InputError class="mt-1" :message="form.errors.address_line1" />
                    </div>

                    <div>
                        <InputLabel
                            for="biz_address_line2"
                            :value="$t('settings.businesses.contact.address_line2')"
                        />
                        <TextInput
                            id="biz_address_line2"
                            v-model="form.address_line2"
                            type="text"
                            class="mt-1 block w-full max-w-sm"
                            :disabled="!canEditSettings"
                        />
                        <InputError class="mt-1" :message="form.errors.address_line2" />
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div>
                            <InputLabel
                                for="biz_city"
                                :value="$t('settings.businesses.contact.city')"
                            />
                            <TextInput
                                id="biz_city"
                                v-model="form.city"
                                type="text"
                                class="mt-1 block w-full"
                                :disabled="!canEditSettings"
                            />
                            <InputError class="mt-1" :message="form.errors.city" />
                        </div>
                        <div>
                            <InputLabel
                                for="biz_state_region"
                                :value="$t('settings.businesses.contact.state_region')"
                            />
                            <TextInput
                                id="biz_state_region"
                                v-model="form.state_region"
                                type="text"
                                class="mt-1 block w-full"
                                :disabled="!canEditSettings"
                            />
                            <InputError class="mt-1" :message="form.errors.state_region" />
                        </div>
                        <div>
                            <InputLabel
                                for="biz_postal_code"
                                :value="$t('settings.businesses.contact.postal_code')"
                            />
                            <TextInput
                                id="biz_postal_code"
                                v-model="form.postal_code"
                                type="text"
                                class="mt-1 block w-full"
                                :disabled="!canEditSettings"
                            />
                            <InputError class="mt-1" :message="form.errors.postal_code" />
                        </div>
                    </div>

                    <div class="max-w-sm">
                        <InputLabel
                            for="biz_country"
                            :value="$t('settings.businesses.contact.country')"
                        />
                        <CountrySelect
                            id="biz_country"
                            v-model="form.country"
                            :countries="countries"
                            :placeholder="$t('settings.businesses.contact.country_placeholder')"
                            :disabled="!canEditSettings"
                        />
                        <InputError class="mt-1" :message="form.errors.country" />
                    </div>

                    <div>
                        <InputLabel
                            for="biz_website_url"
                            :value="$t('settings.businesses.contact.website_url')"
                        />
                        <TextInput
                            id="biz_website_url"
                            v-model="form.website_url"
                            type="text"
                            class="mt-1 block w-full max-w-sm"
                            :disabled="!canEditSettings"
                            placeholder="https://balloonventory.com"
                        />
                        <InputError class="mt-1" :message="form.errors.website_url" />
                    </div>

                    <div>
                        <InputLabel
                            for="biz_website_url_2"
                            :value="$t('settings.businesses.contact.website_url_2')"
                        />
                        <TextInput
                            id="biz_website_url_2"
                            v-model="form.website_url_2"
                            type="text"
                            class="mt-1 block w-full max-w-sm"
                            :disabled="!canEditSettings"
                            placeholder="https://instagram.com/balloonventory"
                        />
                        <InputError class="mt-1" :message="form.errors.website_url_2" />
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        <button
                            type="button"
                            :disabled="form.processing || !canEditSettings"
                            class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                            @click="submit"
                        >
                            {{ $t('settings.businesses.contact.submit') }}
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
                                {{ $t('settings.businesses.contact.saved') }}
                            </span>
                        </Transition>
                    </div>

                    <p
                        v-if="!canEditSettings"
                        class="font-sans text-[12px] text-ink-tertiary"
                    >
                        {{ $t('settings.businesses.name.no_permission') }}
                    </p>
                </div>
            </div>

            <!-- ── Business logo ─────────────────────────────────────────── -->
            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <h2
                    class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('settings.businesses.logo.heading') }}
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('settings.businesses.logo.subheading') }}
                </p>

                <div class="mt-5 flex items-start gap-6">
                    <!-- Circle preview of current logo -->
                    <img
                        :src="
                            logoForm.logo
                                ? undefined
                                : (business.logoUrl ?? undefined)
                        "
                        :class="[
                            'h-20 w-20 shrink-0 rounded-full object-cover ring-2 ring-border',
                            !business.logoUrl && !logoForm.logo
                                ? 'opacity-50'
                                : '',
                        ]"
                        :alt="$t('settings.businesses.logo.preview_alt')"
                    />

                    <div class="flex flex-col gap-4">
                        <ImageUpload
                            v-model:file="logoForm.logo"
                            v-model:clear="logoForm.logo_clear"
                            :current-url="business.logoUrl ?? undefined"
                            :help-text="$t('settings.businesses.logo.help')"
                            :error="logoForm.errors.logo"
                            accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            :disabled="!canManageLogo"
                        />

                        <div class="flex items-center gap-4">
                            <button
                                type="button"
                                :disabled="
                                    logoForm.processing ||
                                    (!logoForm.logo && !logoForm.logo_clear) ||
                                    !canManageLogo
                                "
                                class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                                @click="submitLogo"
                            >
                                {{ $t('settings.businesses.logo.submit') }}
                            </button>

                            <Transition
                                enter-active-class="transition-opacity duration-200"
                                enter-from-class="opacity-0"
                                leave-active-class="transition-opacity duration-200"
                                leave-to-class="opacity-0"
                            >
                                <span
                                    v-if="logoForm.recentlySuccessful"
                                    class="rounded-md border border-success bg-success-soft px-3 py-1.5 font-sans text-[13px] text-ink-primary"
                                >
                                    {{ $t('settings.businesses.logo.saved') }}
                                </span>
                            </Transition>
                        </div>

                        <p
                            v-if="!canManageLogo"
                            class="font-sans text-[12px] text-ink-tertiary"
                        >
                            {{ $t('settings.businesses.logo.no_permission') }}
                        </p>
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
            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <h2
                    class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('settings.businesses.subscription.heading') }}
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('settings.businesses.subscription.subheading') }}
                </p>
                <div
                    class="mt-4 inline-flex items-center gap-2 rounded-md bg-background px-3 py-2"
                >
                    <span class="h-2 w-2 rounded-full bg-success"></span>
                    <span class="font-sans text-[13px] text-ink-primary">{{
                        $t('settings.businesses.subscription.status_free_beta')
                    }}</span>
                </div>
                <p class="mt-3 font-sans text-[12px] text-ink-tertiary">
                    {{ $t('settings.businesses.subscription.footnote') }}
                </p>
            </div>

            <!-- ── Set up shop again (owners/managers only) ──────────────── -->
            <div
                v-if="canEditSettings"
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <h2
                    class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('settings.businesses.setup_again.heading') }}
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('settings.businesses.setup_again.subheading') }}
                </p>
                <Link
                    :href="route('onboarding.wizard')"
                    class="mt-4 inline-flex items-center rounded-md border border-border-strong bg-surface px-4 py-2 font-sans text-[14px] font-semibold text-ink-primary transition hover:bg-background"
                >
                    {{ $t('settings.businesses.setup_again.button') }}
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
