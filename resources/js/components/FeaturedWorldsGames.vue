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
const scrollSlider = (delta: number) => sliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });

const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { hoveredId, onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

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

function themesLine(game: FeaturedGame): string {
    return game.themes.join(' • ');
}
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">

                <SectionHeader
                    title="Featured Worlds"
                    subtitle="Curated story worlds built for choice, consequence, and return."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <div class="relative">
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
                        @click="scrollSlider(-214)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div
                        ref="sliderEl"
                        class="story-slider flex items-start gap-[0.625rem] overflow-x-auto pb-3 transition-[padding] duration-300 ease-out"
                        :class="isDesktopHover && hoveredId ? 'lg:pb-[10rem]' : 'lg:pb-3'"
                    >
                        <StoryExpandableCard
                            v-for="game in games"
                            :key="game.id"
                            class="w-[12rem]"
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
                                class="fg-card block w-full border-0 bg-transparent p-0 text-left outline-none"
                                :class="[
                                    isDesktopHover && isExpanded(game.id) && 'fg-card--expanded',
                                    !isDesktopHover ? 'cursor-pointer' : storyUrl(game) ? 'cursor-pointer no-underline' : 'cursor-default',
                                ]"
                                :aria-label="isDesktopHover && storyUrl(game) ? `Open ${game.title}` : `Preview ${game.title}`"
                                @click="!isDesktopHover && openSheet(game)"
                            >
                                <div class="fg-card__inner">
                                    <!-- Cover: fixed, never changes height -->
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

                                    <!-- Details: slides in on hover via CSS grid trick -->
                                    <div
                                        v-if="isDesktopHover"
                                        class="fg-card__details"
                                        :class="{ 'fg-card__details--open': isExpanded(game.id) }"
                                        aria-hidden="true"
                                    >
                                        <div class="fg-card__details-inner">
                                            <p class="fg-card__themes">{{ themesLine(game) }}</p>
                                            <p class="fg-card__teaser">{{ game.teaser }}</p>
                                            <p v-if="game.branches" class="fg-card__branches">
                                                {{ game.branches }} Branches explored
                                            </p>
                                        </div>
                                    </div>

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
                            </component>
                        </StoryExpandableCard>
                    </div>

                    <button
                        type="button"
                        class="slider-arrow absolute top-1/2 right-0 z-10 hidden translate-x-1/2 -translate-y-1/2 items-center justify-center md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(214)"
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
.story-slider {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.story-slider::-webkit-scrollbar {
    display: none;
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
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
    border-radius: 0.5rem;
    border: 1px solid #373737;
    background: #262626;
    padding: 0.375rem;
    transition:
        border-color 0.22s ease,
        box-shadow 0.22s ease;
}

/* Cover: fixed height, never changes */
.fg-card__cover {
    position: relative;
    height: 17.9649rem;
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
    transition: transform 0.3s ease;
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
    transition: white-space 0.1s;
}

/* Details reveal: grid trick — smooth height animation, no JS needed */
.fg-card__details {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transition:
        grid-template-rows 0.25s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.22s ease;
}

.fg-card__details--open {
    grid-template-rows: 1fr;
    opacity: 1;
}

.fg-card__details-inner {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 0 1px 0.25rem;
}

.fg-card__themes {
    font-size: 0.8125rem;
    line-height: 1.4;
    color: rgba(111, 175, 186, 0.9);
}

.fg-card__teaser {
    font-size: 0.8125rem;
    line-height: 1.55;
    color: #8f8f8f;
}

.fg-card__branches {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #ffbe58;
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

/* ── Desktop expanded state ──────────────────────────────────────────────── */
@media (min-width: 1024px) {
    /* Expanded card elevates above neighbors */
    .fg-card--expanded .fg-card__inner {
        border-color: rgba(111, 175, 186, 0.55);
        box-shadow:
            0 16px 48px rgba(0, 0, 0, 0.6),
            0 0 36px rgba(111, 175, 186, 0.3);
    }

    /* Cover image zooms in slightly */
    .fg-card--expanded .fg-card__cover-img {
        transform: scale(1.04);
    }

    /* Title wraps when expanded */
    .fg-card--expanded .fg-card__title {
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
    }
}
</style>
