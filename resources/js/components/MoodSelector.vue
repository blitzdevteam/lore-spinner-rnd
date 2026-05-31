<script setup lang="ts">
import MoodCard from '@/components/MoodCard.vue';
import { MOOD_CARD_CONFIGS } from '@/data/moodCards';
import type { MoodId } from '@/data/moodBanners';
import { getMoodNavLinks } from '@/data/moodBanners';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { ref } from 'vue';

defineProps<{
    activeMood: MoodId;
}>();

const moodScrollEl = ref<HTMLElement | null>(null);
const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(moodScrollEl);

const moodLinks = getMoodNavLinks(storiesIndex().url);
</script>

<template>
    <section class="mood-selector" aria-label="Browse by mood">
        <div class="container">
            <div class="container-content">
                <div class="relative">
                    <div
                        ref="moodScrollEl"
                        class="mood-scroll flex w-full max-w-full flex-nowrap items-center gap-[0.625rem] overflow-x-auto pb-1 md:w-max md:overflow-visible"
                    >
                        <MoodCard
                            v-for="config in MOOD_CARD_CONFIGS"
                            :key="config.id"
                            :mood-id="config.id"
                            :active="activeMood === config.id"
                            :href="moodLinks.find((link) => link.slug === config.id)?.href"
                        />
                    </div>

                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-6 bg-gradient-to-r from-black/70 to-transparent transition-opacity duration-300 md:hidden"
                        :class="leftShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-12 bg-gradient-to-l from-black to-transparent transition-opacity duration-300 md:hidden"
                        :class="rightShadowVisible ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.mood-selector {
    position: relative;
    z-index: 10;
}
</style>
