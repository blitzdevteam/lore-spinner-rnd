<script setup lang="ts">
import MoodHeroBanner from '@/components/MoodHeroBanner.vue';
import MoodSelector from '@/components/MoodSelector.vue';
import MoodTopPicks from '@/components/MoodTopPicks.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StoryGrid from '@/components/StoryGrid.vue';
import { useHomeHeaderNav } from '@/composables/useHomeHeaderNav';
import { MOCK_LIBRARY_STORIES } from '@/data/mockLibraryStories';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { getMoodTopPickSlugs } from '@/data/moodContent';
import { getMoodBannerConfig, normalizeMood, storyMatchesMood } from '@/data/moodBanners';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { StoryInterface } from '@/types';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Head } from '@inertiajs/vue3';
import { ArrowDownUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type SortMode = 'recent' | 'title_asc' | 'title_desc';

const props = withDefaults(
    defineProps<{
        stories?: StoryInterface[];
    }>(),
    {
        stories: () => [],
    },
);

const { activeMood } = useHomeHeaderNav();

const normalizedMood = computed(() => normalizeMood(activeMood.value));

const moodHero = computed(() => getMoodBannerConfig(activeMood.value));

const pageTitle = computed(() => (normalizedMood.value ? moodHero.value.title : 'Library'));

/** API stories plus featured mock worlds; mocks skipped when the same slug already exists from the server. */
const allLibraryStories = computed((): StoryInterface[] => {
    const real = (props.stories ?? []).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));
    const realSlugs = new Set(real.map((s) => s.slug));
    const extra = MOCK_LIBRARY_STORIES.filter((m) => !realSlugs.has(m.slug)).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));
    return [...real, ...extra];
});

const libraryStories = computed((): StoryInterface[] => {
    const mood = normalizedMood.value;
    if (!mood) return allLibraryStories.value;
    return allLibraryStories.value.filter((story) => storyMatchesMood(story.slug, mood));
});

const isMoodPage = computed(() => normalizedMood.value !== null);

const listHeading = computed(() => {
    if (normalizedMood.value) {
        return `${libraryStories.value.length} Stories`;
    }
    return 'Stories';
});

const headerTitle = computed(() => {
    if (normalizedMood.value) {
        return listHeading.value;
    }
    return `${listHeading.value} (${libraryStories.value.length})`;
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
</script>

<template>
    <Head :title="pageTitle" />

    <HomeLayout>
        <MoodHeroBanner :mood="activeMood" />

        <template v-if="isMoodPage && normalizedMood">
            <div class="mood-page-flow">
                <MoodSelector :active-mood="normalizedMood" />
                <MoodTopPicks
                    :mood="normalizedMood"
                    :mood-label="moodHero.label"
                    :stories="libraryStories"
                    :total-count="libraryStories.length"
                />

                <div id="all-stories" class="mood-page-stories-section pb-12 md:pb-[3.75rem]">
                    <div class="container">
                        <div class="container-content mood-page-section-header-gap">
                            <SectionHeader :title="headerTitle">
                                <template #action>
                                    <button
                                        type="button"
                                        class="library-sort-btn group"
                                        :title="`Sorting: ${sortLabel}. Click to change.`"
                                        :aria-label="`Sort stories. Current: ${sortLabel}.`"
                                        @click="cycleSort"
                                    >
                                        <ArrowDownUp
                                            class="library-sort-btn__icon"
                                            :stroke-width="2.25"
                                            aria-hidden="true"
                                        />
                                        <span class="library-sort-btn__label">Sort</span>
                                        <span class="sr-only"> ({{ sortLabel }})</span>
                                    </button>
                                </template>
                            </SectionHeader>

                            <StoryGrid
                                :stories="sortedStories"
                                :mood-label="moodHero.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div
            v-else
            id="all-stories"
            class="relative z-10 pb-12 pt-6 md:pb-[3.75rem] md:pt-[3.75rem]"
        >
            <div class="container">
                <div
                    class="container-content flex min-w-0 flex-col"
                    :class="normalizedMood ? 'mood-page-sections' : 'gap-5 md:gap-[1.125rem]'"
                >
                    <template v-if="normalizedMood">
                        <MoodSelectorBar :active-mood="normalizedMood" />

                        <MoodTopPicks
                            v-if="topPicks.length"
                            :mood="normalizedMood"
                            :picks="topPicks"
                            :view-all-href="moodStoriesAnchor"
                            :view-all-count="libraryStories.length"
                        />
                    </template>

                    <div
                        :id="normalizedMood ? 'mood-stories' : undefined"
                        class="flex min-w-0 flex-col"
                        :class="normalizedMood ? 'mood-page-stories home-section-gap' : 'gap-5 md:gap-[1.125rem]'"
                    >
                        <SectionHeader :title="headerTitle">
                        <template #action>
                            <button
                                type="button"
                                class="library-sort-btn group"
                                :title="`Sorting: ${sortLabel}. Click to change.`"
                                :aria-label="`Sort stories. Current: ${sortLabel}.`"
                                @click="cycleSort"
                            >
                                <ArrowDownUp
                                    class="library-sort-btn__icon"
                                    :stroke-width="2.25"
                                    aria-hidden="true"
                                />
                                <span class="library-sort-btn__label">Sort</span>
                                <span class="sr-only"> ({{ sortLabel }})</span>
                            </button>
                        </template>
                    </SectionHeader>

                    <StoryGrid :stories="sortedStories" />
                    </div>
                </div>
            </div>
        </div>
    </HomeLayout>
</template>

<style scoped>
.mood-page-sections {
    gap: 3rem;
}

@media (min-width: 768px) {
    .mood-page-sections {
        gap: 4rem;
    }
}

@media (min-width: 1024px) {
    .mood-page-sections {
        gap: 4.5rem;
    }
}

.library-sort-btn {
    position: relative;
    display: inline-flex;
    height: calc(1.375rem * 1.1);
    flex-shrink: 0;
    align-items: center;
    gap: 0.3125rem;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    letter-spacing: 0.01em;
    color: var(--color-primary, #00d4aa);
    white-space: nowrap;
    cursor: pointer;
    transition: opacity 150ms ease, transform 150ms ease;
}

@media (min-width: 768px) {
    .library-sort-btn {
        height: calc(1.625rem * 1.1);
    }
}

.library-sort-btn::before {
    content: '';
    position: absolute;
    inset: -0.625rem -0.375rem;
}

.library-sort-btn:hover {
    opacity: 0.8;
}

.library-sort-btn:active {
    opacity: 0.7;
    transform: scale(0.98);
}

.library-sort-btn__icon {
    width: 1em;
    height: 1em;
    flex-shrink: 0;
    transition: transform 150ms ease;
}

.library-sort-btn:hover .library-sort-btn__icon {
    transform: rotate(180deg);
}

.library-sort-btn__label {
    line-height: 1;
}
</style>
