<script setup lang="ts">
import libraryBannerImage from '@/assets/banner-stories.jpg';
import HomeWorldCard from '@/components/HomeWorldCard.vue';
import { MOCK_LIBRARY_STORIES } from '@/data/mockLibraryStories';
import { STORY_HOVER_META_BY_SLUG } from '@/data/storyCardHoverMeta';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { Head, Link } from '@inertiajs/vue3';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { ArrowDownUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type SortMode = 'recent' | 'title_asc' | 'title_desc';

/** Five 195px cards + four 15px gaps — row width for Library grid. */
const LIBRARY_RAIL_PX = 1035;

const props = withDefaults(
    defineProps<{
        stories?: StoryInterface[];
    }>(),
    {
        stories: () => [],
    },
);

/** API stories plus featured mock worlds; mocks skipped when the same slug already exists from the server. */
const libraryStories = computed((): StoryInterface[] => {
    const real = props.stories ?? [];
    const realSlugs = new Set(real.map((s) => s.slug));
    const extra = MOCK_LIBRARY_STORIES.filter((m) => !realSlugs.has(m.slug));
    return [...real, ...extra];
});

const sortMode = ref<SortMode>('recent');

const sortedStories = computed(() => {
    const list = [...libraryStories.value];
    if (sortMode.value === 'title_asc') {
        list.sort((a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }));
    } else if (sortMode.value === 'title_desc') {
        list.sort((a, b) => b.title.localeCompare(a.title, undefined, { sensitivity: 'base' }));
    } else {
        list.sort((a, b) => {
            const ta = a.updated_at ? new Date(a.updated_at).getTime() : 0;
            const tb = b.updated_at ? new Date(b.updated_at).getTime() : 0;
            return tb - ta;
        });
    }
    return list;
});

const sortLabel = computed(() => {
    switch (sortMode.value) {
        case 'title_asc':
            return 'A–Z';
        case 'title_desc':
            return 'Z–A';
        default:
            return 'Recent';
    }
});

function cycleSort(): void {
    const order: SortMode[] = ['recent', 'title_asc', 'title_desc'];
    const i = order.indexOf(sortMode.value);
    sortMode.value = order[(i + 1) % order.length]!;
}

// Hover popup is positioned relative to this layer — must stay outside overflow-x-auto
// (otherwise overflow-y becomes auto and clips the tooltip). See CSS overflow spec.
const libraryHoverLayerEl = ref<HTMLElement | null>(null);
const wrapperWidth = ref(LIBRARY_RAIL_PX);
const hoveredStoryId = ref<number | null>(null);
const popupPos = ref<{ left: number; top: number } | null>(null);
const cardEls: Record<number, HTMLElement | null> = {};

let _hideTimer: ReturnType<typeof setTimeout> | null = null;
let _onCard = false;
let _onPopup = false;

function scheduleHide(): void {
    if (_hideTimer) clearTimeout(_hideTimer);
    _hideTimer = setTimeout(() => {
        if (!_onCard && !_onPopup) {
            hoveredStoryId.value = null;
            popupPos.value = null;
        }
    }, 120);
}

function onCardEnter(story: StoryInterface): void {
    _onCard = true;
    if (_hideTimer) clearTimeout(_hideTimer);

    hoveredStoryId.value = story.id;

    const cardEl = cardEls[story.id];
    const layer = libraryHoverLayerEl.value;
    if (!cardEl || !layer) return;

    wrapperWidth.value = layer.getBoundingClientRect().width;

    const cr = cardEl.getBoundingClientRect();
    const lr = layer.getBoundingClientRect();
    popupPos.value = { left: cr.left - lr.left, top: cr.top - lr.top };
}

function onCardLeave(): void {
    _onCard = false;
    scheduleHide();
}

function onPopupEnter(): void {
    _onPopup = true;
    if (_hideTimer) clearTimeout(_hideTimer);
}

function onPopupLeave(): void {
    _onPopup = false;
    scheduleHide();
}

const popupStyle = computed(() => {
    if (!popupPos.value) return {};
    const POPUP_W = 282;
    let left = popupPos.value.left;
    const W = Math.min(wrapperWidth.value, LIBRARY_RAIL_PX);
    if (left + POPUP_W > W) left = W - POPUP_W;
    if (left < 0) left = 0;
    return { left: `${left}px`, top: `${popupPos.value.top}px` };
});

const hoveredStory = computed(() => sortedStories.value.find((s) => s.id === hoveredStoryId.value) ?? null);

const hoveredPublished = computed(() => hoveredStory.value?.status?.value === StoryStatusEnum.PUBLISHED);

function themesForStory(story: StoryInterface): string[] {
    const meta = STORY_HOVER_META_BY_SLUG[story.slug];
    if (meta?.themes.length) return meta.themes;
    if (story.category?.title) return [story.category.title];
    return [];
}

function branchesForStory(story: StoryInterface): string | null {
    return STORY_HOVER_META_BY_SLUG[story.slug]?.branches ?? null;
}
</script>

<template>
    <Head title="Library" />

    <HomeLayout>
        <!-- Rail widened to 1035px so five 195px cards fit per row (+ 4×15px gaps). Banner spans full rail. -->
        <!-- z-10: stack above footer (later sibling); hover root must not clip overflow-x (see inner scroll div). -->
        <div class="relative z-10 pb-14 pt-8 md:pb-[60px] md:pt-[82px]">
            <div class="container">
                <div class="mx-auto flex w-full max-w-[1035px] min-w-0 flex-col">
                    <div class="mb-10 w-full shrink-0 overflow-hidden rounded-[8px] md:mb-[60px]">
                        <div class="relative aspect-[1035/370] w-full md:aspect-auto md:h-[370px] md:max-h-none">
                            <img
                                :src="libraryBannerImage"
                                alt=""
                                class="pointer-events-none absolute inset-0 size-full object-cover object-center select-none"
                                width="1035"
                                height="370"
                                decoding="async"
                            />
                        </div>
                    </div>

                    <div class="flex w-full min-w-0 flex-col gap-[18px]">
                        <div class="flex h-auto min-h-10 shrink-0 items-center justify-between gap-4 sm:h-10 sm:justify-between">
                            <h1
                                class="font-[Inter] text-[22px] font-bold uppercase leading-[33px] text-white sm:h-10 sm:text-[26px] sm:leading-[33px]"
                            >
                                My Stories ( {{ libraryStories.length }} )
                            </h1>
                            <button
                                type="button"
                                class="inline-flex h-[38px] w-[96px] shrink-0 cursor-pointer items-center justify-center gap-2 rounded-[8px] border border-solid border-primary bg-[#01343a] px-[6px] text-[18px] font-medium text-primary outline-none transition-colors hover:bg-[#0a454d] focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-black"
                                :title="`Sorting: ${sortLabel}. Click to change.`"
                                @click="cycleSort"
                            >
                                <ArrowDownUp class="size-[22px] shrink-0 text-primary" :stroke-width="2" aria-hidden="true" />
                                <span class="text-primary leading-[33px]">Sort</span>
                            </button>
                        </div>

                        <div ref="libraryHoverLayerEl" class="relative min-h-0 w-full min-w-0 overflow-visible pb-2">
                            <div class="overflow-x-auto pb-1 [scrollbar-gutter:stable]">
                                <div class="library-story-grid">
                                    <div
                                        v-for="story in sortedStories"
                                        :key="story.id"
                                        :ref="(el) => { cardEls[story.id] = el ? (el as HTMLElement) : null }"
                                        @mouseenter="onCardEnter(story)"
                                        @mouseleave="onCardLeave"
                                    >
                                        <HomeWorldCard
                                            :story="story"
                                            :dimmed="hoveredStoryId !== null && hoveredStoryId !== story.id"
                                        />
                                    </div>
                                </div>
                            </div>

                            <Transition name="card-popup">
                                <div
                                    v-if="hoveredStoryId && hoveredStory && popupPos"
                                    class="absolute z-50 flex w-[282px] flex-col gap-[10px] rounded-[8px] border border-primary bg-[#262626] p-[10px] shadow-[0_0_36.6px_rgba(111,175,186,0.4)]"
                                    :style="popupStyle"
                                    @mouseenter="onPopupEnter"
                                    @mouseleave="onPopupLeave"
                                >
                            <div class="relative h-[239px] w-full overflow-hidden rounded-[6px]">
                                <img
                                    v-if="hoveredStory.cover"
                                    :src="hoveredStory.cover"
                                    :alt="hoveredStory.title"
                                    class="absolute inset-0 size-full max-w-none object-cover"
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 flex items-center justify-center rounded-[6px] bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                                >
                                    <span class="text-4xl font-bold text-primary/50">
                                        {{ hoveredStory.title?.charAt(0)?.toUpperCase() }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-[8px]">
                                <div class="flex flex-col gap-[4px]">
                                    <p class="text-[20px] font-medium leading-normal text-white">
                                        {{ hoveredStory.title }}
                                    </p>
                                    <div
                                        v-if="themesForStory(hoveredStory).length"
                                        class="flex flex-wrap items-center gap-x-[8px] gap-y-[4px] text-[14px] text-white"
                                    >
                                        <template v-for="theme in themesForStory(hoveredStory)" :key="theme">
                                            <span class="size-[6px] shrink-0 rounded-full bg-white" />
                                            <span>{{ theme }}</span>
                                        </template>
                                    </div>
                                </div>

                                <p v-if="hoveredStory.teaser" class="line-clamp-3 text-[14px] leading-[1.5] text-[#8f8f8f]">
                                    {{ hoveredStory.teaser }}
                                </p>

                                <p v-if="branchesForStory(hoveredStory)" class="text-[14px] text-[#ffbe58]">
                                    {{ branchesForStory(hoveredStory) }} Branches explored
                                </p>
                            </div>

                            <template v-if="hoveredPublished">
                                <Link
                                    :href="storyShow(hoveredStory.slug).url"
                                    class="flex h-9 w-full items-center justify-center rounded-[6px] bg-cta-fill text-lg font-medium text-cta-text no-underline transition-colors hover:bg-cta-hover active:bg-cta-active"
                                >
                                    Play
                                </Link>
                            </template>
                            <template v-else>
                                <div
                                    class="flex h-9 w-full cursor-default items-center justify-center rounded-[6px] border border-[#4d4d4d] bg-[#3f3f3f] text-lg font-medium text-[#8e8e8e]"
                                >
                                    Coming soon
                                </div>
                            </template>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>

<style scoped>
/* Exactly 5 columns × 195px + 15px gutters = 1035px per row */
.library-story-grid {
    display: grid;
    grid-template-columns: repeat(5, 195px);
    column-gap: 15px;
    row-gap: 18px;
    width: 1035px;
}

/* Popup fade (matches Featured Worlds) */
.card-popup-enter-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}
.card-popup-leave-active {
    transition:
        opacity 0.14s ease,
        transform 0.14s ease;
}
.card-popup-enter-from,
.card-popup-leave-to {
    opacity: 0;
    transform: translateY(6px) scale(0.97);
}
</style>
