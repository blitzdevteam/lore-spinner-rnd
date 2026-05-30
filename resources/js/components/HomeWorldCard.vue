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
    <!-- Figma featured card: outer flex-col gap-[8px]; cover+title group gap-[4px] -->
    <div
        class="world-card flex flex-col gap-[8px] rounded-[8px] border border-[#373737] bg-[#1c1c1c] p-[6px] transition-opacity duration-200"
        :class="dimmed ? 'opacity-[0.3]' : 'opacity-100'"
    >
        <div class="flex flex-col gap-1">
        <!-- Cover image -->
        <component
            :is="isPublished ? Link : 'div'"
            :href="isPublished ? storyUrl : undefined"
            class="block outline-none"
        >
            <div class="relative h-[287.438px] w-[192px] overflow-hidden rounded-[5px] border border-white/5">
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
                    <span class="text-4xl font-bold text-primary/50">{{ story.title?.charAt(0)?.toUpperCase() }}</span>
                </div>
            </div>
        </component>

        <!-- Title (optional) -->
        <div v-if="showTitle" class="flex h-5 items-center px-px">
            <p class="w-[192px] truncate text-[16px] font-semibold leading-normal text-white">{{ story.title }}</p>
        </div>
        </div>

        <!-- Button (optional) -->
        <div v-if="showButton">
            <component
                :is="isPublished ? Link : 'div'"
                :href="isPublished ? storyUrl : undefined"
                :class="isPublished
                    ? 'play-btn cursor-pointer bg-primary border-primary text-black hover:opacity-90'
                    : 'coming-soon-btn cursor-default bg-[#3f3f3f] border-[#4d4d4d] text-[#8e8e8e]'"
                class="flex h-[36px] w-[192px] items-center justify-center rounded-[6px] border text-[18px] font-medium transition"
            >
                {{ isPublished ? 'Play' : 'Coming soon' }}
            </component>
        </div>
    </div>
</template>

<style scoped>
.play-btn,
.coming-soon-btn {
    text-decoration: none;
}
</style>
