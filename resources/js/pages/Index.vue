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

function goToChaosMode() {
    router.visit('/chaos-mode');
}

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

        <!-- Chaos Mode feature section — immediately below the hero -->
        <div class="chaos-feature" @click="goToChaosMode">
            <div class="chaos-feature-glow" aria-hidden="true" />
            <div class="container relative z-[1]">
                <div class="chaos-feature-inner">
                    <!-- Left: text -->
                    <div class="chaos-feature-copy">
                        <div class="chaos-feature-tag">
                            <span class="chaos-feature-tag-dot" aria-hidden="true" />
                            New Experience
                        </div>
                        <h2 class="chaos-feature-headline">
                            Step into<br /><em>Chaos Mode</em>
                        </h2>
                        <p class="chaos-feature-body">
                            A full-agency narration engine. No rails, no event gates — just you,
                            the narrator, and the world's own logic. Six stories live now.
                        </p>
                        <div class="chaos-feature-actions">
                            <button class="chaos-feature-btn-primary" @click.stop="goToChaosMode">
                                Enter Chaos Mode
                            </button>
                            <span class="chaos-feature-meta">Alice · Sherlock · Tell-Tale Heart · Nocturne · Anima Machina · Driftheart</span>
                        </div>
                    </div>

                    <!-- Right: decorative suits -->
                    <div class="chaos-feature-art" aria-hidden="true">
                        <span class="chaos-suit chaos-suit-1">♠</span>
                        <span class="chaos-suit chaos-suit-2">♥</span>
                        <span class="chaos-suit chaos-suit-3">♦</span>
                        <span class="chaos-suit chaos-suit-4">♣</span>
                        <span class="chaos-suit chaos-suit-5">♦</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Continue Stories — only shown when user has an active game -->
        <ContinueStories v-if="lastGame" :game="lastGame" />

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
                <div class="flex flex-col gap-8 md:gap-12">
                    <BaseContentTitle title="Creators">
                        <template #description>
                            Meet the minds behind the worlds you love and explore the worlds they are actively
                            <span class="text-primary">bringing to life</span>
                        </template>
                    </BaseContentTitle>
                    <div class="flex flex-col gap-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
                            <BaseCreatorCard v-for="creator in creators" :key="creator.username ?? creator.id" :creator />
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
/* ─── Chaos Mode feature section ────────────────────────────────────────────── */
.chaos-feature {
    position: relative;
    overflow: hidden;
    cursor: pointer;
    border-top: 1px solid rgba(229, 173, 83, 0.14);
    border-bottom: 1px solid rgba(229, 173, 83, 0.14);
    background:
        linear-gradient(135deg, rgba(229, 173, 83, 0.08) 0%, transparent 60%),
        linear-gradient(to bottom, rgba(7, 6, 4, 0.0) 0%, rgba(229, 173, 83, 0.04) 100%);
    transition: background 0.3s;
}
.chaos-feature:hover {
    background:
        linear-gradient(135deg, rgba(229, 173, 83, 0.14) 0%, transparent 60%),
        linear-gradient(to bottom, rgba(7, 6, 4, 0.0) 0%, rgba(229, 173, 83, 0.07) 100%);
}

.chaos-feature-glow {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(ellipse 60% 120% at 0% 50%, rgba(229, 173, 83, 0.12), transparent 65%),
        radial-gradient(ellipse 40% 80% at 85% 50%, rgba(229, 173, 83, 0.06), transparent 60%);
    transition: opacity 0.3s;
}
.chaos-feature:hover .chaos-feature-glow {
    opacity: 1.4;
}

.chaos-feature-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 32px;
    padding: 48px 0;
}

@media (max-width: 639px) {
    .chaos-feature-inner {
        padding: 36px 0;
        flex-direction: column;
        align-items: flex-start;
        gap: 24px;
    }
}

.chaos-feature-copy {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 560px;
}

.chaos-feature-tag {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: rgba(229, 173, 83, 0.65);
    font-family: Georgia, serif;
}

.chaos-feature-tag-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgba(229, 173, 83, 0.7);
    box-shadow: 0 0 6px 2px rgba(229, 173, 83, 0.35);
    animation: chaos-pulse 2.4s ease-in-out infinite;
}

@keyframes chaos-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.55; transform: scale(0.8); }
}

.chaos-feature-headline {
    font-size: clamp(28px, 5vw, 48px);
    font-weight: 500;
    line-height: 1.15;
    color: rgba(250, 246, 239, 0.92);
    letter-spacing: -0.01em;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
}

.chaos-feature-headline em {
    font-style: normal;
    color: #e5ad53;
    text-shadow: 0 0 40px rgba(229, 173, 83, 0.35), 0 1px 2px rgba(0, 0, 0, 0.4);
}

.chaos-feature-body {
    font-size: 14px;
    line-height: 1.75;
    color: rgba(229, 217, 192, 0.5);
    max-width: 460px;
}

.chaos-feature-actions {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.chaos-feature-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 28px;
    background: #e5ad53;
    color: #1f160d;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.04em;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: filter 0.2s, box-shadow 0.2s, transform 0.15s;
}
.chaos-feature-btn-primary:hover {
    filter: brightness(1.08);
    box-shadow: 0 8px 28px -4px rgba(229, 173, 83, 0.45);
    transform: translateY(-1px);
}
.chaos-feature-btn-primary:active {
    transform: translateY(0);
}

.chaos-feature-meta {
    font-size: 11px;
    color: rgba(229, 217, 192, 0.28);
    letter-spacing: 0.04em;
    font-style: italic;
}

/* Decorative suits */
.chaos-feature-art {
    position: relative;
    width: 180px;
    height: 160px;
    flex-shrink: 0;
    display: none;
}

@media (min-width: 768px) {
    .chaos-feature-art {
        display: block;
    }
}

.chaos-suit {
    position: absolute;
    font-family: Georgia, serif;
    line-height: 1;
    user-select: none;
    transition: opacity 0.3s;
}

.chaos-suit-1 {
    font-size: 80px;
    top: 0;
    left: 20px;
    color: rgba(229, 173, 83, 0.12);
    transform: rotate(-12deg);
}
.chaos-suit-2 {
    font-size: 56px;
    top: 20px;
    right: 10px;
    color: rgba(229, 173, 83, 0.18);
    transform: rotate(8deg);
}
.chaos-suit-3 {
    font-size: 40px;
    bottom: 10px;
    left: 50px;
    color: rgba(229, 173, 83, 0.28);
    transform: rotate(-5deg);
}
.chaos-suit-4 {
    font-size: 28px;
    bottom: 30px;
    right: 0;
    color: rgba(229, 173, 83, 0.15);
    transform: rotate(15deg);
}
.chaos-suit-5 {
    font-size: 18px;
    top: 60px;
    left: 0;
    color: rgba(229, 173, 83, 0.35);
    transform: rotate(-20deg);
}

.chaos-feature:hover .chaos-suit-1 { color: rgba(229, 173, 83, 0.2); }
.chaos-feature:hover .chaos-suit-2 { color: rgba(229, 173, 83, 0.28); }
.chaos-feature:hover .chaos-suit-3 { color: rgba(229, 173, 83, 0.4); }
.chaos-feature:hover .chaos-suit-5 { color: rgba(229, 173, 83, 0.5); }

/* ─── Existing transitions ──────────────────────────────────────────────────── */
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
