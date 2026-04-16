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
        <div class="absolute inset-0">
            <img
                v-if="hasImage"
                :src="heroImage"
                :alt="story.title"
                class="h-full w-full object-cover object-center md:object-[50%_20%]"
            />
            <div
                v-else
                class="h-full w-full bg-gradient-to-br from-gray-800 via-gray-900 to-black"
            />
        </div>

        <!-- Mobile: strong bottom gradient for text readability over full-width image -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/50 to-black/20 md:hidden" />
        <!-- Desktop: left-to-right gradient to keep text area readable -->
        <div class="absolute inset-0 hidden bg-gradient-to-r from-black/90 via-black/60 to-transparent md:block" />
        <!-- Shared: top vignette + bottom fade into page background -->
        <div class="absolute inset-0 bg-gradient-to-t from-gray-950/80 via-transparent to-black/30" />
        <div class="absolute right-0 bottom-0 left-0 h-24 bg-gradient-to-t from-[var(--color-background)] to-transparent sm:h-32" />

        <div class="relative z-10 flex h-full items-end pb-10 sm:pb-14 md:items-center md:pb-0">
            <div class="container">
                <div class="flex max-w-[90%] flex-col gap-3 sm:max-w-lg md:max-w-xl md:gap-5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/20 px-2.5 py-0.5 text-[10px] font-semibold tracking-wider text-primary-300 uppercase backdrop-blur-sm sm:px-3 sm:py-1 sm:text-xs">
                            <LucideStar class="size-3 fill-current" />
                            Editor's Choice
                        </span>
                    </div>

                    <h1 class="font-gill-sans text-2xl leading-tight font-bold text-white sm:text-3xl md:text-5xl lg:text-6xl">
                        {{ story.title }}
                    </h1>

                    <p v-if="story.creator" class="text-xs font-medium text-primary-300 sm:text-sm">
                        By {{ story.creator.full_name }}
                    </p>

                    <p class="line-clamp-2 text-xs leading-relaxed text-gray-300 sm:line-clamp-3 sm:text-sm md:text-base">
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
.hero-banner {
    height: 56vh;
    min-height: 340px;
    max-height: 520px;
}

@media (min-width: 640px) {
    .hero-banner {
        height: 65vh;
        min-height: 400px;
        max-height: 620px;
    }
}

@media (min-width: 768px) {
    .hero-banner {
        height: 80vh;
        min-height: 480px;
        max-height: 720px;
    }
}
</style>
