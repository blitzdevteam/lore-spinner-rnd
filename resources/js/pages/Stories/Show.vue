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
import { formatCreatorDisplayName } from '@/data/loreSpinnerSeriesLabels';
import { resolveStoryCover } from '@/data/storyCoverBySlug';
import { useShare } from '@/composables/useShare';
import { StoryInterface } from '@/types';
import { store, show as showGame } from '@/wayfinder/actions/App/Http/Controllers/User/GameController';
import { show as storyShowRoute } from '@/wayfinder/routes/stories';
import { router } from '@inertiajs/vue3';
import { LucideChevronLeft, LucideShare2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    story: StoryInterface;
    existingGameId?: string | null;
    isPlayable?: boolean;
}>();

type Panel = 'details' | 'chapters';

const panel = ref<Panel>('details');
const hasExistingGame = computed(() => !!props.existingGameId);
const { isBookmarked, toggleBookmark } = useBookmark(props.story.id, props.story.is_bookmarked ?? false);
const { share } = useShare();

const storyShareUrl = computed(() => {
    if (typeof window !== 'undefined') {
        return `${window.location.origin}${storyShowRoute.url(props.story.slug)}`;
    }
    return storyShowRoute.url(props.story.slug);
});

const handleShare = async (): Promise<void> => {
    const result = await share({
        title: props.story.title,
        text: teaserText.value || props.story.title,
        url: storyShareUrl.value,
    });

    if (result === 'copied') {
        toast.success('Link copied to clipboard');
    } else if (result === 'failed') {
        toast.error('Could not share this story');
    }
};

const handleStartStory = (): void => {
    if (!props.isPlayable) return;
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

const creatorName = computed(() => {
    const raw =
        props.story.creator?.full_name?.trim() ||
        [props.story.creator?.first_name, props.story.creator?.last_name].filter(Boolean).join(' ') ||
        props.story.creator?.username ||
        'Author';

    return formatCreatorDisplayName(raw, props.story.creator);
});

const coverHeadline = computed(() => props.story.title.toUpperCase());

const coverFooterCredit = computed(() => creatorName.value.toUpperCase());

const coverImageUrl = computed(() => {
    const resolved = resolveStoryCover(props.story.slug, props.story.cover);
    return resolved || null;
});

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

const primaryCtaLabel = computed(() => (hasExistingGame.value ? 'Continue Story' : 'Start Story'));

const creatorAvatar = computed(() => {
    const a = props.story.creator?.avatar?.trim();
    return a || null;
});

const leftColumnChapters = computed(() => props.story.chapters?.filter((_, index) => index % 2 === 0) ?? []);

const rightColumnChapters = computed(() => props.story.chapters?.filter((_, index) => index % 2 === 1) ?? []);
</script>

<template>
    <div
        class="story-show-page relative min-h-svh overflow-x-hidden bg-black text-white selection:bg-primary-500/30 lg:h-svh lg:overflow-hidden lg:pb-0 lg:selection:bg-primary-500/35"
    >
        <StoryPlayAmbientGlows />

        <div
            class="story-show-shell relative z-[1] mx-auto flex h-full max-w-[72rem] flex-col px-5 pt-8 pb-28 md:px-[3.25rem] md:pt-10 lg:max-h-svh lg:flex-row lg:items-stretch lg:gap-6 lg:overflow-hidden lg:px-[3.25rem] lg:py-6 xl:max-w-[75rem] xl:gap-10"
        >
            <div class="pointer-events-auto absolute top-8 left-5 md:top-10 md:left-[3.25rem] lg:top-6 lg:left-[3.25rem] max-lg:hidden">
                <StoryPlayGlassRoundButton aria-label="Go back" @click="handleBack">
                    <LucideChevronLeft class="!size-5 text-white" :stroke-width="1.85" aria-hidden="true" />
                </StoryPlayGlassRoundButton>
            </div>

            <!-- Cover — fixed in viewport on desktop -->
            <div
                class="relative mb-8 w-[20.875rem] max-w-full shrink-0 self-center lg:mb-0 lg:flex lg:w-[20.875rem] lg:shrink-0 lg:self-center xl:mr-2"
            >
                <StoryPlayCoverColumn :src="coverImageUrl" :title="story.title" :headline="coverHeadline" :footer-credit="coverFooterCredit">
                    <template #overlay>
                        <div class="pointer-events-auto absolute left-4 top-4 lg:hidden">
                            <StoryPlayGlassRoundButton
                                aria-label="Go back"
                                @click="handleBack"
                            >
                                <LucideChevronLeft class="!size-5 text-white" :stroke-width="1.85" aria-hidden="true" />
                            </StoryPlayGlassRoundButton>
                        </div>
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

            <!-- Main column — app panel -->
            <div class="relative flex min-h-0 min-w-0 flex-1 flex-col">
                <div class="shrink-0 overflow-visible">
                    <StoryPlayTopBar
                        :tab="panel"
                        :bookmark-filled="isBookmarked"
                        @update:tab="panel = $event"
                        @bookmark="toggleBookmark"
                        @share="handleShare"
                    />
                </div>

                <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <!-- Details — full panel scrolls -->
                    <div
                        v-if="panel === 'details'"
                        class="story-show-chapters story-show-panel story-show-panel--details mt-4 flex min-h-0 flex-1 flex-col pb-4 lg:mt-0 lg:overflow-y-auto lg:overscroll-contain lg:pr-1"
                    >
                        <div class="story-show-chapters__align hidden shrink-0 lg:block" aria-hidden="true" />
                        <div class="flex flex-col gap-4 lg:gap-5">
                            <div class="flex flex-col gap-2.5">
                                <StoryPlayTitleProgress :title="story.title" />
                                <StoryPlayMetaRow :duration-label="durationLabel" :genre-label="genreLabel" />
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
                                <div class="mb-4 font-['Inter',sans-serif] text-[0.8125rem] font-semibold tracking-wide text-gray-400 uppercase">
                                    Comments · {{ story.comments_count ?? story.comments.length }}
                                </div>
                                <div class="flex flex-col gap-4">
                                    <StoryCommentCard v-for="comment in story.comments" :key="comment.id" :comment />
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- Chapters — fixed align spacer, list scrolls below -->
                    <div v-else class="story-show-chapters mt-4 flex min-h-0 flex-1 flex-col lg:mt-0">
                        <div class="story-show-chapters__align hidden shrink-0 lg:block" aria-hidden="true" />
                        <div class="story-show-panel story-show-panel--chapters min-h-0 flex-1 lg:overflow-y-auto lg:overscroll-contain lg:pr-1">
                            <div v-if="story.chapters?.length" class="flex flex-col gap-2.5 pb-4 sm:flex-row sm:items-start">
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
                    </div>

                    <!-- Desktop CTA — pinned to bottom of main column -->
                    <div class="story-show-cta hidden shrink-0 border-t border-white/8 bg-black/90 pt-4 backdrop-blur-sm lg:block">
                        <StoryPlayStartCta v-if="isPlayable" :label="primaryCtaLabel" @click="handleStartStory" />
                        <div
                            v-else
                            class="flex h-[3.5rem] w-full cursor-not-allowed items-center justify-center rounded-xl border border-white/15 bg-white/5 px-6 font-['Inter',sans-serif] text-sm font-medium tracking-widest text-gray-500 uppercase select-none"
                        >
                            Coming Soon
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile CTA strip -->
        <div class="fixed inset-x-0 bottom-0 z-30 lg:hidden">
            <div class="pointer-events-none absolute inset-x-0 bottom-[4.5rem] h-[6rem] bg-linear-to-t from-black via-black/88 to-transparent" />
            <div class="relative border-t border-white/10 bg-black/90 px-4 pt-4 pb-[calc(16px+env(safe-area-inset-bottom,0px))] backdrop-blur-md">
                <StoryPlayStartCta v-if="isPlayable" :label="primaryCtaLabel" @click="handleStartStory" />
                <div
                    v-else
                    class="flex h-[3.5rem] w-full cursor-not-allowed items-center justify-center rounded-xl border border-white/15 bg-white/5 px-6 font-['Inter',sans-serif] text-sm font-medium tracking-widest text-gray-500 uppercase select-none"
                >
                    Coming Soon
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Cover: 20.875rem × 334/497; top bar: 3.0625rem (pt-1 + tab height). Shell: lg:py-6. */
@media (min-width: 1024px) {
    .story-show-chapters__align {
        height: max(0px, calc((100svh - 3rem - 31.0625rem) / 2 - 3.0625rem));
    }
}

.story-show-panel--chapters {
    scrollbar-gutter: stable;
}

@supports (scrollbar-color: auto) {
    .story-show-panel {
        scrollbar-width: thin;
        scrollbar-color: rgba(111, 175, 186, 0.35) transparent;
    }
}

.story-show-panel::-webkit-scrollbar {
    width: 6px;
}

.story-show-panel::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(111, 175, 186, 0.35);
}

.story-show-panel::-webkit-scrollbar-track {
    background: transparent;
}
</style>
