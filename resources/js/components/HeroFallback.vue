<script setup lang="ts">
import BannerImage from '@/assets/aaaaa.jpg';
// import HeroBranchChain from '@/components/HeroBranchChain.vue';
import BaseButton from '@/components/BaseButton.vue';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
</script>

<template>
    <section class="hero-banner relative overflow-hidden bg-black">
        <!-- Background image -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <img
                :src="BannerImage"
                alt="LoreSpinner"
                class="absolute inset-0 h-full w-full max-w-none object-cover object-[right_top]"
            />
        </div>

        <!-- Gradient overlays — Figma node 7053:293 -->
        <div
            class="pointer-events-none absolute inset-0"
            style="
                background:
                    linear-gradient(265.166deg, rgb(0, 0, 0) 4.3442%, rgba(0, 0, 0, 0) 19.425%),
                    linear-gradient(180deg, rgb(0, 0, 0) 12.656%, rgba(0, 0, 0, 0) 44.831%, rgb(0, 0, 0) 102.32%),
                    linear-gradient(-84.979deg, rgba(0, 0, 0, 0) 3.3237%, rgba(0, 0, 0, 0) 44.373%, rgb(0, 0, 0) 79.38%);
            "
        />

        <!-- <HeroBranchChain /> -->

        <div
            class="relative z-10 flex h-full w-full items-center py-8 md:items-start md:pb-10 md:pt-[5.5625rem]"
        >
            <div class="container w-full">
                <div class="container-content">
                    <div
                        class="flex w-full max-w-[min(556px,100%)] flex-col gap-[1.5rem] md:max-w-[34.75rem] md:pr-6"
                    >
                        <div class="flex flex-col gap-[0.9375rem]">
                            <h1 class="hero-title max-w-[30.75rem] font-marcellus-sc uppercase text-white">
                                Alice's Adventures<br />
                                In Wonderland
                            </h1>

                            <div class="font-[Inter] flex flex-col gap-[0.625rem] text-white">
                                <p class="max-w-[25.6875rem] text-[1.125rem] leading-[1.625rem] text-white md:max-w-[25.6875rem]">
                                    Step into a world where logic bends and<br />
                                    curiosity leads the way. Nothing is as it seems.
                                </p>
                                <div class="flex flex-col gap-0">
                                    <p class="text-[0.875rem] leading-[1.625rem] text-white">
                                        Written by: <span class="font-normal leading-[1.625rem] text-primary">Lewis Carroll</span>
                                    </p>
                                    <p class="text-[0.875rem] leading-[1.625rem] text-white">
                                        <span class="leading-[1.625rem] text-primary">1.267</span>
                                        <span class="leading-[1.625rem] text-white"> Branching paths explored</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <BaseButton
                                severity="primary"
                                type="internal-link"
                                :href="storiesIndex().url"
                                class="begin-btn font-[Inter] !box-border !flex !h-auto !min-h-[3.3125rem] w-full max-w-[17.75rem] items-center justify-center overflow-visible whitespace-nowrap px-8 py-3 text-[1rem] font-medium !leading-none"
                            >
                                Begin Your Journey
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-banner {
    min-height: 25rem;
    height: clamp(26.25rem, 70vh, 35.0625rem);
    max-height: 35.0625rem;
}

@media (min-width: 48rem) {
    .hero-banner {
        height: 35.0625rem;
        min-height: 35.0625rem;
        max-height: 35.0625rem;
    }
}

.hero-title {
    font-size: clamp(1.875rem, 4vw, 3rem);
    line-height: 1.08;
    text-shadow: 0 0 21.2px black;
}

@media (min-width: 48rem) {
    .hero-title {
        font-size: 3rem;
        line-height: 5rem;
    }
}

.begin-btn {
    border-radius: 0.75rem;
    box-shadow:
        0 4px 5rem rgba(0, 0, 0, 0.2),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
}
</style>
