<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ListCard from '@/Components/ListCard.vue';
import BalloonSwatch from '@/Components/BalloonSwatch.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    lists: { type: Array, required: true },
    can: { type: Object, default: () => ({}) },
});

const favorites = computed(
    () => props.lists.find((l) => l.is_business_favorites) ?? null,
);
const customLists = computed(() =>
    props.lists.filter((l) => !l.is_business_favorites),
);

const MAX_SWATCHES = 6;
</script>

<template>
    <Head :title="$t('lists.index.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h1 class="font-display text-[22px] font-semibold text-ink-primary">
                    {{ $t('lists.index.heading') }}
                </h1>
                <Link
                    v-if="can.create"
                    :href="route('lists.create')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-accent px-4 py-[10px] font-sans text-[14px] font-medium text-accent-on transition hover:bg-accent-hover"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 16 16"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            d="M8.75 3.75a.75.75 0 00-1.5 0v3.5h-3.5a.75.75 0 000 1.5h3.5v3.5a.75.75 0 001.5 0v-3.5h3.5a.75.75 0 000-1.5h-3.5v-3.5z"
                        />
                    </svg>
                    {{ $t('lists.index.new_list') }}
                </Link>
            </div>
        </template>

        <div class="flex flex-col gap-8">
            <!-- Lists -->
            <section class="flex flex-col gap-3">
                <h2
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('lists.index.section_lists') }}
                </h2>

                <!-- Favorites — pinned at top -->
                <Link
                    v-if="favorites"
                    :href="route('lists.show', { list: favorites.id })"
                    class="block rounded-lg border border-accent/30 bg-accent-soft/40 p-5 transition hover:border-accent/60"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-5 w-5 text-accent"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <h3
                            class="font-sans text-[18px] font-semibold leading-[1.3] text-ink-primary"
                        >
                            {{ favorites.name }}
                        </h3>
                        <span
                            class="ml-auto font-mono text-[14px] text-ink-secondary"
                        >
                            {{
                                $tChoice(
                                    'lists.index.sku_count',
                                    favorites.sku_count,
                                    { count: favorites.sku_count },
                                )
                            }}
                        </span>
                    </div>
                    <div class="flex items-center gap-0">
                        <BalloonSwatch
                            v-for="sku in (favorites.preview_skus ?? []).slice(
                                0,
                                MAX_SWATCHES,
                            )"
                            :key="sku.id"
                            :hex="sku.hex"
                            :finish="sku.finish"
                            :size="24"
                        />
                        <span
                            v-if="favorites.sku_count === 0"
                            class="font-sans text-[13px] text-ink-tertiary"
                        >
                            {{ $t('lists.index.favorites_empty') }}
                        </span>
                    </div>
                </Link>

                <!-- Custom lists -->
                <div
                    v-if="customLists.length"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <ListCard
                        v-for="list in customLists"
                        :key="list.id"
                        :list="list"
                    />
                </div>
                <p
                    v-else
                    class="rounded-lg border border-dashed border-border px-4 py-6 text-center font-sans text-[14px] text-ink-tertiary"
                >
                    {{ $t('lists.index.no_custom_lists') }}
                </p>
            </section>

            <!-- Jobs (Phase 2) -->
            <section class="flex flex-col gap-3">
                <h2
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('lists.index.section_jobs') }}
                </h2>
                <div
                    class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-border px-4 py-10 text-center"
                >
                    <p class="font-sans text-[15px] font-semibold text-ink-primary">
                        {{ $t('lists.index.jobs_coming_title') }}
                    </p>
                    <p class="max-w-md font-sans text-[14px] text-ink-tertiary">
                        {{ $t('lists.index.jobs_coming_hint') }}
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
