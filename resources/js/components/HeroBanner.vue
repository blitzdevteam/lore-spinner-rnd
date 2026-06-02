<script setup lang="ts">
import janeEyreImage from '@/assets/carosel/Ultrawide jane eyre.png';
import masqueImage from '@/assets/carosel/Ultrawide 21_9 The Masque of the Red Death.png';
import nocturneImage from '@/assets/carosel/Ultrawide nocturne.png';
import ozImage from '@/assets/carosel/Ultrawide Oz.png';
import sherlockImage from '@/assets/carosel/Sherlock Ultrawide hero banner.png';
import tellTaleImage from '@/assets/carosel/Ultrawide the tell tale heart.png';
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
    comingSoon?: boolean;
}

const heroSlideConfigs: HeroSlideConfig[] = [
    {
        slug: 'the-tell-tale-heart',
        image: tellTaleImage,
        imagePosition: 'object-[62%_top] md:object-top',
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
        slug: 'the-adventure-of-the-speckled-band',
        image: sherlockImage,
        imagePosition: 'object-[60%_top] md:object-top',
        titleLines: ['Sherlock Holmes', 'In The Speckled Band'],
        fallbackTitle: 'Sherlock Holmes in The Speckled Band',
        teaserLines: [
            'Helen Stoner fears she will die as her twin did — in a locked room, after a low whistle at three in the morning.',
            'Holmes and Watson must unravel the mystery before the speckled band strikes again.',
        ],
        fallbackTeaser:
            'Helen Stoner fears she will die as her twin did — in a locked room, after a low whistle at three in the morning. Holmes and Watson must unravel the mystery before the speckled band strikes again.',
        fallbackAuthor: 'Arthur Conan Doyle',
        fallbackBranching: 142857,
    },
    {
        slug: 'the-masque-of-the-red-death',
        image: masqueImage,
        imagePosition: 'object-[68%_top] md:object-top',
        titleLines: ['The Masque of', 'the Red Death'],
        fallbackTitle: 'The Masque of the Red Death',
        teaserLines: [
            'A prince seals his revellers inside a great abbey to escape a plague.',
            'But at the height of the masquerade, a masked stranger moves through every room — and no mortal hand can stop what walks beneath the mask.',
        ],
        fallbackTeaser:
            'A prince seals his revellers inside a great abbey to escape a plague. But at the height of the masquerade, a masked stranger moves through every room — and no mortal hand can stop what walks beneath the mask.',
        fallbackAuthor: 'Edgar Allan Poe',
        fallbackBranching: 98765,
    },
    {
        slug: 'nocturne',
        image: nocturneImage,
        imagePosition: 'object-[70%_top] md:object-top',
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
        comingSoon: true,
    },
    {
        slug: 'the-wonderful-wizard-of-oz',
        image: ozImage,
        imagePosition: 'object-[58%_top] md:object-top',
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
    {
        slug: 'jane-eyre',
        image: janeEyreImage,
        imagePosition: 'object-[72%_top] md:object-top',
        titleLines: null,
        fallbackTitle: 'Jane Eyre',
        teaserLines: [
            'An orphaned governess arrives at Thornfield Hall, where she falls for her brooding employer —',
            'but the house holds secrets that could destroy them both.',
        ],
        fallbackTeaser:
            'An orphaned governess arrives at Thornfield Hall, where she falls for her brooding employer — but the house holds secrets that could destroy them both.',
        fallbackAuthor: 'Charlotte Brontë',
        fallbackBranching: 134521,
        comingSoon: true,
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
    comingSoon: boolean;
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
            comingSoon: config.comingSoon ?? false,
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
                                    <p
                                        class="hero-teaser text-white"
                                        :title="activeSlide.teaser"
                                    >
                                        {{ activeSlide.teaser }}
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

                            <span
                                v-if="activeSlide.comingSoon"
                                class="begin-btn begin-btn--coming-soon font-[Inter] !box-border !inline-flex !h-auto !min-h-[3rem] !w-auto max-w-full items-center justify-center self-start whitespace-nowrap px-6 py-3 text-[0.9375rem] font-medium !leading-none sm:min-h-[3.3125rem] sm:max-w-[17.75rem] sm:px-8 sm:text-[1rem]"
                                aria-disabled="true"
                            >
                                Coming Soon
                            </span>
                            <BaseButton
                                v-else
                                severity="transparent"
                                type="internal-link"
                                :href="activeSlide.storyUrl"
                                class="begin-btn font-[Inter] !box-border !inline-flex !h-auto !min-h-[3rem] !w-auto max-w-full items-center justify-center self-start whitespace-nowrap px-6 py-3 text-[0.9375rem] font-medium !leading-none text-white sm:min-h-[3.3125rem] sm:max-w-[17.75rem] sm:px-8 sm:text-[1rem]"
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
    min-height: 12.5rem;
    max-height: min(48vh, 20.5rem);
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
    object-position: top;
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
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
}

.hero-stat-line {
    font-size: 0.8125rem;
    line-height: 1.5;
    text-shadow: 0 1px 8px rgba(0, 0, 0, 0.75);
}

/* See-through glass with subtle brand tint — slide still visible behind */
.begin-btn {
    border-radius: 0.75rem;
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

.begin-btn--coming-soon {
    background:
        linear-gradient(
            135deg,
            color-mix(in srgb, var(--color-secondary-300) 10%, transparent) 0%,
            color-mix(in srgb, var(--color-primary-700) 14%, rgba(0, 0, 0, 0.55)) 100%
        ) !important;
    border: 1px solid color-mix(in srgb, var(--color-secondary-300) 38%, rgba(255, 255, 255, 0.18)) !important;
    box-shadow:
        0 0 14px color-mix(in srgb, var(--color-secondary-300) 10%, transparent),
        inset 0 1px 0 color-mix(in srgb, var(--color-secondary-300) 14%, rgba(255, 255, 255, 0.08)) !important;
    color: rgba(255, 255, 255, 0.88) !important;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.75);
    cursor: default;
    pointer-events: none;
    opacity: 0.88;
}

.begin-btn--coming-soon:hover,
.begin-btn--coming-soon:focus-visible {
    background:
        linear-gradient(
            135deg,
            color-mix(in srgb, var(--color-secondary-300) 10%, transparent) 0%,
            color-mix(in srgb, var(--color-primary-700) 14%, rgba(0, 0, 0, 0.55)) 100%
        ) !important;
    border-color: color-mix(in srgb, var(--color-secondary-300) 38%, rgba(255, 255, 255, 0.18)) !important;
    box-shadow:
        0 0 14px color-mix(in srgb, var(--color-secondary-300) 10%, transparent),
        inset 0 1px 0 color-mix(in srgb, var(--color-secondary-300) 14%, rgba(255, 255, 255, 0.08)) !important;
}

.hero-arrow {
    position: absolute;
    top: 50%;
    z-index: 20;
    display: flex;
    width: 2.125rem;
    height: 2.125rem;
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
    transform: translateY(0.625rem);
}

.hero-copy-leave-to {
    opacity: 0;
    transform: translateY(-0.375rem);
}

@media (min-width: 40rem) {
    .hero-media {
        max-height: min(44vh, 23rem);
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
        width: 2.375rem;
        height: 2.375rem;
    }

    .hero-arrow-prev {
        left: 0.875rem;
    }

    .hero-arrow-next {
        right: 0.875rem;
    }
}

@media (max-width: 47.9375rem) {
    .hero-copy-wrap {
        background: #000;
    }

    .hero-copy {
        gap: 1.5rem;
    }

    .begin-btn {
        width: auto;
        max-width: min(100%, 17.75rem);
        align-self: flex-start;
    }
}

@media (min-width: 48rem) {
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
        /* Scale with viewport height on large displays; floor matches original design. */
        height: clamp(33rem, 50vh, 48rem);
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
        padding: 5.5625rem 0 1.75rem;
        pointer-events: none;
    }

    .hero-copy-wrap .container,
    .hero-copy-wrap .container-content {
        height: 100%;
    }

    .hero-copy {
        pointer-events: auto;
        max-width: min(34.75rem, 100%);
        gap: 1.5rem;
        padding-right: 1.5rem;
    }

    .hero-copy-body {
        gap: 0.9375rem;
    }

    .hero-title {
        max-width: 30.75rem;
        font-size: clamp(2rem, 4vw, 3rem);
        line-height: 1.08;
        text-shadow: 0 0 21.2px rgba(0, 0, 0, 0.85);
    }

    .hero-teaser {
        max-width: 25.6875rem;
        font-size: 1.125rem;
        line-height: 1.444;
        text-shadow: 0 1px 12px rgba(0, 0, 0, 0.75);
        -webkit-line-clamp: 2;
    }

    .hero-stat-line {
        text-shadow: 0 1px 10px rgba(0, 0, 0, 0.75);
    }

    .hero-arrow {
        width: 2.5rem;
        height: 2.5rem;
    }

    .hero-arrow-prev {
        left: 1rem;
    }

    .hero-arrow-next {
        right: 1rem;
    }

}

@media (min-width: 64rem) {
    .hero-title {
        font-size: 3rem;
        line-height: 1.08;
    }
}

/* iMac / large desktop — taller hero without dominating the page */
@media (min-width: 80rem) {
    .hero-banner {
        height: clamp(38rem, 55vh, 56rem);
    }

    .hero-copy-wrap {
        padding-top: clamp(5.5625rem, 10vh, 7.5rem);
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
    gap: 0.375rem;
    z-index: 20;
    padding: 0 0.5rem;
}

@media (min-width: 48rem) {
    .hero-swiper .swiper-pagination {
        bottom: 1.25rem !important;
        gap: 0.5rem;
    }
}

.hero-swiper .swiper-pagination-bullet {
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

@media (min-width: 48rem) {
    .hero-swiper .swiper-pagination-bullet {
        width: 2.25rem;
        height: 0.25rem;
    }
}

.hero-swiper .swiper-pagination-bullet-active {
    width: 2.5rem;
    background: var(--color-primary);
}

@media (min-width: 48rem) {
    .hero-swiper .swiper-pagination-bullet-active {
        width: 3.25rem;
    }
}
</style>
