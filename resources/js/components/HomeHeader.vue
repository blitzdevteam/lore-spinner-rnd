<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.png';
import HomeHeaderMenu from '@/components/HomeHeaderMenu.vue';
import HomeHeaderProfile from '@/components/HomeHeaderProfile.vue';
import { index } from '@/wayfinder/routes';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <header class="home-header-bar relative z-20 border-b border-gray-900 bg-black">
        <div class="container grid min-h-[72px] grid-cols-[auto_1fr_auto] items-center gap-3 py-2 md:min-h-[80px] md:gap-6 md:py-0">
            <Link
                :href="index().url"
                class="shrink-0 justify-self-start outline-none ring-offset-2 ring-offset-black focus-visible:ring-2 focus-visible:ring-[#00C6DE]"
            >
                <img :src="mainLogo" alt="LoreSpinner" class="h-12 w-auto md:h-36" width="300" height="72" />
            </Link>

            <div class="hidden min-w-0 items-center justify-center gap-6 justify-self-center md:flex lg:gap-8">
                <HomeHeaderMenu />
                <Link
                    :href="storiesIndex().url"
                    class="featured-worlds-btn inline-flex h-9 shrink-0 items-center justify-center rounded-full px-5 text-[15px] font-normal whitespace-nowrap"
                >
                    Featured worlds
                </Link>
            </div>

            <div class="flex shrink-0 items-center justify-end justify-self-end">
                <HomeHeaderProfile />
            </div>
        </div>
    </header>
</template>

<style scoped>
.featured-worlds-btn {
    background: linear-gradient(180deg, #3a4d52 0%, #2c3b3f 100%);
    color: #c8ced1;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.06);
    transition:
        color 0.15s ease,
        background 0.15s ease,
        border-color 0.15s ease;
}

.featured-worlds-btn:hover {
    color: #e8ebed;
    background: linear-gradient(180deg, #45595f 0%, #35474c 100%);
    border-color: rgba(0, 198, 222, 0.25);
}

.featured-worlds-btn:focus-visible {
    outline: 2px solid #00c6de;
    outline-offset: 2px;
}
</style>
