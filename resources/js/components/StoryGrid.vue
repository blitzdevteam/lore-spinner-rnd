<script setup lang="ts">
import StoryCard, { type StoryCardCta } from '@/components/StoryCard.vue';
import { STORY_HOVER_META_BY_SLUG } from '@/data/storyCardHoverMeta';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';

const props = withDefaults(
    defineProps<{
        stories: StoryInterface[];
        ctaByStoryId?: Record<number, StoryCardCta>;
        /** Mood label shown in card metadata (e.g. "Adventure"). */
        moodLabel?: string;
    }>(),
    {
        ctaByStoryId: () => ({}),
        moodLabel: undefined,
    },
);

function ctaForStory(story: StoryInterface): StoryCardCta | undefined {
    return props.ctaByStoryId[story.id];
}

function isComingSoon(story: StoryInterface): boolean {
    return story.status?.value !== StoryStatusEnum.PUBLISHED;
}

function genreForStory(story: StoryInterface): string | undefined {
    if (story.category?.title) return story.category.title;
    const meta = STORY_HOVER_META_BY_SLUG[story.slug];
    return meta?.themes[0];
}
</script>

<template>
    <div class="story-grid">
        <StoryCard
            v-for="story in stories"
            :key="story.id"
            :title="story.title"
            :cover-image="story.cover"
            :slug="story.slug"
            :status="story.status?.value"
            :mood="moodLabel"
            :genre="genreForStory(story)"
            :is-coming-soon="isComingSoon(story)"
            :cta="ctaForStory(story)"
        />
    </div>
</template>

<style scoped>
.story-grid {
    display: grid;
    width: 100%;
    min-width: 0;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    align-items: stretch;
}

@media (min-width: 768px) {
    .story-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }
}

@media (min-width: 1024px) {
    .story-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (min-width: 1280px) {
    .story-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 1.125rem;
    }
}

@media (min-width: 1536px) {
    .story-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 1.25rem;
    }
}
</style>
