<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    skuId: { type: String, required: true },
    isFavorite: { type: Boolean, required: true },
    favoriteListId: { type: String, required: true },
});

const optimistic = ref(props.isFavorite);
const pending = ref(false);

async function toggle() {
    if (pending.value) return;
    optimistic.value = !optimistic.value;
    pending.value = true;

    const url = optimistic.value
        ? route('favorites.add', { sku: props.skuId })
        : route('favorites.remove', { sku: props.skuId });

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onError: () => {
                optimistic.value = !optimistic.value;
            },
            onFinish: () => {
                pending.value = false;
            },
        },
    );
}
</script>

<template>
    <button
        type="button"
        :aria-label="optimistic ? 'Remove from favorites' : 'Add to favorites'"
        :title="optimistic ? 'Remove from favorites' : 'Add to favorites'"
        class="flex h-8 w-8 items-center justify-center rounded-md transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1"
        :class="pending ? 'cursor-wait opacity-60' : 'cursor-pointer'"
        @click.prevent="toggle"
    >
        <!-- filled star -->
        <svg
            v-if="optimistic"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            class="h-5 w-5 text-accent"
            fill="currentColor"
        >
            <path
                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
            />
        </svg>

        <!-- outline star -->
        <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            class="h-5 w-5 text-ink-tertiary"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
            />
        </svg>
    </button>
</template>
