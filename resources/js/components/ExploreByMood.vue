<script setup lang="ts">
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.jpg';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';
import heartImg from '@/assets/mood/Heart.svg';
import mountainsImg from '@/assets/mood/Mountains.svg';
import eyeImg from '@/assets/mood/Eye.svg';
import swordImg from '@/assets/mood/Sword.svg';
import spiralImg from '@/assets/mood/Spiral.svg';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
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
        teaser: 'A haunted memory diver must stop a sentient AI from overwriting human grief with synthetic perfection.',
    },
    alice: {
        id: 'alice',
        title: 'Alice In Wonderland',
        cover: aliceCover,
        playable: false,
        themes: ['Wonder', 'Identity', 'Logic'],
        teaser: 'Fall deeper into a world where nothing is as it seems and every choice rewrites the rules.',
    },
    nocturne: {
        id: 'nocturne',
        title: 'Nocturne',
        cover: nocturneCover,
        playable: false,
        themes: ['Mystery', 'Music', 'Sacrifice'],
        teaser: 'A jazz musician discovers the notes she plays can alter reality — but each performance costs a memory.',
    },
    jekyll: {
        id: 'jekyll',
        title: 'Jekyll & Hyde',
        cover: jekyllCover,
        playable: false,
        themes: ['Duality', 'Power', 'Morality'],
        teaser: 'Step into the fractured mind of a man at war with his own nature — and choose which side survives.',
    },
    oz: {
        id: 'oz',
        title: 'The Wonderful Wizard of Oz',
        cover: wizardOzCover,
        playable: false,
        themes: ['Courage', 'Home', 'Illusion'],
        teaser: 'Follow the yellow brick road — but every path leads somewhere different, and not all roads lead home.',
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
const moodSliderEl = ref<HTMLElement | null>(null);

const isPanelOpen = computed(() => openMoodId.value !== null);

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
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="not-italic">
                        <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                            Explore by mood
                        </h2>
                        <p class="text-base font-normal leading-[26px] text-[#b6b6b6]">
                            Choose the feeling you want to step into.
                        </p>
                    </div>
                    <Link
                        :href="storiesIndex().url"
                        class="inline-block shrink-0 whitespace-nowrap text-right text-[14px] font-normal leading-[33px] text-primary hover:underline"
                    >
                        View All{{ storyCount != null ? ` (${storyCount})` : '' }}
                    </Link>
                </div>

                <!-- Figma 5412:908: flex row, gap exactly 10px (not stretched 1fr columns) -->
                <div class="relative">
                    <div
                        class="mood-scroll flex w-full max-w-full flex-nowrap items-center gap-[10px] overflow-x-auto pb-1 md:w-max md:overflow-visible"
                    >
                    <!-- Heartfelt -->
                    <button
                        type="button"
                        class="mood-card mood-card--heartfelt relative z-0 h-[148px] w-[195px] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
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
                            class="absolute left-[59.5px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_125.7px_0px_#792020,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(121,13,13,0.16)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[22px] top-[22px] size-8">
                                <img :src="heartImg" alt="" width="32" height="32" class="block size-full max-w-none" />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <p
                            class="absolute left-[65.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#c93434]"
                        >
                            Heartfelt
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>

                    <!-- Adventurous -->
                    <button
                        type="button"
                        class="mood-card mood-card--adventurous relative z-0 h-[148px] w-[195px] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
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
                            class="absolute left-[60px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_111.9px_0px_#7c5e0b,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(247,179,8,0.07)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[22px] top-[22px] size-8">
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
                            class="absolute left-[51.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#ecc863]"
                        >
                            Adventurous
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>

                    <!-- Mysterious -->
                    <button
                        type="button"
                        class="mood-card mood-card--mysterious relative z-0 h-[148px] w-[195px] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
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
                            class="absolute left-[59.5px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_142.1px_0px_#248077,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(98,232,219,0.09)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[22px] top-[25px] size-8">
                                <img :src="eyeImg" alt="" width="32" height="32" class="block size-full max-w-none" />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <p
                            class="absolute left-[57.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#62e8db]"
                        >
                            Mysterious
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>

                    <!-- Epic -->
                    <button
                        type="button"
                        class="mood-card mood-card--epic relative z-0 h-[148px] w-[195px] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
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
                        <p
                            class="absolute left-[81.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#58d9a1]"
                        >
                            Epic
                        </p>
                        <div
                            class="absolute left-[59.5px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_95.8px_0px_#257351,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(88,217,161,0.15)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div
                                class="absolute left-[15px] top-[15px] flex size-[45.255px] items-center justify-center"
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
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>

                    <!-- Whimsical -->
                    <button
                        type="button"
                        class="mood-card mood-card--whimsical relative z-0 h-[148px] w-[195px] shrink-0 cursor-pointer overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]"
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
                        <p
                            class="absolute left-[60.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#a979c2]"
                        >
                            Whimsical
                        </p>
                        <div
                            class="absolute left-[59.5px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_117.8px_0px_#7e5296,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(169,121,194,0.07)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[22px] top-[24px] size-8">
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
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </button>
                    </div>

                    <!-- Edge fades — only needed when chips row scrolls (narrow viewports); hidden on md+ -->
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-12 bg-gradient-to-r from-black to-transparent md:hidden"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-black to-transparent md:hidden"
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
                            class="relative z-10 flex max-h-[min(92vh,880px)] w-full max-w-5xl flex-col overflow-hidden rounded-t-[20px] border border-white/10 bg-[#0c0c0c] shadow-[0_-8px_60px_rgba(0,0,0,0.65)] sm:rounded-2xl"
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
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/55">
                                        Explore by mood
                                    </p>
                                    <h3
                                        id="mood-panel-title"
                                        class="text-2xl font-bold uppercase leading-tight tracking-tight text-white sm:text-[28px]"
                                    >
                                        {{ activeMoodConfig.label }}
                                    </h3>
                                    <p class="max-w-xl text-[15px] leading-relaxed text-[#a8a8a8]">
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
                                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-12 bg-gradient-to-r from-[#0c0c0c] to-transparent sm:left-[-0.125rem] sm:w-14"
                                        aria-hidden="true"
                                    />
                                    <div
                                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-[#0c0c0c] to-transparent sm:right-[-0.125rem] sm:w-14"
                                        aria-hidden="true"
                                    />
                                    <button
                                        type="button"
                                        class="slider-arrow absolute -left-1 top-[140px] z-10 hidden -translate-y-1/2 sm:flex"
                                        aria-label="Scroll picks left"
                                        @click="scrollMoodGames(-228)"
                                    >
                                        <span class="slider-arrow-icon rotate-180">&#8250;</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="slider-arrow absolute -right-1 top-[140px] z-10 hidden -translate-y-1/2 sm:flex"
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
                                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] leading-snug text-[#5fd4e8]">
                                                    <template v-for="(theme, ti) in game.themes" :key="theme">
                                                        <span v-if="ti > 0" class="text-white/25">·</span>
                                                        <span>{{ theme }}</span>
                                                    </template>
                                                </p>
                                                <p class="line-clamp-2 text-[13px] leading-relaxed text-[#8a8a8a]">
                                                    {{ game.teaser }}
                                                </p>
                                                <template v-if="game.playable && game.slug">
                                                    <Link
                                                        :href="storyShow(game.slug).url"
                                                        class="mt-1 inline-flex h-9 w-full items-center justify-center rounded-lg border border-primary bg-primary text-sm font-semibold text-black no-underline transition hover:brightness-110"
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
    width: 38px;
    height: 38px;
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
    font-size: 22px;
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
        0px 0px 125.7px 0px #792020,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card--adventurous .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(236, 200, 99, 0.4);
}

.mood-card--adventurous:hover .mood-icon-orbit,
.mood-card--adventurous.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 111.9px 0px #7c5e0b,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card--mysterious .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(98, 232, 219, 0.38);
}

.mood-card--mysterious:hover .mood-icon-orbit,
.mood-card--mysterious.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 142.1px 0px #248077,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card--epic .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(88, 217, 161, 0.4);
}

.mood-card--epic:hover .mood-icon-orbit,
.mood-card--epic.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 95.8px 0px #257351,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card--whimsical .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(169, 121, 194, 0.4);
}

.mood-card--whimsical:hover .mood-icon-orbit,
.mood-card--whimsical.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 117.8px 0px #7e5296,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card:hover,
.mood-card.mood-card--active {
    box-shadow:
        0px 4px 80px 0px rgba(0, 0, 0, 0.2),
        inset 0 0 48px var(--mood-glow-inset),
        0 0 20px var(--mood-glow-outer);
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
    transform: translateY(24px) scale(0.98);
}
.mood-sheet-leave-to {
    opacity: 0;
    transform: translateY(16px);
}
@media (max-width: 639px) {
    .mood-sheet-enter-from {
        transform: translateY(100%);
    }
    .mood-sheet-leave-to {
        transform: translateY(40%);
    }
}
</style>
