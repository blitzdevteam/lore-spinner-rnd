<script setup lang="ts">
import ExploreByMood from '@/components/ExploreByMood.vue';
import FrequentlyAskedQuestion from '@/components/FrequentlyAskedQuestion.vue';
import HeroBanner from '@/components/HeroBanner.vue';
import HeroFallback from '@/components/HeroFallback.vue';
import FeaturedWorldsGames from '@/components/FeaturedWorldsGames.vue';
import HomeWorldCard from '@/components/HomeWorldCard.vue';
import StoryChangesWithYou from '@/components/StoryChangesWithYou.vue';
import XenBanner from '@/components/XenBanner.vue';
import ContinueStories from '@/components/ContinueStories.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { GameInterface, StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

function goToChaosMode() {
    router.visit('/chaos-mode');
}

const props = withDefaults(
    defineProps<{
        featuredStory?: StoryInterface | null;
        lastGame?: GameInterface | null;
        stories?: StoryInterface[];
    }>(),
    {
        featuredStory: null,
        lastGame: null,
        stories: () => [],
    },
);

const newStories = computed(() => props.stories.slice(0, 3));
const comingSoonStories = computed(() =>
    props.stories.filter((s) => s.status?.value !== StoryStatusEnum.PUBLISHED).slice(0, 5),
);

// Slider scroll helpers
const comingSoonSliderEl = ref<HTMLElement | null>(null);
const newStoriesSliderEl = ref<HTMLElement | null>(null);

const scrollSlider = (el: HTMLElement | null, delta: number) => {
    if (!el) return;
    el.scrollBy({ left: delta, behavior: 'smooth' });
};
</script>

<template>
    <HomeLayout>
        <!-- Hero -->
        <HeroBanner v-if="featuredStory" :story="featuredStory" />
        <HeroFallback v-else />

        <!-- Continue Stories -->
        <ContinueStories v-if="lastGame" :game="lastGame" />

        <!-- Story Changes With You -->
        <StoryChangesWithYou />

        <!-- Featured Worlds -->
        <FeaturedWorldsGames :story-count="stories.length" />

        <!-- Xen Banner -->
        <XenBanner />

        <!-- Explore by Mood -->
        <ExploreByMood />

        <!-- New Stories -->
        <section class="py-14 md:py-[60px]">
            <div class="container">
                <div class="mx-auto flex w-full max-w-[1018px] flex-col gap-[14px]">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="max-w-[550px] min-w-0">
                            <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                                New Stories
                            </h2>
                            <p class="font-normal leading-[26px] text-[#b6b6b6] text-[16px]">
                                New branches, hidden paths, and fresh story worlds.
                            </p>
                        </div>
                        <Link
                            :href="storiesIndex().url"
                            class="inline-block w-[98px] shrink-0 whitespace-nowrap text-right text-[14px] leading-[33px] text-primary hover:underline font-medium"
                        >
                            View All ({{ stories.length }})
                        </Link>
                    </div>

                    <!-- 3-column horizontal story cards with image + tags -->
                    <div v-if="newStories.length" class="relative">
                        <button
                            class="slider-arrow absolute -left-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                            @click="scrollSlider(newStoriesSliderEl, -460)"
                            aria-label="Scroll left"
                        >
                            <span class="slider-arrow-icon rotate-180">&#8250;</span>
                        </button>

                        <div
                            ref="newStoriesSliderEl"
                            class="story-slider flex gap-[10px] overflow-x-auto pb-2 md:ml-[17px]"
                        >
                            <Link
                                v-for="story in newStories"
                                :key="story.id"
                                :href="storyShow(story.slug).url"
                                class="new-story-card flex w-[450px] shrink-0 flex-col gap-[10px]"
                            >
                                <!-- Story image (Figma: p-[4px] outer, h-[262] inner) -->
                                <div class="rounded-[8px] border border-[#373737] bg-[#262626] p-1">
                                    <div class="relative h-[262px] w-full overflow-hidden rounded-[8px]">
                                        <img
                                            v-if="story.cover"
                                            :src="story.cover"
                                            :alt="story.title"
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                                        >
                                            <span class="text-5xl font-bold text-primary/50">{{ story.title?.charAt(0)?.toUpperCase() }}</span>
                                        </div>
                                        <div class="absolute inset-y-0 left-0 w-20 bg-gradient-to-r from-[#262626] to-transparent" />
                                    </div>
                                </div>
                                <!-- Title + tags -->
                                <div class="flex w-[430px] flex-col gap-[3px] px-px">
                                    <p class="text-[18px] font-semibold leading-normal text-white">{{ story.title }}</p>
                                    <div class="flex flex-wrap items-center gap-[4px] text-[15px] leading-normal text-[#8f8f8f]">
                                        <template v-if="story.category">
                                            <span class="size-[6px] shrink-0 rounded-full bg-[#8f8f8f]" />
                                            <span>{{ story.category.title }}</span>
                                        </template>
                                        <template v-if="story.rating?.label">
                                            <span class="size-[6px] shrink-0 rounded-full bg-[#8f8f8f]" />
                                            <span>{{ story.rating.label }}</span>
                                        </template>
                                        <template v-if="story.status?.label">
                                            <span class="size-[6px] shrink-0 rounded-full bg-[#8f8f8f]" />
                                            <span>{{ story.status.label }}</span>
                                        </template>
                                    </div>
                                </div>
                            </Link>
                        </div>

                        <button
                            class="slider-arrow absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                            @click="scrollSlider(newStoriesSliderEl, 460)"
                            aria-label="Scroll right"
                        >
                            <span class="slider-arrow-icon">&#8250;</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Coming Soon -->
        <section v-if="comingSoonStories.length" class="py-14 md:py-[60px]">
            <div class="container">
                <div class="mx-auto flex w-full max-w-[1018px] flex-col gap-[14px]">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="max-w-[550px] min-w-0">
                            <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                                Coming Soon
                            </h2>
                            <p class="font-normal leading-[26px] text-[#b6b6b6] text-[16px]">
                                New worlds are coming soon.
                            </p>
                        </div>
                        <Link
                            :href="storiesIndex().url"
                            class="inline-block w-[98px] shrink-0 whitespace-nowrap text-right text-[14px] leading-[33px] text-primary hover:underline font-normal"
                        >
                            View All ({{ comingSoonStories.length }})
                        </Link>
                    </div>

                    <div class="relative">
                        <button
                            class="slider-arrow absolute -left-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                            @click="scrollSlider(comingSoonSliderEl, -214)"
                            aria-label="Scroll left"
                        >
                            <span class="slider-arrow-icon rotate-180">&#8250;</span>
                        </button>

                        <div
                            ref="comingSoonSliderEl"
                            class="story-slider flex gap-[10px] overflow-x-auto pb-2 md:ml-[17px]"
                        >
                            <HomeWorldCard
                                v-for="story in comingSoonStories"
                                :key="story.id"
                                :story="story"
                                :show-title="false"
                                :show-button="false"
                                class="shrink-0"
                            />
                        </div>

                        <button
                            class="slider-arrow absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                            @click="scrollSlider(comingSoonSliderEl, 214)"
                            aria-label="Scroll right"
                        >
                            <span class="slider-arrow-icon">&#8250;</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <div class="py-14 md:py-[60px]">
            <div class="container">
                <div class="mx-auto w-full max-w-[1018px]">
                    <FrequentlyAskedQuestion />
                </div>
            </div>
        </div>
    </HomeLayout>
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
    width: 34px;
    height: 34px;
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
.slider-arrow-icon {
    font-size: 22px;
    line-height: 1;
    color: white;
}

.new-story-card {
    display: flex;
    flex-direction: column;
    text-decoration: none;
    transition: opacity 0.2s;
}
.new-story-card:hover {
    opacity: 0.85;
}
</style>
