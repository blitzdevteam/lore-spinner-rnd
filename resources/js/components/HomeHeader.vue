<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.svg';
import HomeHeaderMenu from '@/components/HomeHeaderMenu.vue';
import HomeHeaderProfile from '@/components/HomeHeaderProfile.vue';
import { index } from '@/wayfinder/routes';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
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
                    class="logo-link shrink-0 justify-self-start pr-1 ring-offset-2 ring-offset-black outline-none focus-visible:ring-2 focus-visible:ring-[#00C6DE] md:pr-2"
                >
                    <img
                        :src="mainLogo"
                        alt="LoreSpinner"
                        class="brand-logo select-none"
                        width="229"
                        height="45"
                        decoding="async"
                    />
                </Link>

                <div class="hidden min-h-[75px] min-w-0 items-center justify-center gap-0 justify-self-center md:flex">
                    <HomeHeaderMenu />
                    <Link
                        :href="storiesIndex().url"
                        class="featured-worlds-wrap flex h-10 w-[170px] shrink-0 items-center justify-center p-[10px] outline-none ring-offset-2 ring-offset-black focus-visible:ring-2 focus-visible:ring-[#00C6DE]"
                    >
                        <span class="featured-worlds-btn inline-flex h-[30px] w-[147px] shrink-0 items-center justify-center rounded-[8px] text-[15px] font-normal whitespace-nowrap text-white">
                            Featured worlds
                        </span>
                    </Link>
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

/* Figma: logo frame 229.5×45.098 — render at 45px height, width from aspect ratio */
.brand-logo {
    display: block;
    width: auto;
    max-width: min(100%, 229.5px);
    height: 45px;
    object-fit: contain;
    object-position: left center;
}

.logo-link {
    display: inline-flex;
    align-items: center;
}

.featured-worlds-btn {
    position: relative;
    background: linear-gradient(119.62deg, rgba(0, 128, 144, 0.25) 3.47%, rgba(0, 74, 83, 0.25) 105.69%),
        rgba(53, 53, 53, 0.6);
    box-shadow:
        0 4px 80px rgba(0, 0, 0, 0.2),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    transition:
        filter 0.15s ease,
        box-shadow 0.15s ease;
}

.featured-worlds-btn:hover {
    filter: brightness(1.08);
    box-shadow:
        0 4px 80px rgba(0, 0, 0, 0.28),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.28),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.08);
}

.featured-worlds-btn:focus-visible {
    outline: 2px solid #00c6de;
    outline-offset: 2px;
}
</style>
