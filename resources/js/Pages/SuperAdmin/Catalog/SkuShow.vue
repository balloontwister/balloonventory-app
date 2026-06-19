<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import BackLink from '@/Components/BackLink.vue';
import BrandTag from '@/Components/BrandTag.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    sku: { type: Object, required: true },
    returnQuery: { type: String, default: '' },
});
</script>

<template>
    <Head :title="sku.name" />

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
                :href="
                    route('admin.catalog.skus') +
                    returnQuery +
                    '#sku-' +
                    sku.id
                "
                :label="$t('catalog.tabs.skus')"
            />
        </div>

        <div class="mx-auto max-w-3xl">
            <!-- Header: name + brand + edit button -->
            <div class="mb-6 flex items-start gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="sku.color?.color_hex"
                            class="h-5 w-5 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: sku.color.color_hex }"
                        />
                        <h2
                            class="font-display text-[26px] font-semibold text-ink-primary"
                        >
                            {{ sku.name }}
                        </h2>
                        <span
                            v-if="sku.is_printed"
                            class="rounded bg-accent-soft px-1.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                        >
                            {{ $t('catalog.skus.printed_badge') }}
                        </span>
                        <span
                            v-if="!sku.is_active"
                            class="rounded bg-background px-1.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary ring-1 ring-border"
                        >
                            {{ $t('catalog.sku_show.inactive_badge') }}
                        </span>
                    </div>
                    <div v-if="sku.brand" class="mt-1">
                        <BrandTag :brand="sku.brand.name" />
                    </div>
                </div>

                <Link
                    :href="route('admin.catalog.skus.edit', sku.id)"
                    class="shrink-0"
                >
                    <AppButton variant="secondary">
                        {{ $t('catalog.actions.edit') }}
                    </AppButton>
                </Link>
            </div>

            <!-- Images -->
            <div
                v-if="sku.images?.single || sku.images?.cluster"
                class="mb-6 flex flex-wrap gap-4"
            >
                <div v-if="sku.images.single" class="flex flex-col gap-1">
                    <span
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                    >
                        {{ $t('catalog.sku_show.image_single') }}
                    </span>
                    <img
                        :src="sku.images.single"
                        :alt="sku.name"
                        class="h-48 w-48 rounded-lg object-contain ring-1 ring-inset ring-border"
                    />
                </div>
                <div v-if="sku.images.cluster" class="flex flex-col gap-1">
                    <span
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                    >
                        {{ $t('catalog.sku_show.image_cluster') }}
                    </span>
                    <img
                        :src="sku.images.cluster"
                        :alt="sku.name"
                        class="h-48 w-48 rounded-lg object-contain ring-1 ring-inset ring-border"
                    />
                </div>
            </div>
            <div
                v-else
                class="mb-6 flex h-32 items-center justify-center rounded-lg border border-dashed border-border bg-background"
            >
                <p class="font-sans text-[13px] text-ink-tertiary">
                    {{ $t('catalog.sku_show.no_images') }}
                </p>
            </div>

            <!-- Details -->
            <dl
                class="mb-6 divide-y divide-border rounded-lg border border-border"
            >
                <div
                    v-if="sku.warehouse_sku"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.warehouse_sku') }}
                    </dt>
                    <dd class="font-mono text-[13px] text-ink-primary">
                        {{ sku.warehouse_sku }}
                    </dd>
                </div>
                <div v-if="sku.upc" class="flex items-center gap-3 px-4 py-3">
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.upc') }}
                    </dt>
                    <dd class="font-mono text-[13px] text-ink-primary">
                        {{ sku.upc }}
                    </dd>
                </div>
                <div v-if="sku.ean" class="flex items-center gap-3 px-4 py-3">
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.ean') }}
                    </dt>
                    <dd class="font-mono text-[13px] text-ink-primary">
                        {{ sku.ean }}
                    </dd>
                </div>
                <div v-if="sku.asin" class="flex items-center gap-3 px-4 py-3">
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.asin') }}
                    </dt>
                    <dd class="font-mono text-[13px] text-ink-primary">
                        {{ sku.asin }}
                    </dd>
                </div>
                <div
                    v-if="sku.mfg_no"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.mfg_no') }}
                    </dt>
                    <dd class="font-mono text-[13px] text-ink-primary">
                        {{ sku.mfg_no }}
                    </dd>
                </div>
                <div
                    v-if="sku.material"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.material') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ sku.material.name }}
                    </dd>
                </div>
                <div
                    v-if="sku.balloon_size"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.size') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ sku.balloon_size.name }}
                    </dd>
                </div>
                <div
                    v-if="sku.balloon_size?.shape"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.shape') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ sku.balloon_size.shape.name }}
                    </dd>
                </div>
                <div
                    v-if="sku.color?.texture"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.texture') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ sku.color.texture.name }}
                    </dd>
                </div>
                <div v-if="sku.color" class="flex items-center gap-3 px-4 py-3">
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.color') }}
                    </dt>
                    <dd class="flex items-center gap-2">
                        <span
                            v-if="sku.color.color_hex"
                            class="h-4 w-4 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: sku.color.color_hex }"
                        />
                        <span class="font-sans text-[13px] text-ink-primary">{{
                            sku.color.name
                        }}</span>
                    </dd>
                </div>
                <div
                    v-if="sku.packaging_type"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.packaging') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ sku.packaging_type.name }}
                    </dd>
                </div>
                <div
                    v-if="sku.price_code"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.price_code') }}
                    </dt>
                    <dd class="font-mono text-[13px] text-ink-primary">
                        {{ sku.price_code.code }}
                    </dd>
                </div>
                <div
                    v-if="sku.default_count_per_bag"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.count_per_bag') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ sku.default_count_per_bag }}
                    </dd>
                </div>
                <div
                    v-if="sku.product_version"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.version') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ sku.product_version }}
                    </dd>
                </div>
                <div
                    v-if="sku.description"
                    class="flex items-start gap-3 px-4 py-3"
                >
                    <dt
                        class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.description') }}
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ sku.description }}
                    </dd>
                </div>
            </dl>

            <!-- Identical Products -->
            <div
                v-if="sku.identical_skus?.length"
                class="rounded-lg border border-border"
            >
                <div class="border-b border-border px-4 py-3">
                    <h3
                        class="font-sans text-[13px] font-semibold text-ink-primary"
                    >
                        {{ $t('catalog.sku_show.identical_products') }}
                        <span
                            class="ml-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-accent-soft px-1.5 font-sans text-[11px] font-semibold text-accent"
                        >
                            {{ sku.identical_skus.length }}
                        </span>
                    </h3>
                </div>
                <div class="divide-y divide-border">
                    <Link
                        v-for="sibling in sku.identical_skus"
                        :key="sibling.id"
                        :href="
                            route('admin.catalog.skus.show', sibling.id)
                        "
                        class="flex items-center gap-4 px-4 py-2.5 transition-colors hover:bg-surface"
                    >
                        <span
                            v-if="sku.color?.color_hex"
                            class="h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: sku.color.color_hex }"
                        />
                        <span
                            class="min-w-0 flex-1 truncate font-sans text-[13px] font-medium text-ink-primary"
                        >
                            {{ sibling.name }}
                        </span>
                        <span
                            class="shrink-0 font-sans text-[12px] tabular-nums text-ink-secondary"
                        >
                            {{ sibling.default_count_per_bag }}ct
                        </span>
                        <span
                            v-if="sibling.packaging_type"
                            class="shrink-0 rounded bg-background px-1.5 py-0.5 font-sans text-[11px] text-ink-secondary ring-1 ring-border"
                        >
                            {{ sibling.packaging_type.name }}
                        </span>
                        <span
                            v-if="sibling.upc"
                            class="shrink-0 font-mono text-[11px] text-ink-tertiary"
                        >
                            {{ sibling.upc }}
                        </span>
                    </Link>
                </div>
            </div>

            <!-- Print details -->
            <template v-if="sku.is_printed">
                <div v-if="sku.themes?.length" class="mb-4">
                    <h3
                        class="mb-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.themes') }}
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="theme in sku.themes"
                            :key="theme.id"
                            class="rounded-full border border-border bg-surface px-3 py-1 font-sans text-[13px] text-ink-primary"
                        >
                            {{ theme.name }}
                        </span>
                    </div>
                </div>
                <div v-if="sku.print_colors?.length" class="mb-4">
                    <h3
                        class="mb-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.print_colors') }}
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="color in sku.print_colors"
                            :key="color.id"
                            class="rounded-full border border-border bg-surface px-3 py-1 font-sans text-[13px] text-ink-primary"
                        >
                            {{ color.name }}
                        </span>
                    </div>
                </div>
                <div v-if="sku.print_sides?.length" class="mb-4">
                    <h3
                        class="mb-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.sku_show.print_sides') }}
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="side in sku.print_sides"
                            :key="side.id"
                            class="rounded-full border border-border bg-surface px-3 py-1 font-sans text-[13px] text-ink-primary"
                        >
                            {{ side.name }}
                        </span>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
