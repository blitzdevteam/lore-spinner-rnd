<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import banner1 from '@/assets/newStories/New stories 1- 2x.jpg';
import banner2 from '@/assets/newStories/New stories 2 - 2x .jpg';
import banner3 from '@/assets/newStories/New stories 3 - 2x.jpg';
import banner1Hover from '@/assets/newStories/s1-hover.jpg';
import banner2Hover from '@/assets/newStories/s2-hover.jpg';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
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
        id: 'Alice’s Adventures In Wonderland',
        title: 'Alice’s Adventures In Wonderland',
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
        id: 'Pride & Prejudice',
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
    };
}

function openSheet(story: NewStory) {
    sheetStory.value = toSheetData(story);
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
                        class="story-slider flex gap-[0.625rem] overflow-x-auto pb-2 md:ml-[1.0625rem]"
                    >
                        <button
                            v-for="story in stories"
                            :key="story.id"
                            type="button"
                            class="new-banner-card shrink-0 cursor-pointer border-0 bg-transparent p-0 text-left outline-none transition-transform duration-200 active:scale-[0.98]"
                            :aria-label="`Preview ${story.title}`"
                            @click="openSheet(story)"
                        >
                            <div class="new-banner-card__inner flex w-[min(28.125rem,78vw)] flex-col gap-[0.625rem] md:w-[28.125rem]">
                                <div class="rounded-[0.5rem] border border-[#373737] bg-[#262626] p-1 transition-colors duration-200 hover:border-primary/40">
                                    <div class="relative aspect-[450/262] w-full overflow-hidden rounded-[0.5rem] md:h-[16.375rem] md:aspect-auto">
                                        <img
                                            :src="story.cover"
                                            :alt="story.title"
                                            class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 ease-out group-hover:scale-[1.03]"
                                        />
                                    </div>
                                </div>

                                <div class="flex w-full flex-col gap-[3px] px-px md:w-[26.875rem]">
                                    <p class="text-[1.125rem] font-semibold leading-normal text-white">
                                        {{ story.title }}
                                    </p>
                                    <p class="text-[0.9375rem] leading-normal text-[#8f8f8f]">
                                        {{ story.category }} | {{ story.rating }} | {{ story.playable ? 'Published' : 'Coming soon' }}
                                    </p>
                                </div>
                            </div>
                        </button>
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

    <StoryDetailsSheet :story="sheetStory" @close="sheetStory = null" />
</template>

<style scoped>
.story-slider {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.story-slider::-webkit-scrollbar {
    display: none;
}

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
</style>
