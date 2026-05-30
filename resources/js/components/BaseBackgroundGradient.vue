<script setup lang="ts"></script>

<template>
    <div aria-hidden="true" class="bg-gradient-overlay pointer-events-none fixed inset-0 -z-10 bg-black"></div>
</template>

<style scoped>
.bg-gradient-overlay::after {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0.035;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
    background-repeat: repeat;
    background-size: 16rem 16rem;
}
</style>
