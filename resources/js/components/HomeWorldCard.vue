<script setup lang="ts">
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { show } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        story: StoryInterface;
        showTitle?: boolean;
        showButton?: boolean;
        /** When true (e.g. another card is hovered), match Featured Worlds row dimming */
        dimmed?: boolean;
    }>(),
    {
        showTitle: true,
        showButton: true,
        dimmed: false,
    },
);

const isPublished = computed(() => props.story.status?.value === StoryStatusEnum.PUBLISHED);
const storyUrl = computed(() => show(props.story.slug).url);
</script>

<template>
    <!-- Library card — Figma node 6625:737+: outer w-195 / inner w-182, cover h-271, Play rail #7e7e7e + primary fill -->
    <div
        class="world-card box-border flex w-[195px] shrink-0 items-center justify-center rounded-[8px] border border-[#373737] bg-[#1c1c1c] p-[6px] transition-opacity duration-200"
        :class="dimmed ? 'opacity-[0.3]' : 'opacity-100'"
    >
        <div class="flex w-[182px] max-w-full flex-col gap-[8px]">
            <div class="flex flex-col gap-[4px]">
                <component
                    :is="isPublished ? Link : 'div'"
                    :href="isPublished ? storyUrl : undefined"
                    class="block outline-none"
                >
                    <div class="relative h-[271px] w-full overflow-hidden rounded-[5px] border border-white/5">
                        <img
                            v-if="story.cover"
                            :src="story.cover"
                            :alt="story.title"
                            class="absolute inset-0 h-full w-full max-w-none object-cover"
                        />
                        <div
                            v-else
                            class="absolute inset-0 flex items-center justify-center rounded-[5px] bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                        >
                            <span class="text-4xl font-bold text-primary/50">{{
                                story.title?.charAt(0)?.toUpperCase()
                            }}</span>
                        </div>
                    </div>
                </component>

                <div v-if="showTitle" class="flex h-[20px] items-center">
                    <p class="w-full truncate text-[16px] font-semibold leading-normal text-white">{{ story.title }}</p>
                </div>
            </div>

            <div v-if="showButton" class="w-full shrink-0">
                <div
                    v-if="isPublished"
                    class="flex h-[36px] w-full items-stretch justify-center rounded-[6px] bg-[#7e7e7e] p-0"
                >
                    <Link
                        :href="storyUrl"
                        class="play-btn flex min-h-0 min-w-0 flex-1 items-center justify-center rounded-[6px] border border-primary bg-primary px-1 text-center text-[18px] font-medium text-black hover:opacity-90"
                    >
                        Play
                    </Link>
                </div>
                <div
                    v-else
                    class="flex h-[36px] w-full items-stretch justify-center rounded-[6px] bg-[#7e7e7e] p-0"
                >
                    <div
                        class="flex min-h-0 min-w-0 flex-1 cursor-default items-center justify-center rounded-[6px] border border-[#4d4d4d] bg-[#3f3f3f] text-center text-[18px] font-medium text-[#8e8e8e]"
                    >
                        Coming soon
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.play-btn {
    text-decoration: none;
}
</style>
