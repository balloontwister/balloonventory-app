<script setup>
/**
 * A small circled-"i" button that opens a Modal explaining a concept. The
 * heading is passed via `title`; the explanation is the default slot. Reused for
 * the Location-vs-Bin primer and the bin number-lock note.
 */
import Modal from '@/Components/Modal.vue';
import AppButton from '@/Components/AppButton.vue';
import { ref } from 'vue';

defineProps({
    title: { type: String, required: true },
    label: { type: String, default: '' },
});

const open = ref(false);
</script>

<template>
    <button
        type="button"
        class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-ink-tertiary transition hover:bg-background hover:text-ink-secondary focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
        :aria-label="label || title"
        @click="open = true"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            class="h-[18px] w-[18px]"
        >
            <path
                fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9zm1-4a1 1 0 100 2 1 1 0 000-2z"
                clip-rule="evenodd"
            />
        </svg>
    </button>

    <Modal :show="open" max-width="md" @close="open = false">
        <div class="flex flex-col gap-4 p-6">
            <h2 class="font-display text-[18px] font-semibold text-ink-primary">
                {{ title }}
            </h2>
            <div
                class="flex flex-col gap-3 font-sans text-[14px] leading-relaxed text-ink-secondary"
            >
                <slot />
            </div>
            <div class="flex justify-end">
                <AppButton variant="primary" size="sm" @click="open = false">
                    {{ $t('common.got_it') }}
                </AppButton>
            </div>
        </div>
    </Modal>
</template>
