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
    <!-- Library card — Figma node 6625:737+: outer w-195 / inner w-182, cover h-271 -->
    <div
        class="world-card box-border flex w-[12.1875rem] shrink-0 items-center justify-center rounded-[0.5rem] border border-[#373737] bg-[#1c1c1c] p-[0.375rem] transition-opacity duration-200"
        :class="dimmed ? 'opacity-[0.3]' : 'opacity-100'"
    >
        <div class="flex w-[11.375rem] max-w-full flex-col gap-[0.5rem]">
            <div class="flex flex-col gap-[0.25rem]">
                <component
                    :is="isPublished ? Link : 'div'"
                    :href="isPublished ? storyUrl : undefined"
                    class="block outline-none"
                >
                    <div class="relative h-[16.9375rem] w-full overflow-hidden rounded-[0.3125rem] border border-white/5">
                        <img
                            v-if="story.cover"
                            :src="story.cover"
                            :alt="story.title"
                            class="absolute inset-0 h-full w-full max-w-none object-cover"
                        />
                        <div
                            v-else
                            class="absolute inset-0 flex items-center justify-center rounded-[0.3125rem] bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                        >
                            <span class="text-4xl font-bold text-primary/50">{{
                                story.title?.charAt(0)?.toUpperCase()
                            }}</span>
                        </div>
                    </div>
                </component>

                <div v-if="showTitle" class="flex h-[1.25rem] items-center">
                    <p class="w-full truncate text-[1rem] font-semibold leading-normal text-white">{{ story.title }}</p>
                </div>
            </div>

            <div v-if="showButton" class="w-full shrink-0">
                <Link
                    v-if="isPublished"
                    :href="storyUrl"
                    class="play-btn flex h-[2.25rem] w-full items-center justify-center rounded-[0.375rem] bg-cta-fill px-1 text-center text-[1.125rem] font-medium text-cta-text transition-colors hover:bg-cta-hover active:bg-cta-active"
                >
                    Play
                </Link>
                <div
                    v-else
                    class="flex h-[2.25rem] w-full cursor-default items-center justify-center rounded-[0.375rem] border border-[#4d4d4d] bg-[#3f3f3f] text-center text-[1.125rem] font-medium text-[#8e8e8e]"
                >
                    Coming soon
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
