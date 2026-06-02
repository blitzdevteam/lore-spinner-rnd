<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import animaCover from '@/assets/featured/anima.png';
import aliceCover from '@/assets/featured/alice.png';
import draculaCover from '@/assets/featured/dracula.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import pridePrejudiceCover from '@/assets/featured/Pride-prejudice.png';
import redDeathCover from '@/assets/featured/redDeath.png';
import treasureCover from '@/assets/featured/treasure.png';
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
        id: 'nocturne',
        title: 'Nocturne',
        cover: nocturneCover,
        playable: false,
        slug: 'nocturne',
        themes: ['Identity', 'Secrets', 'Control'],
        teaser:
            'After a public scandal shatters her life, a disgraced Japanese heiress discovers the organization helping her disappear is part of an ancient cult.',
    },
    {
        id: 'red-death',
        title: 'The Masque of the Red Death',
        cover: redDeathCover,
        playable: false,
        slug: 'the-masque-of-the-red-death',
        themes: ['Mortality', 'Isolation', 'Decay'],
        teaser:
            'A prince seals his revellers inside a great abbey to escape a plague. But at the height of the masquerade, a masked stranger moves through every room — and no mortal hand can stop what walks beneath the mask.',
    },
    {
        id: 'anima-machina',
        title: 'Anima Machina',
        cover: animaCover,
        playable: false,
        slug: 'anima-machina',
        themes: ['Destiny', 'Courage', 'Control'],
        teaser:
            'A haunted memory diver must stop a sentient AI from overwriting human grief with synthetic perfection.',
    },
    {
        id: 'alice-in-wonderland',
        title: "Alice's Adventures in Wonderland",
        cover: aliceCover,
        playable: false,
        slug: 'alice-in-wonderland',
        themes: ['Wonder', 'Curiosity', 'Transformation'],
        teaser:
            'A curious girl follows a white rabbit into a world where logic bends and every choice changes what you become.',
    },
    {
        id: 'dracula',
        title: 'Dracula',
        cover: draculaCover,
        playable: false,
        slug: 'dracula',
        themes: ['Hunger', 'Fear', 'Desire'],
        teaser:
            'A voyage, a journal, and a shadow that does not stay in the dark — face the count, and decide what survives.',
    },
    {
        id: 'pride-and-prejudice',
        title: 'Pride and Prejudice',
        cover: pridePrejudiceCover,
        playable: false,
        slug: 'pride-and-prejudice',
        themes: ['Love', 'Duty', 'Society'],
        teaser:
            'A sharp mind meets a guarded heart — and every conversation becomes a contest of pride, prejudice, and possibility.',
    },
    {
        id: 'treasure-island',
        title: 'Treasure Island',
        cover: treasureCover,
        playable: false,
        slug: 'treasure-island',
        themes: ['Adventure', 'Greed', 'Loyalty'],
        teaser:
            'A map. A mutiny. A promise of gold — and the kind of choices that turn boys into pirates or ghosts.',
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
                    title="Featured Worlds"
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
