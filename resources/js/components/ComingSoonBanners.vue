<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import cover1 from '@/assets/commingSoon/Coming soon 1- 2x.jpg';
import cover2 from '@/assets/commingSoon/Coming soon 2 - 2x.png';
import cover3 from '@/assets/commingSoon/Coming soon 3 - 2x.jpg';
import cover4 from '@/assets/commingSoon/Coming soon 4 - 2x.jpg';
import cover5 from '@/assets/commingSoon/Coming soon 5 - 2x.png';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { ref } from 'vue';

defineProps<{
    storyCount: number;
}>();

interface ComingSoonCard {
    id: string;
    title: string;
    cover: string;
    themes: string[];
    teaser: string;
}

const cards: ComingSoonCard[] = [
    {
        id: 'Romeo&Juliet',
        title: "Romeo & Juliet",
        cover: cover1,
        themes: ['Destiny', 'Courage', 'Control'],
        teaser: 'Two star-crossed lovers defy their feuding families — but every choice tightens the trap of fate.',
    },
    {
        id: 'hansel',
        title: 'Hansel & Gretel',
        cover: cover2,
        themes: ['Survival', 'Fear', 'Family'],
        teaser: 'Lost in the woods, siblings must outwit a witch whose house is built from hunger itself.',
    },
    {
        id: 'pride',
        title: 'Pride and Prejudice',
        cover: cover3,
        themes: ['Love', 'Duty', 'Society'],
        teaser: 'First impressions and pride collide in a world where marriage is strategy and love is rebellion.',
    },
    {
        id: 'frankenstein',
        title: 'Frankenstein',
        cover: cover4,
        themes: ['Creation', 'Isolation', 'Ambition'],
        teaser: 'A creator abandons his masterpiece — and the creature returns to demand an answer only blood can settle.',
    },
    {
        id: 'leagues',
        title: '20,000 Leagues Under the Sea',
        cover: cover5,
        themes: ['Discovery', 'Wonder', 'Peril'],
        teaser: 'Captives aboard a submarine discover a captain who has turned the ocean into a kingdom of revenge.',
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
        themes: card.themes,
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
                                            :themes="card.themes"
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
