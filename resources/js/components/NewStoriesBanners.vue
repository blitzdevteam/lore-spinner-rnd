<script setup lang="ts">
import banner1 from '@/assets/newStories/New stories 1- 2x.png';
import banner2 from '@/assets/newStories/New stories 2 - 2x .png';
import banner3 from '@/assets/newStories/New stories 3 - 2x.png';
import { index as storiesIndex, show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{
    storyCount: number;
}>();

interface NewStory {
    id: string;
    title: string;
    cover: string;
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
        category: 'Mystery',
        rating: 'Teen',
        playable: false,
        themes: ['Destiny', 'Deduction', 'Betrayal'],
        teaser: 'When London\'s most famous detective faces his most personal case, the truth may cost him everything — and everyone he trusts.',
        branches: null,
    },
    {
        id: 'nineteen84',
        title: '1984',
        cover: banner2,
        category: 'Science Fiction',
        rating: 'Mature',
        playable: false,
        themes: ['Control', 'Rebellion', 'Identity'],
        teaser: 'In a city where every thought is monitored, one man discovers a truth the state will kill to suppress.',
        branches: null,
    },
    {
        id: 'sensibility',
        title: 'Sense And Sensibility',
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
    const WRAPPER_W = 1018;
    let left = popupPos.value.left;
    if (left + POPUP_W > WRAPPER_W) left = WRAPPER_W - POPUP_W;
    if (left < 0) left = 0;
    return { left: `${left}px`, top: `${popupPos.value.top}px` };
});

const hoveredStory = computed(() => stories.find((s) => s.id === hoveredId.value) ?? null);
</script>

<template>
    <section class="py-14 md:py-[60px]">
        <div class="container">
            <div class="mx-auto flex w-full max-w-[1018px] flex-col gap-[14px]">

                <!-- Section header -->
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-[550px] min-w-0">
                        <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                            New Stories
                        </h2>
                        <p class="text-[16px] font-normal leading-[26px] text-[#b6b6b6]">
                            New branches, hidden paths, and fresh story worlds.
                        </p>
                    </div>
                    <Link
                        :href="storiesIndex().url"
                        class="inline-block w-[98px] shrink-0 whitespace-nowrap text-right text-[14px] font-normal leading-[33px] text-primary hover:underline"
                    >
                        View All ({{ storyCount }})
                    </Link>
                </div>

                <!-- Slider wrapper (popup is absolute inside here) -->
                <div ref="sliderWrapperEl" class="relative">

                    <!-- Left arrow -->
                    <button
                        type="button"
                        class="slider-arrow absolute -left-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                        aria-label="Scroll left"
                        @click="scrollSlider(-460)"
                    >
                        <span class="slider-arrow-icon rotate-180">&#8250;</span>
                    </button>

                    <!-- Scrollable card row -->
                    <div
                        ref="sliderEl"
                        class="story-slider flex gap-[10px] overflow-x-auto pb-2 md:ml-[17px]"
                    >
                        <div
                            v-for="story in stories"
                            :key="story.id"
                            :ref="(el) => { if (el) cardEls[story.id] = el as HTMLElement; }"
                            class="new-banner-card shrink-0"
                            @mouseenter="onCardEnter(story)"
                            @mouseleave="onCardLeave"
                        >
                            <div
                                class="flex w-[450px] flex-col gap-[10px] transition-opacity duration-200"
                                :class="hoveredId && hoveredId !== story.id ? 'opacity-[0.3]' : 'opacity-100'"
                            >
                                <!-- Banner image -->
                                <div class="rounded-[8px] border border-[#373737] bg-[#262626] p-1">
                                    <div class="relative h-[262px] w-full overflow-hidden rounded-[8px]">
                                        <img
                                            :src="story.cover"
                                            :alt="story.title"
                                            class="h-full w-full object-cover transition-transform duration-300 ease-out"
                                            :class="hoveredId === story.id ? 'scale-[1.03]' : 'scale-100'"
                                        />
                                        <!-- Left-edge gradient fade (matches existing style) -->
                                        <div class="absolute inset-y-0 left-0 w-20 bg-gradient-to-r from-[#262626] to-transparent" />
                                    </div>
                                </div>

                                <!-- Title + meta tags -->
                                <div class="flex w-[430px] flex-col gap-[3px] px-px">
                                    <p class="text-[18px] font-semibold leading-normal text-white">
                                        {{ story.title }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-[4px] text-[15px] leading-normal text-[#8f8f8f]">
                                        <span class="size-[6px] shrink-0 rounded-full bg-primary" />
                                        <span>{{ story.category }}</span>
                                        <span class="size-[6px] shrink-0 rounded-full bg-primary" />
                                        <span>{{ story.rating }}</span>
                                        <span class="size-[6px] shrink-0 rounded-full bg-primary" />
                                        <span>{{ story.playable ? 'Published' : 'Coming soon' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right arrow -->
                    <button
                        type="button"
                        class="slider-arrow absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 md:flex"
                        aria-label="Scroll right"
                        @click="scrollSlider(460)"
                    >
                        <span class="slider-arrow-icon">&#8250;</span>
                    </button>

                    <!-- ── Expanded hover popup (horizontal: image left + content right) -->
                    <Transition name="story-popup">
                        <div
                            v-if="hoveredId && hoveredStory && popupPos"
                            class="absolute z-30 flex w-[502px] flex-row rounded-[8px] border border-primary bg-[#262626] px-[12px] py-[6px] shadow-[0_0_18.3px_rgba(0,198,222,0.4)]"
                            :style="popupStyle"
                            @mouseenter="onPopupEnter"
                            @mouseleave="onPopupLeave"
                        >
                            <!-- Left: portrait cover (248×298) -->
                            <div class="h-[298px] w-[248px] shrink-0 overflow-hidden rounded-[5px]">
                                <img
                                    :src="hoveredStory.cover"
                                    :alt="hoveredStory.title"
                                    class="h-full w-full object-cover"
                                />
                            </div>

                            <!-- Right: content panel (217px) -->
                            <div class="ml-[13px] flex w-[217px] flex-col gap-[10px] py-[4px]">
                                <!-- Title + themes -->
                                <div class="flex flex-col gap-[4px]">
                                    <p class="text-[20px] font-medium leading-normal text-white">
                                        {{ hoveredStory.title }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-[4px] text-[14px] text-white">
                                        <template v-for="theme in hoveredStory.themes" :key="theme">
                                            <span class="size-[6px] shrink-0 rounded-full bg-white" />
                                            <span>{{ theme }}</span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Teaser -->
                                <p class="line-clamp-4 text-[14px] leading-[1.55] text-[#8f8f8f]">
                                    {{ hoveredStory.teaser }}
                                </p>

                                <!-- Branches explored -->
                                <p v-if="hoveredStory.branches" class="text-[14px] text-[#ffbe58]">
                                    {{ hoveredStory.branches }} Branches explored
                                </p>

                                <!-- CTA button pushed to bottom -->
                                <div class="mt-auto">
                                    <template v-if="hoveredStory.playable && hoveredStory.slug">
                                        <div class="flex h-9 items-center justify-center rounded-md border border-[#7e7e7e]">
                                            <Link
                                                :href="storyShow(hoveredStory.slug).url"
                                                class="flex h-full w-full items-center justify-center rounded-[6px] border border-primary bg-primary text-[18px] font-medium text-black no-underline"
                                            >
                                                Play
                                            </Link>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex h-9 items-center justify-center rounded-[6px] border border-[#4d4d4d] bg-[#3f3f3f] text-[18px] font-medium text-primary">
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
    transform: translateY(8px) scale(0.98);
}
</style>
