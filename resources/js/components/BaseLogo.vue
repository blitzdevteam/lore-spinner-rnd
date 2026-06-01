<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.png';

withDefaults(
    defineProps<{
        alt?: string;
    }>(),
    {
        alt: 'LoreSpinner',
    },
);
</script>

<template>
    <img
        :src="mainLogo"
        :alt="alt"
        class="brand-logo block w-full object-contain object-center"
        width="286"
        height="56"
        decoding="async"
    />
</template>

<style scoped>
.brand-logo {
    height: 2.75rem;
    width: auto;
    max-width: 100%;
}

@media (min-width: 48rem) {
    .brand-logo {
        height: 3.25rem;
    }
}
</style>
