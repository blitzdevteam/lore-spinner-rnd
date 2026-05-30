<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import cover1 from '@/assets/commingSoon/Coming soon 1- 2x.jpg';
import cover2 from '@/assets/commingSoon/Coming soon 2 - 2x.png';
import cover3 from '@/assets/commingSoon/Coming soon 3 - 2x.jpg';
import cover4 from '@/assets/commingSoon/Coming soon 4 - 2x.jpg';
import cover5 from '@/assets/commingSoon/Coming soon 5 - 2x.png';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
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

const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(sliderEl);

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
    <section class="pt-10 pb-0 md:pt-[3.75rem]">
        <div class="container">
            <div class="container-content home-section-gap">
                <SectionHeader
                    title="Coming Soon"
                    subtitle="New worlds are coming soon."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <!-- Popup is 444px tall; reserve bottom space only while hovered so the default gap to FAQ stays tight like Figma. -->
                <div
                    ref="sliderWrapperEl"
                    class="relative transition-[padding-bottom] duration-200 ease-out"
                    :class="hoveredId ? 'pb-[8.75rem] md:pb-[9.25rem]' : 'pb-2'"
                >
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[15] w-6 bg-gradient-to-r from-black/70 to-transparent transition-opacity duration-300 md:w-8"
                        :class="leftShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[15] w-12 bg-gradient-to-l from-black to-transparent transition-opacity duration-300 md:w-16"
                        :class="rightShadowVisible ? 'opacity-100' : 'opacity-0'"
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
                        class="slider-arrow absolute -left-4 top-1/2 z-[16] hidden -translate-y-1/2 items-center justify-center md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-214)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div
                        ref="sliderEl"
                        class="story-slider relative z-10 flex gap-[0.625rem] overflow-x-auto pb-2 md:ml-[1.0625rem]"
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
                                class="flex flex-col rounded-[0.5rem] border border-solid border-[#373737] bg-[#262626] p-[0.375rem]"
                            >
                                <div class="flex w-[12rem] flex-col items-center">
                                    <div class="relative h-[17.9375rem] w-full overflow-hidden rounded-[0.3125rem]">
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
                        class="slider-arrow absolute -right-4 top-1/2 z-[16] hidden -translate-y-1/2 items-center justify-center md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(214)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <Transition name="coming-soon-popup">
                        <div
                            v-if="hoveredId && hoveredCard && popupPos"
                            class="absolute z-30 flex h-[27.75rem] w-[17.625rem] flex-col rounded-[0.5rem] border border-primary bg-[#262626] p-[0.625rem] shadow-[0_0_36.6px_rgba(111,175,186,0.4)]"
                            :style="popupStyle"
                            @mouseenter="onPopupEnter"
                            @mouseleave="onPopupLeave"
                        >
                            <div class="flex w-[16.375rem] flex-col gap-[0.625rem]">
                                <div class="relative h-[22.6875rem] w-full shrink-0 overflow-hidden rounded-[0.3125rem]">
                                    <img
                                        :src="hoveredCard.cover"
                                        :alt="hoveredCard.title"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <div class="flex flex-col gap-[0.25rem]">
                                    <p class="text-[1.25rem] font-medium leading-normal text-white">
                                        {{ hoveredCard.title }}
                                    </p>
                                    <p class="text-[0.875rem] leading-normal text-white">
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
    width: 2.125rem;
    height: 2.125rem;
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
    transform: translateY(0.375rem) scale(0.98);
}
</style>
