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

        <!-- Mobile: heavy bottom-up black for text legibility -->
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/70 to-black/10 md:hidden" />
        <!-- Shared: top edge darkening + bottom bleed into page bg -->
        <div class="absolute inset-0 bg-gradient-to-b from-black/20 via-transparent to-transparent" />
        <div class="absolute right-0 bottom-0 left-0 h-28 bg-gradient-to-t from-[var(--color-background)] to-transparent sm:h-36" />

        <!-- Text column — pinned to lower-left on mobile, vertical-center on desktop -->
        <div class="relative z-10 flex h-full items-end pb-12 sm:pb-16 md:items-center md:pb-0">
            <div class="container">
                <div class="hero-text flex max-w-[85%] flex-col gap-3 sm:max-w-md md:max-w-lg md:gap-4">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/20 px-2.5 py-0.5 text-[10px] font-semibold tracking-wider text-primary-300 uppercase backdrop-blur-sm sm:px-3 sm:py-1 sm:text-xs">
                            <LucideStar class="size-3 fill-current" />
                            Editor's Choice
                        </span>
                    </div>

                    <p v-if="story.creator" class="text-xs font-medium tracking-wide text-primary-300 drop-shadow-md sm:text-sm">
                        By {{ story.creator.full_name }}
                    </p>

                    <p class="line-clamp-2 text-xs leading-relaxed text-gray-300 drop-shadow-md sm:line-clamp-3 sm:text-sm md:text-base">
                        {{ story.teaser }}
                    </p>

                    <div class="flex flex-wrap items-center gap-2 pt-1 sm:gap-3">
                        <BaseButton
                            severity="primary"
                            type="internal-link"
                            :href="storyUrl"
                            class="gap-2 px-4 text-sm font-semibold sm:px-6 sm:text-base"
                        >
                            <LucidePlay class="size-4 fill-current sm:size-5" />
                            Play Now
                        </BaseButton>
                        <BaseButton
                            severity="muted-glass"
                            type="internal-link"
                            :href="storyUrl"
                            class="gap-2 px-4 text-sm sm:px-6 sm:text-base"
                        >
                            <LucideInfo class="size-4 sm:size-5" />
                            More Info
                        </BaseButton>
                    </div>

                    <div v-if="story.chapters_count || story.category" class="flex flex-wrap items-center gap-2 text-[10px] text-gray-400 sm:gap-3 sm:text-xs">
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
/* Image anchored to the right so the scenic focal point stays visible */
.hero-img {
    object-position: 65% center;
}

@media (min-width: 768px) {
    .hero-img {
        object-position: 60% 25%;
    }
}

/* Banner fills the viewport — no max-height cap */
.hero-banner {
    height: 60vh;
    min-height: 360px;
}

@media (min-width: 640px) {
    .hero-banner {
        height: 70vh;
        min-height: 440px;
    }
}

@media (min-width: 768px) {
    .hero-banner {
        height: 88vh;
        min-height: 540px;
    }
}
</style>
