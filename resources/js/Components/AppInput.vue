<script setup>
defineProps({
    label: { type: String, default: null },
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    error: { type: String, default: null },
    id: { type: String, default: null },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label
            v-if="label"
            :for="id"
            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
        >
            {{ label }}<span v-if="required" class="ml-0.5 text-danger">*</span>
        </label>

        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:cursor-not-allowed disabled:opacity-50"
            :class="{
                'border-danger focus:border-danger focus:ring-danger-soft':
                    error,
            }"
            @input="$emit('update:modelValue', $event.target.value)"
        />

        <p v-if="error" class="font-sans text-[13px] text-danger">
            {{ error }}
        </p>
    </div>
</template>
