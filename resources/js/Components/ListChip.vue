<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    lists: { type: Array, required: true }, // [{ id, name }]
});

const MAX_VISIBLE = 3;
const expanded = ref(false);

const visible = computed(() =>
    expanded.value ? props.lists : props.lists.slice(0, MAX_VISIBLE),
);
const overflow = computed(() => Math.max(0, props.lists.length - MAX_VISIBLE));
</script>

<template>
    <div class="flex flex-wrap gap-1">
        <Link
            v-for="list in visible"
            :key="list.id"
            :href="route('lists.show', { list: list.id })"
            class="inline-flex items-center rounded-pill bg-accent-soft px-2 py-0.5 font-sans text-[12px] font-medium text-accent transition hover:bg-accent hover:text-accent-on"
        >
            <span class="max-w-[24ch] truncate">{{ list.name }}</span>
        </Link>

        <button
            v-if="overflow > 0 && !expanded"
            type="button"
            class="inline-flex items-center rounded-pill bg-accent-soft px-2 py-0.5 font-sans text-[12px] font-medium text-accent hover:bg-accent hover:text-accent-on"
            @click="expanded = true"
        >
            +{{ overflow }} more
        </button>
    </div>
</template>
