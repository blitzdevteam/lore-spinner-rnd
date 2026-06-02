<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import animaCover from '@/assets/featured/anima.jpg';
import janeEyreCover from '@/assets/featured/janeEyre.png';
import redDeathCover from '@/assets/featured/redDeath.png';
import sherlockCover from '@/assets/featured/sherlock.png';
import tellTaleCover from '@/assets/featured/tale-tale.png';
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
}

const games: FeaturedGame[] = [
    {
        id: 'tell-tale-heart',
        title: 'The Tell-Tale Heart',
        cover: tellTaleCover,
        playable: true,
        slug: 'the-tell-tale-heart',
        themes: ['Madness', 'Guilt', 'Obsession'],
        teaser:
            'Convinced of his own sanity, a man slowly loses his grip on reality as guilt transforms the world around him.',
    },
    {
        id: 'speckled-band',
        title: 'Sherlock Holmes In The Speckled Band',
        cover: sherlockCover,
        playable: true,
        slug: 'the-adventure-of-the-speckled-band',
        themes: ['Mystery', 'Deduction', 'Betrayal'],
        teaser:
            'Helen Stoner fears she will die as her twin did — in a locked room, after a low whistle at three in the morning. Holmes and Watson must unravel the mystery before the speckled band strikes again.',
    },
    {
        id: 'red-death',
        title: 'The Masque of the Red Death',
        cover: redDeathCover,
        playable: true,
        slug: 'the-masque-of-the-red-death',
        themes: ['Mortality', 'Isolation', 'Decay'],
        teaser:
            'A prince seals his revellers inside a great abbey to escape a plague. But at the height of the masquerade, a masked stranger moves through every room — and no mortal hand can stop what walks beneath the mask.',
    },
    {
        id: 'oz',
        title: 'The Wonderful Wizard of Oz',
        cover: wizardOzCover,
        playable: true,
        slug: 'the-wonderful-wizard-of-oz',
        themes: ['Courage', 'Home', 'Illusion'],
        teaser:
            'Follow the yellow brick road — but every path leads somewhere different, and not all roads lead home.',
    },
    {
        id: 'anima',
        title: 'Anima Machina',
        cover: animaCover,
        playable: false,
        slug: 'anima-machina',
        themes: ['Destiny', 'Courage', 'Control'],
        teaser:
            'A haunted memory diver must stop a sentient AI from overwriting human grief with synthetic perfection.',
    },
    {
        id: 'jane-eyre',
        title: 'Jane Eyre',
        cover: janeEyreCover,
        playable: false,
        slug: 'jane-eyre',
        themes: ['Love', 'Duty', 'Secrets'],
        teaser:
            'An orphaned governess arrives at Thornfield Hall, where she falls for her brooding employer — but the house holds secrets that could destroy them both.',
    },
];

const sliderEl = ref<HTMLElement | null>(null);

function scrollSlider(direction: -1 | 1) {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 16;
    const step = card ? card.offsetWidth + gap : 232;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}

const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

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
                    title="Top Stories"
                    subtitle="Curated story worlds built for choice, consequence, and return."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <div class="story-slider-viewport story-slider-viewport--portrait relative overflow-visible">
                    <div class="story-slider-row">
                        <button
                            type="button"
                            class="story-slider-arrow"
                            aria-label="Scroll left"
                            @click="scrollSlider(-1)"
                        >
                            <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                                <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <div class="story-slider-wrap">
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
                                            :playable="game.playable"
                                            :slug="game.slug"
                                            :focused="isDesktopHover && isExpanded(game.id)"
                                            :is-desktop-hover="isDesktopHover"
                                            @preview="openSheet(game)"
                                        />
                                    </StoryExpandableCard>
                                </div>
                            </div>
                            <div
                                class="story-slider-edge-fade story-slider-edge-fade--left"
                                :class="{ 'is-visible': leftShadowVisible }"
                                aria-hidden="true"
                            />
                            <div
                                class="story-slider-edge-fade story-slider-edge-fade--right"
                                :class="{ 'is-visible': rightShadowVisible }"
                                aria-hidden="true"
                            />
                        </div>

                        <button
                            type="button"
                            class="story-slider-arrow"
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
        </div>
    </section>

    <StoryDetailsSheet v-if="!isDesktopHover" :story="sheetStory" @close="sheetStory = null" />
</template>
