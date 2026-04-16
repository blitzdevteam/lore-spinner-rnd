<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import BaseContentTitle from '@/components/BaseContentTitle.vue';
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
    <div class="py-10 md:py-18">
        <div class="container">
            <div class="flex flex-col gap-8 md:gap-10">
                <BaseContentTitle title="Continue Your Story">
                    <template #description>
                        Pick up right where you left off
                    </template>
                </BaseContentTitle>

                <div class="group relative overflow-hidden rounded-2xl border border-gray-700/60 bg-gray-800/50 transition-all hover:border-primary-500/40">
                    <div class="flex flex-col md:flex-row">
                        <!-- Cover image -->
                        <div class="relative h-48 shrink-0 overflow-hidden md:h-auto md:w-72">
                            <img
                                v-if="hasCover"
                                :src="story!.cover"
                                :alt="story!.title"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="grid h-full w-full place-items-center bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                            >
                                <span class="text-5xl font-bold text-primary/40">{{ story?.title?.charAt(0)?.toUpperCase() }}</span>
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent to-gray-800/50 md:block hidden" />
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-800/50 to-transparent md:hidden" />
                        </div>

                        <!-- Content -->
                        <div class="flex flex-1 flex-col justify-between gap-4 p-5 md:p-6">
                            <div class="flex flex-col gap-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span v-if="chapterLabel" class="rounded-full bg-primary-500/15 px-2.5 py-0.5 text-xs font-medium text-primary-300">
                                        <LucideBookOpen class="mb-0.5 inline size-3" />
                                        {{ chapterLabel }}
                                    </span>
                                    <span class="flex items-center gap-1 text-xs text-gray-400">
                                        <LucideClock class="size-3" />
                                        Last played {{ timeAgo }}
                                    </span>
                                </div>

                                <h3 class="text-xl font-semibold text-white md:text-2xl">{{ story?.title }}</h3>

                                <p v-if="story?.creator" class="text-sm text-gray-400">
                                    By {{ story.creator.full_name }}
                                </p>

                                <p v-if="eventTitle" class="text-sm text-gray-300">
                                    Currently at: <span class="text-primary-300">{{ eventTitle }}</span>
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <BaseButton
                                    severity="primary"
                                    type="internal-link"
                                    :href="gameUrl"
                                    class="gap-2 px-8 text-base font-semibold"
                                >
                                    <LucidePlay class="size-4 fill-current" />
                                    Continue
                                </BaseButton>
                                <span v-if="game.prompts_count" class="text-xs text-gray-500">
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
