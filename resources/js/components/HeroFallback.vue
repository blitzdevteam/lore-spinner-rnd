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
            class="relative z-10 flex h-full w-full items-center py-8 md:items-start md:pb-10 md:pt-[89px]"
        >
            <div class="container w-full">
                <div class="container-content">
                    <div
                        class="flex w-full max-w-[min(556px,100%)] flex-col gap-[24px] md:max-w-[556px] md:pr-6"
                    >
                        <div class="flex flex-col gap-[15px]">
                            <h1 class="hero-title max-w-[492px] font-marcellus-sc uppercase text-white">
                                Alice's Adventures<br />
                                In Wonderland
                            </h1>

                            <div class="font-[Inter] flex flex-col gap-[10px] text-white">
                                <p class="max-w-[411px] text-[18px] leading-[26px] text-white md:max-w-[411px]">
                                    Step into a world where logic bends and<br />
                                    curiosity leads the way. Nothing is as it seems.
                                </p>
                                <div class="flex flex-col gap-0">
                                    <p class="text-[14px] leading-[26px] text-white">
                                        Written by: <span class="font-normal leading-[26px] text-primary">Lewis Carroll</span>
                                    </p>
                                    <p class="text-[14px] leading-[26px] text-white">
                                        <span class="leading-[26px] text-primary">1.267</span>
                                        <span class="leading-[26px] text-white"> Branching paths explored</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <BaseButton
                                severity="primary"
                                type="internal-link"
                                :href="storiesIndex().url"
                                class="begin-btn font-[Inter] !box-border !flex !h-auto !min-h-[53px] w-full max-w-[284px] items-center justify-center overflow-visible whitespace-nowrap px-8 py-3 text-[16px] font-medium !leading-none"
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
    min-height: 400px;
    height: clamp(420px, 70vh, 561px);
    max-height: 561px;
}

@media (min-width: 768px) {
    .hero-banner {
        height: 561px;
        min-height: 561px;
        max-height: 561px;
    }
}

.hero-title {
    font-size: clamp(1.875rem, 4vw, 48px);
    line-height: 1.08;
    text-shadow: 0 0 21.2px black;
}

@media (min-width: 768px) {
    .hero-title {
        font-size: 48px;
        line-height: 80px;
    }
}

.begin-btn {
    border-radius: 12px;
    box-shadow:
        0 4px 80px rgba(0, 0, 0, 0.2),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
}
</style>
