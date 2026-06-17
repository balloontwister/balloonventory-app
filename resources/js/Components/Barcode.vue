<script setup>
import JsBarcode from 'jsbarcode';
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    value: { type: String, required: true },
    // Bar height in px; width is the per-bar module width.
    height: { type: Number, default: 48 },
    width: { type: Number, default: 2 },
});

const svg = ref(null);

function render() {
    if (!svg.value || !props.value) {
        return;
    }
    JsBarcode(svg.value, props.value, {
        format: 'CODE128',
        displayValue: false,
        height: props.height,
        width: props.width,
        margin: 0,
    });
}

onMounted(render);
watch(() => props.value, render);
</script>

<template>
    <svg ref="svg" />
</template>
