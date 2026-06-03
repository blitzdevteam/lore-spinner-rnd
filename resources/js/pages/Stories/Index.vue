<script setup lang="ts">
import LibrarySortMenu, { type LibrarySortMode } from '@/components/LibrarySortMenu.vue';
import MoodHeroBanner from '@/components/MoodHeroBanner.vue';
import MoodSelector from '@/components/MoodSelector.vue';
import MoodTopPicks from '@/components/MoodTopPicks.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StoryGrid from '@/components/StoryGrid.vue';
import { useHomeHeaderNav } from '@/composables/useHomeHeaderNav';
import { MOCK_LIBRARY_STORIES } from '@/data/mockLibraryStories';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { getMoodBannerConfig, normalizeMood, storyMatchesMood } from '@/data/moodBanners';
import { filterVisibleLibraryStories } from '@/data/hiddenLibraryStorySlugs';
import {
    dedupeStoriesByCanonicalSlug,
    getMoodSecondaryPickSlugs,
    selectStoriesByMoodSlugs,
} from '@/data/moodStories';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { StoryInterface } from '@/types';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    const real = filterVisibleLibraryStories(props.stories ?? []).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));
    const realSlugs = new Set(real.map((s) => s.slug));
    const extra = MOCK_LIBRARY_STORIES.filter((m) => !realSlugs.has(m.slug)).map((story) => ({
        ...story,
        cover: resolveStoryCover(story.slug, story.cover),
    }));
    return dedupeStoriesByCanonicalSlug([...real, ...extra]);
});

/** All stories assigned to the active mood (home portrait / landscape card art). */
const moodPageStories = computed((): StoryInterface[] => {
    const mood = normalizedMood.value;
    if (!mood) return [];

    return allLibraryStories.value.filter((story) => storyMatchesMood(story.slug, mood));
});

/** Mood page grid: secondary catalog order (top picks live in MoodTopPicks). */
const moodGridStories = computed((): StoryInterface[] => {
    const mood = normalizedMood.value;
    if (!mood) return [];

    return selectStoriesByMoodSlugs(moodPageStories.value, getMoodSecondaryPickSlugs(mood));
});

const libraryStories = computed((): StoryInterface[] => {
    const mood = normalizedMood.value;
    if (!mood) return allLibraryStories.value;

    return moodGridStories.value;
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

const sortMode = ref<LibrarySortMode>('recent');

const sortedStories = computed(() => {
    const list = [...libraryStories.value];
    if (sortMode.value === 'title_asc') {
        list.sort((a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }));
    } else if (sortMode.value === 'title_desc') {
        list.sort((a, b) => b.title.localeCompare(a.title, undefined, { sensitivity: 'base' }));
    } else if (!normalizedMood.value) {
        list.sort((a, b) => {
            const ta = a.updated_at ? new Date(a.updated_at).getTime() : 0;
            const tb = b.updated_at ? new Date(b.updated_at).getTime() : 0;
            return tb - ta;
        });
    }
    /* Mood pages: keep secondary catalog order when sort is Recent. */
    return list;
});

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
                    :stories="moodPageStories"
                />

                <div id="all-stories" class="mood-page-stories-section pb-12 md:pb-[3.75rem]">
                    <div class="container">
                        <div class="container-content mood-page-section-header-gap">
                            <SectionHeader :title="headerTitle">
                                <template #action>
                                    <LibrarySortMenu v-model="sortMode" />
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
                            <LibrarySortMenu v-model="sortMode" />
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

</style>
