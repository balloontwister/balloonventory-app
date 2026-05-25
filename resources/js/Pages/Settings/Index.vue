<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    preferences: { type: Object, required: true },
    supportedLocales: { type: Object, required: true },
});

const form = useForm({
    locale: props.preferences.locale,
    timezone: props.preferences.timezone ?? '',
});

const localeOptions = computed(() =>
    Object.entries(props.supportedLocales).map(([code, label]) => ({
        value: code,
        label,
    })),
);

const timezoneOptions = computed(() => {
    const list =
        typeof Intl.supportedValuesOf === 'function'
            ? Intl.supportedValuesOf('timeZone')
            : [
                  'UTC',
                  'America/New_York',
                  'America/Chicago',
                  'America/Denver',
                  'America/Los_Angeles',
                  'America/Phoenix',
                  'America/Anchorage',
                  'Pacific/Honolulu',
                  'Europe/London',
                  'Europe/Madrid',
                  'Europe/Paris',
                  'Europe/Berlin',
                  'Asia/Tokyo',
                  'Australia/Sydney',
              ];

    if (form.timezone && !list.includes(form.timezone)) {
        return [form.timezone, ...list];
    }

    return list;
});

const submit = () => form.patch(route('settings.preferences.update'));
</script>

<template>
    <Head :title="$t('settings.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <BackLink
                :href="route('account.index')"
                :label="$t('nav.account')"
            />
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('settings.heading') }}
            </h1>
        </template>

        <div class="flex flex-col gap-6 py-2">
            <!-- ── Preferences ───────────────────────────────────────────── -->
            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <h2
                    class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('settings.preferences.heading') }}
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('settings.preferences.subheading') }}
                </p>

                <form class="mt-5 flex flex-col gap-4" @submit.prevent="submit">
                    <div class="max-w-sm">
                        <InputLabel
                            for="locale"
                            :value="$t('settings.preferences.language_label')"
                        />
                        <select
                            id="locale"
                            v-model="form.locale"
                            class="mt-1 block w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        >
                            <option
                                v-for="opt in localeOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p
                            v-if="Object.keys(supportedLocales).length === 1"
                            class="mt-1 font-sans text-[12px] text-ink-tertiary"
                        >
                            {{
                                $t('settings.preferences.more_languages_coming')
                            }}
                        </p>
                    </div>

                    <div class="max-w-sm">
                        <InputLabel
                            for="timezone"
                            :value="$t('settings.preferences.timezone_label')"
                        />
                        <select
                            id="timezone"
                            v-model="form.timezone"
                            class="mt-1 block w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        >
                            <option value="">
                                {{ $t('settings.preferences.timezone_unset') }}
                            </option>
                            <option
                                v-for="tz in timezoneOptions"
                                :key="tz"
                                :value="tz"
                            >
                                {{ tz }}
                            </option>
                        </select>
                        <p class="mt-1 font-sans text-[12px] text-ink-tertiary">
                            {{ $t('settings.preferences.timezone_help') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                        >
                            {{ $t('settings.preferences.submit') }}
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
                                {{ $t('settings.preferences.saved') }}
                            </span>
                        </Transition>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
