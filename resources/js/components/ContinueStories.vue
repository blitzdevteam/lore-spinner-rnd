<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { GameInterface } from '@/types';
import { show } from '@/wayfinder/routes/user/games';
import { LucidePlay, LucideBookOpen, LucideClock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    game: GameInterface;
}>();

const story = computed(() => props.game.story);
const hasCover = computed(() => !!story.value?.cover);
const gameUrl = computed(() => show(props.game.id).url);

const timeAgo = computed(() => {
    if (!props.game.updated_at) return 'Just now';
    const diff = Date.now() - new Date(props.game.updated_at).getTime();
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;
    return `${Math.floor(days / 30)}mo ago`;
});

const chapterLabel = computed(() => {
    const event = props.game.currentEvent as any;
    if (event?.chapter?.position) {
        return `Chapter ${event.chapter.position}`;
    }
    return null;
});

const eventTitle = computed(() => {
    const event = props.game.currentEvent as any;
    return event?.title ?? null;
});
</script>

<template>
    <div class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">
                <div>
                    <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                        Continue Your Story
                    </h2>
                    <p class="text-[16px] font-normal leading-[26px] text-[#b6b6b6]">
                        Pick up right where you left off
                    </p>
                </div>

                <div class="group relative min-w-0 overflow-hidden rounded-xl border border-gray-700/60 bg-gray-800/50 transition-all hover:border-primary-500/40 sm:rounded-2xl">
                    <div class="flex flex-col sm:flex-row">
                        <!-- Cover image -->
                        <div class="relative h-40 shrink-0 overflow-hidden sm:h-auto sm:w-48 md:w-72">
                            <img
                                v-if="hasCover"
                                :src="story!.cover"
                                :alt="story!.title"
                                class="h-full w-full object-cover object-center transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="grid h-full w-full place-items-center bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                            >
                                <span class="text-4xl font-bold text-primary/40 sm:text-5xl">{{ story?.title?.charAt(0)?.toUpperCase() }}</span>
                            </div>
                            <div class="absolute inset-0 hidden bg-gradient-to-r from-transparent to-gray-800/50 sm:block" />
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-800/50 to-transparent sm:hidden" />
                        </div>

                        <!-- Content -->
                        <div class="flex flex-1 flex-col justify-between gap-3 p-4 sm:gap-4 sm:p-5 md:p-6">
                            <div class="flex flex-col gap-2 sm:gap-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span v-if="chapterLabel" class="rounded-full bg-primary-500/15 px-2 py-0.5 text-[10px] font-medium text-primary-300 sm:px-2.5 sm:text-xs">
                                        <LucideBookOpen class="mb-0.5 inline size-3" />
                                        {{ chapterLabel }}
                                    </span>
                                    <span class="flex items-center gap-1 text-[10px] text-gray-400 sm:text-xs">
                                        <LucideClock class="size-3" />
                                        Last played {{ timeAgo }}
                                    </span>
                                </div>

                                <h3 class="text-lg font-semibold text-white sm:text-xl md:text-2xl">{{ story?.title }}</h3>

                                <p v-if="story?.creator" class="text-xs text-gray-400 sm:text-sm">
                                    By {{ story.creator.full_name }}
                                </p>

                                <p v-if="eventTitle" class="truncate text-xs text-gray-300 sm:text-sm">
                                    Currently at: <span class="text-primary-300">{{ eventTitle }}</span>
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                <BaseButton
                                    severity="primary"
                                    type="internal-link"
                                    :href="gameUrl"
                                    class="gap-2 px-6 text-sm font-semibold sm:px-8 sm:text-base"
                                >
                                    <LucidePlay class="size-4 fill-current" />
                                    Continue
                                </BaseButton>
                                <span v-if="game.prompts_count" class="text-[10px] text-gray-500 sm:text-xs">
                                    {{ game.prompts_count }} turns played
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
