<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { StoryInterface } from '@/types';
import { show } from '@/wayfinder/routes/stories';
import { LucidePlay, LucideInfo, LucideStar } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    story: StoryInterface;
}>();

const hasCover = computed(() => !!props.story?.cover);
const storyUrl = computed(() => show(props.story.slug).url);
</script>

<template>
    <section class="hero-banner relative overflow-hidden">
        <div class="absolute inset-0">
            <img
                v-if="hasCover"
                :src="story.cover"
                :alt="story.title"
                class="h-full w-full object-cover object-[50%_20%]"
            />
            <div
                v-else
                class="h-full w-full bg-gradient-to-br from-gray-800 via-gray-900 to-black"
            />
        </div>

        <!-- Gradient overlays -->
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/60 to-transparent" />
        <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-transparent to-black/30" />
        <div class="absolute right-0 bottom-0 left-0 h-32 bg-gradient-to-t from-[var(--color-background)] to-transparent" />

        <div class="relative z-10 flex h-full items-end pb-16 md:items-center md:pb-0">
            <div class="container">
                <div class="flex max-w-xl flex-col gap-4 md:gap-5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/20 px-3 py-1 text-xs font-semibold tracking-wider text-primary-300 uppercase backdrop-blur-sm">
                            <LucideStar class="size-3 fill-current" />
                            Editor's Choice
                        </span>
                    </div>

                    <h1 class="font-gill-sans text-3xl leading-tight font-bold text-white md:text-5xl lg:text-6xl">
                        {{ story.title }}
                    </h1>

                    <p v-if="story.creator" class="text-sm font-medium text-primary-300">
                        By {{ story.creator.full_name }}
                    </p>

                    <p class="line-clamp-3 text-sm leading-relaxed text-gray-300 md:text-base">
                        {{ story.teaser }}
                    </p>

                    <div class="flex items-center gap-3 pt-1">
                        <BaseButton
                            severity="primary"
                            type="internal-link"
                            :href="storyUrl"
                            class="gap-2 px-6 text-base font-semibold"
                        >
                            <LucidePlay class="size-5 fill-current" />
                            Play Now
                        </BaseButton>
                        <BaseButton
                            severity="muted-glass"
                            type="internal-link"
                            :href="storyUrl"
                            class="gap-2 px-6 text-base"
                        >
                            <LucideInfo class="size-5" />
                            More Info
                        </BaseButton>
                    </div>

                    <div v-if="story.chapters_count || story.category" class="flex items-center gap-3 text-xs text-gray-400">
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
    height: 70vh;
    min-height: 420px;
    max-height: 680px;
}

@media (min-width: 768px) {
    .hero-banner {
        height: 80vh;
        max-height: 720px;
    }
}
</style>
