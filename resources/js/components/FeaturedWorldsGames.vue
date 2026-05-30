<script setup lang="ts">
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.jpg';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

// ── Slider scroll ─────────────────────────────────────────────────────────────
const sliderEl = ref<HTMLElement | null>(null);
const scrollSlider = (delta: number) => sliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });

// ── Hover / popup state ───────────────────────────────────────────────────────
const hoveredId = ref<string | null>(null);
const popupPos = ref<{ left: number; top: number } | null>(null);

// The relative div wrapping the slider — popup is absolute inside it.
const sliderWrapperEl = ref<HTMLElement | null>(null);

// Per-card element refs collected in a plain object (not reactive, DOM-only)
const cardEls: Record<string, HTMLElement | null> = {};

// Prevent flicker when cursor moves from card → popup extension area
let _onCard = false;
let _onPopup = false;
let _hideTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleHide() {
    if (_hideTimer) clearTimeout(_hideTimer);
    _hideTimer = setTimeout(() => {
        if (!_onCard && !_onPopup) {
            hoveredId.value = null;
            popupPos.value = null;
        }
    }, 120);
}

function onCardEnter(game: FeaturedGame) {
    _onCard = true;
    if (_hideTimer) clearTimeout(_hideTimer);

    hoveredId.value = game.id;

    const cardEl = cardEls[game.id];
    const wrapper = sliderWrapperEl.value;
    if (!cardEl || !wrapper) return;

    const cr = cardEl.getBoundingClientRect();
    const wr = wrapper.getBoundingClientRect();
    popupPos.value = { left: cr.left - wr.left, top: cr.top - wr.top };
}

function onCardLeave() {
    _onCard = false;
    scheduleHide();
}

function onPopupEnter() {
    _onPopup = true;
    if (_hideTimer) clearTimeout(_hideTimer);
}

function onPopupLeave() {
    _onPopup = false;
    scheduleHide();
}

// Clamp popup so it never bleeds past the right edge of the wrapper
const popupStyle = computed(() => {
    if (!popupPos.value) return {};
    const POPUP_W = 282; // 262 inner + 10*2 padding
    const WRAPPER_W = 1018; // `.container-content` max width / Figma content frame
    let left = popupPos.value.left;
    if (left + POPUP_W > WRAPPER_W) left = WRAPPER_W - POPUP_W;
    if (left < 0) left = 0;
    return { left: `${left}px`, top: `${popupPos.value.top}px` };
});

const hoveredGame = computed(() => games.find((g) => g.id === hoveredId.value) ?? null);
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">

                <!-- Section header -->
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-[550px] min-w-0">
                        <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                            Featured Worlds
                        </h2>
                        <p class="text-[16px] font-normal leading-[26px] text-[#b6b6b6]">
                            Curated story worlds built for choice, consequence, and return.
                        </p>
                    </div>
                    <Link
                        :href="storiesIndex().url"
                        class="inline-block w-[98px] shrink-0 whitespace-nowrap text-right text-[14px] font-normal leading-[33px] text-primary hover:underline"
                    >
                        View All ({{ storyCount }})
                    </Link>
                </div>

                <!-- Slider wrapper — popup is absolute inside here, safe from overflow-clip -->
                <div ref="sliderWrapperEl" class="relative">

                    <!-- Left arrow -->
                    <button
                        type="button"
                        class="slider-arrow absolute -left-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-214)"
                    >
                        <span class="slider-arrow-icon rotate-180">&#8250;</span>
                    </button>

                    <!-- Scrollable card row -->
                    <div
                        ref="sliderEl"
                        class="story-slider flex gap-[10px] overflow-x-auto pb-2 md:ml-[17px]"
                    >
                        <div
                            v-for="game in games"
                            :key="game.id"
                            :ref="(el) => { if (el) cardEls[game.id] = el as HTMLElement; }"
                            class="shrink-0"
                            @mouseenter="onCardEnter(game)"
                            @mouseleave="onCardLeave"
                        >
                            <div
                                class="featured-game-card relative flex flex-col gap-2 rounded-[8px] border border-[#373737] bg-[#262626] p-[6px] transition-opacity duration-200"
                                :class="hoveredId && hoveredId !== game.id ? 'opacity-[0.3]' : 'opacity-100'"
                            >
                                <!-- Cover -->
                                <component
                                    :is="game.playable ? Link : 'div'"
                                    :href="game.playable && game.slug ? storyShow(game.slug).url : undefined"
                                    class="block outline-none"
                                >
                                    <div class="relative h-[287.438px] w-[192px] overflow-hidden rounded-[5px] border border-white/5">
                                        <img
                                            :src="game.cover"
                                            :alt="game.title"
                                            class="absolute inset-0 size-full max-w-none object-cover"
                                        />
                                    </div>
                                </component>

                                <!-- Title -->
                                <div class="flex h-5 items-center px-px">
                                    <p class="w-[192px] truncate text-base font-semibold leading-normal text-white">
                                        {{ game.title }}
                                    </p>
                                </div>

                                <!-- Button -->
                                <template v-if="game.playable && game.slug">
                                    <div class="flex h-9 w-[192px] items-center justify-center rounded-md border border-[#7e7e7e]">
                                        <Link
                                            :href="storyShow(game.slug).url"
                                            class="flex h-full w-full items-center justify-center rounded-[6px] border border-primary bg-primary text-lg font-medium text-black no-underline"
                                        >
                                            Play
                                        </Link>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="flex h-9 w-[192px] items-center justify-center rounded-[6px] border border-[#4d4d4d] bg-[#3f3f3f] text-lg font-medium text-primary">
                                        Coming soon
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Right arrow -->
                    <button
                        type="button"
                        class="slider-arrow absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(214)"
                    >
                        <span class="slider-arrow-icon">&#8250;</span>
                    </button>

                    <!-- ── Expanded hover popup ────────────────────────────────── -->
                    <Transition name="card-popup">
                        <div
                            v-if="hoveredId && hoveredGame && popupPos"
                            class="absolute z-30 flex w-[282px] flex-col gap-[10px] rounded-[8px] border border-primary bg-[#262626] p-[10px] shadow-[0_0_36.6px_rgba(0,198,222,0.4)]"
                            :style="popupStyle"
                            @mouseenter="onPopupEnter"
                            @mouseleave="onPopupLeave"
                        >
                            <!-- Larger cover -->
                            <div class="relative h-[239px] w-full overflow-hidden rounded-[6px]">
                                <img
                                    :src="hoveredGame.cover"
                                    :alt="hoveredGame.title"
                                    class="absolute inset-0 size-full max-w-none object-cover"
                                />
                            </div>

                            <!-- Info block -->
                            <div class="flex flex-col gap-[8px]">
                                <!-- Title + themes -->
                                <div class="flex flex-col gap-[4px]">
                                    <p class="text-[20px] font-medium leading-normal text-white">
                                        {{ hoveredGame.title }}
                                    </p>
                                    <p class="text-[14px] leading-normal text-white">
                                        {{ hoveredGame.themes.join(' | ') }}
                                    </p>
                                </div>

                                <!-- Teaser -->
                                <p class="line-clamp-3 text-[14px] leading-[1.5] text-[#8f8f8f]">
                                    {{ hoveredGame.teaser }}
                                </p>

                                <!-- Branches explored (playable only) -->
                                <p v-if="hoveredGame.branches" class="text-[14px] text-[#ffbe58]">
                                    {{ hoveredGame.branches }} Branches explored
                                </p>
                            </div>

                            <!-- CTA button -->
                            <template v-if="hoveredGame.playable && hoveredGame.slug">
                                <div class="flex h-9 w-full items-center justify-center rounded-md border border-[#7e7e7e]">
                                    <Link
                                        :href="storyShow(hoveredGame.slug).url"
                                        class="flex h-full w-full items-center justify-center rounded-[6px] border border-primary bg-primary text-lg font-medium text-black no-underline"
                                    >
                                        Play
                                    </Link>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex h-9 w-full items-center justify-center rounded-[6px] border border-[#4d4d4d] bg-[#3f3f3f] text-lg font-medium text-primary">
                                    Coming soon
                                </div>
                            </template>
                        </div>
                    </Transition>

                </div>
            </div>
        </div>
    </section>
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

/* Popup fade-in / fade-out */
.card-popup-enter-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.card-popup-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}
.card-popup-enter-from,
.card-popup-leave-to {
    opacity: 0;
    transform: translateY(6px) scale(0.97);
}
</style>
