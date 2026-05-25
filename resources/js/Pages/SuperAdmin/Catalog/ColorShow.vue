<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    color: { type: Object, required: true },
});
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
                :href="
                    route('super-admin.catalog.colors') + '#color-' + color.id
                "
                :label="$t('catalog.tabs.colors')"
            />
        </div>

        <div class="mx-auto max-w-2xl">
            <!-- Header row: swatch + name + edit link -->
            <div class="mb-6 flex items-center gap-4">
                <span
                    v-if="color.color_hex"
                    class="h-10 w-10 shrink-0 rounded-md ring-1 ring-inset ring-black/10"
                    :style="{ backgroundColor: color.color_hex }"
                />
                <span
                    v-else
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-dashed border-border bg-background font-sans text-[10px] uppercase tracking-eyebrow text-ink-tertiary"
                >
                    N/A
                </span>

                <div class="min-w-0 flex-1">
                    <h2
                        class="font-display text-[26px] font-semibold text-ink-primary"
                    >
                        {{ color.name }}
                    </h2>
                    <p
                        v-if="color.brand"
                        class="font-sans text-[14px] text-ink-secondary"
                    >
                        {{ color.brand.name }}
                    </p>
                </div>

                <Link
                    :href="route('super-admin.catalog.colors.edit', color.id)"
                    class="shrink-0 rounded-md border border-border px-4 py-2 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background hover:text-ink-primary"
                >
                    {{ $t('catalog.actions.edit') }}
                </Link>
            </div>

            <!-- Images -->
            <div
                v-if="color.single_image_url || color.cluster_image_url"
                class="mb-6 flex flex-wrap gap-4"
            >
                <div v-if="color.single_image_url" class="flex flex-col gap-1">
                    <span
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                    >
                        Single
                    </span>
                    <img
                        :src="color.single_image_url"
                        :alt="color.name"
                        class="h-48 w-48 rounded-lg object-contain ring-1 ring-inset ring-border"
                    />
                </div>
                <div v-if="color.cluster_image_url" class="flex flex-col gap-1">
                    <span
                        class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                    >
                        Cluster
                    </span>
                    <img
                        :src="color.cluster_image_url"
                        :alt="color.name"
                        class="h-48 w-48 rounded-lg object-contain ring-1 ring-inset ring-border"
                    />
                </div>
            </div>
            <div
                v-else
                class="mb-6 flex h-48 items-center justify-center rounded-lg border border-dashed border-border bg-background"
            >
                <p class="font-sans text-[13px] text-ink-tertiary">
                    No images uploaded
                </p>
            </div>

            <!-- Details -->
            <dl class="divide-y divide-border rounded-lg border border-border">
                <div
                    v-if="color.color_hex"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-36 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        Hex
                    </dt>
                    <dd class="flex items-center gap-2">
                        <span
                            class="h-4 w-4 rounded-sm ring-1 ring-inset ring-black/10"
                            :style="{ backgroundColor: color.color_hex }"
                        />
                        <span class="font-mono text-[13px] text-ink-primary">{{
                            color.color_hex
                        }}</span>
                    </dd>
                </div>
                <div
                    v-if="color.pms_value"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-36 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        PMS
                    </dt>
                    <dd class="font-mono text-[13px] text-ink-primary">
                        {{ color.pms_value }}
                    </dd>
                </div>
                <div
                    v-if="color.color_family"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-36 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        Family
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ color.color_family.name }}
                    </dd>
                </div>
                <div
                    v-if="color.texture"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-36 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        Texture
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ color.texture.name }}
                    </dd>
                </div>
                <div
                    v-if="color.material"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <dt
                        class="w-36 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        Material
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ color.material.name }}
                    </dd>
                </div>
                <div
                    v-if="color.description"
                    class="flex items-start gap-3 px-4 py-3"
                >
                    <dt
                        class="w-36 shrink-0 font-sans text-[13px] text-ink-secondary"
                    >
                        Description
                    </dt>
                    <dd class="font-sans text-[13px] text-ink-primary">
                        {{ color.description }}
                    </dd>
                </div>
            </dl>
        </div>
    </AuthenticatedLayout>
</template>
