<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import StoryExpandableCard from '@/components/StoryExpandableCard.vue';
import banner1 from '@/assets/newStories/New stories 1- 2x.jpg';
import banner2 from '@/assets/newStories/New stories 2 - 2x .jpg';
import banner3 from '@/assets/newStories/New stories 3 - 2x.jpg';
import banner1Hover from '@/assets/newStories/s1-hover.jpg';
import banner2Hover from '@/assets/newStories/s2-hover.jpg';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { useStoryCardExpand } from '@/composables/useStoryCardExpand';
import { useDesktopStoryPreview } from '@/composables/useDesktopStoryPreview';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { Link } from '@inertiajs/vue3';
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
    branches: string | null;
}

const stories: NewStory[] = [
    {
        id: 'sherlock',
        title: 'Sherlock Holmes',
        cover: banner1,
        coverHover: banner1Hover,
        category: 'Mystery',
        rating: 'Teen',
        playable: false,
        themes: ['Destiny', 'Deduction', 'Betrayal'],
        teaser: 'When London\'s most famous detective faces his most personal case, the truth may cost him everything — and everyone he trusts.',
        branches: null,
    },
    {
        id: 'alices-adventures-in-wonderland',
        title: "Alice\u2019s Adventures In Wonderland",
        cover: banner2,
        coverHover: banner2Hover,
        category: 'Science Fiction',
        rating: 'Mature',
        playable: false,
        themes: ['Distiny', 'Courage', 'Control'],
        teaser: 'In a city where every thought is monitored, one man discovers a truth the state will kill to suppress.',
        branches: null,
    },
    {
        id: 'pride-and-prejudice',
        title: 'Pride & Prejudice',
        cover: banner3,
        category: 'Historical Drama',
        rating: 'Teen',
        playable: false,
        themes: ['Love', 'Duty', 'Society'],
        teaser: 'Two sisters navigate love, loss, and society\'s expectations — where the heart and reason are rarely in agreement.',
        branches: null,
    },
];

const sliderEl = ref<HTMLElement | null>(null);
const scrollSlider = (delta: number) => sliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });

const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

const isDesktopHover = useDesktopStoryPreview();
const { hoveredId, onCardEnter, onCardLeave, isExpanded, isDimmed } = useStoryCardExpand(isDesktopHover);

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
        branches: story.branches,
        slug: story.slug,
        cta: story.playable ? 'play' : 'coming-soon',
    };
}

function openSheet(story: NewStory) {
    sheetStory.value = toSheetData(story);
}

function storyUrl(story: NewStory): string | undefined {
    return story.playable && story.slug ? storyShow(story.slug).url : undefined;
}

function themesLine(story: NewStory): string {
    return story.themes.join(' • ');
}

function activeCover(story: NewStory): string {
    return isExpanded(story.id) && story.coverHover ? story.coverHover : story.cover;
}

function ctaLabel(story: NewStory): string {
    return story.playable ? 'Play' : 'Coming Soon';
}
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">

                <SectionHeader
                    title="New Stories"
                    subtitle="New branches, hidden paths, and fresh story worlds."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <div class="relative">
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
                        class="slider-arrow slider-arrow-banner absolute -left-4 z-10 hidden items-center justify-center md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-460)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div
                        ref="sliderEl"
                        class="story-slider flex items-start gap-[0.625rem] overflow-x-auto pb-3 transition-[padding] duration-300 ease-out md:ml-[1.0625rem]"
                        :class="isDesktopHover && hoveredId ? 'lg:pb-[8rem]' : 'lg:pb-3'"
                    >
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
                            <component
                                :is="isDesktopHover ? (storyUrl(story) ? Link : 'div') : 'button'"
                                :href="isDesktopHover ? storyUrl(story) : undefined"
                                type="button"
                                class="ns-card block w-full border-0 bg-transparent p-0 text-left outline-none"
                                :class="[
                                    isDesktopHover && isExpanded(story.id) && 'ns-card--expanded',
                                    !isDesktopHover ? 'cursor-pointer' : storyUrl(story) ? 'cursor-pointer no-underline' : 'cursor-default',
                                ]"
                                :aria-label="isDesktopHover && storyUrl(story) ? `Open ${story.title}` : `Preview ${story.title}`"
                                @click="!isDesktopHover && openSheet(story)"
                            >
                                <div class="ns-card__inner">
                                    <!-- Cover: always same height -->
                                    <div class="ns-card__cover">
                                        <img
                                            :src="activeCover(story)"
                                            :alt="story.title"
                                            class="ns-card__cover-img"
                                        />
                                    </div>

                                    <!-- Meta row: always visible -->
                                    <div class="ns-card__meta">
                                        <p class="ns-card__title">{{ story.title }}</p>
                                        <p
                                            v-if="!isDesktopHover || !isExpanded(story.id)"
                                            class="ns-card__subtitle"
                                        >
                                            {{ story.category }} | {{ story.rating }} | {{ story.playable ? 'Published' : 'Coming soon' }}
                                        </p>
                                    </div>

                                    <!-- Details: slides in via grid trick -->
                                    <div
                                        v-if="isDesktopHover"
                                        class="ns-card__details"
                                        :class="{ 'ns-card__details--open': isExpanded(story.id) }"
                                        aria-hidden="true"
                                    >
                                        <div class="ns-card__details-inner">
                                            <p class="ns-card__themes">{{ themesLine(story) }}</p>
                                            <p class="ns-card__teaser">{{ story.teaser }}</p>
                                            <p v-if="story.branches" class="ns-card__branches">
                                                {{ story.branches }} Branches explored
                                            </p>
                                            <!-- CTA inside details so it appears with the content -->
                                            <div
                                                class="ns-card__cta"
                                                :class="story.playable ? 'ns-card__cta--active' : 'ns-card__cta--disabled'"
                                            >
                                                {{ ctaLabel(story) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </component>
                        </StoryExpandableCard>
                    </div>

                    <button
                        type="button"
                        class="slider-arrow slider-arrow-banner absolute -right-4 z-10 hidden items-center justify-center md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(460)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <StoryDetailsSheet v-if="!isDesktopHover" :story="sheetStory" @close="sheetStory = null" />
</template>

<style scoped>
/* ── Slider ──────────────────────────────────────────────────────────────── */
.story-slider {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.story-slider::-webkit-scrollbar {
    display: none;
}

/* ── Arrow buttons ───────────────────────────────────────────────────────── */
.slider-arrow {
    width: 2.125rem;
    height: 2.125rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    transition: background 0.2s;
}
.slider-arrow-banner {
    top: calc((16.375rem + 0.5rem + 2px) / 2);
    transform: translateY(-50%);
}
.slider-arrow:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* ── Card wrapper ────────────────────────────────────────────────────────── */
.ns-card {
    text-decoration: none;
}

.ns-card__inner {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    width: 100%;
    border-radius: 0.5rem;
    border: 1px solid #373737;
    background: #262626;
    padding: 0.25rem;
    transition:
        border-color 0.22s ease,
        box-shadow 0.22s ease;
}

/* Cover: fixed height, never resizes */
.ns-card__cover {
    position: relative;
    width: 100%;
    aspect-ratio: 450 / 262;
    overflow: hidden;
    border-radius: 0.4375rem;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .ns-card__cover {
        aspect-ratio: unset;
        height: 16.375rem;
    }
}

.ns-card__cover-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

/* Meta */
.ns-card__meta {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 0 1px;
    flex-shrink: 0;
}

.ns-card__title {
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.4;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ns-card__subtitle {
    font-size: 0.9375rem;
    line-height: 1.4;
    color: #8f8f8f;
}

/* Details reveal: grid trick */
.ns-card__details {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transition:
        grid-template-rows 0.25s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.22s ease;
}

.ns-card__details--open {
    grid-template-rows: 1fr;
    opacity: 1;
}

.ns-card__details-inner {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0 1px 0.375rem;
}

.ns-card__themes {
    font-size: 0.8125rem;
    line-height: 1.4;
    color: rgba(111, 175, 186, 0.9);
}

.ns-card__teaser {
    font-size: 0.875rem;
    line-height: 1.6;
    color: #8f8f8f;
}

.ns-card__branches {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #ffbe58;
}

.ns-card__cta {
    display: flex;
    height: 2.25rem;
    width: 100%;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    font-size: 1rem;
    font-weight: 500;
    margin-top: 0.125rem;
}

.ns-card__cta--active {
    background: var(--color-cta-fill, #6fafba);
    color: var(--color-cta-text, #000);
}

.ns-card__cta--disabled {
    border: 1px solid #4d4d4d;
    background: #3f3f3f;
    color: #8e8e8e;
}

/* ── Desktop expanded ────────────────────────────────────────────────────── */
@media (min-width: 1024px) {
    .ns-card--expanded .ns-card__inner {
        border-color: rgba(111, 175, 186, 0.55);
        box-shadow:
            0 16px 48px rgba(0, 0, 0, 0.6),
            0 0 36px rgba(111, 175, 186, 0.3);
    }

    .ns-card--expanded .ns-card__cover-img {
        transform: scale(1.03);
    }

    .ns-card--expanded .ns-card__title {
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
    }
}
</style>
