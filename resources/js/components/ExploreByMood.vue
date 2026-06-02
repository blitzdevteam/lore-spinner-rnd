<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.png';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import ozCover from '@/assets/newStories/Oz landscape titled.png';
import heartImg from '@/assets/mood/Heart.svg';
import mountainsImg from '@/assets/mood/Mountains.svg';
import eyeImg from '@/assets/mood/Eye.svg';
import swordImg from '@/assets/mood/Sword.svg';
import spiralImg from '@/assets/mood/Spiral.svg';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { computed, onUnmounted, ref, watch } from 'vue';

defineProps<{
    storyCount?: number;
}>();

type MoodId = 'heartfelt' | 'adventurous' | 'mysterious' | 'epic' | 'whimsical';

interface MoodGame {
    id: string;
    title: string;
    cover: string;
    playable: boolean;
    slug?: string;
    themes: string[];
    teaser: string;
}

const gamesById: Record<string, MoodGame> = {
    anima: {
        id: 'anima',
        title: 'Anima Machina',
        cover: animaCover,
        playable: true,
        slug: 'anima-machina',
        themes: ['Destiny', 'Courage', 'Control'],
        teaser: 'When a sentient AI threatens to overwrite all human grief with synthetic perfection, a haunted memory diver races against the clock to stop the digital reset.',
    },
    alice: {
        id: 'alice',
        title: 'Alice In Wonderland',
        cover: aliceCover,
        playable: false,
        themes: ['Wonder', 'Identity', 'Logic'],
        teaser: 'Follow Alice into a curious world of talking cats, mad tea parties, and impossible adventures where every path leads somewhere unexpected.',
    },
    nocturne: {
        id: 'nocturne',
        title: 'Nocturne',
        cover: nocturneCover,
        playable: false,
        themes: ['Mystery', 'Music', 'Sacrifice'],
        teaser: 'Beyond the rain-soaked glass walls of Nocturne, Akira finds herself trapped inside a system where identities are rewritten and nothing is quite as voluntary as it seems.',
    },
    jekyll: {
        id: 'jekyll',
        title: 'Jekyll & Hyde',
        cover: jekyllCover,
        playable: false,
        themes: ['Duality', 'Power', 'Morality'],
        teaser: "Beneath the fog-covered streets of Victorian London, a terrifying secret grows inside Dr. Jekyll's laboratory, threatening to consume everyone around him.",
    },
    oz: {
        id: 'oz',
        title: 'The Wonderful Wizard of Oz',
        cover: ozCover,
        playable: false,
        themes: ['Courage', 'Home', 'Illusion'],
        teaser: 'A storm carries you into the magical land of Oz, where witches whisper, lions tremble, and every step down the Yellow Brick Road changes who you are becoming.',
    },
};

const moodConfigs: {
    id: MoodId;
    label: string;
    accentRgb: string;
    blurb: string;
    gameIds: string[];
}[] = [
    {
        id: 'heartfelt',
        label: 'Heartfelt',
        accentRgb: '201, 52, 52',
        blurb: 'Stories centered on connection, loss, and hope.',
        gameIds: ['anima', 'jekyll', 'oz'],
    },
    {
        id: 'adventurous',
        label: 'Adventurous',
        accentRgb: '236, 200, 99',
        blurb: 'Bold journeys, perilous roads, and unforgettable detours.',
        gameIds: ['alice', 'oz', 'anima'],
    },
    {
        id: 'mysterious',
        label: 'Mysterious',
        accentRgb: '98, 232, 219',
        blurb: 'Secrets, shadows, and riddles that refuse to stay buried.',
        gameIds: ['nocturne', 'jekyll', 'anima'],
    },
    {
        id: 'epic',
        label: 'Epic',
        accentRgb: '88, 217, 161',
        blurb: 'High stakes, sweeping scale, and choices that echo forward.',
        gameIds: ['anima', 'jekyll', 'oz'],
    },
    {
        id: 'whimsical',
        label: 'Whimsical',
        accentRgb: '169, 121, 194',
        blurb: 'Strange, playful worlds where logic takes a holiday.',
        gameIds: ['alice', 'nocturne', 'oz'],
    },
];

const openMoodId = ref<MoodId | null>(null);
const moodScrollEl = ref<HTMLElement | null>(null);
const moodSliderEl = ref<HTMLElement | null>(null);

const isPanelOpen = computed(() => openMoodId.value !== null);

const { leftShadowVisible: moodLeftShadow, rightShadowVisible: moodRightShadow } =
    useSliderEdgeShadows(moodScrollEl);
const { leftShadowVisible: panelLeftShadow, rightShadowVisible: panelRightShadow } =
    useSliderEdgeShadows(moodSliderEl, { enabled: isPanelOpen });

const activeMoodConfig = computed(() => moodConfigs.find((m) => m.id === openMoodId.value) ?? null);

const panelGames = computed(() => {
    if (!activeMoodConfig.value) return [];
    return activeMoodConfig.value.gameIds.map((gid) => gamesById[gid]).filter(Boolean);
});

function openMood(id: MoodId) {
    openMoodId.value = id;
}

function closeMood() {
    openMoodId.value = null;
}

function scrollMoodGames(delta: number) {
    moodSliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });
}

function onBackdropKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        closeMood();
    }
}

watch(isPanelOpen, (open) => {
    if (typeof document === 'undefined') return;
    if (open) {
        document.body.classList.add('overflow-hidden');
        window.addEventListener('keydown', onBackdropKeydown);
    } else {
        document.body.classList.remove('overflow-hidden');
        window.removeEventListener('keydown', onBackdropKeydown);
    }
});

onUnmounted(() => {
    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onBackdropKeydown);
});
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Explore by mood"
                    subtitle="Choose the feeling you want to step into."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <!-- Figma 5412:908: flex row, gap exactly 10px (not stretched 1fr columns) -->
                <div class="relative">
                    <div
                        ref="moodScrollEl"
                        class="mood-scroll flex w-full max-w-full flex-nowrap items-center gap-[0.625rem] overflow-x-auto pb-1 md:w-max md:overflow-visible"
                    >
                    <!-- Heartfelt -->
                    <button
                        type="button"
                        class="mood-card mood-card--heartfelt relative z-0 h-[9.25rem] w-[12.1875rem] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
                        :class="{ 'mood-card--active': openMoodId === 'heartfelt' }"
                        :aria-expanded="openMoodId === 'heartfelt'"
                        aria-controls="mood-explore-panel"
                        @click="openMood('heartfelt')"
                    >
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <div
                            class="mood-card-glow mood-card-glow--heartfelt pointer-events-none absolute inset-0 z-[1] rounded-lg"
                            aria-hidden="true"
                        />
                        <div class="mood-icon-orbit absolute z-[2] left-[3.7188rem] top-[1.0625rem] size-[4.75rem] overflow-hidden rounded-[9.375rem]">
                            <div class="mood-icon-orbit-bg pointer-events-none absolute inset-0 rounded-[9.375rem]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[9.375rem] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[9.375rem] bg-[rgba(121,13,13,0.16)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[1.375rem] top-[1.375rem] size-8">
                                <img :src="heartImg" alt="" width="32" height="32" class="block size-full max-w-none" />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <p
                            class="absolute z-[2] left-[4.0938rem] top-[7.0938rem] -translate-y-1/2 whitespace-nowrap text-[0.9375rem] font-medium capitalize leading-[2.0625rem] not-italic text-[#c93434]"
                        >
                            Heartfelt
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 z-[3] rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>

                    <!-- Adventurous -->
                    <button
                        type="button"
                        class="mood-card mood-card--adventurous relative z-0 h-[9.25rem] w-[12.1875rem] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
                        :class="{ 'mood-card--active': openMoodId === 'adventurous' }"
                        :aria-expanded="openMoodId === 'adventurous'"
                        aria-controls="mood-explore-panel"
                        @click="openMood('adventurous')"
                    >
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <div
                            class="mood-card-glow mood-card-glow--adventurous pointer-events-none absolute inset-0 z-[1] rounded-lg"
                            aria-hidden="true"
                        />
                        <div class="mood-icon-orbit absolute z-[2] left-[3.75rem] top-[1.0625rem] size-[4.75rem] overflow-hidden rounded-[9.375rem]">
                            <div class="mood-icon-orbit-bg pointer-events-none absolute inset-0 rounded-[9.375rem]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[9.375rem] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[9.375rem] bg-[rgba(247,179,8,0.07)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[1.375rem] top-[1.375rem] size-8">
                                <img
                                    :src="mountainsImg"
                                    alt=""
                                    width="32"
                                    height="32"
                                    class="block size-full max-w-none"
                                />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <p
                            class="absolute z-[2] left-[3.2188rem] top-[7.0938rem] -translate-y-1/2 whitespace-nowrap text-[0.9375rem] font-medium capitalize leading-[2.0625rem] not-italic text-[#ecc863]"
                        >
                            Adventurous
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 z-[3] rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>

                    <!-- Mysterious -->
                    <button
                        type="button"
                        class="mood-card mood-card--mysterious relative z-0 h-[9.25rem] w-[12.1875rem] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
                        :class="{ 'mood-card--active': openMoodId === 'mysterious' }"
                        :aria-expanded="openMoodId === 'mysterious'"
                        aria-controls="mood-explore-panel"
                        @click="openMood('mysterious')"
                    >
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <div
                            class="mood-card-glow mood-card-glow--mysterious pointer-events-none absolute inset-0 z-[1] rounded-lg"
                            aria-hidden="true"
                        />
                        <div class="mood-icon-orbit absolute z-[2] left-[3.7188rem] top-[1.0625rem] size-[4.75rem] overflow-hidden rounded-[9.375rem]">
                            <div class="mood-icon-orbit-bg pointer-events-none absolute inset-0 rounded-[9.375rem]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[9.375rem] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[9.375rem] bg-[rgba(98,232,219,0.09)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[1.375rem] top-[1.5625rem] size-8">
                                <img :src="eyeImg" alt="" width="32" height="32" class="block size-full max-w-none" />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <p
                            class="absolute z-[2] left-[3.5938rem] top-[7.0938rem] -translate-y-1/2 whitespace-nowrap text-[0.9375rem] font-medium capitalize leading-[2.0625rem] not-italic text-[#62e8db]"
                        >
                            Mysterious
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 z-[3] rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>

                    <!-- Epic -->
                    <button
                        type="button"
                        class="mood-card mood-card--epic relative z-0 h-[9.25rem] w-[12.1875rem] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
                        :class="{ 'mood-card--active': openMoodId === 'epic' }"
                        :aria-expanded="openMoodId === 'epic'"
                        aria-controls="mood-explore-panel"
                        @click="openMood('epic')"
                    >
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <div
                            class="mood-card-glow mood-card-glow--epic pointer-events-none absolute inset-0 z-[1] rounded-lg"
                            aria-hidden="true"
                        />
                        <p
                            class="absolute z-[2] left-[5.0938rem] top-[7.0938rem] -translate-y-1/2 whitespace-nowrap text-[0.9375rem] font-medium capitalize leading-[2.0625rem] not-italic text-[#58d9a1]"
                        >
                            Epic
                        </p>
                        <div class="mood-icon-orbit absolute z-[2] left-[3.7188rem] top-[1.0625rem] size-[4.75rem] overflow-hidden rounded-[9.375rem]">
                            <div class="mood-icon-orbit-bg pointer-events-none absolute inset-0 rounded-[9.375rem]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[9.375rem] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[9.375rem] bg-[rgba(88,217,161,0.15)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div
                                class="absolute left-[0.9375rem] top-[0.9375rem] flex size-[2.8284rem] items-center justify-center"
                            >
                                <div class="flex-none -rotate-45">
                                    <div class="relative size-8">
                                        <img :src="swordImg" alt="" width="32" height="32" class="block size-full max-w-none" />
                                    </div>
                                </div>
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <div
                            class="pointer-events-none absolute inset-0 z-[3] rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>

                    <!-- Whimsical -->
                    <button
                        type="button"
                        class="mood-card mood-card--whimsical relative z-0 h-[9.25rem] w-[12.1875rem] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
                        :class="{ 'mood-card--active': openMoodId === 'whimsical' }"
                        :aria-expanded="openMoodId === 'whimsical'"
                        aria-controls="mood-explore-panel"
                        @click="openMood('whimsical')"
                    >
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <div
                            class="mood-card-glow mood-card-glow--whimsical pointer-events-none absolute inset-0 z-[1] rounded-lg"
                            aria-hidden="true"
                        />
                        <p
                            class="absolute z-[2] left-[3.7813rem] top-[7.0938rem] -translate-y-1/2 whitespace-nowrap text-[0.9375rem] font-medium capitalize leading-[2.0625rem] not-italic text-[#a979c2]"
                        >
                            Whimsical
                        </p>
                        <div class="mood-icon-orbit absolute z-[2] left-[3.7188rem] top-[1.0625rem] size-[4.75rem] overflow-hidden rounded-[9.375rem]">
                            <div class="mood-icon-orbit-bg pointer-events-none absolute inset-0 rounded-[9.375rem]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[9.375rem] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[9.375rem] bg-[rgba(169,121,194,0.07)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[1.375rem] top-[1.5rem] size-8">
                                <img
                                    :src="spiralImg"
                                    alt=""
                                    width="32"
                                    height="32"
                                    class="block size-full max-w-none"
                                />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <div
                            class="pointer-events-none absolute inset-0 z-[3] rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>
                    </div>

                    <!-- Edge fades — only needed when chips row scrolls (narrow viewports); hidden on md+ -->
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-6 bg-gradient-to-r from-black/70 to-transparent transition-opacity duration-300 md:hidden"
                        :class="moodLeftShadow ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-black to-transparent transition-opacity duration-300 md:hidden"
                        :class="moodRightShadow ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                </div>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="mood-overlay">
                <div
                    v-if="activeMoodConfig"
                    id="mood-explore-panel"
                    class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="mood-panel-title"
                >
                    <div
                        class="absolute inset-0 bg-[rgba(0,0,0,0.78)] backdrop-blur-md"
                        @click="closeMood"
                    />
                    <Transition name="mood-sheet" appear>
                        <div
                            v-if="activeMoodConfig"
                            class="relative z-10 flex max-h-[min(92vh,880px)] w-full max-w-5xl flex-col overflow-hidden rounded-t-[1.25rem] border border-white/10 bg-[#0c0c0c] shadow-[0_-8px_60px_rgba(0,0,0,0.65)] sm:rounded-2xl"
                            :style="{
                                boxShadow: `0 0 0 1px rgba(${activeMoodConfig.accentRgb}, 0.2), 0 24px 80px rgba(0,0,0,0.55), 0 0 120px rgba(${activeMoodConfig.accentRgb}, 0.12)`,
                            }"
                            @click.stop
                        >
                            <div
                                class="pointer-events-none absolute inset-x-0 top-0 h-32 opacity-90"
                                :style="{
                                    background: `radial-gradient(ellipse 90% 100% at 50% -20%, rgba(${activeMoodConfig.accentRgb}, 0.35), transparent 72%)`,
                                }"
                            />
                            <div
                                class="relative flex items-start justify-between gap-4 border-b border-white/10 px-5 pb-4 pt-5 sm:px-8 sm:pt-7"
                            >
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.2em] text-white/55">
                                        Explore by mood
                                    </p>
                                    <h3
                                        id="mood-panel-title"
                                        class="text-2xl font-bold uppercase leading-tight tracking-tight text-white sm:text-[1.75rem]"
                                    >
                                        {{ activeMoodConfig.label }}
                                    </h3>
                                    <p class="max-w-xl text-[0.9375rem] leading-relaxed text-[#a8a8a8]">
                                        {{ activeMoodConfig.blurb }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <Link
                                        :href="`${storiesIndex().url}?mood=${activeMoodConfig.id}`"
                                        class="hidden rounded-lg border border-primary/50 bg-primary/10 px-3 py-2 text-sm font-medium text-primary hover:bg-primary/20 sm:inline-block"
                                        @click="closeMood"
                                    >
                                        Browse library
                                    </Link>
                                    <button
                                        type="button"
                                        class="flex size-10 items-center justify-center rounded-full border border-white/15 bg-white/5 text-white transition hover:bg-white/12"
                                        aria-label="Close"
                                        @click="closeMood"
                                    >
                                        <X class="size-5" :stroke-width="2" />
                                    </button>
                                </div>
                            </div>

                            <div class="relative flex flex-1 flex-col gap-4 overflow-hidden px-3 pb-5 pt-2 sm:px-8 sm:pb-8 sm:pt-4">
                                <div class="relative">
                                    <div
                                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-6 bg-gradient-to-r from-[#0c0c0c] to-transparent transition-opacity duration-300 sm:left-[-0.125rem] sm:w-14"
                                        :class="panelLeftShadow ? 'opacity-100' : 'opacity-0'"
                                        aria-hidden="true"
                                    />
                                    <div
                                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-[#0c0c0c] to-transparent transition-opacity duration-300 sm:right-[-0.125rem] sm:w-14"
                                        :class="panelRightShadow ? 'opacity-100' : 'opacity-0'"
                                        aria-hidden="true"
                                    />
                                    <button
                                        type="button"
                                        class="slider-arrow absolute -left-1 top-[8.75rem] z-10 hidden -translate-y-1/2 sm:flex"
                                        aria-label="Scroll picks left"
                                        @click="scrollMoodGames(-228)"
                                    >
                                        <span class="slider-arrow-icon rotate-180">&#8250;</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="slider-arrow absolute -right-1 top-[8.75rem] z-10 hidden -translate-y-1/2 sm:flex"
                                        aria-label="Scroll picks right"
                                        @click="scrollMoodGames(228)"
                                    >
                                        <span class="slider-arrow-icon">&#8250;</span>
                                    </button>

                                    <div
                                        ref="moodSliderEl"
                                        class="mood-game-slider flex gap-4 overflow-x-auto px-2 pb-2 pt-1 sm:gap-5 sm:px-1"
                                    >
                                        <article
                                            v-for="game in panelGames"
                                            :key="game.id"
                                            class="group flex w-[min(192px,78vw)] shrink-0 flex-col gap-2.5"
                                        >
                                            <component
                                                :is="game.playable ? Link : 'div'"
                                                :href="game.playable && game.slug ? storyShow(game.slug).url : undefined"
                                                class="block outline-none"
                                                @click="game.playable && closeMood()"
                                            >
                                                <div
                                                    class="relative aspect-[192/287] w-full overflow-hidden rounded-xl border border-white/10 bg-[#1a1a1a] shadow-lg ring-1 ring-black/40 transition duration-300 group-hover:border-primary/40 group-hover:ring-primary/20"
                                                >
                                                    <img
                                                        :src="game.cover"
                                                        :alt="game.title"
                                                        class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                                    />
                                                    <div
                                                        class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-80"
                                                    />
                                                </div>
                                            </component>
                                            <div class="flex min-h-0 flex-col gap-1.5 px-0.5">
                                                <p class="truncate text-base font-semibold text-white">
                                                    {{ game.title }}
                                                </p>
                                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.8125rem] leading-snug text-[#5fd4e8]">
                                                    <template v-for="(theme, ti) in game.themes" :key="theme">
                                                        <span v-if="ti > 0" class="text-white/25">·</span>
                                                        <span>{{ theme }}</span>
                                                    </template>
                                                </p>
                                                <p class="line-clamp-2 text-[0.8125rem] leading-relaxed text-[#8a8a8a]">
                                                    {{ game.teaser }}
                                                </p>
                                                <template v-if="game.playable && game.slug">
                                                    <Link
                                                        :href="storyShow(game.slug).url"
                                                        class="mt-1 inline-flex h-9 w-full items-center justify-center rounded-lg bg-cta-fill text-sm font-semibold text-cta-text no-underline transition-colors hover:bg-cta-hover active:bg-cta-active"
                                                        @click="closeMood"
                                                    >
                                                        Play
                                                    </Link>
                                                </template>
                                                <div
                                                    v-else
                                                    class="mt-1 flex h-9 w-full items-center justify-center rounded-lg border border-white/15 bg-white/5 text-sm font-medium text-white/45"
                                                >
                                                    Coming soon
                                                </div>
                                            </div>
                                        </article>
                                    </div>
                                </div>

                                <Link
                                    :href="`${storiesIndex().url}?mood=${activeMoodConfig.id}`"
                                    class="mx-auto flex h-11 w-full max-w-md items-center justify-center rounded-xl border border-primary/35 bg-primary/10 text-sm font-semibold text-primary sm:hidden"
                                    @click="closeMood"
                                >
                                    Browse full library for this mood
                                </Link>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>

<style scoped>
.mood-scroll::-webkit-scrollbar {
    display: none;
}

.mood-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.mood-game-slider {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.mood-game-slider::-webkit-scrollbar {
    display: none;
}

.slider-arrow {
    width: 2.375rem;
    height: 2.375rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: background 0.2s, transform 0.15s;
}
.slider-arrow:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-50%) scale(1.04);
}
.slider-arrow-icon {
    font-size: 1.375rem;
    line-height: 1;
    color: white;
}

/* Figma mood tiles: default = calm tile + faint icon ring; hover = border + glow (6320-267) */
.mood-card {
    background-color: #000;
    border: 1px solid transparent;
    box-shadow: none;
    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease;
}

.mood-icon-orbit {
    transition: box-shadow 0.3s ease;
}

.mood-icon-orbit-bg {
    transition: opacity 0.3s ease;
}

.mood-card:not(:hover):not(.mood-card--active) .mood-icon-orbit-bg {
    opacity: 0.42;
}

.mood-card:hover .mood-icon-orbit-bg,
.mood-card.mood-card--active .mood-icon-orbit-bg {
    opacity: 1;
}

/* Idle: thin accent ring around icon; hover: restore Figma orbit glow */
.mood-card--heartfelt .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(201, 52, 52, 0.38);
}

.mood-card--heartfelt:hover .mood-icon-orbit,
.mood-card--heartfelt.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 7.8563rem 0px #792020,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-card--adventurous .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(236, 200, 99, 0.4);
}

.mood-card--adventurous:hover .mood-icon-orbit,
.mood-card--adventurous.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 6.9938rem 0px #7c5e0b,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-card--mysterious .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(98, 232, 219, 0.38);
}

.mood-card--mysterious:hover .mood-icon-orbit,
.mood-card--mysterious.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 8.8812rem 0px #248077,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-card--epic .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(88, 217, 161, 0.4);
}

.mood-card--epic:hover .mood-icon-orbit,
.mood-card--epic.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 5.9875rem 0px #257351,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-card--whimsical .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(169, 121, 194, 0.4);
}

.mood-card--whimsical:hover .mood-icon-orbit,
.mood-card--whimsical.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 7.3625rem 0px #7e5296,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-card:hover,
.mood-card.mood-card--active {
    box-shadow:
        0px 4px 5rem 0px rgba(0, 0, 0, 0.2),
        inset 0 0 3rem var(--mood-glow-inset),
        0 0 1.25rem var(--mood-glow-outer);
}

.mood-card-glow {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mood-card:hover .mood-card-glow,
.mood-card.mood-card--active .mood-card-glow {
    opacity: 1;
}

.mood-card--heartfelt {
    --mood-glow-inset: rgba(201, 52, 52, 0.14);
    --mood-glow-outer: rgba(201, 52, 52, 0.1);
}

.mood-card--heartfelt:hover,
.mood-card--heartfelt.mood-card--active {
    border-color: #c93434;
}

.mood-card-glow--heartfelt {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(201, 52, 52, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--adventurous {
    --mood-glow-inset: rgba(236, 200, 99, 0.14);
    --mood-glow-outer: rgba(236, 200, 99, 0.1);
}

.mood-card--adventurous:hover,
.mood-card--adventurous.mood-card--active {
    border-color: #ecc863;
}

.mood-card-glow--adventurous {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(236, 200, 99, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--mysterious {
    --mood-glow-inset: rgba(98, 232, 219, 0.14);
    --mood-glow-outer: rgba(98, 232, 219, 0.1);
}

.mood-card--mysterious:hover,
.mood-card--mysterious.mood-card--active {
    border-color: #62e8db;
}

.mood-card-glow--mysterious {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(98, 232, 219, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--epic {
    --mood-glow-inset: rgba(88, 217, 161, 0.14);
    --mood-glow-outer: rgba(88, 217, 161, 0.1);
}

.mood-card--epic:hover,
.mood-card--epic.mood-card--active {
    border-color: #58d9a1;
}

.mood-card-glow--epic {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(88, 217, 161, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--whimsical {
    --mood-glow-inset: rgba(169, 121, 194, 0.14);
    --mood-glow-outer: rgba(169, 121, 194, 0.1);
}

.mood-card--whimsical:hover,
.mood-card--whimsical.mood-card--active {
    border-color: #a979c2;
}

.mood-card-glow--whimsical {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(169, 121, 194, 0.22) 0%,
        transparent 62%
    );
}

.mood-overlay-enter-active,
.mood-overlay-leave-active {
    transition: opacity 0.28s ease;
}
.mood-overlay-enter-from,
.mood-overlay-leave-to {
    opacity: 0;
}

.mood-sheet-enter-active {
    transition:
        opacity 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.36s cubic-bezier(0.22, 1, 0.36, 1);
}
.mood-sheet-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.22s ease;
}
.mood-sheet-enter-from {
    opacity: 0;
    transform: translateY(1.5rem) scale(0.98);
}
.mood-sheet-leave-to {
    opacity: 0;
    transform: translateY(1rem);
}
@media (max-width: 39.9375rem) {
    .mood-sheet-enter-from {
        transform: translateY(100%);
    }
    .mood-sheet-leave-to {
        transform: translateY(40%);
    }
}
</style>
