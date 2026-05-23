<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import BackLink from '@/Components/BackLink.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    brand: { type: Object, required: true },
});

const prefixForm = useForm({ prefix: '' });

function submitPrefix() {
    prefixForm.post(
        route('super-admin.catalog.brands.gs1-prefixes.store', props.brand.id),
        {
            preserveScroll: true,
            onSuccess: () => prefixForm.reset('prefix'),
        },
    );
}

function removePrefix(prefix) {
    if (!confirm(trans('catalog.brand_show.gs1_remove_confirm', { prefix: prefix.prefix }))) {
        return;
    }
    router.delete(
        route('super-admin.catalog.brands.gs1-prefixes.destroy', [
            props.brand.id,
            prefix.id,
        ]),
        { preserveScroll: true },
    );
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
                :href="route('super-admin.catalog.brands')"
                :label="$t('catalog.tabs.brands')"
            />
        </div>

        <div class="mx-auto max-w-3xl">
            <!-- Header: logo + name + edit -->
            <div class="mb-6 flex items-start gap-4">
                <div
                    class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-surface"
                >
                    <img
                        v-if="brand.logo_url"
                        :src="brand.logo_url"
                        :alt="`${brand.name} logo`"
                        class="max-h-full max-w-full object-contain"
                    />
                    <span
                        v-else
                        class="font-sans text-[10px] uppercase tracking-eyebrow text-ink-tertiary"
                    >
                        {{ brand.abbreviation }}
                    </span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            class="font-display text-[26px] font-semibold text-ink-primary"
                        >
                            {{ brand.name }}
                        </h2>
                        <span
                            v-if="!brand.is_active"
                            class="rounded bg-background px-1.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary ring-1 ring-border"
                        >
                            {{ $t('catalog.brand_show.inactive_badge') }}
                        </span>
                    </div>
                    <p
                        class="mt-1 font-mono text-[13px] text-ink-secondary"
                    >
                        {{ brand.abbreviation }}
                    </p>
                </div>

                <Link
                    :href="route('super-admin.catalog.brands.edit', brand.id)"
                    class="shrink-0"
                >
                    <AppButton variant="secondary">
                        {{ $t('catalog.actions.edit') }}
                    </AppButton>
                </Link>
            </div>

            <!-- Identity / Appearance / Links -->
            <dl class="mb-6 divide-y divide-border rounded-lg border border-border">
                <div class="flex items-center gap-3 px-4 py-3">
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.status') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{
                            brand.is_active
                                ? $t('catalog.brand_show.status_active')
                                : $t('catalog.brand_show.status_inactive')
                        }}
                    </dd>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.sku_count') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ brand.skus_count ?? 0 }}
                    </dd>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.sort_order') }}
                    </dt>
                    <dd class="font-mono text-[13px] text-ink-primary">
                        {{ brand.sort_order }}
                    </dd>
                </div>
                <div
                    v-if="brand.primary_color_hex"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.primary_color') }}
                    </dt>
                    <dd class="flex items-center gap-2">
                        <span
                            class="h-4 w-4 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: brand.primary_color_hex }"
                        />
                        <span class="font-mono text-[13px] text-ink-primary">{{
                            brand.primary_color_hex
                        }}</span>
                    </dd>
                </div>
                <div
                    v-if="brand.secondary_color_hex"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.secondary_color') }}
                    </dt>
                    <dd class="flex items-center gap-2">
                        <span
                            class="h-4 w-4 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: brand.secondary_color_hex }"
                        />
                        <span class="font-mono text-[13px] text-ink-primary">{{
                            brand.secondary_color_hex
                        }}</span>
                    </dd>
                </div>
                <div
                    v-if="brand.url_1"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.url_1') }}
                    </dt>
                    <dd class="min-w-0 truncate font-sans text-[13px]">
                        <a
                            :href="brand.url_1"
                            target="_blank"
                            rel="noopener"
                            class="text-accent hover:underline"
                        >
                            {{ brand.url_1 }}
                        </a>
                    </dd>
                </div>
                <div
                    v-if="brand.url_2"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.url_2') }}
                    </dt>
                    <dd class="min-w-0 truncate font-sans text-[13px]">
                        <a
                            :href="brand.url_2"
                            target="_blank"
                            rel="noopener"
                            class="text-accent hover:underline"
                        >
                            {{ brand.url_2 }}
                        </a>
                    </dd>
                </div>
                <div
                    v-if="brand.description"
                    class="flex items-start gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.description') }}
                    </dt>
                    <dd class="whitespace-pre-line font-sans text-[13px] text-ink-primary">
                        {{ brand.description }}
                    </dd>
                </div>
            </dl>

            <!-- GS1 prefixes -->
            <section
                class="mb-6 rounded-lg border border-border bg-surface"
            >
                <header class="flex items-baseline justify-between border-b border-border px-4 py-3">
                    <h3
                        class="font-sans text-[13px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.brand_show.section_gs1') }}
                    </h3>
                </header>
                <p class="px-4 pt-3 font-sans text-[12px] text-ink-tertiary">
                    {{ $t('catalog.brand_show.gs1_help') }}
                </p>

                <ul
                    v-if="brand.gs1_prefixes?.length"
                    class="divide-y divide-border px-4"
                >
                    <li
                        v-for="prefix in brand.gs1_prefixes"
                        :key="prefix.id"
                        class="flex items-center justify-between py-2"
                    >
                        <span class="font-mono text-[13px] text-ink-primary">
                            {{ prefix.prefix }}
                        </span>
                        <AppButton
                            variant="ghost"
                            size="sm"
                            class="text-danger hover:bg-danger-soft"
                            @click="removePrefix(prefix)"
                        >
                            {{ $t('catalog.brand_show.gs1_remove') }}
                        </AppButton>
                    </li>
                </ul>
                <p
                    v-else
                    class="px-4 py-3 font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('catalog.brand_show.gs1_empty') }}
                </p>

                <form
                    @submit.prevent="submitPrefix"
                    class="flex items-end gap-2 border-t border-border px-4 py-3"
                >
                    <div class="flex-1">
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brand_show.gs1_add_label') }}
                        </label>
                        <input
                            type="text"
                            v-model="prefixForm.prefix"
                            :placeholder="
                                $t('catalog.brand_show.gs1_add_placeholder')
                            "
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="12"
                            class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        />
                        <p
                            v-if="prefixForm.errors.prefix"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ prefixForm.errors.prefix }}
                        </p>
                    </div>
                    <AppButton
                        type="submit"
                        variant="primary"
                        :disabled="prefixForm.processing"
                    >
                        {{ $t('catalog.brand_show.gs1_add_button') }}
                    </AppButton>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
