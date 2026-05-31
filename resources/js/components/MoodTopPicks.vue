<script setup lang="ts">
import HomeBannerStoryCard from '@/components/HomeBannerStoryCard.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { MOOD_TOP_PICK_SLUGS } from '@/data/moodCards';
import type { MoodId } from '@/data/moodBanners';
import { STORY_HOVER_META_BY_SLUG } from '@/data/storyCardHoverMeta';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { computed, ref } from 'vue';

const props = defineProps<{
    mood: MoodId;
    moodLabel: string;
    stories: StoryInterface[];
    totalCount: number;
}>();

const sliderEl = ref<HTMLElement | null>(null);
const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

const sectionTitle = computed(() => `Top ${props.moodLabel} Picks`);

const viewAllHref = computed(() => `${storiesIndex().url}?mood=${props.mood}#all-stories`);

const topPickStories = computed((): StoryInterface[] => {
    const bySlug = new Map(props.stories.map((story) => [story.slug, story]));
    const curated = MOOD_TOP_PICK_SLUGS[props.mood]
        .map((slug) => bySlug.get(slug))
        .filter((story): story is StoryInterface => story != null);

    if (curated.length >= 3) return curated;

    const usedSlugs = new Set(curated.map((story) => story.slug));
    const extras = props.stories.filter((story) => !usedSlugs.has(story.slug));

    return [...curated, ...extras].slice(0, Math.max(3, curated.length));
});

function themesForStory(story: StoryInterface): string[] {
    const meta = STORY_HOVER_META_BY_SLUG[story.slug];
    if (meta?.themes.length) return meta.themes;
    if (story.category?.title) return [story.category.title];
    return [];
}

function categoryForStory(story: StoryInterface): string {
    return story.category?.title ?? 'Story';
}

function ratingForStory(story: StoryInterface): string {
    return story.rating?.label ?? 'Everyone';
}

function branchesForStory(story: StoryInterface): string | null {
    return STORY_HOVER_META_BY_SLUG[story.slug]?.branches ?? null;
}

function isPlayable(story: StoryInterface): boolean {
    return story.status?.value === StoryStatusEnum.PUBLISHED;
}

function scrollSlider(direction: -1 | 1): void {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.mood-top-picks__slot');
    const gap = 10;
    const step = card ? card.offsetWidth + gap : 460;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
}
</script>

<template>
    <section class="mood-top-picks" :aria-label="sectionTitle">
        <div class="container">
            <div class="container-content mood-page-section-header-gap">
                <SectionHeader
                    :title="sectionTitle"
                    :href="viewAllHref"
                    :count="totalCount"
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
                        class="story-slider-arrow absolute -left-4"
                        aria-label="Scroll top picks left"
                        @click="scrollSlider(-1)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div ref="sliderEl" class="story-slider overflow-x-auto">
                        <div class="story-slider-track">
                            <div
                                v-for="story in topPickStories"
                                :key="story.id"
                                class="mood-top-picks__slot w-[min(28.125rem,78vw)] shrink-0 md:w-[28.125rem]"
                            >
                                <HomeBannerStoryCard
                                    :title="story.title"
                                    :cover="story.cover"
                                    :category="categoryForStory(story)"
                                    :rating="ratingForStory(story)"
                                    :themes="themesForStory(story)"
                                    :teaser="story.teaser"
                                    :branches="branchesForStory(story)"
                                    :playable="isPlayable(story)"
                                    :slug="story.slug"
                                    :focused="false"
                                    :expand-on-hover="false"
                                    :is-desktop-hover="true"
                                />
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="story-slider-arrow absolute -right-4"
                        aria-label="Scroll top picks right"
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
</template>

<style scoped>
.mood-top-picks__slot {
    position: relative;
}
</style>
