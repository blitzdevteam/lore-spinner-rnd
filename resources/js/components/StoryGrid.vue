<script setup lang="ts">
import StoryCard, { type StoryCardCta } from '@/components/StoryCard.vue';
import { isStoryPlayable } from '@/data/playableStorySlugs';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';

const props = withDefaults(
    defineProps<{
        stories: StoryInterface[];
        ctaByStoryId?: Record<number, StoryCardCta>;
        /** Mood label for card data attribute (e.g. analytics / styling). */
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
    if (story.status?.value !== StoryStatusEnum.PUBLISHED) return true;
    return !isStoryPlayable(story.slug);
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
