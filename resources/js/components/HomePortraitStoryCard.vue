<script setup lang="ts">
import StoryCardCover from '@/components/StoryCardCover.vue';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, withDefaults } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        cover: string;
        themes: string[];
        mood?: string;
        genre?: string;
        teaser?: string | null;
        branches?: string | null;
        playable: boolean;
        slug?: string;
        focused: boolean;
        isDesktopHover: boolean;
        /** Carousel hover reveal — disable in grid layouts. */
        expandOnHover?: boolean;
    }>(),
    {
        expandOnHover: true,
        mood: undefined,
        genre: undefined,
    },
);

const emit = defineEmits<{ preview: [] }>();

const storyUrl = computed(() =>
    props.playable && props.slug ? storyShow(props.slug).url : undefined,
);

const ctaLabel = computed(() => (props.playable ? 'Play' : 'Coming Soon'));

const metadataLine = computed(() => {
    const genre = props.genre ?? props.themes[0] ?? null;
    const parts = [props.mood, genre].filter(Boolean);
    return parts.length ? parts.join(' • ') : null;
});
</script>

<template>
    <component
        :is="isDesktopHover ? (storyUrl ? Link : 'div') : 'button'"
        :href="isDesktopHover ? storyUrl : undefined"
        type="button"
        class="hp-card group block h-full min-w-0 border-0 bg-transparent p-0 text-left outline-none"
        :class="[
            expandOnHover && focused && 'hp-card--focused',
            !expandOnHover && 'hp-card--static',
            !isDesktopHover ? 'cursor-pointer' : storyUrl ? 'cursor-pointer no-underline' : 'cursor-default',
        ]"
        :aria-label="isDesktopHover && storyUrl ? `Open ${title}` : `Preview ${title}`"
        @click="!isDesktopHover && emit('preview')"
    >
        <div class="hp-card__frame">
            <StoryCardCover :src="cover" :title="title" />

            <div class="hp-card__body">
                <div class="hp-card__info">
                    <p class="hp-card__title">{{ title }}</p>
                    <p v-if="metadataLine" class="hp-card__meta">{{ metadataLine }}</p>
                </div>

                <div
                    v-if="expandOnHover"
                    class="hp-card__reveal"
                    :class="focused && 'hp-card__reveal--open'"
                >
                    <div class="hp-card__reveal-inner">
                        <div v-if="themes.length" class="hp-card__themes">
                            <template v-for="(theme, i) in themes" :key="theme">
                                <span v-if="i > 0" class="hp-card__theme-dot" aria-hidden="true">•</span>
                                <span class="hp-card__theme">{{ theme }}</span>
                            </template>
                        </div>
                        <p v-if="teaser" class="hp-card__teaser">{{ teaser }}</p>
                        <p v-if="branches" class="hp-card__branches">
                            {{ branches }} Branches explored
                        </p>
                    </div>
                </div>

                <div
                    class="hp-card__cta"
                    :class="playable ? 'hp-card__cta--active' : 'hp-card__cta--disabled'"
                >
                    {{ ctaLabel }}
                </div>
            </div>
        </div>
    </component>
</template>

<style scoped>
.hp-card {
    text-decoration: none;
}

.hp-card__frame {
    display: flex;
    height: 100%;
    width: min(12rem, 78vw);
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

.hp-card__body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--story-card-body-gap);
    padding: var(--story-card-body-pad-y) var(--story-card-body-pad-x)
        calc(var(--story-card-body-pad-y) + 0.125rem);
    min-height: 0;
}

.hp-card__info {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
}

.hp-card__title {
    margin: 0;
    font-size: var(--story-card-title-size);
    font-weight: 700;
    line-height: 1.3;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hp-card__meta {
    margin: 0;
    font-size: var(--story-card-meta-size);
    line-height: 1.35;
    color: var(--story-card-meta-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hp-card__reveal {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(4px);
    transition:
        grid-template-rows 200ms ease,
        opacity 200ms ease,
        transform 200ms ease;
}

.hp-card__reveal--open {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
}

.hp-card__reveal-inner {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-height: 0;
}

.hp-card__themes {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.375rem;
}

.hp-card__theme-dot {
    font-size: 0.625rem;
    line-height: 1;
    color: rgba(255, 255, 255, 0.28);
}

.hp-card__theme {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--color-primary, #6fafba);
}

.hp-card__teaser {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.52);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.hp-card__branches {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #ffbe58;
}

.hp-card__cta {
    display: flex;
    height: var(--story-card-btn-height);
    width: 100%;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: var(--story-card-btn-radius);
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1;
    margin-top: auto;
}

.hp-card__cta--active {
    background: var(--story-card-btn-bg);
    color: var(--story-card-btn-text);
    transition: background 180ms ease;
}

.hp-card__cta--active:hover {
    background: var(--story-card-btn-bg-hover);
}

.hp-card__cta--active:active {
    background: var(--story-card-btn-bg-active);
}

.hp-card__cta--disabled {
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.42);
}

@media (min-width: 768px) {
    .hp-card__frame {
        width: 12rem;
    }
}

@media (min-width: 1024px) {
    .hp-card--focused .hp-card__frame {
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: var(--story-card-shadow-hover), var(--story-card-shadow-glow);
    }

    .hp-card--static:hover .hp-card__frame {
        border-color: rgba(255, 255, 255, 0.08);
        box-shadow: var(--story-card-shadow-hover), var(--story-card-shadow-glow);
    }
}
</style>
