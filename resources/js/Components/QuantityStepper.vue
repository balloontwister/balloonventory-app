<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Number, default: 1 },
    presets: { type: Array, default: () => [3, 5, 10] },
    min: { type: Number, default: 1 },
});

const emit = defineEmits(['update:modelValue']);

const canDecrement = computed(() => props.modelValue > props.min);

function setValue(val) {
    if (val >= props.min) {
        emit('update:modelValue', val);
    }
}

function decrement() {
    if (canDecrement.value) {
        emit('update:modelValue', props.modelValue - 1);
    }
}

function increment() {
    emit('update:modelValue', props.modelValue + 1);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <!-- Preset chips -->
        <div class="flex items-center gap-1.5">
            <button
                v-for="p in presets"
                :key="p"
                type="button"
                class="flex h-8 w-10 items-center justify-center rounded-md border font-sans text-[13px] font-medium transition-colors"
                :class="
                    modelValue === p
                        ? 'border-accent bg-accent text-accent-on'
                        : 'border-border-strong bg-surface text-ink-secondary hover:bg-background'
                "
                @click="setValue(p)"
            >
                {{ p }}
            </button>
        </div>

        <!-- Stepper -->
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-md border border-border-strong bg-surface text-ink-primary transition-colors hover:bg-background disabled:cursor-not-allowed disabled:opacity-30"
                :disabled="!canDecrement"
                @click="decrement"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M4 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H4.75A.75.75 0 014 10z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>

            <span
                class="min-w-[2ch] text-center font-mono text-[20px] font-semibold tabular-nums text-ink-primary"
            >
                {{ modelValue }}
            </span>

            <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-md border border-border-strong bg-surface text-ink-primary transition-colors hover:bg-background"
                @click="increment"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="h-4 w-4"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10 4a.75.75 0 01.75.75v4.5h4.5a.75.75 0 010 1.5h-4.5v4.5a.75.75 0 01-1.5 0v-4.5h-4.5a.75.75 0 010-1.5h4.5v-4.5A.75.75 0 0110 4z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
        </div>
    </div>
</template>
