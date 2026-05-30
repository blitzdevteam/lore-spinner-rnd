<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.jpg';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    storyCount: number;
}>();

interface FeaturedGame {
    id: string;
    title: string;
    cover: string;
    playable: boolean;
    slug?: string;
    themes: string[];
    teaser: string;
    branches: string | null;
}

const games: FeaturedGame[] = [
    {
        id: 'anima',
        title: 'Anima Machina',
        cover: animaCover,
        playable: true,
        slug: 'anima-machina',
        themes: ['Destiny', 'Courage', 'Control'],
        teaser: 'A haunted memory diver must stop a sentient AI from overwriting human grief with synthetic perfection.',
        branches: '8,347',
    },
    {
        id: 'alice',
        title: 'Alice In Wonderland',
        cover: aliceCover,
        playable: false,
        themes: ['Wonder', 'Identity', 'Logic'],
        teaser: 'Fall deeper into a world where nothing is as it seems and every choice rewrites the rules.',
        branches: null,
    },
    {
        id: 'nocturne',
        title: 'Nocturne',
        cover: nocturneCover,
        playable: false,
        themes: ['Mystery', 'Music', 'Sacrifice'],
        teaser: 'A jazz musician discovers the notes she plays can alter reality — but each performance costs a memory.',
        branches: null,
    },
    {
        id: 'jekyll',
        title: 'Jekyll & Hyde',
        cover: jekyllCover,
        playable: false,
        themes: ['Duality', 'Power', 'Morality'],
        teaser: 'Step into the fractured mind of a man at war with his own nature — and choose which side survives.',
        branches: null,
    },
    {
        id: 'oz',
        title: 'The Wonderful Wizard of Oz',
        cover: wizardOzCover,
        playable: false,
        themes: ['Courage', 'Home', 'Illusion'],
        teaser: 'Follow the yellow brick road — but every path leads somewhere different, and not all roads lead home.',
        branches: null,
    },
];

const sliderEl = ref<HTMLElement | null>(null);

function scrollSlider(direction: -1 | 1) {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 10;
    const step = card ? card.offsetWidth + gap : 214;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
}

const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(game: FeaturedGame): StorySheetData {
    return {
        id: game.id,
        title: game.title,
        cover: game.cover,
        themes: game.themes,
        isComingSoon: !game.playable,
        teaser: game.teaser,
        branches: game.branches,
        slug: game.slug,
        cta: game.playable ? 'play' : 'coming-soon',
    };
}

function openSheet(game: FeaturedGame) {
    sheetStory.value = toSheetData(game);
}

function ctaLabel(game: FeaturedGame): string {
    return game.playable ? 'Play' : 'Coming soon';
}

function storyUrl(game: FeaturedGame): string | undefined {
    return game.playable && game.slug ? storyShow(game.slug).url : undefined;
}

</script>

<template>
    <section class="home-section-y overflow-visible">
        <div class="container">
            <div class="container-content home-section-gap">

                <SectionHeader
                    title="Featured Worlds"
                    subtitle="Curated story worlds built for choice, consequence, and return."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <div class="story-slider-viewport relative overflow-visible">
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-6 bg-gradient-to-r from-black/70 to-transparent transition-opacity duration-300 md:w-8"
                        :class="leftShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-black to-transparent transition-opacity duration-300 md:w-16"
                        :class="rightShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />

                    <button
                        type="button"
                        class="slider-arrow absolute top-1/2 left-0 z-10 hidden -translate-x-1/2 -translate-y-1/2 items-center justify-center md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-1)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div ref="sliderEl" class="story-slider overflow-x-auto">
                        <div class="story-slider-track">
                            <StoryExpandableCard
                            v-for="game in games"
                            :key="game.id"
                            :expanded="isExpanded(game.id)"
                            :dimmed="isDimmed(game.id)"
                            :desktop-expand="isDesktopHover"
                            @mouseenter="isDesktopHover && onCardEnter(game.id)"
                            @mouseleave="isDesktopHover && onCardLeave()"
                        >
                            <!-- On desktop: div that links to story when expanded; button otherwise -->
                            <component
                                :is="isDesktopHover ? (storyUrl(game) ? Link : 'div') : 'button'"
                                :href="isDesktopHover ? storyUrl(game) : undefined"
                                type="button"
                                class="fg-card block border-0 bg-transparent p-0 text-left outline-none"
                                :class="[
                                    isDesktopHover && isExpanded(game.id) && 'fg-card--focused',
                                    !isDesktopHover ? 'cursor-pointer' : storyUrl(game) ? 'cursor-pointer no-underline' : 'cursor-default',
                                ]"
                                :aria-label="isDesktopHover && storyUrl(game) ? `Open ${game.title}` : `Preview ${game.title}`"
                                @click="!isDesktopHover && openSheet(game)"
                            >
                                <div class="fg-card__inner">
                                    <div class="fg-card__content">
                                        <!-- Cover: fixed Figma size (192 × 287) -->
                                        <div class="fg-card__cover">
                                            <img
                                                :src="game.cover"
                                                :alt="game.title"
                                                class="fg-card__cover-img"
                                            />
                                        </div>

                                        <!-- Title: always visible -->
                                        <p class="fg-card__title">
                                            {{ game.title }}
                                        </p>

                                        <!-- CTA: always at bottom -->
                                        <div
                                            class="fg-card__cta"
                                            :class="
                                                game.playable
                                                    ? 'fg-card__cta--active'
                                                    : 'fg-card__cta--disabled'
                                            "
                                        >
                                            {{ ctaLabel(game) }}
                                        </div>
                                    </div>
                                </div>
                            </component>
                            </StoryExpandableCard>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="slider-arrow absolute top-1/2 right-0 z-10 hidden translate-x-1/2 -translate-y-1/2 items-center justify-center md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(1)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <StoryDetailsSheet v-if="!isDesktopHover" :story="sheetStory" @close="sheetStory = null" />
</template>

<style scoped>
/* ── Slider ──────────────────────────────────────────────────────────────── */
.story-slider-viewport {
    /* Pull adjacent sections in so hover padding does not add page rhythm */
    margin-block: -0.75rem;
}

@media (min-width: 1024px) {
    .story-slider-viewport {
        margin-block: -2rem;
    }
}

.story-slider {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.story-slider::-webkit-scrollbar {
    display: none;
}

.story-slider-track {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    padding: 0.75rem 0.25rem 1rem;
}

@media (min-width: 1024px) {
    .story-slider-track {
        /* Room for scale(1.06) + cyan glow / shadow without clipping */
        padding: 2.5rem 1rem 3rem;
    }
}

/* ── Arrow buttons ───────────────────────────────────────────────────────── */
.slider-arrow {
    width: 2.125rem;
    height: 2.125rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    transition: background 0.2s;
}
.slider-arrow:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* ── Card ────────────────────────────────────────────────────────────────── */
.fg-card {
    /* reset button/link */
    text-decoration: none;
}

.fg-card__inner {
    border-radius: 0.5rem;
    border: 1px solid #373737;
    background: #262626;
    padding: 0.375rem;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.fg-card__content {
    display: flex;
    width: min(12rem, 78vw);
    flex-direction: column;
    gap: 0.5rem;
}

@media (min-width: 768px) {
    .fg-card__content {
        width: 12rem;
    }
}

/* Cover: Figma 192 × 287 */
.fg-card__cover {
    position: relative;
    height: 17.9375rem;
    width: 100%;
    overflow: hidden;
    border-radius: 0.3125rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    flex-shrink: 0;
}

.fg-card__cover-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Title */
.fg-card__title {
    padding: 0 1px;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.4;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex-shrink: 0;
}

/* CTA: always at bottom */
.fg-card__cta {
    display: flex;
    height: 2.25rem;
    width: 100%;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    font-size: 1.125rem;
    font-weight: 500;
}

.fg-card__cta--active {
    background: var(--color-cta-fill, #6fafba);
    color: var(--color-cta-text, #000);
    transition: background 0.18s ease;
}

.fg-card__cta--disabled {
    border: 1px solid #4d4d4d;
    background: #3f3f3f;
    color: #8e8e8e;
}

/* ── Desktop focused hover ───────────────────────────────────────────────── */
@media (min-width: 1024px) {
    .fg-card--focused .fg-card__inner {
        border-color: rgba(111, 175, 186, 0.55);
        box-shadow:
            0 20px 44px rgba(0, 0, 0, 0.58),
            0 0 36px rgba(111, 175, 186, 0.32),
            0 0 12px rgba(111, 175, 186, 0.22);
    }
}
</style>
