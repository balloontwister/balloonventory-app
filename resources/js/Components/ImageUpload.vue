<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    label: { type: String, default: null },
    file: { type: [File, null], default: null },
    clear: { type: Boolean, default: false },
    currentUrl: { type: String, default: null },
    error: { type: String, default: null },
    accept: {
        type: String,
        default: 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml',
    },
    helpText: { type: String, default: null },
});

const emit = defineEmits(['update:file', 'update:clear']);

const inputRef = ref(null);

// Local preview of the file the user just picked (Object URL). Falls back to
// the existing stored image URL when no new file is selected.
const localPreviewUrl = computed(() =>
    props.file ? URL.createObjectURL(props.file) : null,
);

const previewUrl = computed(() => {
    if (localPreviewUrl.value) return localPreviewUrl.value;
    if (props.clear) return null;
    return props.currentUrl;
});

function onFileChange(event) {
    const picked = event.target.files?.[0] ?? null;
    emit('update:file', picked);
    if (picked) {
        // Picking a new file overrides any pending clear.
        emit('update:clear', false);
    }
}

function onClearToggle(event) {
    emit('update:clear', event.target.checked);
    if (event.target.checked) {
        // Toggling clear discards any picked file too.
        emit('update:file', null);
        if (inputRef.value) inputRef.value.value = '';
    }
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <label
            v-if="label"
            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
        >
            {{ label }}
            <span
                v-if="currentUrl"
                class="normal-case tracking-normal text-ink-tertiary"
            >
                (current)
            </span>
        </label>

        <div class="flex items-center gap-2">
            <img
                v-if="previewUrl"
                :src="previewUrl"
                class="h-12 w-12 shrink-0 rounded-sm object-contain ring-1 ring-inset ring-border"
                alt="Preview"
            />
            <div
                v-else
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-sm border border-dashed border-border bg-background font-sans text-[10px] uppercase tracking-eyebrow text-ink-tertiary"
            >
                None
            </div>
            <input
                ref="inputRef"
                type="file"
                :accept="accept"
                class="block w-full max-w-xs rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-secondary file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:font-sans file:text-[12px] file:font-medium file:text-accent"
                @change="onFileChange"
            />
        </div>

        <label
            v-if="currentUrl"
            class="mt-1 flex cursor-pointer items-center gap-2 font-sans text-[12px] text-ink-secondary"
        >
            <input
                type="checkbox"
                :checked="clear"
                class="h-3.5 w-3.5 accent-danger"
                @change="onClearToggle"
            />
            Remove this image
        </label>

        <p v-if="helpText" class="font-sans text-[11px] text-ink-tertiary">
            {{ helpText }}
        </p>

        <p v-if="error" class="font-sans text-[13px] text-danger">
            {{ error }}
        </p>
    </div>
</template>
