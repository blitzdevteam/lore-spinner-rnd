<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import banner1 from '@/assets/newStories/New stories 1- 2x.jpg';
import banner2 from '@/assets/newStories/New stories 2 - 2x .jpg';
import banner3 from '@/assets/newStories/New stories 3 - 2x.jpg';
import banner1Hover from '@/assets/newStories/s1-hover.jpg';
import banner2Hover from '@/assets/newStories/s2-hover.jpg';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineProps<{
    storyCount: number;
}>();

interface NewStory {
    id: string;
    title: string;
    cover: string;
    /** Optional alternate cover shown while the row card (or popup) is hovered */
    coverHover?: string;
    category: string;
    rating: string;
    playable: boolean;
    slug?: string;
    themes: string[];
    teaser: string;
    branches: string | null;
}

const stories: NewStory[] = [
    {
        id: 'sherlock',
        title: 'Sherlock Holmes',
        cover: banner1,
        coverHover: banner1Hover,
        category: 'Mystery',
        rating: 'Teen',
        playable: false,
        themes: ['Destiny', 'Deduction', 'Betrayal'],
        teaser: 'When London\'s most famous detective faces his most personal case, the truth may cost him everything — and everyone he trusts.',
        branches: null,
    },
    {
        id: 'Alice’s Adventures In Wonderland',
        title: 'Alice’s Adventures In Wonderland',
        cover: banner2,
        coverHover: banner2Hover,
        category: 'Science Fiction',
        rating: 'Mature',
        playable: false,
        themes: ['Distiny', 'Courage', 'Control'],
        teaser: 'In a city where every thought is monitored, one man discovers a truth the state will kill to suppress.',
        branches: null,
    },
    {
        id: 'Pride & Prejudice',
        title: 'Pride & Prejudice',
        cover: banner3,
        category: 'Historical Drama',
        rating: 'Teen',
        playable: false,
        themes: ['Love', 'Duty', 'Society'],
        teaser: 'Two sisters navigate love, loss, and society\'s expectations — where the heart and reason are rarely in agreement.',
        branches: null,
    },
];

// ── Slider scroll ─────────────────────────────────────────────────────────────
const sliderEl = ref<HTMLElement | null>(null);
const scrollSlider = (delta: number) => sliderEl.value?.scrollBy({ left: delta, behavior: 'smooth' });

// ── Shadow visibility ─────────────────────────────────────────────────────────
const SHADOW_THRESHOLD = 8;
const leftShadowVisible = ref(false);
const rightShadowVisible = ref(true);

function updateShadows() {
    const el = sliderEl.value;
    if (!el) return;
    leftShadowVisible.value = el.scrollLeft > SHADOW_THRESHOLD;
    rightShadowVisible.value = el.scrollLeft + el.clientWidth < el.scrollWidth - SHADOW_THRESHOLD;
}

onMounted(() => {
    const el = sliderEl.value;
    if (!el) return;
    updateShadows();
    el.addEventListener('scroll', updateShadows, { passive: true });
    window.addEventListener('resize', updateShadows, { passive: true });
});

onUnmounted(() => {
    const el = sliderEl.value;
    if (el) el.removeEventListener('scroll', updateShadows);
    window.removeEventListener('resize', updateShadows);
});

// ── Hover / popup state ───────────────────────────────────────────────────────
const hoveredId = ref<string | null>(null);
const popupPos = ref<{ left: number; top: number } | null>(null);

const sliderWrapperEl = ref<HTMLElement | null>(null);
const cardEls: Record<string, HTMLElement | null> = {};

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

function onCardEnter(story: NewStory) {
    _onCard = true;
    if (_hideTimer) clearTimeout(_hideTimer);
    hoveredId.value = story.id;

    const cardEl = cardEls[story.id];
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

// Clamp so popup never bleeds past the right edge of the 1018px wrapper
const popupStyle = computed(() => {
    if (!popupPos.value) return {};
    const POPUP_W = 502;
    const WRAPPER_W = 1018; // `.container-content` max width / Figma content frame
    let left = popupPos.value.left;
    if (left + POPUP_W > WRAPPER_W) left = WRAPPER_W - POPUP_W;
    if (left < 0) left = 0;
    return { left: `${left}px`, top: `${popupPos.value.top}px` };
});

const hoveredStory = computed(() => stories.find((s) => s.id === hoveredId.value) ?? null);

function coverForPopup(story: NewStory): string {
    return story.coverHover ?? story.cover;
}
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">

                <SectionHeader
                    title="New Stories"
                    subtitle="New branches, hidden paths, and fresh story worlds."
                    :href="storiesIndex().url"
                    :count="storyCount"
                />

                <!-- Slider wrapper (popup is absolute inside here) -->
                <div ref="sliderWrapperEl" class="relative">
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-12 bg-gradient-to-r from-black to-transparent transition-opacity duration-300 md:w-16"
                        :class="leftShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-black to-transparent transition-opacity duration-300 md:w-16"
                        :class="rightShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />

                    <!-- Left arrow -->
                    <button
                        type="button"
                        class="slider-arrow slider-arrow-banner absolute -left-4 z-10 hidden items-center justify-center md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-460)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="rotate-180">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <!-- Scrollable card row -->
                    <div
                        ref="sliderEl"
                        class="story-slider flex gap-[0.625rem] overflow-x-auto pb-2 md:ml-[1.0625rem]"
                    >
                        <div
                            v-for="story in stories"
                            :key="story.id"
                            :ref="(el) => { if (el) cardEls[story.id] = el as HTMLElement; }"
                            class="new-banner-card shrink-0"
                            @mouseenter="onCardEnter(story)"
                            @mouseleave="onCardLeave"
                        >
                            <div class="flex w-[28.125rem] flex-col gap-[0.625rem]">
                                <!-- Banner image -->
                                <div class="rounded-[0.5rem] border border-[#373737] bg-[#262626] p-1">
                                    <div class="relative h-[16.375rem] w-full overflow-hidden rounded-[0.5rem]">
                                        <img
                                            :src="story.cover"
                                            :alt="story.title"
                                            class="absolute inset-0 h-full w-full object-cover transition-all duration-300 ease-out"
                                            :class="[
                                                hoveredId === story.id ? 'scale-[1.03]' : 'scale-100',
                                                story.coverHover && hoveredId === story.id ? 'opacity-0' : 'opacity-100',
                                            ]"
                                        />
                                        <img
                                            v-if="story.coverHover"
                                            :src="story.coverHover"
                                            :alt="`${story.title} (alternate)`"
                                            class="absolute inset-0 h-full w-full object-cover transition-all duration-300 ease-out"
                                            :class="[
                                                hoveredId === story.id ? 'scale-[1.03]' : 'scale-100',
                                                hoveredId === story.id ? 'opacity-100' : 'opacity-0',
                                            ]"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </div>

                                <!-- Title + meta tags -->
                                <div class="flex w-[26.875rem] flex-col gap-[3px] px-px">
                                    <p class="text-[1.125rem] font-semibold leading-normal text-white">
                                        {{ story.title }}
                                    </p>
                                    <p class="text-[0.9375rem] leading-normal text-[#8f8f8f]">
                                        {{ story.category }} | {{ story.rating }} | {{ story.playable ? 'Published' : 'Coming soon' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right arrow -->
                    <button
                        type="button"
                        class="slider-arrow slider-arrow-banner absolute -right-4 z-10 hidden items-center justify-center md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(460)"
                    >
                        <svg viewBox="0 0 8 14" width="8" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M1 1L7 7L1 13" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <!-- ── Expanded hover popup (horizontal: image left + content right) -->
                    <Transition name="story-popup">
                        <div
                            v-if="hoveredId && hoveredStory && popupPos"
                            class="absolute z-30 flex w-[31.375rem] flex-row rounded-[0.5rem] border border-primary bg-[#262626] px-[0.75rem] py-[0.375rem] shadow-[0_0_18.3px_rgba(111,175,186,0.4)]"
                            :style="popupStyle"
                            @mouseenter="onPopupEnter"
                            @mouseleave="onPopupLeave"
                        >
                            <!-- Left: portrait cover (248×298) -->
                            <div class="h-[18.625rem] w-[15.5rem] shrink-0 overflow-hidden rounded-[0.3125rem]">
                                <img
                                    :src="coverForPopup(hoveredStory)"
                                    :alt="hoveredStory.title"
                                    class="h-full w-full object-cover"
                                />
                            </div>

                            <!-- Right: content panel (217px) -->
                            <div class="ml-[0.8125rem] flex w-[13.5625rem] flex-col gap-[0.625rem] py-[0.25rem]">
                                <!-- Title + themes -->
                                <div class="flex flex-col gap-[0.25rem]">
                                    <p class="text-[1.25rem] font-medium leading-normal text-white">
                                        {{ hoveredStory.title }}
                                    </p>
                                    <p class="text-[0.875rem] leading-normal text-white">
                                        {{ hoveredStory.themes.join(' | ') }}
                                    </p>
                                </div>

                                <!-- Teaser -->
                                <p class="line-clamp-4 text-[0.875rem] leading-[1.55] text-[#8f8f8f]">
                                    {{ hoveredStory.teaser }}
                                </p>

                                <!-- Branches explored -->
                                <p v-if="hoveredStory.branches" class="text-[0.875rem] text-[#ffbe58]">
                                    {{ hoveredStory.branches }} Branches explored
                                </p>

                                <!-- CTA button pushed to bottom -->
                                <div class="mt-auto">
                                    <template v-if="hoveredStory.playable && hoveredStory.slug">
                                        <Link
                                            :href="storyShow(hoveredStory.slug).url"
                                            class="flex h-9 w-full items-center justify-center rounded-[0.375rem] bg-cta-fill text-[1.125rem] font-medium text-cta-text no-underline transition-colors hover:bg-cta-hover active:bg-cta-active"
                                        >
                                            Play
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <div class="flex h-9 items-center justify-center rounded-[0.375rem] border border-[#4d4d4d] bg-[#3f3f3f] text-[1.125rem] font-medium text-[#8e8e8e]">
                                            Coming soon
                                        </div>
                                    </template>
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
/* Vertically centered on banner posters only — matches `.p-1` wrapper + inner `h-[16.375rem]` */
.slider-arrow-banner {
    top: calc((16.375rem + 0.5rem + 2px) / 2); /* inner image + vertical padding + 1px border top/bottom */
    transform: translateY(-50%);
}
.slider-arrow:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* Popup transition */
.story-popup-enter-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.story-popup-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}
.story-popup-enter-from,
.story-popup-leave-to {
    opacity: 0;
    transform: translateY(0.5rem) scale(0.98);
}
</style>
