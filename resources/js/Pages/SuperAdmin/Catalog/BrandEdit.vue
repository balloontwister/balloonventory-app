<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import BackLink from '@/Components/BackLink.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    brand: { type: Object, required: true },
});

const form = useForm({
    name: props.brand.name,
    abbreviation: props.brand.abbreviation,
    description: props.brand.description ?? '',
    url_1: props.brand.url_1 ?? '',
    url_2: props.brand.url_2 ?? '',
    primary_color_hex: props.brand.primary_color_hex ?? '',
    secondary_color_hex: props.brand.secondary_color_hex ?? '',
    sort_order: props.brand.sort_order ?? 0,
    is_active: props.brand.is_active,
    logo: null,
    logo_clear: false,
    return_to_show: true,
    _method: 'patch',
});

function submit() {
    form.post(route('admin.catalog.brands.update', props.brand.id), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head :title="brand.name" />

    <AuthenticatedLayout>
        <template #header>
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('catalog.heading') }}
            </h1>
        </template>

        <div class="mb-6">
            <BackLink
                :href="route('admin.catalog.brands.show', brand.id)"
                :label="brand.name"
            />
        </div>

        <div class="mx-auto max-w-2xl">
            <h2
                class="mb-6 font-display text-[20px] font-semibold text-ink-primary"
            >
                {{ $t('catalog.brand_edit.heading') }}: {{ brand.name }}
            </h2>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <!-- Identity -->
                <fieldset class="flex flex-col gap-4">
                    <legend
                        class="mb-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.brand_edit.section_identity') }}
                    </legend>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <AppInput
                                :label="$t('catalog.brand_edit.name_label')"
                                v-model="form.name"
                                :error="form.errors.name"
                                required
                            />
                        </div>
                        <div>
                            <AppInput
                                :label="
                                    $t('catalog.brand_edit.abbreviation_label')
                                "
                                v-model="form.abbreviation"
                                :error="form.errors.abbreviation"
                                required
                            />
                        </div>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brand_edit.description_label') }}
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            :placeholder="
                                $t('catalog.brand_edit.description_placeholder')
                            "
                            class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        />
                        <p
                            v-if="form.errors.description"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                    <div class="flex items-start gap-3">
                        <input
                            id="is_active"
                            type="checkbox"
                            v-model="form.is_active"
                            class="mt-[3px] h-4 w-4 rounded border-border-strong text-accent focus:ring-accent-soft"
                        />
                        <label for="is_active" class="flex-1">
                            <span
                                class="block font-sans text-[14px] font-medium text-ink-primary"
                            >
                                {{ $t('catalog.brand_edit.is_active_label') }}
                            </span>
                            <span
                                class="mt-0.5 block font-sans text-[12px] text-ink-tertiary"
                            >
                                {{ $t('catalog.brand_edit.is_active_help') }}
                            </span>
                        </label>
                    </div>
                </fieldset>

                <!-- Appearance -->
                <fieldset
                    class="flex flex-col gap-4 border-t border-border pt-5"
                >
                    <legend
                        class="mb-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.brand_edit.section_appearance') }}
                    </legend>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                            >
                                {{
                                    $t('catalog.brand_edit.primary_color_label')
                                }}
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    v-model="form.primary_color_hex"
                                    class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                                />
                                <input
                                    type="text"
                                    v-model="form.primary_color_hex"
                                    placeholder="#000000"
                                    maxlength="7"
                                    class="w-32 rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                />
                            </div>
                            <p
                                v-if="form.errors.primary_color_hex"
                                class="mt-1 font-sans text-[13px] text-danger"
                            >
                                {{ form.errors.primary_color_hex }}
                            </p>
                        </div>
                        <div>
                            <label
                                class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                            >
                                {{
                                    $t(
                                        'catalog.brand_edit.secondary_color_label',
                                    )
                                }}
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    v-model="form.secondary_color_hex"
                                    class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                                />
                                <input
                                    type="text"
                                    v-model="form.secondary_color_hex"
                                    placeholder="#000000"
                                    maxlength="7"
                                    class="w-32 rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                />
                            </div>
                            <p
                                v-if="form.errors.secondary_color_hex"
                                class="mt-1 font-sans text-[13px] text-danger"
                            >
                                {{ form.errors.secondary_color_hex }}
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <AppInput
                                :label="
                                    $t('catalog.brand_edit.sort_order_label')
                                "
                                type="number"
                                v-model="form.sort_order"
                                :error="form.errors.sort_order"
                            />
                        </div>
                    </div>
                    <ImageUpload
                        :label="$t('catalog.brand_edit.logo_label')"
                        v-model:file="form.logo"
                        v-model:clear="form.logo_clear"
                        :current-url="brand.logo_url"
                        :error="form.errors.logo"
                        :help-text="$t('catalog.brand_edit.logo_help')"
                    />
                </fieldset>

                <!-- Links -->
                <fieldset
                    class="flex flex-col gap-4 border-t border-border pt-5"
                >
                    <legend
                        class="mb-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.brand_edit.section_links') }}
                    </legend>
                    <div>
                        <AppInput
                            :label="$t('catalog.brand_edit.url_1_label')"
                            v-model="form.url_1"
                            type="url"
                            placeholder="https://"
                            :error="form.errors.url_1"
                        />
                    </div>
                    <div>
                        <AppInput
                            :label="$t('catalog.brand_edit.url_2_label')"
                            v-model="form.url_2"
                            type="url"
                            placeholder="https://"
                            :error="form.errors.url_2"
                        />
                    </div>
                </fieldset>

                <!-- Actions -->
                <div class="flex gap-3 border-t border-border pt-4">
                    <AppButton
                        type="submit"
                        variant="primary"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? $t('catalog.actions.saving')
                                : $t('catalog.actions.save')
                        }}
                    </AppButton>
                    <Link
                        :href="
                            route('admin.catalog.brands.show', brand.id)
                        "
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-border-strong bg-surface px-4 py-[10px] font-sans text-[14px] font-medium leading-none text-ink-primary transition-colors hover:bg-background"
                    >
                        {{ $t('catalog.actions.cancel') }}
                    </Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
