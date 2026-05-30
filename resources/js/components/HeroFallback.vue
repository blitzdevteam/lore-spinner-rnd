<script setup lang="ts">
import BannerImage from '@/assets/banner.png';
import BaseButton from '@/components/BaseButton.vue';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
</script>

<template>
    <section class="hero-banner relative overflow-hidden bg-black">
        <!-- Background image -->
        <div class="absolute inset-0">
            <img
                :src="BannerImage"
                alt="LoreSpinner"
                class="h-full w-full object-cover object-[60%_center]"
            />
        </div>

        <!-- Complex gradient overlays matching Figma -->
        <div
            class="absolute inset-0"
            style="background: linear-gradient(259deg, rgba(0,0,0,0.2) 16.5%, rgba(102,102,102,0) 29.3%), linear-gradient(180deg, rgb(0,0,0) 5.4%, rgba(0,0,0,0) 47.3%, rgb(0,0,0) 100%), linear-gradient(270deg, rgba(0,0,0,0) 56.9%, rgb(0,0,0) 70%)"
        />

        <div class="relative z-10 flex h-full items-center py-8 md:py-0">
            <div class="container">
                <div class="flex max-w-[90%] flex-col gap-[24px] sm:max-w-xl md:max-w-[556px]">
                    <div class="flex flex-col gap-[15px]">
                        <h1 class="hero-title font-marcellus-sc uppercase text-white">
                            Stories That Live<br />Through You
                        </h1>

                        <div class="flex flex-col gap-[10px] text-white">
                            <p class="max-w-[411px] text-[18px] leading-[26px] text-white/90 md:max-w-[556px]">
                                Step into worlds where your choices shape the story. Read, choose, or write your own path
                                through endlessly branching narratives.
                            </p>
                            <p class="text-[14px] text-primary font-medium leading-[26px]">
                                Thousands of branching paths to explore
                            </p>
                        </div>
                    </div>

                    <div>
                        <BaseButton
                            severity="primary"
                            type="internal-link"
                            :href="storiesIndex().url"
                            class="begin-btn h-[53px] min-w-[284px] px-8 text-base font-medium"
                        >
                            Begin Your Journey
                        </BaseButton>
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
    text-shadow: 0 0 21px black;
}

@media (min-width: 768px) {
    .hero-title {
        font-size: 48px;
        line-height: 80px;
    }
}

.begin-btn {
    border-radius: 12px;
}
</style>
