<script setup lang="ts">
import StoryChapterCard from '@/components/StoryChapterCard.vue';
import StoryCommentCard from '@/components/StoryCommentCard.vue';
import StoryPlayAmbientGlows from '@/components/story-play/StoryPlayAmbientGlows.vue';
import StoryPlayAuthorDescription from '@/components/story-play/StoryPlayAuthorDescription.vue';
import StoryPlayCoverColumn from '@/components/story-play/StoryPlayCoverColumn.vue';
import StoryPlayGlassRoundButton from '@/components/story-play/StoryPlayGlassRoundButton.vue';
import StoryPlayMetaRow from '@/components/story-play/StoryPlayMetaRow.vue';
import StoryPlayStartCta from '@/components/story-play/StoryPlayStartCta.vue';
import StoryPlayStatStrip from '@/components/story-play/StoryPlayStatStrip.vue';
import StoryPlayTitleProgress from '@/components/story-play/StoryPlayTitleProgress.vue';
import StoryPlayTopBar from '@/components/story-play/StoryPlayTopBar.vue';
import { useBookmark } from '@/composables/useBookmark';
import { StoryInterface } from '@/types';
import { store, show as showGame } from '@/wayfinder/actions/App/Http/Controllers/User/GameController';
import { router } from '@inertiajs/vue3';
import { LucideChevronLeft } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    story: StoryInterface;
    existingGameId?: string | null;
}>();

type Panel = 'details' | 'chapters';

const panel = ref<Panel>('details');
const hasExistingGame = computed(() => !!props.existingGameId);
const { isBookmarked, toggleBookmark } = useBookmark(props.story.id, props.story.is_bookmarked ?? false);

const handleStartStory = (): void => {
    if (props.existingGameId) {
        router.visit(showGame.url(props.existingGameId));
    } else {
        router.post(store(), {
            story_id: props.story.id,
        });
    }
};

const handleBack = (): void => {
    window.history.back();
};

const teaserText = computed(() => props.story.teaser?.trim() || '');

const creatorName = computed(
    () => props.story.creator?.full_name?.trim()
        || [props.story.creator?.first_name, props.story.creator?.last_name].filter(Boolean).join(' ')
        || props.story.creator?.username
        || 'Author',
);

const coverHeadline = computed(() => props.story.title.toUpperCase());

const coverFooterCredit = computed(() => creatorName.value.toUpperCase());

const coverImageUrl = computed(() => props.story.cover?.trim() || null);

const progressBadge = computed(() => (hasExistingGame.value ? '—' : '00%'));

const durationLabel = computed(() => {
    const n = props.story.chapters_count ?? props.story.chapters?.length ?? 0;
    if (n <= 0) {
        return null;
    }
    const mins = Math.max(25, Math.round(n * 10));
    return `${mins}min`;
});

const genreLabel = computed(() => props.story.category?.title ?? null);

function formatRelativeUpdated(dateIso: string | null | undefined): string {
    if (!dateIso) {
        return '—';
    }
    const ts = Date.parse(dateIso);
    if (Number.isNaN(ts)) {
        return '—';
    }
    const days = Math.floor((Date.now() - ts) / 86400000);
    if (days < 1) {
        return 'Today';
    }
    if (days < 7) {
        return `${days} day${days === 1 ? '' : 's'} ago`;
    }
    if (days < 30) {
        const w = Math.floor(days / 7);
        return `${w} week${w === 1 ? '' : 's'} ago`;
    }
    const months = Math.floor(days / 30);
    if (months < 12) {
        return months === 1 ? '1 Month Ago' : `${months} Months Ago`;
    }
    const y = Math.floor(months / 12);
    return y === 1 ? '1 Year Ago' : `${y} Years Ago`;
}

const statItems = computed(() => [
    {
        label: 'CHAPTERS',
        value: `${props.story.chapters_count ?? props.story.chapters?.length ?? 0}`,
    },
    {
        label: 'RATING',
        value: props.story.rating?.label ?? '—',
    },
    {
        label: 'UPDATED',
        value: formatRelativeUpdated(props.story.updated_at ?? props.story.published_at),
    },
    {
        label: 'STATUS',
        value: props.story.status?.label ?? '—',
    },
]);

const primaryCtaLabel = computed(() => (hasExistingGame.value ? 'Continue game' : 'Start game'));

const creatorAvatar = computed(() => {
    const a = props.story.creator?.avatar?.trim();
    return a || null;
});

const leftColumnChapters = computed(() =>
    props.story.chapters?.filter((_, index) => index % 2 === 0) ?? [],
);

const rightColumnChapters = computed(() =>
    props.story.chapters?.filter((_, index) => index % 2 === 1) ?? [],
);
</script>

<template>
    <div class="relative min-h-svh overflow-x-hidden bg-black pb-28 text-white selection:bg-primary-500/30 lg:pb-[min(136px,max(112px,calc(env(safe-area-inset-bottom,0px)+104px)))] lg:selection:bg-primary-500/35">
        <StoryPlayAmbientGlows />

        <div
            class="relative z-[1] mx-auto flex max-w-[72rem] flex-col px-5 pt-10 pb-8 md:px-[3.25rem] md:pb-12 md:pt-[3.75rem] lg:flex-row lg:items-stretch lg:justify-between xl:max-w-[75rem]"
        >
            <!-- Cover -->
            <div class="relative mb-10 shrink-0 lg:mb-0 lg:mr-6 xl:mr-10">
                <StoryPlayCoverColumn
                    :src="coverImageUrl"
                    :title="story.title"
                    :headline="coverHeadline"
                    :footer-credit="coverFooterCredit"
                >
                    <template #overlay>
                        <StoryPlayGlassRoundButton
                            class="pointer-events-auto absolute left-4 top-4 z-[5]"
                            aria-label="Go back"
                            @click="handleBack"
                        >
                            <LucideChevronLeft class="!size-5 text-white" :stroke-width="1.85" aria-hidden="true" />
                        </StoryPlayGlassRoundButton>
                        <div
                            v-if="!coverImageUrl"
                            class="pointer-events-none absolute inset-0 z-[2] grid place-items-center"
                            aria-hidden="true"
                        >
                            <span class="font-['Marcellus_SC','Marcellus SC',serif] text-4xl uppercase tracking-wider text-white/55">
                                {{ story.title.charAt(0)?.toUpperCase() }}
                            </span>
                        </div>
                    </template>
                </StoryPlayCoverColumn>
            </div>

            <!-- Main column -->
            <div
                class="relative flex min-h-0 min-w-0 flex-1 flex-col gap-[1.3125rem] lg:max-w-[41.25rem] lg:min-h-[calc(100svh-120px)]"
            >
                <StoryPlayTopBar
                    :tab="panel"
                    :bookmark-filled="isBookmarked"
                    @update:tab="panel = $event"
                    @bookmark="toggleBookmark"
                />

                <div v-if="panel === 'details'" class="flex flex-col gap-[1.3125rem] pb-4">
                    <div class="flex flex-col gap-[0.625rem]">
                        <StoryPlayTitleProgress :title="story.title" :progress-label="progressBadge" />
                        <StoryPlayMetaRow
                            :duration-label="durationLabel"
                            :genre-label="genreLabel"
                        />
                        <StoryPlayStatStrip :items="statItems" />
                    </div>

                    <StoryPlayAuthorDescription
                        :author-name="creatorName"
                        :avatar-url="creatorAvatar"
                        :description="teaserText || 'Explore this playable story.'"
                        :collapse-at="360"
                    />

                    <section
                        v-if="story.comments?.length"
                        aria-label="Community comments"
                        class="rounded-xl border border-white/12 bg-black/35 p-4 backdrop-blur-sm"
                    >
                        <div class="mb-4 font-['Inter',sans-serif] text-[0.8125rem] font-semibold uppercase tracking-wide text-gray-400">
                            Comments · {{ story.comments_count ?? story.comments.length }}
                        </div>
                        <div class="flex flex-col gap-4">
                            <StoryCommentCard
                                v-for="comment in story.comments"
                                :key="comment.id"
                                :comment
                            />
                        </div>
                    </section>
                </div>

                <div v-else class="lg:pb-8">
                    <div
                        v-if="story.chapters?.length"
                        class="flex flex-col gap-2.5 sm:flex-row sm:items-start"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-2.5">
                            <StoryChapterCard
                                v-for="(chapter, index) in leftColumnChapters"
                                :key="chapter.id"
                                :chapter
                                :episode-number="index * 2 + 1"
                            />
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-2.5">
                            <StoryChapterCard
                                v-for="(chapter, index) in rightColumnChapters"
                                :key="chapter.id"
                                :chapter
                                :episode-number="index * 2 + 2"
                            />
                        </div>
                    </div>
                    <p
                        v-else
                        class="rounded-xl border border-white/15 bg-black/45 py-14 text-center font-['Inter',sans-serif] text-sm font-medium text-gray-500 backdrop-blur-sm"
                    >
                        No chapters listed yet.
                    </p>
                </div>

                <!-- Fade + CTA (desktop: in column, matches Figma) -->
                <div class="relative z-[6] mx-auto mt-10 hidden max-w-[41.25rem] lg:mt-auto lg:block">
                    <div
                        class="pointer-events-none absolute -inset-x-6 -top-[7.5rem] bottom-0 mx-auto mb-[-20px] h-[clamp(148px,18vw,200px)] bg-linear-to-t from-black from-[32%] via-black/92 to-transparent lg:-inset-x-10"
                    />
                    <div class="relative pt-14">
                        <StoryPlayStartCta :label="primaryCtaLabel" @click="handleStartStory" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile CTA strip -->
        <div class="fixed inset-x-0 bottom-0 z-30 lg:hidden">
            <div class="pointer-events-none absolute inset-x-0 bottom-[4.5rem] h-[8.25rem] bg-linear-to-t from-black via-black/88 to-transparent" />
            <div class="relative border-t border-white/10 bg-black/85 px-4 pb-[calc(16px+env(safe-area-inset-bottom,0px))] pt-5 backdrop-blur-md">
                <StoryPlayStartCta :label="primaryCtaLabel" @click="handleStartStory" />
            </div>
        </div>
    </div>
</template>

<style scoped></style>
