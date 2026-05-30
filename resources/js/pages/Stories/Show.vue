<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useBookmark } from '@/composables/useBookmark';
import { StoryInterface } from '@/types';
import {
    LucideBookmark,
    LucideShare2,
    LucideChevronLeft,
    LucideMessageCircleMore,
    LucideLayers2, LucidePlay
} from 'lucide-vue-next';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import StoryGallery from '@/components/StoryGallery.vue';
import StoryChapterCard from '@/components/StoryChapterCard.vue';
import StoryCommentCard from '@/components/StoryCommentCard.vue';
import { computed } from 'vue';
import { store, show as showGame } from '@/wayfinder/actions/App/Http/Controllers/User/GameController';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    story: StoryInterface;
    existingGameId?: string | null;
    isPlayable?: boolean;
}>();

const hasExistingGame = computed(() => !!props.existingGameId);
const { isBookmarked, toggleBookmark } = useBookmark(props.story.id, props.story.is_bookmarked ?? false);

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
</script>

<template>
    <div class="flex min-h-svh flex-col md:flex-row">
        <div class="relative flex-1 overflow-x-clip">
            <div v-if="story.cover" class="absolute top-0 right-0 bottom-0 -start-7.5 z-0 h-full w-[115%] blur-xl">
                <img :src="story.cover" alt="" class="object-cover object-center opacity-75" />
            </div>
            <div class="px-4 pt-6 md:px-12 md:pt-12">
                <div class="flex flex-col gap-6 md:gap-8">
                    <div class="relative overflow-hidden rounded-2xl aspect-video md:rounded-3xl">
                        <div class="z-10 absolute top-0 right-0 left-0 p-4 md:p-8 w-full">
                            <div class="flex items-center justify-between">
                                <BaseButton :icon-only="true" type="button" severity="glass" class="size-10! md:size-12!" @click="handleBack">
                                    <LucideChevronLeft class="size-6 md:size-8" :stroke-width="1.5" />
                                </BaseButton>
                                <div class="flex items-center gap-2 md:gap-3">
                                    <BaseButton severity="glass" :icon-only="true" class="size-10! md:size-12!" @click="toggleBookmark">
                                        <LucideBookmark
                                            class="size-5 md:size-6 transition-colors"
                                            :class="isBookmarked ? 'fill-secondary-300 text-secondary-300' : 'text-secondary-300'"
                                            :stroke-width="1.5"
                                        />
                                    </BaseButton>
                                    <BaseButton severity="glass" :icon-only="true" class="size-10! md:size-12!">
                                        <LucideShare2 class="size-5 md:size-6 text-secondary-300" :stroke-width="1.5" />
                                    </BaseButton>
                                </div>
                            </div>
                        </div>
                        <div class="grid relative">
                            <div class="absolute bg-linear-to-b from-black/35 to-transparent top-0 right-0 bottom-0 left-0 w-full h-full z-5"></div>
                            <StoryGallery
                                :gallery="[story.cover]"
                            />
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 md:gap-4 relative">
                        <h3 class="text-2xl md:text-3xl font-semibold text-white">{{ story.title }}</h3>
                        <div class="flex flex-wrap items-center gap-4 md:gap-12">
                            <div class="flex items-center gap-1.5 text-gray-400">
                                <LucidePlay class="size-5 md:size-6" />
                                <span class="text-sm md:text-base font-semibold">110K</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-gray-400">
                                <LucideMessageCircleMore class="size-5 md:size-6" />
                                <span class="text-sm md:text-base font-semibold">{{ story.comments_count }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-gray-400">
                                <LucideLayers2 class="size-5 md:size-6" />
                                <span class="text-sm md:text-base font-semibold">{{ story.category?.title }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="relative flex flex-col gap-3 md:gap-4">
                        <div class="flex items-center gap-3 md:gap-4">
                            <img v-if="story.creator?.avatar" :src="story.creator?.avatar" alt="" class="size-12 md:size-16 rounded-full">
                            <div v-else class="size-12 md:size-16 rounded-full bg-gradient-to-br from-gray-700 to-gray-900 grid place-items-center text-lg md:text-xl font-bold text-primary/60">
                                {{ story.creator?.full_name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <div class="text-gray-400 text-lg md:text-xl font-semibold">
                                {{ story.creator?.full_name }}
                            </div>
                        </div>
                        <p class="leading-relaxed text-base md:text-xl font-light text-gray-100">{{ story.teaser }}</p>
                    </div>
                </div>
            </div>
            <div class="sticky bottom-0 hidden w-full md:block">
                <div class="p-12">
                    <div class="relative z-5">
                        <BaseButton
                            v-if="isPlayable"
                            severity="primary"
                            class="w-full py-4 text-lg font-semibold shadow-primary shadow-[0_0px_50px_-12px]"
                            type="button"
                            @click="handleStartStory"
                        >
                            {{ hasExistingGame ? 'Continue' : 'Start' }}
                        </BaseButton>
                        <div
                            v-else
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-700 bg-gray-800/60 py-4 text-lg font-semibold text-gray-500 cursor-not-allowed select-none"
                        >
                            <span class="text-sm font-medium uppercase tracking-widest">Coming Soon</span>
                        </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 w-full h-full bg-linear-to-t from-black/75 from-50% to-transparent pointer-events-none"></div>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-700 bg-gray-900 pb-20 md:sticky md:top-0 md:h-svh md:w-120 md:border-t-0 md:border-s md:pb-0">
            <Tabs value="details_chapters" class="flex w-full flex-col gap-6 px-4 py-6 md:h-full md:gap-8 md:overflow-y-scroll md:px-8 md:py-8" :show-navigators="false" unstyled>
                <TabList pt:tab-list="flex items-center gap-4" pt:content="" pt:active-bar="hidden">
                    <Tab class="flex-1" value="details_chapters" v-slot="slotProps" as-child>
                        <BaseButton
                            @click="slotProps.onClick"
                            class="w-full"
                            :severity="slotProps.active ? 'primary-muted-outline' : 'gray-muted'"
                        >
                            Details / Chapters
                        </BaseButton>
                    </Tab>
                    <Tab class="flex-1" value="comments" v-slot="slotProps" as-child>
                        <BaseButton
                            @click="slotProps.onClick"
                            class="w-full"
                            :severity="slotProps.active ? 'primary-muted-outline' : 'gray-muted'"
                        >
                            Comments
                        </BaseButton>
                    </Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="details_chapters">
                        <div class="flex flex-col gap-6 md:gap-8">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-700 px-4 py-3 bg-gray-800/50">
                                    <p class="text-sm md:text-base text-gray-300 uppercase font-semibold">Chapters</p>
                                    <span class="text-base md:text-lg text-center text-white">{{ story.chapters_count }}</span>
                                </div>
                                <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-700 px-4 py-3 bg-gray-800/50">
                                    <p class="text-sm md:text-base text-gray-300 uppercase font-semibold">Rating</p>
                                    <span class="text-base md:text-lg text-center text-white">{{ story.rating.label }}</span>
                                </div>
                                <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-700 px-4 py-3 bg-gray-800/50">
                                    <p class="text-sm md:text-base text-gray-300 uppercase font-semibold">Status</p>
                                    <span class="text-base md:text-lg text-center text-white">{{ story.status.label }}</span>
                                </div>
                                <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-700 px-4 py-3 bg-gray-800/50">
                                    <p class="text-sm md:text-base text-gray-300 uppercase font-semibold">Updated</p>
                                    <span class="text-base md:text-lg text-center text-white">2 Months Ago</span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-4">
                                <StoryChapterCard
                                    v-for="(chapter, index) in story.chapters"
                                    :key="chapter.id"
                                    :chapter
                                    :is-open="index === 0"
                                />
                            </div>
                        </div>
                    </TabPanel>
                    <TabPanel value="comments">
                        <div class="flex flex-col gap-4">
                            <StoryCommentCard
                                v-for="comment in story.comments"
                                :key="comment.id"
                                :comment
                            />
                            <p v-if="!story.comments?.length" class="text-center text-gray-500 py-8">
                                No comments yet. Be the first to share your thoughts!
                            </p>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
        <div class="fixed inset-x-0 bottom-0 z-20 md:hidden">
            <div class="px-4 pb-4 pt-8">
                <div class="relative z-5">
                    <BaseButton
                        v-if="isPlayable"
                        severity="primary"
                        class="w-full py-3.5 text-lg font-semibold shadow-primary shadow-[0_0px_50px_-12px]"
                        type="button"
                        @click="handleStartStory"
                    >
                        {{ hasExistingGame ? 'Continue' : 'Start' }}
                    </BaseButton>
                    <div
                        v-else
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-700 bg-gray-800/60 py-3.5 text-lg font-semibold text-gray-500 cursor-not-allowed select-none"
                    >
                        <span class="text-sm font-medium uppercase tracking-widest">Coming Soon</span>
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 w-full h-full bg-linear-to-t from-black/90 from-50% to-transparent pointer-events-none"></div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
