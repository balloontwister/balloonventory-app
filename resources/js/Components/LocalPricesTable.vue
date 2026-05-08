<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    prices: { type: Array, default: () => [] }, // [{ id, price_code, local_price }]
    editable: { type: Boolean, default: false },
});

const editingId = ref(null);
const draft = ref('');

function startEdit(price) {
    if (!props.editable) return;
    editingId.value = price.id;
    draft.value = price.local_price ?? '';
}

function commitEdit(price) {
    editingId.value = null;
    const newVal = draft.value.trim();
    if (newVal !== String(price.local_price ?? '')) {
        router.patch(
            route('local-prices.update', { localPrice: price.id }),
            { local_price: newVal || null },
            { preserveScroll: true },
        );
    }
}

function addRow() {
    router.post(route('local-prices.store'), {}, { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- info banner -->
        <div class="rounded-md bg-accent-soft px-3 py-2.5">
            <p class="font-sans text-[13px] text-accent">
                Local Prices are reference data. They are not currently applied to job estimates, proposals, or any other UI.
            </p>
        </div>

        <!-- table -->
        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-background">
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                            Price Code
                        </th>
                        <th class="px-3 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                            Local Price
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="price in prices"
                        :key="price.id"
                        class="h-12 transition hover:bg-[color:var(--color-accent-soft)]/40"
                    >
                        <td class="px-3 font-mono text-[14px] text-ink-primary">
                            {{ price.price_code }}
                        </td>
                        <td class="px-3">
                            <input
                                v-if="editingId === price.id"
                                v-model="draft"
                                type="text"
                                class="w-full rounded border border-border-strong bg-surface px-2 py-1 font-mono text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent-soft"
                                @blur="commitEdit(price)"
                                @keydown.enter="commitEdit(price)"
                                @keydown.escape="editingId = null"
                                autofocus
                            />
                            <button
                                v-else
                                type="button"
                                class="font-mono text-[14px] text-ink-primary"
                                :class="editable ? 'cursor-pointer hover:text-accent' : 'cursor-default'"
                                @click="startEdit(price)"
                            >
                                {{ price.local_price ? `$${price.local_price}` : '—' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button
            v-if="editable"
            type="button"
            class="flex items-center gap-1.5 font-sans text-[14px] text-accent hover:underline"
            @click="addRow"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4">
                <path d="M8.75 3.75a.75.75 0 00-1.5 0v3.5h-3.5a.75.75 0 000 1.5h3.5v3.5a.75.75 0 001.5 0v-3.5h3.5a.75.75 0 000-1.5h-3.5v-3.5z" />
            </svg>
            Add row
        </button>
    </div>
</template>
