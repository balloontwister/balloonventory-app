<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import AppButton from '@/Components/AppButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    // { id, name }
    sku: { type: Object, required: true },
    // Map of field key -> the value currently shown on the detail card, so the
    // report can capture what our record says alongside the user's correction.
    fieldValues: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

// Field keys MUST match InventoryController::FEEDBACK_FIELDS.
const FIELDS = [
    'name',
    'brand',
    'size',
    'shape',
    'color',
    'texture',
    'material',
    'count_per_bag',
    'packaging',
    'barcode',
    'image',
    'other',
];

const form = useForm({
    field: 'name',
    current_value: '',
    suggested_value: '',
    note: '',
});

const sent = ref(false);

// The current record for the selected field (read-only context for the user).
const currentValue = computed(() => props.fieldValues?.[form.field] ?? '');

// Image/other reports are described in the note rather than a single value.
const isFreeform = computed(
    () => form.field === 'image' || form.field === 'other',
);

watch(
    () => props.show,
    (val) => {
        if (val) {
            sent.value = false;
            form.reset();
            form.clearErrors();
            form.field = 'name';
        }
    },
);

// Keep the captured "our record" snapshot in sync with the chosen field.
watch(
    () => form.field,
    () => {
        form.suggested_value = '';
        form.clearErrors();
    },
);

function submit() {
    form
        .transform((data) => ({
            ...data,
            current_value: currentValue.value || null,
            suggested_value: isFreeform.value ? null : data.suggested_value,
        }))
        .post(route('inventory.sku.feedback', props.sku.id), {
            preserveScroll: true,
            onSuccess: () => {
                sent.value = true;
                form.reset();
            },
        });
}

function close() {
    emit('close');
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6">
            <!-- Success state -->
            <template v-if="sent">
                <div class="flex flex-col items-center gap-4 py-4 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-6 w-6 text-accent"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div>
                        <p
                            class="font-sans text-[16px] font-semibold text-ink-primary"
                        >
                            {{ $t('inventory.show.feedback_success_title') }}
                        </p>
                        <p class="mt-1 font-sans text-[14px] text-ink-secondary">
                            {{ $t('inventory.show.feedback_success_body') }}
                        </p>
                    </div>
                    <AppButton variant="secondary" size="sm" @click="close">
                        {{ $t('inventory.show.feedback_close') }}
                    </AppButton>
                </div>
            </template>

            <!-- Form state -->
            <template v-else>
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <p
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                        >
                            {{ $t('inventory.show.feedback_eyebrow') }}
                        </p>
                        <h2
                            class="mt-0.5 font-sans text-[18px] font-semibold text-ink-primary"
                        >
                            {{ $t('inventory.show.feedback_heading') }}
                        </h2>
                        <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                            {{ $t('inventory.show.feedback_subheading') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="ml-4 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
                        @click="close"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    <!-- Which field is wrong -->
                    <div class="flex flex-col gap-1">
                        <label
                            for="feedback-field"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.feedback_field_label') }}
                        </label>
                        <select
                            id="feedback-field"
                            v-model="form.field"
                            class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        >
                            <option
                                v-for="key in FIELDS"
                                :key="key"
                                :value="key"
                            >
                                {{ $t(`inventory.show.feedback_field_${key}`) }}
                            </option>
                        </select>
                    </div>

                    <!-- Our current record (read-only context) -->
                    <div
                        v-if="currentValue"
                        class="rounded-md border border-border bg-background px-3 py-2"
                    >
                        <p
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-tertiary"
                        >
                            {{ $t('inventory.show.feedback_current_label') }}
                        </p>
                        <p
                            class="mt-0.5 font-sans text-[14px] text-ink-secondary"
                        >
                            {{ currentValue }}
                        </p>
                    </div>

                    <!-- Suggested correct value (hidden for image/other) -->
                    <div v-if="!isFreeform" class="flex flex-col gap-1">
                        <label
                            for="feedback-suggested"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.feedback_suggested_label') }}
                        </label>
                        <input
                            id="feedback-suggested"
                            v-model="form.suggested_value"
                            type="text"
                            :placeholder="
                                $t('inventory.show.feedback_suggested_placeholder')
                            "
                            :disabled="form.processing"
                            class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:cursor-not-allowed disabled:opacity-50"
                            :class="{
                                'border-danger focus:border-danger focus:ring-danger-soft':
                                    form.errors.suggested_value,
                            }"
                        />
                        <p
                            v-if="form.errors.suggested_value"
                            class="font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.suggested_value }}
                        </p>
                    </div>

                    <!-- Note -->
                    <div class="flex flex-col gap-1">
                        <label
                            for="feedback-note"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.feedback_note_label') }}
                        </label>
                        <textarea
                            id="feedback-note"
                            v-model="form.note"
                            rows="3"
                            :placeholder="
                                $t('inventory.show.feedback_note_placeholder')
                            "
                            :disabled="form.processing"
                            class="w-full resize-y rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:cursor-not-allowed disabled:opacity-50"
                            :class="{
                                'border-danger focus:border-danger focus:ring-danger-soft':
                                    form.errors.note,
                            }"
                        />
                        <p
                            v-if="form.errors.note"
                            class="font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.note }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-1">
                        <AppButton
                            variant="secondary"
                            type="button"
                            :disabled="form.processing"
                            @click="close"
                        >
                            {{ $t('inventory.show.feedback_cancel') }}
                        </AppButton>
                        <AppButton
                            variant="primary"
                            type="submit"
                            :disabled="form.processing"
                        >
                            {{ $t('inventory.show.feedback_submit') }}
                        </AppButton>
                    </div>
                </form>
            </template>
        </div>
    </Modal>
</template>
