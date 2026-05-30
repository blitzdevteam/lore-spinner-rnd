<script setup lang="ts">
import HomeFooter from '@/components/HomeFooter.vue';
import HomeHeader from '@/components/HomeHeader.vue';
</script>

<template>
    <div class="home-layout-root bg-black">
        <HomeHeader />
        <slot></slot>
        <HomeFooter />
    </div>
</template>

<style scoped>
.home-layout-root {
    position: relative;
    isolation: isolate;
    overflow: hidden;
}
</style>
