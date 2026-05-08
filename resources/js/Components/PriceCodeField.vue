<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    editable: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'save']);

const editing = ref(false);
const draft = ref(props.modelValue);

watch(() => props.modelValue, (v) => { draft.value = v; });

function commit() {
    editing.value = false;
    if (draft.value !== props.modelValue) {
        emit('update:modelValue', draft.value);
        emit('save', draft.value);
    }
}

function startEdit() {
    if (props.editable) editing.value = true;
}
</script>

<template>
    <div class="flex flex-col gap-0.5">
        <span class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
            Price code
        </span>

        <input
            v-if="editing"
            v-model="draft"
            class="rounded-md border border-border-strong bg-surface px-2 py-1 font-mono text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
            @blur="commit"
            @keydown.enter="commit"
            @keydown.escape="editing = false; draft = modelValue"
            autofocus
        />

        <span
            v-else
            class="font-mono text-[14px] text-ink-primary"
            :class="editable ? 'cursor-pointer hover:text-accent' : ''"
            @click="startEdit"
        >
            {{ modelValue || '—' }}
        </span>
    </div>
</template>
