<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    activeScope: { type: String, default: 'all' }, // all | favorites | list
    activeListId: { type: String, default: null },
    lists: { type: Array, default: () => [] }, // [{ id, name, sku_count }]
});

const emit = defineEmits(['change']);

const listsOpen = ref(false);

function select(scope, listId = null) {
    listsOpen.value = false;
    emit('change', { scope, listId });
}

const activeList = computed(() =>
    props.activeListId
        ? props.lists.find((l) => l.id === props.activeListId)
        : null,
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <!-- tab bar -->
        <div
            class="relative inline-flex items-center rounded-pill bg-background p-1"
        >
            <!-- All -->
            <button
                type="button"
                class="relative rounded-pill px-4 py-1.5 font-sans text-[14px] font-medium transition-colors"
                :class="
                    activeScope === 'all'
                        ? 'bg-accent-soft font-semibold text-accent'
                        : 'text-ink-secondary hover:text-ink-primary'
                "
                @click="select('all')"
            >
                All
            </button>

            <!-- Favorites -->
            <button
                type="button"
                class="relative rounded-pill px-4 py-1.5 font-sans text-[14px] font-medium transition-colors"
                :class="
                    activeScope === 'favorites'
                        ? 'bg-accent-soft font-semibold text-accent'
                        : 'text-ink-secondary hover:text-ink-primary'
                "
                @click="select('favorites')"
            >
                Favorites
            </button>

            <!-- Lists dropdown trigger -->
            <div class="relative">
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-pill px-4 py-1.5 font-sans text-[14px] font-medium transition-colors"
                    :class="
                        activeScope === 'list'
                            ? 'bg-accent-soft font-semibold text-accent'
                            : 'text-ink-secondary hover:text-ink-primary'
                    "
                    @click="listsOpen = !listsOpen"
                >
                    Lists
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 12 12"
                        fill="currentColor"
                        class="h-3 w-3"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M3.22 4.72a.75.75 0 011.06 0L6 6.44l1.72-1.72a.75.75 0 111.06 1.06L6.53 8.03a.75.75 0 01-1.06 0L3.22 5.78a.75.75 0 010-1.06z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <!-- lists dropdown -->
                <div
                    v-if="listsOpen"
                    class="absolute left-0 top-full z-40 mt-1 min-w-[200px] overflow-hidden rounded-md border border-border bg-surface shadow-pop"
                >
                    <button
                        v-for="list in lists"
                        :key="list.id"
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-background"
                        @click="select('list', list.id)"
                    >
                        <span class="font-sans text-[14px] text-ink-primary">{{
                            list.name
                        }}</span>
                        <span
                            class="font-mono text-[12px] text-ink-secondary"
                            >{{ list.sku_count }}</span
                        >
                    </button>

                    <div class="border-t border-border">
                        <a
                            :href="route('lists.create')"
                            class="flex items-center gap-1.5 px-3 py-2 font-sans text-[14px] text-accent hover:bg-background"
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
                            New list
                        </a>
                    </div>
                </div>

                <div
                    v-if="listsOpen"
                    class="fixed inset-0 z-30"
                    @click="listsOpen = false"
                />
            </div>
        </div>

        <!-- active list label -->
        <div
            v-if="activeScope === 'list' && activeList"
            class="flex items-center gap-3"
        >
            <h2
                class="font-display text-[24px] font-semibold leading-[1.2] tracking-h2 text-ink-primary"
            >
                {{ activeList.name }}
            </h2>
            <span class="font-mono text-[14px] text-ink-secondary"
                >{{ activeList.sku_count }} SKUs</span
            >
            <a
                :href="route('lists.edit', { list: activeList.id })"
                class="ml-auto font-sans text-[13px] text-accent hover:underline"
            >
                Edit list
            </a>
        </div>
    </div>
</template>
