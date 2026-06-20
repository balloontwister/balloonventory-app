<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    countries: { type: Object, required: true },
    id: { type: String, default: 'country' },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Select a country…' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <select
        :id="id"
        :value="modelValue"
        :disabled="disabled"
        class="mt-1 block w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:opacity-50"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <option value="">{{ placeholder }}</option>
        <option
            v-for="(name, code) in countries"
            :key="code"
            :value="code"
        >
            {{ name }}
        </option>
    </select>
</template>
