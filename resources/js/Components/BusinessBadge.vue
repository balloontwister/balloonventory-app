<script setup>
defineProps({
    color: { type: String, required: true }, // hex color chosen by user for this business
});
</script>

<template>
    <!-- 2px top bar spanning the full viewport width -->
    <div
        class="fixed inset-x-0 top-0 z-50 h-0.5"
        :style="{ backgroundColor: color }"
        aria-hidden="true"
    />
</template>
