<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { loadLanguageAsync } from 'laravel-vue-i18n';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import ImageUpload from '@/Components/ImageUpload.vue';

const props = defineProps({
    business: { type: Object, required: true },
    brands: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    supportedLocales: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    preferences: { type: Object, default: () => ({}) },
    answers: { type: Object, default: null },
    alreadyCompleted: { type: Boolean, default: false },
});

const STEPS = [
    'welcome',
    'language',
    'role',
    'brands',
    'locations',
    'branding',
    'review',
];
const stepIndex = ref(0);
const currentStep = computed(() => STEPS[stepIndex.value]);
const lastStepIndex = STEPS.length - 1;

const initialLocations = props.answers?.locations?.length
    ? props.answers.locations.map((l) => ({
          name: l.name ?? '',
          bins: l.bins?.length ? [...l.bins] : [''],
      }))
    : [{ name: '', bins: [''] }];

const form = useForm({
    role: props.answers?.role ?? '',
    brands: props.answers?.brands ? [...props.answers.brands] : [],
    locale: props.preferences?.locale ?? 'en',
    timezone: props.preferences?.timezone ?? null,
    badge_color: props.preferences?.badge_color ?? '#6366F1',
    logo: null,
    locations: initialLocations,
});

function chooseLocale(code) {
    form.locale = code;
    loadLanguageAsync(code);
}

function toggleBrand(name) {
    const i = form.brands.indexOf(name);
    if (i !== -1) {
        form.brands.splice(i, 1);
    } else if (form.brands.length < 2) {
        form.brands.push(name);
    }
}

const hasPendingBrand = computed(() =>
    form.brands.some((name) => {
        const brand = props.brands.find((b) => b.name === name);
        return brand && !brand.seedable;
    }),
);

function addLocation() {
    form.locations.push({ name: '', bins: [''] });
}
function removeLocation(i) {
    form.locations.splice(i, 1);
}
function addBin(i) {
    form.locations[i].bins.push('');
}
function removeBin(i, j) {
    form.locations[i].bins.splice(j, 1);
}

const localeLabel = computed(
    () => props.supportedLocales[form.locale] ?? form.locale,
);
const locationCount = computed(
    () => form.locations.filter((l) => (l.name ?? '').trim().length > 0).length,
);
const binCount = computed(() =>
    form.locations.reduce(
        (sum, l) =>
            (l.name ?? '').trim().length > 0
                ? sum + l.bins.filter((b) => (b ?? '').trim().length > 0).length
                : sum,
        0,
    ),
);

const canAdvance = computed(() => {
    if (currentStep.value === 'role') return !!form.role;
    if (currentStep.value === 'brands') return form.brands.length > 0;
    return true;
});

function next() {
    if (stepIndex.value < lastStepIndex && canAdvance.value) {
        stepIndex.value += 1;
    }
}
function back() {
    if (stepIndex.value > 0) {
        stepIndex.value -= 1;
    }
}

const skipForm = useForm({});
function skip() {
    skipForm.post(route('onboarding.wizard.skip'));
}

function submit() {
    form.transform((data) => {
        const locations = (data.locations ?? [])
            .map((l) => ({
                name: (l.name ?? '').trim(),
                bins: (l.bins ?? []).map((b) => b.trim()).filter(Boolean),
            }))
            .filter((l) => l.name.length > 0);

        const payload = {
            role: data.role,
            brands: data.brands,
            locale: data.locale,
            timezone: data.timezone,
            badge_color: data.badge_color,
            locations,
        };
        if (data.logo) {
            payload.logo = data.logo;
        }
        return payload;
    }).post(route('onboarding.wizard.complete'), { forceFormData: true });
}
</script>

<template>
    <Head :title="$t('onboarding.wizard.welcome_heading')" />

    <GuestLayout>
        <div class="mx-auto w-full max-w-lg px-4 py-10">
            <!-- Header: progress + skip -->
            <div class="mb-4 flex items-center justify-between">
                <p
                    v-if="stepIndex > 0"
                    class="font-sans text-[12px] font-medium uppercase tracking-eyebrow text-ink-tertiary"
                >
                    {{
                        $t('onboarding.wizard.step', {
                            current: stepIndex,
                            total: lastStepIndex,
                        })
                    }}
                </p>
                <span v-else></span>
                <button
                    type="button"
                    class="font-sans text-[13px] text-ink-tertiary underline-offset-2 hover:text-ink-secondary hover:underline"
                    @click="skip"
                >
                    {{ $t('onboarding.wizard.skip') }}
                </button>
            </div>

            <div class="rounded-xl border border-border bg-surface p-8">
                <!-- Welcome -->
                <div v-if="currentStep === 'welcome'">
                    <h1
                        class="mb-2 font-display text-[24px] font-semibold text-ink-primary"
                    >
                        {{ $t('onboarding.wizard.welcome_heading') }}
                    </h1>
                    <p
                        class="mb-5 font-sans text-[14px] font-medium text-accent"
                    >
                        {{ $t('onboarding.wizard.welcome_lead') }}
                    </p>
                    <p
                        class="mb-3 font-sans text-[15px] leading-relaxed text-ink-secondary"
                    >
                        {{ $t('onboarding.wizard.welcome_body') }}
                    </p>
                    <p
                        class="font-sans text-[15px] leading-relaxed text-ink-secondary"
                    >
                        {{ $t('onboarding.wizard.welcome_body2') }}
                    </p>
                </div>

                <!-- Language -->
                <div v-else-if="currentStep === 'language'">
                    <h2 class="step-title">
                        {{ $t('onboarding.wizard.lang_title') }}
                    </h2>
                    <p class="step-heading">
                        {{ $t('onboarding.wizard.lang_heading') }}
                    </p>
                    <p class="step-body">{{ $t('onboarding.wizard.lang_body') }}</p>

                    <div class="grid grid-cols-2 gap-3">
                        <button
                            v-for="(label, code) in supportedLocales"
                            :key="code"
                            type="button"
                            class="option-card"
                            :class="{ 'option-card--active': form.locale === code }"
                            @click="chooseLocale(code)"
                        >
                            <span class="font-sans text-[15px] font-medium">{{
                                label
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Role -->
                <div v-else-if="currentStep === 'role'">
                    <h2 class="step-title">
                        {{ $t('onboarding.wizard.role_title') }}
                    </h2>
                    <p class="step-heading">
                        {{ $t('onboarding.wizard.role_heading') }}
                    </p>
                    <p class="step-body">{{ $t('onboarding.wizard.role_body') }}</p>

                    <div class="flex flex-col gap-3">
                        <button
                            v-for="role in roles"
                            :key="role"
                            type="button"
                            class="option-card option-card--row"
                            :class="{ 'option-card--active': form.role === role }"
                            @click="form.role = role"
                        >
                            <span
                                class="font-sans text-[15px] font-medium text-ink-primary"
                            >
                                {{ $t(`onboarding.wizard.role_${role}_label`) }}
                            </span>
                            <span class="font-sans text-[13px] text-ink-tertiary">
                                {{ $t(`onboarding.wizard.role_${role}_desc`) }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Brands -->
                <div v-else-if="currentStep === 'brands'">
                    <h2 class="step-title">
                        {{ $t('onboarding.wizard.brands_title') }}
                    </h2>
                    <p class="step-heading">
                        {{ $t('onboarding.wizard.brands_heading') }}
                    </p>
                    <p class="step-body">
                        {{ $t('onboarding.wizard.brands_body') }}
                    </p>
                    <p
                        class="mb-3 font-sans text-[12px] font-medium uppercase tracking-eyebrow text-ink-tertiary"
                    >
                        {{ $t('onboarding.wizard.brands_hint') }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="brand in brands"
                            :key="brand.name"
                            type="button"
                            class="chip"
                            :class="{
                                'chip--active': form.brands.includes(brand.name),
                                'chip--muted':
                                    form.brands.length >= 2 &&
                                    !form.brands.includes(brand.name),
                            }"
                            @click="toggleBrand(brand.name)"
                        >
                            {{ brand.name }}
                        </button>
                    </div>

                    <p
                        v-if="hasPendingBrand"
                        class="mt-4 rounded-lg bg-accent-soft px-3 py-2 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('onboarding.wizard.brands_pending') }}
                    </p>
                </div>

                <!-- Locations -->
                <div v-else-if="currentStep === 'locations'">
                    <h2 class="step-title">
                        {{ $t('onboarding.wizard.loc_title') }}
                    </h2>
                    <p class="step-heading">
                        {{ $t('onboarding.wizard.loc_heading') }}
                    </p>
                    <p class="step-body">{{ $t('onboarding.wizard.loc_body') }}</p>

                    <div
                        v-for="(location, i) in form.locations"
                        :key="i"
                        class="mb-4 rounded-lg border border-border bg-background p-4"
                    >
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <AppInput
                                    v-model="location.name"
                                    :label="$t('onboarding.wizard.loc_location_name')"
                                    :placeholder="
                                        $t('onboarding.wizard.loc_location_placeholder')
                                    "
                                />
                            </div>
                            <button
                                v-if="form.locations.length > 1"
                                type="button"
                                class="link-danger pb-2.5"
                                @click="removeLocation(i)"
                            >
                                {{ $t('onboarding.wizard.loc_remove') }}
                            </button>
                        </div>

                        <div class="mt-3 flex flex-col gap-2">
                            <div
                                v-for="(bin, j) in location.bins"
                                :key="j"
                                class="flex items-center gap-2"
                            >
                                <div class="flex-1">
                                    <AppInput
                                        v-model="location.bins[j]"
                                        :placeholder="
                                            $t('onboarding.wizard.loc_bin_placeholder')
                                        "
                                    />
                                </div>
                                <button
                                    v-if="location.bins.length > 1"
                                    type="button"
                                    class="link-danger"
                                    @click="removeBin(i, j)"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="link-accent mt-2"
                            @click="addBin(i)"
                        >
                            + {{ $t('onboarding.wizard.loc_add_bin') }}
                        </button>
                    </div>

                    <button type="button" class="link-accent" @click="addLocation">
                        + {{ $t('onboarding.wizard.loc_add_location') }}
                    </button>
                </div>

                <!-- Branding -->
                <div v-else-if="currentStep === 'branding'">
                    <h2 class="step-title">
                        {{ $t('onboarding.wizard.brand_title') }}
                    </h2>
                    <p class="step-heading">
                        {{ $t('onboarding.wizard.brand_heading') }}
                    </p>
                    <p class="step-body">
                        {{ $t('onboarding.wizard.brand_body') }}
                    </p>

                    <div class="mb-5 flex flex-col gap-1">
                        <span
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('onboarding.wizard.brand_color_label') }}
                        </span>
                        <div class="flex items-center gap-3">
                            <input
                                v-model="form.badge_color"
                                type="color"
                                class="h-10 w-14 cursor-pointer rounded-md border border-border-strong bg-surface"
                            />
                            <span
                                class="font-mono text-[13px] uppercase text-ink-secondary"
                            >
                                {{ form.badge_color }}
                            </span>
                        </div>
                    </div>

                    <ImageUpload
                        :label="$t('onboarding.wizard.brand_logo_label')"
                        :file="form.logo"
                        :current-url="business.logoUrl"
                        :help-text="$t('onboarding.wizard.brand_logo_help')"
                        :error="form.errors.logo"
                        @update:file="form.logo = $event"
                    />
                </div>

                <!-- Review -->
                <div v-else-if="currentStep === 'review'">
                    <h2 class="step-title">
                        {{ $t('onboarding.wizard.review_title') }}
                    </h2>
                    <p class="step-heading">
                        {{ $t('onboarding.wizard.review_heading') }}
                    </p>
                    <p class="step-body">
                        {{ $t('onboarding.wizard.review_body') }}
                    </p>

                    <dl class="divide-y divide-border">
                        <div class="review-row">
                            <dt>{{ $t('onboarding.wizard.review_role') }}</dt>
                            <dd>
                                {{
                                    form.role
                                        ? $t(`onboarding.wizard.role_${form.role}_label`)
                                        : $t('onboarding.wizard.review_none')
                                }}
                            </dd>
                        </div>
                        <div class="review-row">
                            <dt>{{ $t('onboarding.wizard.review_brands') }}</dt>
                            <dd>
                                {{
                                    form.brands.length
                                        ? form.brands.join(', ')
                                        : $t('onboarding.wizard.review_none')
                                }}
                            </dd>
                        </div>
                        <div class="review-row">
                            <dt>{{ $t('onboarding.wizard.review_language') }}</dt>
                            <dd>{{ localeLabel }}</dd>
                        </div>
                        <div class="review-row">
                            <dt>{{ $t('onboarding.wizard.review_locations') }}</dt>
                            <dd>
                                {{
                                    $t('onboarding.wizard.review_locations_summary', {
                                        locations: locationCount,
                                        bins: binCount,
                                    })
                                }}
                            </dd>
                        </div>
                        <div class="review-row">
                            <dt>{{ $t('onboarding.wizard.review_color') }}</dt>
                            <dd class="flex items-center gap-2">
                                <span
                                    class="inline-block h-4 w-4 rounded-full ring-1 ring-inset ring-border"
                                    :style="{ backgroundColor: form.badge_color }"
                                ></span>
                                <span class="font-mono text-[12px] uppercase">{{
                                    form.badge_color
                                }}</span>
                                <span v-if="form.logo" class="text-ink-tertiary"
                                    >· {{ $t('onboarding.wizard.review_logo_set') }}</span
                                >
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Footer -->
                <div class="mt-8 flex items-center justify-between gap-3">
                    <AppButton
                        v-if="stepIndex > 0"
                        variant="ghost"
                        @click="back"
                    >
                        {{ $t('onboarding.wizard.back') }}
                    </AppButton>
                    <span v-else></span>

                    <AppButton
                        v-if="currentStep === 'welcome'"
                        variant="primary"
                        @click="next"
                    >
                        {{ $t('onboarding.wizard.welcome_cta') }}
                    </AppButton>
                    <AppButton
                        v-else-if="currentStep === 'review'"
                        variant="primary"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{
                            form.processing
                                ? $t('onboarding.wizard.finishing')
                                : $t('onboarding.wizard.finish')
                        }}
                    </AppButton>
                    <AppButton
                        v-else
                        variant="primary"
                        :disabled="!canAdvance"
                        @click="next"
                    >
                        {{ $t('onboarding.wizard.next') }}
                    </AppButton>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
.step-title {
    @apply mb-1 font-sans text-[12px] font-semibold uppercase tracking-eyebrow text-accent;
}
.step-heading {
    @apply mb-2 font-display text-[20px] font-semibold text-ink-primary;
}
.step-body {
    @apply mb-6 font-sans text-[14px] leading-relaxed text-ink-secondary;
}
.option-card {
    @apply flex flex-col items-start gap-0.5 rounded-lg border border-border-strong bg-surface px-4 py-3 text-left transition hover:border-accent;
}
.option-card--row {
    @apply w-full;
}
.option-card--active {
    @apply border-accent bg-accent-soft ring-1 ring-accent;
}
.chip {
    @apply rounded-full border border-border-strong bg-surface px-4 py-2 font-sans text-[14px] text-ink-primary transition hover:border-accent;
}
.chip--active {
    @apply border-accent bg-accent text-accent-on;
}
.chip--muted {
    @apply opacity-50;
}
.link-accent {
    @apply self-start font-sans text-[13px] font-medium text-accent hover:underline;
}
.link-danger {
    @apply font-sans text-[13px] text-danger hover:underline;
}
.review-row {
    @apply flex items-start justify-between gap-4 py-2.5;
}
.review-row dt {
    @apply font-sans text-[13px] text-ink-tertiary;
}
.review-row dd {
    @apply text-right font-sans text-[14px] font-medium text-ink-primary;
}
</style>
