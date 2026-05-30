<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.png';
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
                        width="530"
                        height="300"
                        decoding="async"
                    />
                </Link>

                <div class="hidden min-w-0 items-center justify-center gap-6 justify-self-center md:flex lg:gap-7">
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

/* 6rem lockup on md+; drop max-width caps so the asset isn’t letterboxed smaller than the box */
.brand-logo {
    display: block;
    width: auto;
    max-width: 100%;
    height: clamp(3.75rem, 10vw, 6rem);
    object-fit: contain;
    object-position: left center;
}

@media (min-width: 768px) {
    .brand-logo {
        height: 6rem;
    }
}

.logo-link {
    display: inline-flex;
    align-items: center;
}

@media (min-width: 768px) {
    .logo-link {
        min-height: 6rem;
    }
}

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
