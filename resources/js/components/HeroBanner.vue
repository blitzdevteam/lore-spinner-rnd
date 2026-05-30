<script setup lang="ts">
import nocturneImage from '@/assets/carosel/Nocturne 16_9 landscape art.png';
import ozImage from '@/assets/carosel/Oz Landscape art only.png';
import tellTaleImage from '@/assets/carosel/Pride Landscape art only.png';
import BaseButton from '@/components/BaseButton.vue';
import { StoryInterface } from '@/types';
import { show } from '@/wayfinder/routes/stories';
import { Autoplay, EffectFade, Pagination } from 'swiper/modules';
import type { Swiper as SwiperInstance } from 'swiper';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { computed, ref } from 'vue';

import 'swiper/css';
import 'swiper/css/effect-fade';
import 'swiper/css/pagination';

const props = withDefaults(
    defineProps<{
        stories?: StoryInterface[];
    }>(),
    {
        stories: () => [],
    },
);

interface HeroSlideConfig {
    slug: string;
    image: string;
    imagePosition: string;
    titleLines: [string, string] | null;
    fallbackTitle: string;
    teaserLines: [string, string] | null;
    fallbackTeaser: string;
    fallbackAuthor: string;
    fallbackBranching: number;
}

const heroSlideConfigs: HeroSlideConfig[] = [
    {
        slug: 'the-tell-tale-heart',
        image: tellTaleImage,
        imagePosition: 'object-[62%_center] md:object-center',
        titleLines: ['The Tell-Tale', 'Heart'],
        fallbackTitle: 'The Tell-Tale Heart',
        teaserLines: [
            'Convinced of his own sanity, a man slowly loses his grip on reality',
            'as guilt transforms the world around him.',
        ],
        fallbackTeaser:
            'Convinced of his own sanity, a man slowly loses his grip on reality as guilt transforms the world around him.',
        fallbackAuthor: 'Edgar Allan Poe',
        fallbackBranching: 123456,
    },
    {
        slug: 'nocturne',
        image: nocturneImage,
        imagePosition: 'object-[70%_center] md:object-center',
        titleLines: null,
        fallbackTitle: 'Nocturne',
        teaserLines: [
            'After a public scandal shatters her life, a disgraced Japanese heiress discovers',
            'the organization helping her disappear is part of an ancient cult.',
        ],
        fallbackTeaser:
            'After a public scandal shatters her life, a disgraced Japanese heiress discovers the organization helping her disappear is part of an ancient cult that erases and rewrites identity.',
        fallbackAuthor: 'Hilton Williams',
        fallbackBranching: 98765,
    },
    {
        slug: 'the-wonderful-wizard-of-oz',
        image: ozImage,
        imagePosition: 'object-[58%_center] md:object-center',
        titleLines: ['The Wonderful', 'Wizard of Oz'],
        fallbackTitle: 'The Wonderful Wizard of Oz',
        teaserLines: [
            'Follow the yellow brick road — but every path leads somewhere different,',
            'and not all roads lead home.',
        ],
        fallbackTeaser:
            'Follow the yellow brick road — but every path leads somewhere different, and not all roads lead home.',
        fallbackAuthor: 'L. Frank Baum',
        fallbackBranching: 156789,
    },
];

interface ResolvedHeroSlide {
    slug: string;
    image: string;
    imagePosition: string;
    titleLines: [string, string] | null;
    title: string;
    teaserLines: [string, string] | null;
    teaser: string;
    author: string | null;
    branchingCount: string;
    storyUrl: string;
}

const swiperModules = [Autoplay, EffectFade, Pagination];

const swiperRef = ref<SwiperInstance | null>(null);
const activeIndex = ref(0);

function formatBranchingCount(n: number): string {
    return new Intl.NumberFormat('de-DE').format(n);
}

function resolveTitleLines(title: string): [string, string] | null {
    const t = title.trim();
    const m = t.match(/^(.+?)\s+in\s+(.+)$/i);
    if (!m) return null;
    return [m[1].trim(), `In ${m[2].trim()}`];
}

function resolveTeaserLines(teaser: string): [string, string] | null {
    const s = teaser.trim();
    const needle = ' bends and ';
    const i = s.indexOf(needle);
    if (i !== -1) {
        const first = (s.slice(0, i) + ' bends and').trimEnd();
        const second = s.slice(i + needle.length).trim();
        if (second) return [first, second];
    }

    const splitAt = s.search(/\s+(as|and|but|—)\s+/i);
    if (splitAt !== -1) {
        const first = s.slice(0, splitAt).trim();
        const second = s.slice(splitAt).trim();
        if (first && second) return [first, second];
    }

    return null;
}

function branchingFromStory(story: StoryInterface | undefined, fallback: number): string {
    if (!story) return formatBranchingCount(fallback);
    const n = story.chapters_count ?? 0;
    return formatBranchingCount(n * 47 + 312);
}

const slides = computed((): ResolvedHeroSlide[] =>
    heroSlideConfigs.map((config) => {
        const story = props.stories.find((s) => s.slug === config.slug);
        const title = story?.title ?? config.fallbackTitle;
        const teaser = story?.teaser?.trim() ?? config.fallbackTeaser;

        return {
            slug: config.slug,
            image: config.image,
            imagePosition: config.imagePosition,
            titleLines: story ? resolveTitleLines(title) ?? config.titleLines : config.titleLines,
            title,
            teaserLines: story ? resolveTeaserLines(teaser) ?? config.teaserLines : config.teaserLines,
            teaser,
            author: story?.creator?.full_name ?? config.fallbackAuthor,
            branchingCount: branchingFromStory(story, config.fallbackBranching),
            storyUrl: show(config.slug).url,
        };
    }),
);

const activeSlide = computed(() => slides.value[activeIndex.value] ?? slides.value[0]);

function onSwiper(swiper: SwiperInstance) {
    swiperRef.value = swiper;
}

function onSlideChange(swiper: SwiperInstance) {
    activeIndex.value = swiper.realIndex;
}

function goPrev() {
    swiperRef.value?.slidePrev();
}

function goNext() {
    swiperRef.value?.slideNext();
}
</script>

<template>
    <section class="hero-banner">
        <div class="hero-media">
            <Swiper
                class="hero-swiper h-full w-full"
                :modules="swiperModules"
                :slides-per-view="1"
                :effect="'fade'"
                :fade-effect="{ crossFade: true }"
                :speed="900"
                :loop="true"
                :autoplay="{ delay: 6500, disableOnInteraction: false, pauseOnMouseEnter: true }"
                :pagination="{ clickable: true }"
                @swiper="onSwiper"
                @slide-change="onSlideChange"
            >
                <SwiperSlide v-for="slide in slides" :key="slide.slug" class="hero-slide">
                    <div class="hero-slide-media">
                        <img
                            :src="slide.image"
                            alt=""
                            class="hero-slide-image h-full w-full object-cover"
                            :class="slide.imagePosition"
                        />
                        <div class="hero-slide-gradient" aria-hidden="true" />
                    </div>
                </SwiperSlide>
            </Swiper>

            <button
                type="button"
                class="hero-arrow hero-arrow-prev"
                aria-label="Previous slide"
                @click="goPrev"
            >
                <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                    <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <button
                type="button"
                class="hero-arrow hero-arrow-next"
                aria-label="Next slide"
                @click="goNext"
            >
                <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <div class="hero-copy-wrap">
            <div class="container w-full">
                <div class="container-content">
                    <Transition name="hero-copy" mode="out-in">
                        <div :key="activeSlide.slug" class="hero-copy">
                            <div class="hero-copy-body">
                                <h1 class="hero-title font-marcellus-sc uppercase text-white">
                                    <template v-if="activeSlide.titleLines">
                                        {{ activeSlide.titleLines[0] }}<br />
                                        {{ activeSlide.titleLines[1] }}
                                    </template>
                                    <template v-else>{{ activeSlide.title }}</template>
                                </h1>

                                <div class="hero-meta font-[Inter] text-white">
                                    <p class="hero-teaser text-white">
                                        <span class="md:hidden">{{ activeSlide.teaser }}</span>
                                        <span class="hidden md:inline">
                                            <template v-if="activeSlide.teaserLines">
                                                {{ activeSlide.teaserLines[0] }}<br />
                                                {{ activeSlide.teaserLines[1] }}
                                            </template>
                                            <template v-else>{{ activeSlide.teaser }}</template>
                                        </span>
                                    </p>
                                    <div class="hero-stats flex flex-col gap-0">
                                        <p v-if="activeSlide.author" class="hero-stat-line text-white">
                                            Written by:
                                            <span class="text-primary">{{ activeSlide.author }}</span>
                                        </p>
                                        <p class="hero-stat-line text-white">
                                            <span class="text-primary">{{ activeSlide.branchingCount }}</span>
                                            <span> Branching paths explored</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <BaseButton
                                severity="transparent"
                                type="internal-link"
                                :href="activeSlide.storyUrl"
                                class="begin-btn font-[Inter] !box-border !inline-flex !h-auto !min-h-[48px] !w-auto max-w-full items-center justify-center self-start whitespace-nowrap px-6 py-3 text-[15px] font-medium !leading-none text-white sm:min-h-[53px] sm:max-w-[284px] sm:px-8 sm:text-[16px]"
                            >
                                Begin Your Journey
                            </BaseButton>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Full viewport height — no cap */
.hero-banner {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #000;
}

.hero-media {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    min-height: 200px;
    max-height: min(52vh, 360px);
    flex-shrink: 0;
}

.hero-swiper :deep(.swiper-slide) {
    position: relative;
}

.hero-slide-media {
    position: relative;
    width: 100%;
    height: 100%;
}

.hero-slide-image {
    display: block;
}

.hero-slide-gradient {
    display: none;
    pointer-events: none;
    position: absolute;
    inset: 0;
}

.hero-copy-wrap {
    position: relative;
    z-index: 10;
    background: #000;
    padding: 1.25rem 0 1.5rem;
}

.hero-copy {
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 1.25rem;
}

.hero-copy-body {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.hero-title {
    max-width: 18ch;
    font-size: clamp(1.625rem, 7vw, 2.25rem);
    line-height: 1.12;
    text-shadow: 0 0 21.2px rgba(0, 0, 0, 0.85);
}

.hero-meta {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.hero-teaser {
    max-width: 38rem;
    font-size: 0.9375rem;
    line-height: 1.55;
    text-shadow: 0 1px 10px rgba(0, 0, 0, 0.75);
}

.hero-stat-line {
    font-size: 0.8125rem;
    line-height: 1.5;
    text-shadow: 0 1px 8px rgba(0, 0, 0, 0.75);
}

/* See-through glass with subtle brand tint — slide still visible behind */
.begin-btn {
    border-radius: 12px;
    background: color-mix(in srgb, var(--color-primary-700) 16%, transparent) !important;
    border: 1px solid color-mix(in srgb, var(--color-primary-400) 55%, rgba(255, 255, 255, 0.28)) !important;
    box-shadow:
        0 0 28px color-mix(in srgb, var(--color-primary-500) 22%, transparent),
        inset 0 1px 0 color-mix(in srgb, var(--color-primary-300) 30%, transparent) !important;
    filter: none !important;
    outline: none !important;
    backdrop-filter: blur(10px) saturate(1.15);
    -webkit-backdrop-filter: blur(10px) saturate(1.15);
    color: #fff !important;
    text-shadow: 0 1px 8px rgba(0, 0, 0, 0.8);
}

.begin-btn:hover,
.begin-btn:focus-visible {
    background: color-mix(in srgb, var(--color-primary-600) 26%, transparent) !important;
    border-color: color-mix(in srgb, var(--color-primary-300) 65%, rgba(255, 255, 255, 0.35)) !important;
    box-shadow:
        0 0 36px color-mix(in srgb, var(--color-primary-400) 32%, transparent),
        inset 0 1px 0 color-mix(in srgb, var(--color-primary-200) 40%, transparent) !important;
    outline: none !important;
}

.hero-arrow {
    position: absolute;
    top: 50%;
    z-index: 20;
    display: flex;
    width: 34px;
    height: 34px;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(6px);
    transform: translateY(-50%);
    transition: background 0.2s, border-color 0.2s;
}

.hero-arrow-prev {
    left: 0.625rem;
}

.hero-arrow-next {
    right: 0.625rem;
}

.hero-arrow:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(255, 255, 255, 0.28);
}


.hero-copy-enter-active,
.hero-copy-leave-active {
    transition:
        opacity 0.45s ease,
        transform 0.45s ease;
}

.hero-copy-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.hero-copy-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

@media (min-width: 640px) {
    .hero-media {
        max-height: min(48vh, 400px);
    }

    .hero-copy-wrap {
        padding: 1.5rem 0 1.75rem;
    }

    .hero-copy {
        gap: 1.5rem;
    }

    .hero-copy-body {
        gap: 1rem;
    }

    .hero-title {
        max-width: 22ch;
        font-size: clamp(2rem, 5vw, 2.75rem);
    }

    .hero-teaser {
        font-size: 1rem;
        line-height: 1.6;
    }

    .hero-stat-line {
        font-size: 0.875rem;
        line-height: 1.625;
    }

    .hero-arrow {
        width: 38px;
        height: 38px;
    }

    .hero-arrow-prev {
        left: 0.875rem;
    }

    .hero-arrow-next {
        right: 0.875rem;
    }
}

@media (max-width: 767px) {
    .hero-copy-wrap {
        background: #000;
    }

    .hero-copy {
        gap: 1.5rem;
    }

    .begin-btn {
        width: auto;
        max-width: min(100%, 284px);
        align-self: flex-start;
    }
}

@media (min-width: 768px) {
    .hero-slide-gradient {
        display: block;
        /* LoreSpinner Desktop Hero Readability Gradient — left-to-right cinematic fade */
        background: linear-gradient(
            90deg,
            rgba(0, 0, 0, 0.86) 0%,
            rgba(0, 0, 0, 0.7) 16%,
            rgba(0, 0, 0, 0.42) 30%,
            rgba(0, 0, 0, 0.16) 40%,
            rgba(0, 0, 0, 0) 46%
        );
    }

    .hero-banner {
        position: relative;
        display: block;
        height: 561px;
        min-height: 561px;
        max-height: 561px;
    }

    .hero-media {
        position: absolute;
        inset: 0;
        aspect-ratio: unset;
        min-height: unset;
        max-height: none;
        height: 100%;
    }

    .hero-copy-wrap {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: flex-start;
        background: transparent;
        padding: 5.5625rem 0 2.5rem;
        pointer-events: none;
    }

    .hero-copy-wrap .container,
    .hero-copy-wrap .container-content {
        height: 100%;
    }

    .hero-copy {
        pointer-events: auto;
        max-width: min(556px, 100%);
        gap: 1.5rem;
        padding-right: 1.5rem;
    }

    .hero-copy-body {
        gap: 0.9375rem;
    }

    .hero-title {
        max-width: 492px;
        font-size: clamp(2rem, 4vw, 3rem);
        line-height: 1.08;
        text-shadow: 0 0 21.2px rgba(0, 0, 0, 0.85);
    }

    .hero-teaser {
        max-width: 411px;
        font-size: 1.125rem;
        line-height: 1.444;
        text-shadow: 0 1px 12px rgba(0, 0, 0, 0.75);
    }

    .hero-stat-line {
        text-shadow: 0 1px 10px rgba(0, 0, 0, 0.75);
    }

    .hero-arrow {
        width: 40px;
        height: 40px;
    }

    .hero-arrow-prev {
        left: 1rem;
    }

    .hero-arrow-next {
        right: 1rem;
    }

}

@media (min-width: 1024px) {
    .hero-title {
        font-size: 3rem;
        line-height: 5rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-copy-enter-active,
    .hero-copy-leave-active {
        transition-duration: 0.01ms;
    }
}
</style>

<style>
.hero-swiper .swiper-pagination {
    bottom: 0.75rem !important;
    left: 50% !important;
    transform: translateX(-50%);
    width: auto !important;
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 20;
    padding: 0 0.5rem;
}

@media (min-width: 768px) {
    .hero-swiper .swiper-pagination {
        bottom: 1.25rem !important;
        gap: 8px;
    }
}

.hero-swiper .swiper-pagination-bullet {
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

@media (min-width: 768px) {
    .hero-swiper .swiper-pagination-bullet {
        width: 36px;
        height: 4px;
    }
}

.hero-swiper .swiper-pagination-bullet-active {
    width: 40px;
    background: var(--color-primary);
}

@media (min-width: 768px) {
    .hero-swiper .swiper-pagination-bullet-active {
        width: 52px;
    }
}
</style>
