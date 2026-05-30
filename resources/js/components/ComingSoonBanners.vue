<script setup lang="ts">
import cover1 from '@/assets/commingSoon/Coming soon 1- 2x.jpg';
import cover2 from '@/assets/commingSoon/Coming soon 2 - 2x.png';
import cover3 from '@/assets/commingSoon/Coming soon 3 - 2x.jpg';
import cover4 from '@/assets/commingSoon/Coming soon 4 - 2x.jpg';
import cover5 from '@/assets/commingSoon/Coming soon 5 - 2x.png';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

defineProps<{
    storyCount: number;
}>();

interface ComingSoonCard {
    id: string;
    title: string;
    cover: string;
    themes: string[];
}

const cards: ComingSoonCard[] = [
    {
        id: 'Romeo&Juliet',
        title: "Romeo & Juliet",
        cover: cover1,
        themes: ['Destiny', 'Courage', 'Control'],
    },
    {
        id: 'hansel',
        title: 'Hansel & Gretel',
        cover: cover2,
        themes: ['Survival', 'Fear', 'Family'],
    },
    {
        id: 'pride',
        title: 'Pride and Prejudice',
        cover: cover3,
        themes: ['Love', 'Duty', 'Society'],
    },
    {
        id: 'frankenstein',
        title: 'Frankenstein',
        cover: cover4,
        themes: ['Creation', 'Isolation', 'Ambition'],
    },
    {
        id: 'leagues',
        title: '20,000 Leagues Under the Sea',
        cover: cover5,
        themes: ['Discovery', 'Wonder', 'Peril'],
    },
];

const sliderEl = ref<HTMLElement | null>(null);
const scrollSlider = (delta: number) => sliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });

const hoveredId = ref<string | null>(null);
const popupPos = ref<{ left: number; top: number } | null>(null);

const sliderWrapperEl = ref<HTMLElement | null>(null);
const cardEls: Record<string, HTMLElement | null> = {};

/** Expanded panel: 262px content + 10px padding each side (Figma 5412:1520) */
const POPUP_W = 282;
const POPUP_H = 444;
const WRAPPER_W = 1018;

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

function updatePopupPosition(cardId: string) {
    const cardEl = cardEls[cardId];
    const wrapper = sliderWrapperEl.value;
    if (!cardEl || !wrapper) return;

    const cr = cardEl.getBoundingClientRect();
    const wr = wrapper.getBoundingClientRect();

    const cardCenterX = cr.left + cr.width / 2 - wr.left;
    let left = cardCenterX - POPUP_W / 2;
    if (left + POPUP_W > WRAPPER_W) left = WRAPPER_W - POPUP_W;
    if (left < 0) left = 0;

    const cardMiddleY = cr.top + cr.height / 2 - wr.top;
    let top = cardMiddleY - POPUP_H / 2;
    const wrapperH = wrapper.clientHeight;
    if (top + POPUP_H > wrapperH) top = wrapperH - POPUP_H;
    if (top < 0) top = 0;

    popupPos.value = { left, top };
}

function onCardEnter(card: ComingSoonCard) {
    _onCard = true;
    if (_hideTimer) clearTimeout(_hideTimer);
    hoveredId.value = card.id;

    updatePopupPosition(card.id);
    void nextTick(() => {
        updatePopupPosition(card.id);
    });
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

const popupStyle = computed(() => {
    if (!popupPos.value) return {};
    return { left: `${popupPos.value.left}px`, top: `${popupPos.value.top}px` };
});

const hoveredCard = computed(() => cards.find((c) => c.id === hoveredId.value) ?? null);
</script>

<template>
    <!-- Tighter bottom than .home-section-y so spacing to FAQ matches Figma; hover reserves extra space via wrapper padding. -->
    <section class="pt-14 pb-0 md:pt-[60px]">
        <div class="container">
            <div class="container-content home-section-gap">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-[550px] min-w-0">
                        <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                            Coming Soon
                        </h2>
                        <p class="text-[16px] font-normal leading-[26px] text-[#b6b6b6]">
                            New worlds are coming soon.
                        </p>
                    </div>
                    <Link
                        :href="storiesIndex().url"
                        class="inline-block w-[98px] shrink-0 whitespace-nowrap text-right text-[14px] font-normal leading-[33px] text-primary hover:underline"
                    >
                        View All ({{ storyCount }})
                    </Link>
                </div>

                <!-- Popup is 444px tall; reserve bottom space only while hovered so the default gap to FAQ stays tight like Figma. -->
                <div
                    ref="sliderWrapperEl"
                    class="relative transition-[padding-bottom] duration-200 ease-out"
                    :class="hoveredId ? 'pb-[140px] md:pb-[148px]' : 'pb-2'"
                >
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-12 bg-gradient-to-r from-black to-transparent md:w-16"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-black to-transparent md:w-16"
                        aria-hidden="true"
                    />

                    <!-- Dim rest of row while hovering (Figma 5412:1519) -->
                    <div
                        v-show="hoveredId"
                        class="pointer-events-none absolute inset-0 z-20 bg-black/60"
                        aria-hidden="true"
                    />

                    <button
                        type="button"
                        class="slider-arrow absolute -left-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-214)"
                    >
                        <span class="slider-arrow-icon rotate-180">&#8250;</span>
                    </button>

                    <div
                        ref="sliderEl"
                        class="story-slider relative z-10 flex gap-[10px] overflow-x-auto pb-2 md:ml-[17px]"
                    >
                        <div
                            v-for="card in cards"
                            :key="card.id"
                            :ref="(el) => { if (el) cardEls[card.id] = el as HTMLElement; }"
                            class="shrink-0"
                            @mouseenter="onCardEnter(card)"
                            @mouseleave="onCardLeave"
                        >
                            <div
                                class="flex flex-col rounded-[8px] border border-solid border-[#373737] bg-[#262626] p-[6px]"
                            >
                                <div class="flex w-[192px] flex-col items-center">
                                    <div class="relative h-[287px] w-full overflow-hidden rounded-[5px]">
                                        <img
                                            :src="card.cover"
                                            :alt="card.title"
                                            class="pointer-events-none h-full w-full object-cover select-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="slider-arrow absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(214)"
                    >
                        <span class="slider-arrow-icon">&#8250;</span>
                    </button>

                    <Transition name="coming-soon-popup">
                        <div
                            v-if="hoveredId && hoveredCard && popupPos"
                            class="absolute z-30 flex h-[444px] w-[282px] flex-col rounded-[8px] border border-primary bg-[#262626] p-[10px] shadow-[0_0_36.6px_rgba(0,198,222,0.4)]"
                            :style="popupStyle"
                            @mouseenter="onPopupEnter"
                            @mouseleave="onPopupLeave"
                        >
                            <div class="flex w-[262px] flex-col gap-[10px]">
                                <div class="relative h-[363px] w-full shrink-0 overflow-hidden rounded-[5px]">
                                    <img
                                        :src="hoveredCard.cover"
                                        :alt="hoveredCard.title"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <div class="flex flex-col gap-[4px]">
                                    <p class="text-[20px] font-medium leading-normal text-white">
                                        {{ hoveredCard.title }}
                                    </p>
                                    <p class="text-[14px] leading-normal text-white">
                                        {{ hoveredCard.themes.join(' | ') }}
                                    </p>
                                </div>
                            </div>
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

.coming-soon-popup-enter-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease,
        box-shadow 0.18s ease;
}
.coming-soon-popup-leave-active {
    transition:
        opacity 0.14s ease,
        transform 0.14s ease;
}
.coming-soon-popup-enter-from,
.coming-soon-popup-leave-to {
    opacity: 0;
    transform: translateY(6px) scale(0.98);
}
</style>
