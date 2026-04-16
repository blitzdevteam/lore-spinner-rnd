<script setup lang="ts">
import BannerImage from '@/assets/banner.png';
import BaseButton from '@/components/BaseButton.vue';
import BaseContentTitle from '@/components/BaseContentTitle.vue';
import BaseCreatorCard from '@/components/BaseCreatorCard.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import BaseStoryCard from '@/components/BaseStoryCard.vue';
import CommunitySignup from '@/components/CommunitySignup.vue';
import ContinueStories from '@/components/ContinueStories.vue';
import FrequentlyAskedQuestion from '@/components/FrequentlyAskedQuestion.vue';
import HeroBanner from '@/components/HeroBanner.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { CreatorInterface, GameInterface, StoryInterface } from '@/types';
import { show } from '@/wayfinder/routes/stories';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        featuredStory?: StoryInterface | null;
        lastGame?: GameInterface | null;
        creators?: CreatorInterface[];
        stories?: StoryInterface[];
    }>(),
    {
        featuredStory: null,
        lastGame: null,
        creators: () => [],
        stories: () => [],
    },
);

const selectedStory = ref<StoryInterface | null>(null);

const activeStory = computed(() => selectedStory.value ?? props.stories[0] ?? null);

const handleSelectStory = (story: StoryInterface) => {
    if (window.innerWidth < 768) {
        router.visit(show(story.slug).url);
        return;
    }
    selectedStory.value = story;
};

// Smart scroll shadows — opacity scales with available scroll distance
const scrollEl = ref<HTMLElement | null>(null);
const topShadow = ref(0);
const bottomShadow = ref(0);

const updateShadows = () => {
    const el = scrollEl.value;
    if (!el) return;

    const { scrollTop, scrollHeight, clientHeight } = el;
    const maxScroll = scrollHeight - clientHeight;

    if (maxScroll <= 0) {
        // Nothing to scroll
        topShadow.value = 0;
        bottomShadow.value = 0;
        return;
    }

    // Ramp up over the first/last 80px of scroll, capped at 1
    topShadow.value = Math.min(scrollTop / 80, 1);
    bottomShadow.value = Math.min((maxScroll - scrollTop) / 80, 1);
};

onMounted(() => {
    const el = scrollEl.value;
    if (el) {
        el.addEventListener('scroll', updateShadows, { passive: true });
        updateShadows();
    }
});

onBeforeUnmount(() => {
    scrollEl.value?.removeEventListener('scroll', updateShadows);
});
</script>

<template>
    <HomeLayout>
        <!-- Netflix-style Editor's Choice Banner -->
        <HeroBanner v-if="featuredStory" :story="featuredStory" />

        <!-- Fallback: original banner when no featured story -->
        <div
            v-else
            class="grid h-64 place-items-center bg-cover md:h-108"
            :style="{ background: `url(${BannerImage}) center center no-repeat`, backgroundSize: 'cover' }"
        >
            <div class="container">
                <div class="mx-auto flex w-56 flex-col items-center gap-3 md:mx-0 md:-ms-20 md:w-86 md:gap-4">
                    <BaseLogo class="w-full" fill="white" />
                    <h3 class="text-center font-gill-sans text-lg font-light text-primary md:text-2xl">Stories That Live Through You</h3>
                </div>
            </div>
        </div>

        <!-- Continue Stories — only shown when user has an active game -->
        <ContinueStories v-if="lastGame" :game="lastGame" />

        <div class="py-10 md:py-18">
            <div class="container">
                <div class="flex flex-col gap-8 md:gap-12">
                    <BaseContentTitle title="Creators">
                        <template #description>
                            Meet the minds behind the worlds you love and explore the worlds they are actively
                            <span class="text-primary">bringing to life</span>
                        </template>
                    </BaseContentTitle>
                    <div class="flex flex-col gap-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
                            <BaseCreatorCard v-for="creator in creators" :key="creator.username" :creator />
                        </div>
                        <div class="mx-auto w-full sm:w-auto">
                            <BaseButton class="w-full text-lg sm:w-64" severity="transparent"> View All ({{ creators.length }}) </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="py-10 md:py-18">
            <div class="container">
                <div class="flex flex-col gap-8 md:gap-12">
                    <BaseContentTitle title="Stories">
                        <template #description>
                            Explore
                            <span class="text-primary">original worlds</span>
                            created by creators and unlocked gradually as you read
                        </template>
                    </BaseContentTitle>
                    <div v-if="stories.length" class="flex flex-col gap-4 md:h-[640px] md:flex-row md:gap-6">
                        <!-- Left: independently scrollable story list -->
                        <div class="relative w-full md:w-1/2">
                            <div ref="scrollEl" class="md:h-full md:overflow-y-auto md:pr-2 scrollbar-thin">
                                <div class="flex flex-col gap-4">
                                    <BaseStoryCard
                                        v-for="story in stories"
                                        :key="story.id"
                                        :story
                                        :selectable="true"
                                        :active="activeStory?.id === story.id"
                                        @select="handleSelectStory"
                                    />
                                </div>
                            </div>
                            <!-- Top shadow — fades in as you scroll down -->
                            <div
                                class="pointer-events-none absolute top-0 right-0 left-0 hidden h-10 bg-gradient-to-b from-gray-950 to-transparent transition-opacity duration-200 md:block"
                                :style="{ opacity: topShadow }"
                            />
                            <!-- Bottom shadow — fades in when there's more to scroll -->
                            <div
                                class="pointer-events-none absolute right-0 bottom-0 left-0 hidden h-14 bg-gradient-to-t from-gray-950 to-transparent transition-opacity duration-200 md:block"
                                :style="{ opacity: bottomShadow }"
                            />
                        </div>
                        <!-- Right: sticky detail panel for selected story -->
                        <div class="hidden w-1/2 overflow-hidden md:block">
                            <Transition name="fade" mode="out-in">
                                <BaseStoryCard
                                    v-if="activeStory"
                                    :key="activeStory.id"
                                    :story="activeStory"
                                    type="column"
                                />
                            </Transition>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="py-10 md:py-18">
            <div class="container">
                <CommunitySignup />
            </div>
        </div>

        <div class="pt-10 pb-10 md:pt-18 md:pb-16">
            <div class="container">
                <FrequentlyAskedQuestion />
            </div>
        </div>
    </HomeLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 2px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>
