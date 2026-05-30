<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.jpg';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { ref } from 'vue';

defineProps<{
    storyCount: number;
}>();

interface FeaturedGame {
    id: string;
    title: string;
    cover: string;
    playable: boolean;
    slug?: string;
    themes: string[];
    teaser: string;
    branches: string | null;
}

const games: FeaturedGame[] = [
    {
        id: 'anima',
        title: 'Anima Machina',
        cover: animaCover,
        playable: true,
        slug: 'anima-machina',
        themes: ['Destiny', 'Courage', 'Control'],
        teaser: 'A haunted memory diver must stop a sentient AI from overwriting human grief with synthetic perfection.',
        branches: '8,347',
    },
    {
        id: 'alice',
        title: 'Alice In Wonderland',
        cover: aliceCover,
        playable: false,
        themes: ['Wonder', 'Identity', 'Logic'],
        teaser: 'Fall deeper into a world where nothing is as it seems and every choice rewrites the rules.',
        branches: null,
    },
    {
        id: 'nocturne',
        title: 'Nocturne',
        cover: nocturneCover,
        playable: false,
        themes: ['Mystery', 'Music', 'Sacrifice'],
        teaser: 'A jazz musician discovers the notes she plays can alter reality — but each performance costs a memory.',
        branches: null,
    },
    {
        id: 'jekyll',
        title: 'Jekyll & Hyde',
        cover: jekyllCover,
        playable: false,
        themes: ['Duality', 'Power', 'Morality'],
        teaser: 'Step into the fractured mind of a man at war with his own nature — and choose which side survives.',
        branches: null,
    },
    {
        id: 'oz',
        title: 'The Wonderful Wizard of Oz',
        cover: wizardOzCover,
        playable: false,
        themes: ['Courage', 'Home', 'Illusion'],
        teaser: 'Follow the yellow brick road — but every path leads somewhere different, and not all roads lead home.',
        branches: null,
    },
];

const sliderEl = ref<HTMLElement | null>(null);
const scrollSlider = (delta: number) => sliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });

const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(game: FeaturedGame): StorySheetData {
    return {
        id: game.id,
        title: game.title,
        cover: game.cover,
        themes: game.themes,
        isComingSoon: !game.playable,
        teaser: game.teaser,
        branches: game.branches,
        slug: game.slug,
    };
}

function openSheet(game: FeaturedGame) {
    sheetStory.value = toSheetData(game);
}

function ctaLabel(game: FeaturedGame): string {
    return game.playable ? 'Play' : 'Coming soon';
}
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">

                <SectionHeader
                    title="Featured Worlds"
                    subtitle="Curated story worlds built for choice, consequence, and return."
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
                        class="slider-arrow absolute top-1/2 left-0 z-10 hidden -translate-x-1/2 -translate-y-1/2 items-center justify-center md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-214)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div
                        ref="sliderEl"
                        class="story-slider flex gap-[0.625rem] overflow-x-auto pb-2"
                    >
                        <button
                            v-for="game in games"
                            :key="game.id"
                            type="button"
                            class="shrink-0 cursor-pointer border-0 bg-transparent p-0 text-left outline-none transition-transform duration-200 active:scale-[0.98]"
                            :aria-label="`Preview ${game.title}`"
                            @click="openSheet(game)"
                        >
                            <div
                                class="featured-game-card relative flex flex-col gap-2 rounded-[0.5rem] border border-[#373737] bg-[#262626] p-[0.375rem] transition-[border-color,opacity] duration-200 hover:border-primary/40"
                            >
                                <div class="relative h-[17.9649rem] w-[12rem] overflow-hidden rounded-[0.3125rem] border border-white/5">
                                    <img
                                        :src="game.cover"
                                        :alt="game.title"
                                        class="absolute inset-0 size-full max-w-none object-cover"
                                    />
                                </div>

                                <div class="flex h-5 items-center px-px">
                                    <p class="w-[12rem] truncate text-base font-semibold leading-normal text-white">
                                        {{ game.title }}
                                    </p>
                                </div>

                                <div
                                    class="flex h-9 w-[12rem] items-center justify-center rounded-[0.375rem] text-lg font-medium"
                                    :class="
                                        game.playable
                                            ? 'bg-cta-fill text-cta-text'
                                            : 'border border-[#4d4d4d] bg-[#3f3f3f] text-[#8e8e8e]'
                                    "
                                >
                                    {{ ctaLabel(game) }}
                                </div>
                            </div>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="slider-arrow absolute top-1/2 right-0 z-10 hidden translate-x-1/2 -translate-y-1/2 items-center justify-center md:flex"
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
