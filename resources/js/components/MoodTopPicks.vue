<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import { MOOD_BANNER_CONFIGS, type MoodId } from '@/data/moodBanners';
import { STORY_HOVER_META_BY_SLUG } from '@/data/storyCardHoverMeta';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    mood: MoodId;
    picks: StoryInterface[];
    viewAllHref: string;
    viewAllCount: number;
}>();

const sliderEl = ref<HTMLElement | null>(null);
const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

const sectionTitle = computed(() => `Top ${MOOD_BANNER_CONFIGS[props.mood].label} Picks`);

function pickImage(story: StoryInterface): string {
    return story.banner?.trim() ? story.banner : story.cover;
}

function pickThemes(slug: string): string[] {
    return STORY_HOVER_META_BY_SLUG[slug]?.themes ?? [];
}

function isPlayable(story: StoryInterface): boolean {
    return story.status?.value === StoryStatusEnum.PUBLISHED;
}

function scrollSlider(direction: -1 | 1): void {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.mood-pick-card');
    const gap = 15;
    const step = card ? card.offsetWidth + gap : 465;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
}
</script>

<template>
    <section class="mood-top-picks home-section-gap">
        <SectionHeader
            :title="sectionTitle"
            :href="viewAllHref"
            :count="viewAllCount"
        />

        <div class="mood-picks-viewport relative overflow-visible">
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
                class="mood-picks-arrow mood-picks-arrow--left"
                aria-label="Scroll top picks left"
                @click="scrollSlider(-1)"
            >
                <svg
                    viewBox="0 0 8 14"
                    width="8"
                    height="14"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                    class="rotate-180"
                >
                    <path
                        d="M1 1L7 7L1 13"
                        stroke="white"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>

            <div ref="sliderEl" class="mood-picks-slider overflow-x-auto">
                <div class="mood-picks-track">
                    <article
                        v-for="story in picks"
                        :key="story.slug"
                        class="mood-pick-card shrink-0"
                    >
                        <component
                            :is="isPlayable(story) ? Link : 'div'"
                            :href="isPlayable(story) ? storyShow(story.slug).url : undefined"
                            class="mood-pick-card__link block no-underline outline-none"
                            :class="isPlayable(story) ? 'cursor-pointer' : 'cursor-default'"
                        >
                            <div class="mood-pick-card__frame">
                                <img
                                    :src="pickImage(story)"
                                    :alt="story.title"
                                    class="mood-pick-card__image"
                                    loading="lazy"
                                />
                            </div>
                            <div class="mood-pick-card__meta">
                                <p class="mood-pick-card__title">{{ story.title }}</p>
                                <p
                                    v-if="pickThemes(story.slug).length"
                                    class="mood-pick-card__themes"
                                >
                                    <template
                                        v-for="(theme, index) in pickThemes(story.slug)"
                                        :key="theme"
                                    >
                                        <span v-if="index > 0" class="mood-pick-card__theme-gap" />
                                        <span>{{ theme }}</span>
                                    </template>
                                </p>
                            </div>
                        </component>
                    </article>
                </div>
            </div>

            <button
                type="button"
                class="mood-picks-arrow mood-picks-arrow--right"
                aria-label="Scroll top picks right"
                @click="scrollSlider(1)"
            >
                <svg
                    viewBox="0 0 8 14"
                    width="8"
                    height="14"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <path
                        d="M1 1L7 7L1 13"
                        stroke="white"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>
        </div>
    </section>
</template>

<style scoped>
/*
 * Arrow center = vertical midpoint of landscape card art only (not title/meta).
 * Card width drives image height via aspect-ratio so the math stays responsive.
 */
.mood-picks-viewport {
    --mood-pick-card-width: min(28.125rem, 78vw);
    --mood-pick-frame-pad: 0.25rem;
    --mood-pick-image-ratio: 254 / 450;
    --mood-pick-image-height: calc(
        (var(--mood-pick-card-width) - (2 * var(--mood-pick-frame-pad))) * var(--mood-pick-image-ratio) +
            (2 * var(--mood-pick-frame-pad))
    );
    --mood-picks-track-pad-top: 0.25rem;
}

.mood-picks-slider {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.mood-picks-slider::-webkit-scrollbar {
    display: none;
}

.mood-picks-track {
    display: flex;
    align-items: flex-start;
    gap: 0.9375rem;
    padding: var(--mood-picks-track-pad-top) 0.25rem 0;
}

.mood-picks-arrow {
    position: absolute;
    z-index: 10;
    display: none;
    width: 2.125rem;
    height: 2.125rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.08);
    transition: background 0.2s;
    top: calc(var(--mood-picks-track-pad-top) + var(--mood-pick-image-height) / 2);
}

@media (min-width: 768px) {
    .mood-picks-arrow {
        display: flex;
    }
}

.mood-picks-arrow:hover {
    background: rgba(255, 255, 255, 0.15);
}

.mood-picks-arrow--left {
    left: 0;
    transform: translate(-50%, -50%);
}

.mood-picks-arrow--right {
    right: 0;
    transform: translate(50%, -50%);
}

.mood-pick-card {
    width: var(--mood-pick-card-width);
}

.mood-pick-card__frame {
    overflow: hidden;
    border-radius: 0.5rem;
    border: 1px solid #373737;
    background: #1c1c1c;
    padding: var(--mood-pick-frame-pad);
}

.mood-pick-card__image {
    display: block;
    aspect-ratio: 450 / 254;
    width: 100%;
    border-radius: 0.5rem;
    object-fit: cover;
}

.mood-pick-card__meta {
    display: flex;
    flex-direction: column;
    gap: 0.1875rem;
    padding-top: 0.625rem;
}

.mood-pick-card__title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    line-height: normal;
    color: #fff;
}

.mood-pick-card__themes {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    margin: 0;
    font-size: 0.9375rem;
    line-height: normal;
    color: #7e7e7e;
}

.mood-pick-card__theme-gap {
    display: inline-block;
    width: 0.25rem;
}
</style>
