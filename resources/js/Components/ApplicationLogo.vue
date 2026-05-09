<script setup>
import logoLight from '../../images/balloonventory-logo-light.png';
import logoDark from '../../images/balloonventory-logo-dark.png';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <img v-bind="$attrs" :src="logoLight" alt="Balloonventory" class="block dark:hidden" />
    <img v-bind="$attrs" :src="logoDark" alt="Balloonventory" class="hidden dark:block" />
</template>
