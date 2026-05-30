<script setup lang="ts">
import StoryCard, { type StoryCardCta } from '@/components/StoryCard.vue';
import StoryDetailsSheet, { type StorySheetData } from '@/components/StoryDetailsSheet.vue';
import { STORY_HOVER_META_BY_SLUG } from '@/data/storyCardHoverMeta';
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { Link } from '@inertiajs/vue3';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        stories: StoryInterface[];
        enableHoverPopup?: boolean;
        ctaByStoryId?: Record<number, StoryCardCta>;
    }>(),
    {
        enableHoverPopup: true,
        ctaByStoryId: () => ({}),
    },
);

const gridLayerEl = ref<HTMLElement | null>(null);
const hoveredStoryId = ref<number | null>(null);
const popupPos = ref<{ left: number; top: number } | null>(null);
const cardEls: Record<number, HTMLElement | null> = {};

let hideTimer: ReturnType<typeof setTimeout> | null = null;
let onCard = false;
let onPopup = false;

function scheduleHide(): void {
    if (hideTimer) clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        if (!onCard && !onPopup) {
            hoveredStoryId.value = null;
            popupPos.value = null;
        }
    }, 120);
}

function onCardEnter(story: StoryInterface): void {
    if (!props.enableHoverPopup) return;

    onCard = true;
    if (hideTimer) clearTimeout(hideTimer);

    hoveredStoryId.value = story.id;

    const cardEl = cardEls[story.id];
    const layer = gridLayerEl.value;
    if (!cardEl || !layer) return;

    const cardRect = cardEl.getBoundingClientRect();
    const layerRect = layer.getBoundingClientRect();
    popupPos.value = { left: cardRect.left - layerRect.left, top: cardRect.top - layerRect.top };
}

function onCardLeave(): void {
    onCard = false;
    scheduleHide();
}

function onPopupEnter(): void {
    onPopup = true;
    if (hideTimer) clearTimeout(hideTimer);
}

function onPopupLeave(): void {
    onPopup = false;
    scheduleHide();
}

const popupStyle = computed(() => {
    if (!popupPos.value || !gridLayerEl.value) return {};

    const POPUP_W = 282;
    const layerWidth = gridLayerEl.value.getBoundingClientRect().width;
    let left = popupPos.value.left;

    if (left + POPUP_W > layerWidth) left = layerWidth - POPUP_W;
    if (left < 0) left = 0;

    return { left: `${left}px`, top: `${popupPos.value.top}px` };
});

const hoveredStory = computed(
    () => props.stories.find((story) => story.id === hoveredStoryId.value) ?? null,
);

const hoveredPublished = computed(
    () => hoveredStory.value?.status?.value === StoryStatusEnum.PUBLISHED,
);

function themesForStory(story: StoryInterface): string[] {
    const meta = STORY_HOVER_META_BY_SLUG[story.slug];
    if (meta?.themes.length) return meta.themes;
    if (story.category?.title) return [story.category.title];
    return [];
}

function branchesForStory(story: StoryInterface): string | null {
    return STORY_HOVER_META_BY_SLUG[story.slug]?.branches ?? null;
}

function ctaForStory(story: StoryInterface): StoryCardCta | undefined {
    return props.ctaByStoryId[story.id];
}

function isComingSoon(story: StoryInterface): boolean {
    return story.status?.value !== StoryStatusEnum.PUBLISHED;
}

// ── Mobile bottom sheet ───────────────────────────────────────────────────────
const sheetStory = ref<StorySheetData | null>(null);

function toSheetData(story: StoryInterface): StorySheetData {
    const meta = STORY_HOVER_META_BY_SLUG[story.slug];
    return {
        id: story.id,
        title: story.title,
        cover: story.cover,
        themes: meta?.themes.length ? meta.themes : story.category ? [story.category.title] : undefined,
        category: story.category?.title,
        rating: story.rating?.label,
        isComingSoon: isComingSoon(story),
        teaser: story.teaser,
        branches: meta?.branches ?? null,
        slug: story.slug,
        cta: (ctaForStory(story) === 'read-again' ? 'play' : ctaForStory(story)) as StorySheetData['cta'],
    };
}

function onCardClick(e: MouseEvent, story: StoryInterface) {
    if (!window.matchMedia('(hover: hover)').matches) {
        e.stopPropagation();
        sheetStory.value = toSheetData(story);
    }
}
</script>

<template>
    <div ref="gridLayerEl" class="story-grid-layer relative w-full min-w-0">
        <div class="story-grid">
            <div
                v-for="story in stories"
                :key="story.id"
                :ref="(el) => { cardEls[story.id] = el ? (el as HTMLElement) : null }"
                class="min-w-0"
                @mouseenter="onCardEnter(story)"
                @mouseleave="onCardLeave"
                @click.capture="onCardClick($event, story)"
            >
                <StoryCard
                    :title="story.title"
                    :cover-image="story.cover"
                    :slug="story.slug"
                    :status="story.status?.value"
                    :is-coming-soon="isComingSoon(story)"
                    :cta="ctaForStory(story)"
                    :dimmed="enableHoverPopup && hoveredStoryId !== null && hoveredStoryId !== story.id"
                />
            </div>
        </div>

        <Transition name="story-grid-popup">
            <div
                v-if="enableHoverPopup && hoveredStoryId && hoveredStory && popupPos"
                class="story-grid-popup absolute z-50 hidden w-[17.625rem] flex-col gap-[0.625rem] rounded-lg border border-primary bg-[#262626] p-[0.625rem] shadow-[0_0_36.6px_rgba(111,175,186,0.4)] md:flex"
                :style="popupStyle"
                @mouseenter="onPopupEnter"
                @mouseleave="onPopupLeave"
            >
                <div class="relative aspect-[2/3] w-full overflow-hidden rounded-md border border-white/5">
                    <img
                        v-if="hoveredStory.cover"
                        :src="hoveredStory.cover"
                        :alt="hoveredStory.title"
                        class="absolute inset-0 size-full object-cover"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                    >
                        <span class="text-4xl font-bold text-primary/50">
                            {{ hoveredStory.title?.charAt(0)?.toUpperCase() }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex flex-col gap-1">
                        <p class="text-[1.125rem] font-medium leading-snug text-white">
                            {{ hoveredStory.title }}
                        </p>
                        <div
                            v-if="themesForStory(hoveredStory).length"
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.8125rem] text-white"
                        >
                            <template v-for="theme in themesForStory(hoveredStory)" :key="theme">
                                <span class="size-1.5 shrink-0 rounded-full bg-white" />
                                <span>{{ theme }}</span>
                            </template>
                        </div>
                    </div>

                    <p
                        v-if="hoveredStory.teaser"
                        class="line-clamp-3 text-[0.8125rem] leading-relaxed text-[#8f8f8f]"
                    >
                        {{ hoveredStory.teaser }}
                    </p>

                    <p v-if="branchesForStory(hoveredStory)" class="text-[0.8125rem] text-[#ffbe58]">
                        {{ branchesForStory(hoveredStory) }} Branches explored
                    </p>
                </div>

                <Link
                    v-if="hoveredPublished"
                    :href="storyShow(hoveredStory.slug).url"
                    class="flex h-[2.25rem] w-full items-center justify-center rounded-[0.375rem] bg-cta-fill text-[1.125rem] font-medium text-cta-text no-underline transition-colors hover:bg-cta-hover active:bg-cta-active"
                >
                    Play
                </Link>
                <div
                    v-else
                    class="flex h-[2.25rem] w-full cursor-default items-center justify-center rounded-[0.375rem] border border-[#4d4d4d] bg-[#3f3f3f] text-[1.125rem] font-medium text-[#8e8e8e]"
                >
                    Coming soon
                </div>
            </div>
        </Transition>
    <StoryDetailsSheet :story="sheetStory" @close="sheetStory = null" />
    </div>
</template>

<style scoped>
.story-grid {
    display: grid;
    width: 100%;
    min-width: 0;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

@media (min-width: 768px) {
    .story-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }
}

@media (min-width: 1024px) {
    .story-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (min-width: 1280px) {
    .story-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 1.125rem;
    }
}

@media (min-width: 1536px) {
    .story-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 1.25rem;
    }
}

.story-grid-popup-enter-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.story-grid-popup-leave-active {
    transition:
        opacity 0.14s ease,
        transform 0.14s ease;
}

.story-grid-popup-enter-from,
.story-grid-popup-leave-to {
    opacity: 0;
    transform: translateY(0.375rem) scale(0.97);
}
</style>
