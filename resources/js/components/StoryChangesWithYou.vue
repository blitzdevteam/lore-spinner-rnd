<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import iconYouChoose from '@/assets/story/left.svg';
import iconStoryResponds from '@/assets/story/center.svg';
import iconXen from '@/assets/story/right .svg';
import storyChangesBg from '@/assets/story/story-changes.png';
import tryTellingBanner from '@/assets/banner-nex.png';
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
        iconImgClass: 'h-[2.1875rem] w-[2.125rem]',
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
        iconImgClass: 'h-[1.75rem] w-[2.375rem]',
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
        iconImgClass: 'h-[1.0625rem] w-[3.25rem]',
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
                <SectionHeader
                    title="The Story Changes With You"
                    subtitle="Speak, choose, or write your own path."
                />

                <div
                    class="story-changes-card relative flex min-h-[13.75rem] flex-col overflow-hidden rounded-[1.5rem] border border-solid border-white/15 p-5 outline-none md:min-h-[13.75rem] md:p-5"
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
                                class="story-changes-col flex w-full max-w-[17.5rem] flex-col items-center text-center md:mx-auto md:w-[17.5rem]"
                                :class="item.colClass"
                            >
                                <div
                                    class="icon-circle relative flex size-[5.625rem] shrink-0 items-center justify-center overflow-hidden rounded-full"
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
                                        class="story-changes-col__icon relative z-[2] shrink-0 object-contain"
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
                                <h3 class="story-changes-col__title text-primary">
                                    {{ item.title }}
                                </h3>
                                <p class="story-changes-col__body text-white">
                                    {{ item.description }}
                                </p>
                            </div>
                        </SwiperSlide>
                    </Swiper>
                </div>

                <div
                    class="relative w-full overflow-hidden rounded-[0.875rem] border border-solid border-white/15"
                >
                    <img
                        :src="tryTellingBanner"
                        alt="Try telling the story: Inspect the clue, Climb the tower, or MAKE THE TIN MAN BREAKDANCE. The story responds in real time."
                        class="block w-full h-auto"
                    />
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
    align-items: flex-start;
    height: auto !important;
    box-sizing: border-box;
}

/* Shared vertical rhythm — identical across all three columns */
.story-changes-col {
    --col-gap-icon-title: 0.875rem;
    --col-gap-title-body: 1.25rem;
    gap: var(--col-gap-icon-title);
}

.story-changes-col__icon {
    display: block;
}

.story-changes-col__title {
    margin: 0;
    width: 100%;
    max-width: 15rem;
    text-align: center;
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.375rem;
    min-height: 1.375rem;
}

.story-changes-col__body {
    margin: calc(var(--col-gap-title-body) - var(--col-gap-icon-title)) 0 0;
    width: 100%;
    max-width: 15rem;
    min-height: 2.5rem;
    text-align: center;
    font-size: 0.875rem;
    font-weight: 300;
    line-height: 1.25rem;
    text-shadow: 0px 0px 26.9px #0f0f0f;
}

@media (min-width: 48rem) {
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
    gap: 0.375rem;
}

@media (min-width: 48rem) {
    .story-changes-swiper :deep(.swiper-pagination) {
        display: none;
    }
}

.story-changes-swiper :deep(.swiper-pagination-bullet) {
    width: 1.75rem;
    height: 3px;
    margin: 0 !important;
    border-radius: 62.4375rem;
    background: rgba(255, 255, 255, 0.35);
    opacity: 1;
    transition:
        width 0.3s ease,
        background 0.3s ease;
}

.story-changes-swiper :deep(.swiper-pagination-bullet-active) {
    width: 2.5rem;
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
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.35),
        0 0 1.375rem 3px color-mix(in srgb, var(--color-primary-400) 28%, transparent);
}

.story-changes-col--responds:hover .story-responds-icon {
    border-color: rgba(255, 255, 255, 0.92);
    box-shadow:
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.35),
        0 0 1.375rem 3px rgba(255, 255, 255, 0.28);
}

.story-changes-col--xen:hover .xen-icon {
    border-color: rgba(255, 190, 88, 0.96);
    box-shadow:
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.35),
        0 0 1.375rem 3px rgba(255, 190, 88, 0.28);
}

/* Inset glow overlays rendered as the topmost layer inside each circle */
.you-choose-glow,
.story-responds-glow {
    box-shadow:
        inset 0px 0px 0.8688rem 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}

/* Xen: neutral inset at rest so the circle matches the other two */
.xen-glow {
    box-shadow:
        inset 0px 0px 0.8688rem 0px rgba(255, 255, 255, 0.06),
        inset 0.188px 0.375px 0.375px 0.188px rgba(255, 255, 255, 0.22),
        inset -0.15px -0.375px 0.113px 0.375px rgba(255, 255, 255, 0.05);
}
</style>
