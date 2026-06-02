<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import HomeBannerStoryCard from '@/components/HomeBannerStoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import sherlockNewCover from '@/assets/newStories/sherlock-new.png';
import ozNewCover from '@/assets/newStories/Oz landscape titled.png';
import tellTaleNewCover from '@/assets/newStories/Tell Tale 5_3 landscape.png';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { ref } from 'vue';

defineProps<{
    storyCount: number;
}>();

interface NewStory {
    id: string;
    title: string;
    cover: string;
    coverHover?: string;
    category: string;
    rating: string;
    playable: boolean;
    slug?: string;
    themes: string[];
    teaser: string;
}

const stories: NewStory[] = [
    {
        id: 'the-adventure-of-the-speckled-band',
        title: 'The Adventures of Sherlock Holmes: The Speckled Band',
        cover: sherlockNewCover,
        category: 'Mystery',
        rating: 'Everyone',
        playable: true,
        slug: 'the-adventure-of-the-speckled-band',
        themes: ['Mystery', 'Deduction', 'Betrayal'],
        teaser:
            'A young woman fears she will suffer the same fate as her sister, forcing Sherlock Holmes to confront a mystery hidden behind locked doors and deadly secrets.',
    },
    {
        id: 'the-wonderful-wizard-of-oz',
        title: 'The Wizard of Oz',
        cover: ozNewCover,
        category: 'Fantasy Adventure',
        rating: 'Everyone',
        playable: true,
        slug: 'the-wonderful-wizard-of-oz',
        themes: ['Courage', 'Home', 'Illusion'],
        teaser:
            'A storm carries you into the magical land of Oz, where witches whisper, lions tremble, and every step down the Yellow Brick Road changes who you are becoming.',
    },
    {
        id: 'the-tell-tale-heart',
        title: 'The Tell-Tale Heart',
        cover: tellTaleNewCover,
        category: 'Gothic Horror',
        rating: 'Everyone',
        playable: false,
        slug: 'the-tell-tale-heart',
        themes: ['Madness', 'Guilt', 'Obsession'],
        teaser:
            "As guilt begins to twist reality around him, a man struggles to silence the terrifying sound he cannot escape: the beating of a dead man's heart.",
    },
];

const sliderEl = ref<HTMLElement | null>(null);

function scrollSlider(direction: -1 | 1) {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 16;
    const step = card ? card.offsetWidth + gap : 460;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
}

const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(story: NewStory): StorySheetData {
    return {
        id: story.id,
        title: story.title,
        cover: story.coverHover ?? story.cover,
        themes: story.themes,
        category: story.category,
        rating: story.rating,
        isComingSoon: !story.playable,
        teaser: story.teaser,
        slug: story.slug,
        cta: story.playable ? 'play' : 'coming-soon',
    };
}

function openSheet(story: NewStory) {
    sheetStory.value = toSheetData(story);
}
</script>

<template>
    <section class="home-section-y overflow-visible">
        <div class="container">
            <div class="container-content home-section-gap">

                <SectionHeader
                    title="New Stories"
                    subtitle="New branches, hidden paths, and fresh story worlds."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <div class="story-slider-viewport story-slider-viewport--banner relative overflow-visible">
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
                                        v-for="story in stories"
                                        :key="story.id"
                                        class="w-[min(28.125rem,78vw)] md:w-[28.125rem]"
                                        :expanded="isExpanded(story.id)"
                                        :dimmed="isDimmed(story.id)"
                                        :desktop-expand="isDesktopHover"
                                        @mouseenter="isDesktopHover && onCardEnter(story.id)"
                                        @mouseleave="isDesktopHover && onCardLeave()"
                                    >
                                        <HomeBannerStoryCard
                                            :title="story.title"
                                            :cover="story.cover"
                                            :category="story.category"
                                            :rating="story.rating"
                                            :themes="story.themes"
                                            :teaser="story.teaser"
                                            :playable="story.playable"
                                            :slug="story.slug"
                                            :focused="isDesktopHover && isExpanded(story.id)"
                                            :is-desktop-hover="isDesktopHover"
                                            @preview="openSheet(story)"
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
