<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { StoryInterface } from '@/types';
import { show } from '@/wayfinder/routes/stories';
import { LucidePlay, LucideInfo, LucideStar } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    story: StoryInterface;
}>();

const heroImage = computed(() => props.story?.banner || props.story?.cover || '');
const hasImage = computed(() => !!heroImage.value);
const storyUrl = computed(() => show(props.story.slug).url);
</script>

<template>
    <section class="hero-banner relative overflow-hidden">
        <!-- Full-bleed image — shifted right so subject stays visible while text lives in the dark left zone -->
        <div class="absolute inset-0">
            <img
                v-if="hasImage"
                :src="heroImage"
                :alt="story.title"
                class="hero-img h-full w-full object-cover"
            />
            <div
                v-else
                class="h-full w-full bg-gradient-to-br from-gray-800 via-gray-900 to-black"
            />
        </div>

        <!-- Bottom scrim — just enough to lift the UI chrome off the image -->
        <div class="absolute right-0 bottom-0 left-0 h-48 bg-gradient-to-t from-black/70 via-black/30 to-transparent md:h-56" />
        <!-- Page-bg bleed at very bottom edge -->
        <div class="absolute right-0 bottom-0 left-0 h-16 bg-gradient-to-t from-[var(--color-background)] to-transparent sm:h-20" />

        <!--
            UI chrome — sits at the bottom of the banner, center-aligned.
            Image title lives top-left; author lives bottom-left.
            We claim the bottom-center zone (the dark spiral entrance area)
            which is the natural compositional breathing room.
        -->
        <div class="relative z-10 flex h-full items-end">
            <div class="container pb-10 sm:pb-12 md:pb-14">
                <div class="hero-ui mx-auto flex flex-col items-center gap-3 text-center md:gap-4">

                    <!-- Editor's Choice badge -->
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/20 px-3 py-1 text-[10px] font-semibold tracking-wider text-primary-300 uppercase backdrop-blur-sm sm:text-xs">
                        <LucideStar class="size-3 fill-current" />
                        Editor's Choice
                    </span>

                    <!-- Teaser -->
                    <p class="hero-text line-clamp-2 max-w-sm text-xs leading-relaxed text-gray-200 sm:text-sm md:max-w-md md:text-base">
                        {{ story.teaser }}
                    </p>

                    <!-- CTAs -->
                    <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3">
                        <BaseButton
                            severity="primary"
                            type="internal-link"
                            :href="storyUrl"
                            class="gap-2 px-5 text-sm font-semibold sm:px-7 sm:text-base"
                        >
                            <LucidePlay class="size-4 fill-current sm:size-5" />
                            Play Now
                        </BaseButton>
                        <BaseButton
                            severity="muted-glass"
                            type="internal-link"
                            :href="storyUrl"
                            class="gap-2 px-5 text-sm sm:px-7 sm:text-base"
                        >
                            <LucideInfo class="size-4 sm:size-5" />
                            More Info
                        </BaseButton>
                    </div>

                    <!-- Meta tags -->
                    <div v-if="story.chapters_count || story.category || story.rating" class="flex flex-wrap items-center justify-center gap-2 text-[10px] text-gray-400 sm:gap-3 sm:text-xs">
                        <span v-if="story.category" class="rounded bg-white/10 px-2 py-0.5">{{ story.category.title }}</span>
                        <span v-if="story.chapters_count">{{ story.chapters_count }} Chapters</span>
                        <span v-if="story.rating" class="border border-gray-600 px-1.5 py-0.5 text-[10px] uppercase">{{ story.rating.label }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Show the full image centered — no crop bias */
.hero-img {
    object-position: center center;
}

/* Full viewport height — no cap */
.hero-banner {
    height: 100svh;
    min-height: 480px;
}

/* Small text-shadow so UI chrome pops off the image without a heavy card */
.hero-text,
.hero-ui p,
.hero-ui span {
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.75);
}
</style>
