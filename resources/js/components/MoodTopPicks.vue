<script setup lang="ts">
import HomeBannerStoryCard from '@/components/HomeBannerStoryCard.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import { MOOD_TOP_PICK_SLUGS } from '@/data/moodCards';
import type { MoodId } from '@/data/moodBanners';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { resolveStoryTopMoodCover } from '@/data/storyTopMoodCoverBySlug';
import { STORY_HOVER_META_BY_SLUG } from '@/data/storyCardHoverMeta';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { computed, ref } from 'vue';

const props = defineProps<{
    mood: MoodId;
    moodLabel: string;
    stories: StoryInterface[];
    totalCount: number;
}>();

const sliderEl = ref<HTMLElement | null>(null);
const { leftShadowVisible, rightShadowVisible, updateShadows } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { onCardEnter, onCardLeave, isExpanded, isDimmed, hoveredId } = useStoryCardExpand(isDesktopHover);

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

function isPlayable(story: StoryInterface): boolean {
    return story.status?.value === StoryStatusEnum.PUBLISHED && isStoryPlayable(story.slug);
}

function coverForStory(story: StoryInterface): string {
    return resolveStoryTopMoodCover(story.slug, story.cover);
}

function scrollSlider(direction: -1 | 1): void {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 16;
    const step = card ? card.offsetWidth + gap : 460;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
    updateShadows();
    requestAnimationFrame(updateShadows);
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

                <div
                    class="story-slider-viewport story-slider-viewport--banner relative overflow-visible"
                    :class="isDesktopHover && hoveredId !== null && 'story-slider-viewport--cinema'"
                >
                    <div class="story-slider-row">
                        <button
                            type="button"
                            class="story-slider-arrow"
                            aria-label="Scroll top picks left"
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
                                        v-for="story in topPickStories"
                                        :key="story.id"
                                        class="w-[min(28.125rem,78vw)] md:w-[28.125rem]"
                                        :expanded="isExpanded(String(story.id))"
                                        :dimmed="isDimmed(String(story.id))"
                                        :desktop-expand="isDesktopHover"
                                        @mouseenter="isDesktopHover && onCardEnter(String(story.id))"
                                        @mouseleave="isDesktopHover && onCardLeave()"
                                    >
                                        <HomeBannerStoryCard
                                            :title="story.title"
                                            :cover="coverForStory(story)"
                                            :category="categoryForStory(story)"
                                            :rating="ratingForStory(story)"
                                            :mood="moodLabel"
                                            :themes="themesForStory(story)"
                                            :teaser="story.teaser"
                                            :playable="isPlayable(story)"
                                            :slug="story.slug"
                                            :focused="isDesktopHover && isExpanded(String(story.id))"
                                            :is-desktop-hover="isDesktopHover"
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
        </div>
    </section>
</template>

