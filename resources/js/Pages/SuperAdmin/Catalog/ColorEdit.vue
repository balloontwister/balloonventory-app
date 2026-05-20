<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import BackLink from '@/Components/BackLink.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    color: { type: Object, required: true },
    colorFamilies: { type: Array, required: true },
    brands: { type: Array, required: true },
    textures: { type: Array, required: true },
});

const form = useForm({
    name: props.color.name,
    color_family_id: props.color.color_family_id,
    brand_id: props.color.brand_id ?? '',
    texture_id: props.color.texture_id ?? '',
    color_hex: props.color.color_hex ?? '',
    sort_order: props.color.sort_order ?? '',
    description: props.color.description ?? '',
    single_image: null,
    single_image_clear: false,
    cluster_image: null,
    cluster_image_clear: false,
    _method: 'patch',
});

function submit() {
    form.post(route('super-admin.catalog.colors.update', props.color.id), {
        forceFormData: true,
        onSuccess: () => {
            // Redirect handled server-side (back to colors index).
        },
    });
}

const selectClass =
    'w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft';
</script>

<template>
    <Head :title="color.name" />

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
                :href="route('super-admin.catalog.colors.show', color.id)"
                :label="color.name"
            />
        </div>

        <div class="mx-auto max-w-2xl">
            <h2
                class="mb-6 font-display text-[20px] font-semibold text-ink-primary"
            >
                {{ $t('catalog.actions.edit') }}: {{ color.name }}
            </h2>

            <form @submit.prevent="submit" class="flex flex-col gap-5">
                <!-- Name + Family + Brand -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <AppInput
                            :label="$t('catalog.colors.name_label')"
                            v-model="form.name"
                            :error="form.errors.name"
                            required
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.family_label') }}
                        </label>
                        <select
                            v-model="form.color_family_id"
                            required
                            :class="selectClass"
                        >
                            <option
                                v-for="f in colorFamilies"
                                :key="f.id"
                                :value="f.id"
                            >
                                {{ f.name }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.color_family_id"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.color_family_id }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.brand_label') }}
                        </label>
                        <select v-model="form.brand_id" required :class="selectClass">
                            <option value="">
                                {{ $t('catalog.colors.select_placeholder') }}
                            </option>
                            <option
                                v-for="b in brands"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.abbreviation }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.brand_id"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.brand_id }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.texture_label') }}
                        </label>
                        <select v-model="form.texture_id" required :class="selectClass">
                            <option value="">
                                {{ $t('catalog.colors.select_placeholder') }}
                            </option>
                            <option
                                v-for="t in textures"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.texture_id"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.texture_id }}
                        </p>
                    </div>
                </div>

                <!-- Hex -->
                <div>
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.colors.hex_label') }}
                    </label>
                    <div class="flex items-center gap-2">
                        <input
                            type="color"
                            v-model="form.color_hex"
                            class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                        />
                        <input
                            type="text"
                            v-model="form.color_hex"
                            placeholder="#000000"
                            maxlength="7"
                            class="w-40 rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        />
                    </div>
                    <p
                        v-if="form.errors.color_hex"
                        class="mt-1 font-sans text-[13px] text-danger"
                    >
                        {{ form.errors.color_hex }}
                    </p>
                </div>

                <!-- Images -->
                <div class="flex flex-wrap gap-6">
                    <ImageUpload
                        label="Single balloon"
                        v-model:file="form.single_image"
                        v-model:clear="form.single_image_clear"
                        :current-url="color.single_image_url"
                        :error="form.errors.single_image"
                    />
                    <ImageUpload
                        label="Cluster"
                        v-model:file="form.cluster_image"
                        v-model:clear="form.cluster_image_clear"
                        :current-url="color.cluster_image_url"
                        :error="form.errors.cluster_image"
                    />
                </div>

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
                        :href="route('super-admin.catalog.colors.show', color.id)"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-border-strong bg-surface px-4 py-[10px] font-sans text-[14px] font-medium leading-none text-ink-primary transition-colors hover:bg-background"
                    >
                        {{ $t('catalog.actions.cancel') }}
                    </Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
