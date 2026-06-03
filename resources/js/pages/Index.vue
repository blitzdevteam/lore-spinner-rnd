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
import { filterVisibleLibraryStories } from '@/data/hiddenLibraryStorySlugs';
import { GameInterface, StoryInterface } from '@/types';
import { computed } from 'vue';

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

const visibleStories = computed(() => filterVisibleLibraryStories(props.stories));
</script>

<template>
    <HomeLayout>
        <!-- Hero -->
        <HeroBanner :stories="visibleStories" />

        <!-- Story Changes With You -->
        <StoryChangesWithYou />

        <!-- Continue Stories -->
        <ContinueStories v-if="lastGame" :game="lastGame" />

        <!-- Top Stories -->
        <FeaturedWorldsGames />

        <!-- Xen Banner -->
        <XenBanner />

        <!-- Explore by Mood -->
        <ExploreByMood />

        <!-- Featured Worlds -->
        <NewStoriesBanners />

        <ComingSoonBanners />

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
