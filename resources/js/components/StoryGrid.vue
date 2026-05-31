<script setup lang="ts">
import HomePortraitStoryCard from '@/components/HomePortraitStoryCard.vue';
import StoryCard, { type StoryCardCta } from '@/components/StoryCard.vue';
import { STORY_HOVER_META_BY_SLUG } from '@/data/storyCardHoverMeta';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';

const props = withDefaults(
    defineProps<{
        stories: StoryInterface[];
        ctaByStoryId?: Record<number, StoryCardCta>;
        /** Featured Worlds portrait cards (192×287) — used on mood pages. */
        portrait?: boolean;
    }>(),
    {
        ctaByStoryId: () => ({}),
        portrait: false,
    },
);

function ctaForStory(story: StoryInterface): StoryCardCta | undefined {
    return props.ctaByStoryId[story.id];
}

function isComingSoon(story: StoryInterface): boolean {
    return story.status?.value !== StoryStatusEnum.PUBLISHED;
}

function isPlayable(story: StoryInterface): boolean {
    return story.status?.value === StoryStatusEnum.PUBLISHED;
}

function themesForStory(story: StoryInterface): string[] {
    const meta = STORY_HOVER_META_BY_SLUG[story.slug];
    if (meta?.themes.length) return meta.themes;
    if (story.category?.title) return [story.category.title];
    return [];
}

function branchesForStory(story: StoryInterface): string | null {
    return STORY_HOVER_META_BY_SLUG[story.slug]?.branches ?? null;
}
</script>

<template>
    <div class="story-grid" :class="portrait && 'story-grid--portrait'">
        <template v-if="portrait">
            <div
                v-for="story in stories"
                :key="story.id"
                class="story-grid__portrait-slot"
            >
                <HomePortraitStoryCard
                    :title="story.title"
                    :cover="story.cover"
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
        </template>

        <template v-else>
            <StoryCard
                v-for="story in stories"
                :key="story.id"
                :title="story.title"
                :cover-image="story.cover"
                :slug="story.slug"
                :status="story.status?.value"
                :is-coming-soon="isComingSoon(story)"
                :cta="ctaForStory(story)"
            />
        </template>
    </div>
</template>

<style scoped>
.story-grid {
    display: grid;
    width: 100%;
    min-width: 0;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
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

/* Featured Worlds card size: 12rem (192px) wide, 10px gap */
.story-grid--portrait {
    grid-template-columns: repeat(2, minmax(0, 12rem));
    gap: 0.625rem;
    justify-content: start;
}

@media (min-width: 640px) {
    .story-grid--portrait {
        grid-template-columns: repeat(3, minmax(0, 12rem));
    }
}

@media (min-width: 768px) {
    .story-grid--portrait {
        grid-template-columns: repeat(4, minmax(0, 12rem));
    }
}

@media (min-width: 1024px) {
    .story-grid--portrait {
        grid-template-columns: repeat(5, minmax(0, 12rem));
    }
}

.story-grid__portrait-slot {
    position: relative;
    min-width: 0;
    width: 100%;
    max-width: 12rem;
}

@media (min-width: 1024px) {
    .story-grid__portrait-slot:hover {
        z-index: 2;
    }
}
</style>
