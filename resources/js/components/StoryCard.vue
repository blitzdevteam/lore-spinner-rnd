<script setup lang="ts">
import StoryCardCover from '@/components/StoryCardCover.vue';
import { StoryStatusEnum } from '@/types/enum';
import { show } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export type StoryCardCta = 'play' | 'continue' | 'read-again' | 'coming-soon';

const props = withDefaults(
    defineProps<{
        title: string;
        coverImage?: string | null;
        slug: string;
        status?: StoryStatusEnum | string;
        mood?: string;
        genre?: string;
        isComingSoon?: boolean;
        cta?: StoryCardCta;
        dimmed?: boolean;
    }>(),
    {
        coverImage: null,
        status: undefined,
        mood: undefined,
        genre: undefined,
        isComingSoon: false,
        cta: undefined,
        dimmed: false,
    },
);

const storyUrl = computed(() => show(props.slug).url);

const isPublished = computed(() => {
    if (props.isComingSoon) return false;
    return props.status === StoryStatusEnum.PUBLISHED || props.status === 'published';
});

const resolvedCta = computed((): StoryCardCta => {
    if (props.cta) return props.cta;
    if (props.isComingSoon || !isPublished.value) return 'coming-soon';
    return 'play';
});

const ctaLabel = computed(() => {
    switch (resolvedCta.value) {
        case 'continue':
            return 'Continue';
        case 'read-again':
            return 'Read Again';
        case 'coming-soon':
            return 'Coming Soon';
        default:
            return 'Play';
    }
});

const isInteractive = computed(() => resolvedCta.value !== 'coming-soon');

const metadataLine = computed(() => {
    const parts = [props.mood, props.genre].filter(Boolean);
    return parts.length ? parts.join(' • ') : null;
});
</script>

<template>
    <article
        class="story-card group min-w-0 h-full transition-opacity duration-200"
        :class="dimmed ? 'opacity-[0.35]' : 'opacity-100'"
        :data-mood="mood || undefined"
    >
        <div class="story-card__frame">
            <component
                :is="isInteractive ? Link : 'div'"
                :href="isInteractive ? storyUrl : undefined"
                class="story-card__cover-link block min-w-0 outline-none"
            >
                <StoryCardCover :src="coverImage" :title="title" />
            </component>

            <div class="story-card__body">
                <div class="story-card__info">
                    <h3 class="story-card__title">{{ title }}</h3>
                    <p v-if="metadataLine" class="story-card__meta">{{ metadataLine }}</p>
                </div>

                <div class="story-card__cta">
                    <Link
                        v-if="isInteractive"
                        :href="storyUrl"
                        class="story-card__btn story-card__btn--active"
                    >
                        {{ ctaLabel }}
                    </Link>
                    <div v-else class="story-card__btn story-card__btn--disabled">
                        {{ ctaLabel }}
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>

<style scoped>
.story-card__frame {
    display: flex;
    height: 100%;
    flex-direction: column;
    overflow: hidden;
    border-radius: var(--story-card-radius);
    border: var(--story-card-border);
    background: var(--story-card-bg);
    box-shadow: var(--story-card-shadow);
    transition:
        transform 200ms ease,
        box-shadow 200ms ease;
}

@media (min-width: 1024px) {
    .group:hover .story-card__frame {
        transform: scale(1.03);
        box-shadow: var(--story-card-shadow-hover);
    }
}

.story-card__cover-link {
    text-decoration: none;
}

.story-card__body {
    display: flex;
    flex: 1;
    flex-direction: column;
    justify-content: space-between;
    gap: var(--story-card-body-gap);
    padding: var(--story-card-body-pad-y) var(--story-card-body-pad-x)
        calc(var(--story-card-body-pad-y) + 0.125rem);
    min-height: 0;
}

.story-card__info {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
}

.story-card__title {
    margin: 0;
    font-size: var(--story-card-title-size);
    font-weight: 700;
    line-height: 1.3;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.story-card__meta {
    margin: 0;
    font-size: var(--story-card-meta-size);
    line-height: 1.35;
    color: var(--story-card-meta-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.story-card__cta {
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.story-card__btn {
    display: flex;
    height: var(--story-card-btn-height);
    width: 100%;
    align-items: center;
    justify-content: center;
    border-radius: var(--story-card-btn-radius);
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1;
    text-decoration: none;
    transition: background 180ms ease, color 180ms ease;
}

.story-card__btn--active {
    background: var(--color-primary, #6fafba);
    color: #0a1214;
}

.story-card__btn--active:hover {
    background: var(--color-primary-300, #8fcbd3);
}

.story-card__btn--active:active {
    background: var(--color-primary-600, #5a96a1);
}

.story-card__btn--disabled {
    cursor: default;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.42);
}
</style>
