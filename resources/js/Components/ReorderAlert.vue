<script setup>
defineProps({
    skuName: { type: String, required: true },
    count: { type: Number, required: true },
    skuId: { type: String, required: true },
});
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-md border px-4 py-3"
        style="background-color: var(--color-warning-soft); border-color: rgba(234,88,12,0.30)"
    >
        <!-- icon -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 flex-shrink-0 text-warning">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>

        <div class="min-w-0 flex-1">
            <p class="font-sans text-[14px] font-semibold text-ink-primary">
                Low stock: {{ skuName }}
            </p>
            <p class="font-sans text-[13px] text-ink-secondary">
                <span class="font-mono">{{ count }}</span> bags remaining
            </p>
        </div>

        <a
            :href="route('inventory.reorder', { sku: skuId })"
            class="flex-shrink-0 rounded-md bg-warning px-3 py-2 font-sans text-[13px] font-medium text-white transition hover:opacity-90"
        >
            Reorder
        </a>
    </div>
</template>
