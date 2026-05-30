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

function scrollSlider(direction: -1 | 1) {
    const slider = sliderEl.value;
    if (!slider) return;

    const card = slider.querySelector<HTMLElement>('.story-card-slot');
    const gap = 10;
    const step = card ? card.offsetWidth + gap : 460;

    slider.scrollBy({ left: direction * step, behavior: 'smooth' });
}

const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

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
                        @click="scrollSlider(-1)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div
                        ref="sliderEl"
                        class="story-slider flex items-start gap-[0.625rem] overflow-x-auto py-1 pb-3 md:ml-[1.0625rem]"
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
                                    isDesktopHover && isExpanded(story.id) && 'ns-card--focused',
                                    !isDesktopHover ? 'cursor-pointer' : storyUrl(story) ? 'cursor-pointer no-underline' : 'cursor-default',
                                ]"
                                :aria-label="isDesktopHover && storyUrl(story) ? `Open ${story.title}` : `Preview ${story.title}`"
                                @click="!isDesktopHover && openSheet(story)"
                            >
                                <div class="ns-card__inner">
                                    <!-- Cover: always same height -->
                                    <div class="ns-card__cover">
                                        <img
                                            :src="story.cover"
                                            :alt="story.title"
                                            class="ns-card__cover-img"
                                        />
                                    </div>

                                    <!-- Meta row: always visible -->
                                    <div class="ns-card__meta">
                                        <p class="ns-card__title">{{ story.title }}</p>
                                        <p class="ns-card__subtitle">
                                            {{ story.category }} | {{ story.rating }} | {{ story.playable ? 'Published' : 'Coming soon' }}
                                        </p>
                                    </div>
                                </div>
                            </component>
                        </StoryExpandableCard>
                    </div>

                    <button
                        type="button"
                        class="slider-arrow slider-arrow-banner absolute -right-4 z-10 hidden items-center justify-center md:flex"
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
        border-color 0.2s ease,
        box-shadow 0.2s ease;
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

/* ── Desktop focused hover ───────────────────────────────────────────────── */
@media (min-width: 1024px) {
    .ns-card--focused .ns-card__inner {
        border-color: rgba(111, 175, 186, 0.55);
        box-shadow:
            0 20px 44px rgba(0, 0, 0, 0.58),
            0 0 36px rgba(111, 175, 186, 0.32),
            0 0 12px rgba(111, 175, 186, 0.22);
    }
}
</style>
