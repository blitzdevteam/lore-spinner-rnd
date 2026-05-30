<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import cover1 from '@/assets/commingSoon/Coming soon 1- 2x.jpg';
import cover2 from '@/assets/commingSoon/Coming soon 2 - 2x.png';
import cover3 from '@/assets/commingSoon/Coming soon 3 - 2x.jpg';
import cover4 from '@/assets/commingSoon/Coming soon 4 - 2x.jpg';
import cover5 from '@/assets/commingSoon/Coming soon 5 - 2x.png';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { ref } from 'vue';

defineProps<{
    storyCount: number;
}>();

interface ComingSoonCard {
    id: string;
    title: string;
    cover: string;
    themes: string[];
}

const cards: ComingSoonCard[] = [
    {
        id: 'Romeo&Juliet',
        title: "Romeo & Juliet",
        cover: cover1,
        themes: ['Destiny', 'Courage', 'Control'],
    },
    {
        id: 'hansel',
        title: 'Hansel & Gretel',
        cover: cover2,
        themes: ['Survival', 'Fear', 'Family'],
    },
    {
        id: 'pride',
        title: 'Pride and Prejudice',
        cover: cover3,
        themes: ['Love', 'Duty', 'Society'],
    },
    {
        id: 'frankenstein',
        title: 'Frankenstein',
        cover: cover4,
        themes: ['Creation', 'Isolation', 'Ambition'],
    },
    {
        id: 'leagues',
        title: '20,000 Leagues Under the Sea',
        cover: cover5,
        themes: ['Discovery', 'Wonder', 'Peril'],
    },
];

const sliderEl = ref<HTMLElement | null>(null);
const scrollSlider = (delta: number) => sliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });

const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(card: ComingSoonCard): StorySheetData {
    return {
        id: card.id,
        title: card.title,
        cover: card.cover,
        themes: card.themes,
        isComingSoon: true,
    };
}

function openSheet(card: ComingSoonCard) {
    sheetStory.value = toSheetData(card);
}
</script>

<template>
    <section class="pt-10 pb-0 md:pt-[3.75rem]">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Coming Soon"
                    subtitle="New worlds are coming soon."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <div class="relative pb-2">
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
                        class="slider-arrow absolute -left-4 top-1/2 z-10 hidden -translate-y-1/2 items-center justify-center md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-214)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div
                        ref="sliderEl"
                        class="story-slider relative flex gap-[0.625rem] overflow-x-auto pb-2 md:ml-[1.0625rem]"
                    >
                        <button
                            v-for="card in cards"
                            :key="card.id"
                            type="button"
                            class="shrink-0 cursor-pointer border-0 bg-transparent p-0 text-left outline-none transition-transform duration-200 active:scale-[0.98]"
                            :aria-label="`Preview ${card.title}`"
                            @click="openSheet(card)"
                        >
                            <div
                                class="flex flex-col rounded-[0.5rem] border border-solid border-[#373737] bg-[#262626] p-[0.375rem] transition-colors duration-200 hover:border-primary/40"
                            >
                                <div class="flex w-[12rem] flex-col items-center">
                                    <div class="relative h-[17.9375rem] w-full overflow-hidden rounded-[0.3125rem]">
                                        <img
                                            :src="card.cover"
                                            :alt="card.title"
                                            class="pointer-events-none h-full w-full object-cover select-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="slider-arrow absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 items-center justify-center md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(214)"
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
.slider-arrow:hover {
    background: rgba(255, 255, 255, 0.15);
}
</style>
