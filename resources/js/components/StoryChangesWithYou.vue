<script setup lang="ts">
import iconYouChoose from '@/assets/story/left.svg';
import iconStoryResponds from '@/assets/story/center.svg';
import iconXen from '@/assets/story/right .svg';
import storyChangesBg from '@/assets/story/story-changes.png';
import { Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/vue';

import 'swiper/css';
import 'swiper/css/pagination';

const swiperModules = [Pagination];

const storyItems = [
    {
        id: 'choose',
        colClass: 'story-changes-col--choose',
        iconClass: 'you-choose-icon',
        glowClass: 'you-choose-glow',
        icon: iconYouChoose,
        iconImgClass: 'h-[35px] w-[34px]',
        width: 34,
        height: 35,
        title: 'You Choose',
        description: 'Choose how the story unfolds and shape what\'s next.',
    },
    {
        id: 'responds',
        colClass: 'story-changes-col--responds',
        iconClass: 'story-responds-icon',
        glowClass: 'story-responds-glow',
        icon: iconStoryResponds,
        iconImgClass: 'h-[28px] w-[38px]',
        width: 38,
        height: 28,
        title: 'The Story Responds',
        description: 'Every choice opens new branches and changes the story in real time.',
    },
    {
        id: 'xen',
        colClass: 'story-changes-col--xen',
        iconClass: 'xen-icon',
        glowClass: 'xen-glow',
        icon: iconXen,
        iconImgClass: 'h-[17px] w-[52px]',
        width: 52,
        height: 17,
        title: 'Xen Guides You',
        description: 'Xen listens, adapts, and brings the story to life.',
    },
] as const;
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">
                <div class="flex w-full flex-col gap-3">
                    <h2 class="text-[26px] font-bold uppercase leading-[33px] text-white">
                        The Story Changes With You
                    </h2>
                    <p class="text-[16px] font-normal leading-[26px] text-[#b6b6b6]">Speak, choose, or write your own path.</p>
                </div>

                <div
                    class="story-changes-card relative flex min-h-[220px] flex-col overflow-hidden rounded-[24px] border border-solid border-white/15 p-5 outline-none md:min-h-[220px] md:p-5"
                >
                    <div
                        class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                        aria-hidden="true"
                    >
                        <img
                            :src="storyChangesBg"
                            alt=""
                            class="absolute inset-0 size-full object-cover object-center"
                        />
                    </div>

                    <Swiper
                        class="story-changes-swiper relative z-[1] w-full"
                        :modules="swiperModules"
                        :slides-per-view="1"
                        :space-between="0"
                        :pagination="{ clickable: true }"
                        :breakpoints="{
                            768: {
                                slidesPerView: 3,
                                spaceBetween: 35,
                                allowTouchMove: false,
                                pagination: { enabled: false },
                            },
                        }"
                    >
                        <SwiperSlide v-for="item in storyItems" :key="item.id">
                            <div
                                class="story-changes-col flex w-full max-w-[280px] flex-col items-center gap-[14px] text-center md:mx-auto md:w-[280px]"
                                :class="item.colClass"
                            >
                                <div
                                    class="icon-circle relative flex size-[90px] shrink-0 items-center justify-center overflow-hidden rounded-full"
                                    :class="item.iconClass"
                                >
                                    <div class="absolute inset-0 rounded-full bg-[rgba(255,255,255,0.02)]" aria-hidden="true" />
                                    <div
                                        class="absolute inset-0 rounded-full backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                        aria-hidden="true"
                                    />
                                    <img
                                        :src="item.icon"
                                        alt=""
                                        class="relative z-[2] shrink-0"
                                        :class="item.iconImgClass"
                                        :width="item.width"
                                        :height="item.height"
                                    />
                                    <div
                                        class="absolute inset-0 pointer-events-none rounded-full"
                                        :class="item.glowClass"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div class="flex flex-col gap-5">
                                    <h3 class="text-center text-[18px] font-semibold leading-normal text-primary">
                                        {{ item.title }}
                                    </h3>
                                    <p
                                        class="max-w-[270px] text-center text-[14px] font-light leading-[1.43] text-white [text-shadow:0px_0px_26.9px_#0f0f0f]"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>
                        </SwiperSlide>
                    </Swiper>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.story-changes-card {
    background-color: #0d0d0d;
    box-shadow: none;
}

.story-changes-swiper {
    height: auto !important;
}

.story-changes-swiper :deep(.swiper-wrapper) {
    align-items: stretch;
}

.story-changes-swiper :deep(.swiper-slide) {
    display: flex;
    justify-content: center;
    align-items: center;
    height: auto !important;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .story-changes-swiper :deep(.swiper-slide:not(:last-child)) {
        border-right: 1px solid rgba(255, 255, 255, 0.14);
    }
}

.story-changes-swiper :deep(.swiper-pagination) {
    position: relative;
    bottom: auto;
    left: auto;
    transform: none;
    width: 100%;
    margin-top: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

@media (min-width: 768px) {
    .story-changes-swiper :deep(.swiper-pagination) {
        display: none;
    }
}

.story-changes-swiper :deep(.swiper-pagination-bullet) {
    width: 28px;
    height: 3px;
    margin: 0 !important;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.35);
    opacity: 1;
    transition:
        width 0.3s ease,
        background 0.3s ease;
}

.story-changes-swiper :deep(.swiper-pagination-bullet-active) {
    width: 40px;
    background: var(--color-primary);
}

/* Glass base for all icon circles -- Figma: subtle 2.25px blur + near-transparent fill */
.icon-circle {
    background: rgba(255, 255, 255, 0.02);
    backdrop-filter: blur(2.25px);
    -webkit-backdrop-filter: blur(2.25px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    transition:
        box-shadow 0.3s ease,
        border-color 0.3s ease;
}

/* Default: all three circles match (no gold until Xen column hover) */
.you-choose-icon,
.story-responds-icon,
.xen-icon {
    border-color: #131313;
    box-shadow: 0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

/* Shared hover intensity — same shadow structure, accent color per column */
.story-changes-col--choose:hover .you-choose-icon {
    border-color: color-mix(in srgb, var(--color-primary-400) 92%, transparent);
    box-shadow:
        0px 3px 60px 0px rgba(0, 0, 0, 0.35),
        0 0 22px 3px color-mix(in srgb, var(--color-primary-400) 28%, transparent);
}

.story-changes-col--responds:hover .story-responds-icon {
    border-color: rgba(255, 255, 255, 0.92);
    box-shadow:
        0px 3px 60px 0px rgba(0, 0, 0, 0.35),
        0 0 22px 3px rgba(255, 255, 255, 0.28);
}

.story-changes-col--xen:hover .xen-icon {
    border-color: rgba(255, 190, 88, 0.96);
    box-shadow:
        0px 3px 60px 0px rgba(0, 0, 0, 0.35),
        0 0 22px 3px rgba(255, 190, 88, 0.28);
}

/* Inset glow overlays rendered as the topmost layer inside each circle */
.you-choose-glow,
.story-responds-glow {
    box-shadow:
        inset 0px 0px 13.9px 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}

/* Xen: neutral inset at rest so the circle matches the other two */
.xen-glow {
    box-shadow:
        inset 0px 0px 13.9px 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}
</style>
