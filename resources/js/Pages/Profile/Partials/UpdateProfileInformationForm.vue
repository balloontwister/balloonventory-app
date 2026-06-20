<script setup>
import CountrySelect from '@/Components/CountrySelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    countries: { type: Object, default: () => ({}) },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone ?? '',
    address_line1: user.address_line1 ?? '',
    address_line2: user.address_line2 ?? '',
    city: user.city ?? '',
    state_region: user.state_region ?? '',
    postal_code: user.postal_code ?? '',
    country: user.country ?? 'US',
    website_url: user.website_url ?? '',
    website_url_2: user.website_url_2 ?? '',
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

            <!-- ── Contact details ─────────────────────────────────────── -->
            <div class="flex flex-col gap-4 border-t border-border pt-4">
                <div>
                    <h3
                        class="font-display text-[15px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{ $t('profile.contact.heading') }}
                    </h3>
                    <p class="mt-1 font-sans text-[12px] text-ink-tertiary">
                        {{ $t('profile.contact.privacy_note') }}
                    </p>
                </div>

                <div>
                    <InputLabel
                        for="phone"
                        :value="$t('profile.contact.phone')"
                    />
                    <TextInput
                        id="phone"
                        v-model="form.phone"
                        type="text"
                        class="mt-1 block w-full max-w-sm"
                        autocomplete="tel"
                    />
                    <InputError class="mt-1" :message="form.errors.phone" />
                </div>

                <div>
                    <InputLabel
                        for="address_line1"
                        :value="$t('profile.contact.address_line1')"
                    />
                    <TextInput
                        id="address_line1"
                        v-model="form.address_line1"
                        type="text"
                        class="mt-1 block w-full max-w-sm"
                        autocomplete="address-line1"
                    />
                    <InputError
                        class="mt-1"
                        :message="form.errors.address_line1"
                    />
                </div>

                <div>
                    <InputLabel
                        for="address_line2"
                        :value="$t('profile.contact.address_line2')"
                    />
                    <TextInput
                        id="address_line2"
                        v-model="form.address_line2"
                        type="text"
                        class="mt-1 block w-full max-w-sm"
                        autocomplete="address-line2"
                    />
                    <InputError
                        class="mt-1"
                        :message="form.errors.address_line2"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <InputLabel
                            for="city"
                            :value="$t('profile.contact.city')"
                        />
                        <TextInput
                            id="city"
                            v-model="form.city"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="address-level2"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.city"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="state_region"
                            :value="$t('profile.contact.state_region')"
                        />
                        <TextInput
                            id="state_region"
                            v-model="form.state_region"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="address-level1"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.state_region"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="postal_code"
                            :value="$t('profile.contact.postal_code')"
                        />
                        <TextInput
                            id="postal_code"
                            v-model="form.postal_code"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="postal-code"
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.postal_code"
                        />
                    </div>
                </div>

                <div class="max-w-sm">
                    <InputLabel
                        for="country"
                        :value="$t('profile.contact.country')"
                    />
                    <CountrySelect
                        id="country"
                        v-model="form.country"
                        :countries="countries"
                        :placeholder="$t('profile.contact.country_placeholder')"
                    />
                    <InputError class="mt-1" :message="form.errors.country" />
                </div>

                <div>
                    <InputLabel
                        for="website_url"
                        :value="$t('profile.contact.website_url')"
                    />
                    <TextInput
                        id="website_url"
                        v-model="form.website_url"
                        type="text"
                        class="mt-1 block w-full max-w-sm"
                        autocomplete="url"
                        placeholder="https://balloonventory.com"
                    />
                    <InputError
                        class="mt-1"
                        :message="form.errors.website_url"
                    />
                </div>

                <div>
                    <InputLabel
                        for="website_url_2"
                        :value="$t('profile.contact.website_url_2')"
                    />
                    <TextInput
                        id="website_url_2"
                        v-model="form.website_url_2"
                        type="text"
                        class="mt-1 block w-full max-w-sm"
                        placeholder="https://instagram.com/balloonventory"
                    />
                    <InputError
                        class="mt-1"
                        :message="form.errors.website_url_2"
                    />
                </div>
            </div>

            <div class="flex items-center gap-4 pt-1">
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
                        {{ $t('profile.contact.saved') }}
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>
