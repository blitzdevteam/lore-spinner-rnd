<script setup lang="ts">
import ExploreByMood from '@/components/ExploreByMood.vue';
import FrequentlyAskedQuestion from '@/components/FrequentlyAskedQuestion.vue';
import HeroBanner from '@/components/HeroBanner.vue';
import HeroFallback from '@/components/HeroFallback.vue';
import ComingSoonBanners from '@/components/ComingSoonBanners.vue';
import FeaturedWorldsGames from '@/components/FeaturedWorldsGames.vue';
import NewStoriesBanners from '@/components/NewStoriesBanners.vue';
import StoryChangesWithYou from '@/components/StoryChangesWithYou.vue';
import XenBanner from '@/components/XenBanner.vue';
import ContinueStories from '@/components/ContinueStories.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { GameInterface, StoryInterface } from '@/types';

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
        <NewStoriesBanners :story-count="stories.length" />

        <ComingSoonBanners :story-count="stories.length" />

        <!-- FAQ -->
        <div class="home-section-y">
            <div class="container">
                <div class="container-content">
                    <FrequentlyAskedQuestion />
                </div>
            </div>
        </div>
    </HomeLayout>
</template>
