<script setup lang="ts">
import ExploreByMood from '@/components/ExploreByMood.vue';
import FrequentlyAskedQuestion from '@/components/FrequentlyAskedQuestion.vue';
import HeroBanner from '@/components/HeroBanner.vue';
import ComingSoonBanners from '@/components/ComingSoonBanners.vue';
import FeaturedWorldsGames from '@/components/FeaturedWorldsGames.vue';
import NewStoriesBanners from '@/components/NewStoriesBanners.vue';
import StoryChangesWithYou from '@/components/StoryChangesWithYou.vue';
import XenBanner from '@/components/XenBanner.vue';
import ContinueStories from '@/components/ContinueStories.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { GameInterface, StoryInterface } from '@/types';

withDefaults(
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
</script>

<template>
    <HomeLayout>
        <!-- Hero -->
        <HeroBanner :stories="stories" />

        <!-- Continue Stories -->
        <ContinueStories v-if="lastGame" :game="lastGame" />

        <!-- Story Changes With You -->
        <StoryChangesWithYou />

        <!-- Featured Worlds -->
        <FeaturedWorldsGames :story-count="stories.length" />

        <!-- Xen Banner -->
        <XenBanner />

        <!-- Explore by Mood -->
        <ExploreByMood :story-count="stories.length" />

        <!-- New Stories -->
        <NewStoriesBanners :story-count="stories.length" />

        <ComingSoonBanners :story-count="stories.length" />

        <!-- FAQ: slightly tighter top padding after Coming Soon so spacing matches Figma (hover glow → heading). -->
        <div class="pt-10 pb-14 md:pt-12 md:pb-[3.75rem]">
            <div class="container">
                <div class="container-content">
                    <FrequentlyAskedQuestion />
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
