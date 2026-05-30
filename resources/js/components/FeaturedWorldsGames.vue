<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.jpg';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
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
                        class="story-slider-arrow story-slider-arrow--edge-left"
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
                                <HomePortraitStoryCard
                                    :title="game.title"
                                    :cover="game.cover"
                                    :themes="game.themes"
                                    :teaser="game.teaser"
                                    :branches="game.branches"
                                    :playable="game.playable"
                                    :slug="game.slug"
                                    :focused="isDesktopHover && isExpanded(game.id)"
                                    :is-desktop-hover="isDesktopHover"
                                    @preview="openSheet(game)"
                                />
                            </StoryExpandableCard>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="story-slider-arrow story-slider-arrow--edge-right"
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
