<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import janeCover from '@/assets/commingSoon/jane-comming.png';
import frankensteinCover from '@/assets/commingSoon/frankstein-comming.png';
import drjCover from '@/assets/commingSoon/drj-comming.png';
import underseaCover from '@/assets/commingSoon/undersea-comming.JPG';
import wastelandCover from '@/assets/commingSoon/wasteland-comming.JPG';
import romeoCover from '@/assets/commingSoon/romeo-comming.png';
import pjCover from '@/assets/commingSoon/pj-comming.JPG';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { getStoryDescriptorThemes } from '@/data/storyCardHoverMeta';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { ref } from 'vue';

interface ComingSoonCard {
    id: string;
    title: string;
    cover: string;
    teaser: string;
}

const cards: ComingSoonCard[] = [
    {
        id: 'jane-eyre',
        title: 'Jane Eyre',
        cover: janeCover,
        teaser: 'A young orphan enters a dark and mysterious estate where buried secrets, dangerous love, and the search for belonging may change the course of her life forever.',
    },
    {
        id: 'frankenstein',
        title: 'Frankenstein',
        cover: frankensteinCover,
        teaser: 'Step inside a world where creation, rejection, and consequence follow you like a shadow.',
    },
    {
        id: 'dr-jekyll-and-mr-hyde',
        title: 'The Strange Case of Dr. Jekyll and Mr. Hyde',
        cover: drjCover,
        teaser: "Beneath the fog-covered streets of Victorian London, a terrifying secret grows inside Dr. Jekyll's laboratory, threatening to consume everyone around him.",
    },
    {
        id: 'leagues',
        title: '20,000 Leagues Under the Sea',
        cover: underseaCover,
        teaser: 'Step aboard the Nautilus, where each choice pulls you deeper into beauty, danger, and the mystery of Captain Nemo.',
    },
    {
        id: 'wasteland',
        title: 'Wasteland',
        cover: wastelandCover,
        teaser: "Abandoned in a desert built from humanity's castoffs, an engineer must decide whether to escape or help the people that the world chose to forget.",
    },
    {
        id: 'romeo-and-juliet',
        title: 'Romeo & Juliet',
        cover: romeoCover,
        teaser: 'A masked room. A borrowed name. A city holding its breath. Somewhere in the dark of Verona, love discovers it has enemies.',
    },
    {
        id: 'pjs',
        title: "PJ's",
        cover: pjCover,
        teaser: "A team of elite Air Force PJs discover that the hardest battlefield may be the one where there's no enemy to shoot, only lives to save and ghosts to outrun.",
    },
];

const sliderEl = ref<HTMLElement | null>(null);

function scrollSlider(direction: -1 | 1) {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 16;
    const step = card ? card.offsetWidth + gap : 214;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}

const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(card: ComingSoonCard): StorySheetData {
    return {
        id: card.id,
        title: card.title,
        cover: card.cover,
        themes: getStoryDescriptorThemes(card.id),
        isComingSoon: true,
        teaser: card.teaser,
    };
}

function openSheet(card: ComingSoonCard) {
    sheetStory.value = toSheetData(card);
}
</script>

<template>
    <section class="home-section-y overflow-visible !pb-2 md:!pb-2">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Coming Soon"
                    subtitle="New worlds are coming soon."
                    :href="storiesIndex().url"
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
                                        v-for="card in cards"
                                        :key="card.id"
                                        :expanded="isExpanded(card.id)"
                                        :dimmed="isDimmed(card.id)"
                                        :desktop-expand="isDesktopHover"
                                        @mouseenter="isDesktopHover && onCardEnter(card.id)"
                                        @mouseleave="isDesktopHover && onCardLeave()"
                                    >
                                        <HomePortraitStoryCard
                                            :title="card.title"
                                            :cover="card.cover"
                                            :themes="getStoryDescriptorThemes(card.id)"
                                            :teaser="card.teaser"
                                            :playable="false"
                                            :focused="isDesktopHover && isExpanded(card.id)"
                                            :is-desktop-hover="isDesktopHover"
                                            @preview="openSheet(card)"
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
