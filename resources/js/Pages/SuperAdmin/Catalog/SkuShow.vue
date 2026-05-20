<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import BrandTag from '@/Components/BrandTag.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    sku: { type: Object, required: true },
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
                :href="route('super-admin.catalog.skus') + '#sku-' + sku.id"
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
                            Inactive
                        </span>
                    </div>
                    <div v-if="sku.brand" class="mt-1">
                        <BrandTag :brand="sku.brand.name" />
                    </div>
                </div>

                <Link
                    :href="route('super-admin.catalog.skus.edit', sku.id)"
                    class="shrink-0 rounded-md border border-border px-4 py-2 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background hover:text-ink-primary"
                >
                    {{ $t('catalog.actions.edit') }}
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
                        Single
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
                        Cluster
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
                    No images uploaded
                </p>
            </div>

            <!-- Details -->
            <dl class="mb-6 divide-y divide-border rounded-lg border border-border">
                <div
                    v-if="sku.warehouse_sku"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Warehouse SKU</dt>
                    <dd class="font-mono text-[13px] text-ink-primary">{{ sku.warehouse_sku }}</dd>
                </div>
                <div
                    v-if="sku.upc"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">UPC</dt>
                    <dd class="font-mono text-[13px] text-ink-primary">{{ sku.upc }}</dd>
                </div>
                <div
                    v-if="sku.ean"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">EAN</dt>
                    <dd class="font-mono text-[13px] text-ink-primary">{{ sku.ean }}</dd>
                </div>
                <div
                    v-if="sku.asin"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">ASIN</dt>
                    <dd class="font-mono text-[13px] text-ink-primary">{{ sku.asin }}</dd>
                </div>
                <div
                    v-if="sku.mfg_no"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Mfg #</dt>
                    <dd class="font-mono text-[13px] text-ink-primary">{{ sku.mfg_no }}</dd>
                </div>
                <div
                    v-if="sku.material"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Material</dt>
                    <dd class="font-sans text-[13px] text-ink-primary">{{ sku.material.name }}</dd>
                </div>
                <div
                    v-if="sku.balloon_size"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Size</dt>
                    <dd class="font-sans text-[13px] text-ink-primary">{{ sku.balloon_size.name }}</dd>
                </div>
                <div
                    v-if="sku.shape"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Shape</dt>
                    <dd class="font-sans text-[13px] text-ink-primary">{{ sku.shape.name }}</dd>
                </div>
                <div
                    v-if="sku.texture"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Texture</dt>
                    <dd class="font-sans text-[13px] text-ink-primary">{{ sku.texture.name }}</dd>
                </div>
                <div
                    v-if="sku.color"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Color</dt>
                    <dd class="flex items-center gap-2">
                        <span
                            v-if="sku.color.color_hex"
                            class="h-4 w-4 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: sku.color.color_hex }"
                        />
                        <span class="font-sans text-[13px] text-ink-primary">{{ sku.color.name }}</span>
                    </dd>
                </div>
                <div
                    v-if="sku.packaging_type"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Packaging</dt>
                    <dd class="font-sans text-[13px] text-ink-primary">{{ sku.packaging_type.name }}</dd>
                </div>
                <div
                    v-if="sku.price_code"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Price Code</dt>
                    <dd class="font-mono text-[13px] text-ink-primary">{{ sku.price_code.code }}</dd>
                </div>
                <div
                    v-if="sku.default_count_per_bag"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Count / Bag</dt>
                    <dd class="font-sans text-[13px] text-ink-primary">{{ sku.default_count_per_bag }}</dd>
                </div>
                <div
                    v-if="sku.product_version"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Version</dt>
                    <dd class="font-sans text-[13px] text-ink-primary">{{ sku.product_version }}</dd>
                </div>
                <div
                    v-if="sku.description"
                    class="flex items-start gap-3 px-4 py-3"
                >
                    <dt class="w-40 shrink-0 font-sans text-[13px] text-ink-secondary">Description</dt>
                    <dd class="font-sans text-[13px] text-ink-primary">{{ sku.description }}</dd>
                </div>
            </dl>

            <!-- Print details -->
            <template v-if="sku.is_printed">
                <div
                    v-if="sku.themes?.length"
                    class="mb-4"
                >
                    <h3 class="mb-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                        Themes
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
                <div
                    v-if="sku.print_colors?.length"
                    class="mb-4"
                >
                    <h3 class="mb-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                        Print Colors
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
                <div
                    v-if="sku.print_sides?.length"
                    class="mb-4"
                >
                    <h3 class="mb-2 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                        Print Sides
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
