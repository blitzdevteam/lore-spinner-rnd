<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.png';
import HomeHeaderMenu from '@/components/HomeHeaderMenu.vue';
import HomeHeaderProfile from '@/components/HomeHeaderProfile.vue';
import { index } from '@/wayfinder/routes';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <header class="home-header-bar relative z-20">
        <div class="relative container py-3 md:py-4">
            <div
                class="container-content grid min-h-[7.5rem] grid-cols-[auto_1fr_auto] items-center gap-x-6 gap-y-3 md:min-h-[7.75rem] md:gap-x-12 md:gap-y-0 lg:gap-x-16"
            >
                <Link
                    :href="index().url"
                    class="logo-link shrink-0 justify-self-start pr-1 ring-offset-2 ring-offset-black outline-none focus-visible:ring-2 focus-visible:ring-primary md:pr-0"
                >
                    <img
                        :src="mainLogo"
                        alt="LoreSpinner"
                        class="brand-logo select-none"
                        width="286"
                        height="56"
                        decoding="async"
                    />
                </Link>

                <div class="hidden min-h-[75px] min-w-0 items-center justify-center justify-self-center md:flex">
                    <HomeHeaderMenu />
                </div>

                <div class="flex shrink-0 items-center justify-end justify-self-end">
                    <HomeHeaderProfile />
                </div>
            </div>
        </div>
    </header>
</template>

<style scoped>
.home-header-bar {
    background:rgba(0, 0, 0);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}

/* Figma: logo frame 229.5×45.098 — scaled ~25% for PNG legibility */
.brand-logo {
    display: block;
    width: auto;
    max-width: min(100%, 280px);
    height: 56px;
    object-fit: contain;
    object-position: left center;
}

.logo-link {
    display: inline-flex;
    align-items: center;
}
</style>
