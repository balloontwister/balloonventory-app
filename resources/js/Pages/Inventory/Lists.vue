<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InventoryTabs from '@/Components/InventoryTabs.vue';
import ListContents from '@/Components/ListContents.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    lists: { type: Array, required: true },
    activeList: { type: Object, default: null },
});

function isActive(list) {
    return props.activeList && list.id === props.activeList.id;
}
</script>

<template>
    <Head :title="$t('lists.inventory.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3">
                <h1
                    class="font-display text-[22px] font-semibold text-ink-primary"
                >
                    {{ $t('inventory.heading') }}
                </h1>
                <InventoryTabs active="lists" />
            </div>
        </template>

        <!-- List switcher -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <Link
                v-for="list in lists"
                :key="list.id"
                :href="route('inventory.lists.index', { list: list.id })"
                class="inline-flex items-center gap-1.5 rounded-pill px-3 py-1.5 font-sans text-[14px] font-medium transition-colors"
                :class="
                    isActive(list)
                        ? 'bg-accent-soft font-semibold text-accent'
                        : 'bg-background text-ink-secondary hover:text-ink-primary'
                "
            >
                <svg
                    v-if="list.is_business_favorites"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span>{{ list.name }}</span>
                <span class="font-mono text-[12px] opacity-70">{{
                    list.sku_count
                }}</span>
            </Link>

            <Link
                :href="route('lists.index')"
                class="ml-auto font-sans text-[13px] text-accent hover:underline"
            >
                {{ $t('lists.inventory.manage_lists') }}
            </Link>
        </div>

        <!-- Active list contents -->
        <div
            v-if="activeList"
            class="rounded-lg border border-border bg-surface"
        >
            <ListContents :list="activeList" back-context="inventory-list" />
        </div>
        <p
            v-else
            class="rounded-lg border border-dashed border-border px-4 py-10 text-center font-sans text-[14px] text-ink-tertiary"
        >
            {{ $t('lists.inventory.no_lists') }}
        </p>
    </AuthenticatedLayout>
</template>
