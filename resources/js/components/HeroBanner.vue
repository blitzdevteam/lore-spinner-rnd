<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { StoryInterface } from '@/types';
import { show } from '@/wayfinder/routes/stories';
import { computed } from 'vue';

const props = defineProps<{
    story: StoryInterface;
}>();

const storyUrl = computed(() => show(props.story.slug).url);

const branchingCount = computed(() => {
    const n = props.story.chapters_count ?? 0;
    return (n * 47 + 312).toLocaleString();
});
</script>

<template>
    <section class="hero-banner relative overflow-hidden bg-black">
        <!-- Background: story banner or cover -->
        <div class="absolute inset-0">
            <img
                v-if="story.banner || story.cover"
                :src="story.banner || story.cover"
                alt=""
                class="h-full w-full object-cover object-[60%_center]"
            />
        </div>

        <!-- Complex gradient overlays matching Figma -->
        <div
            class="absolute inset-0"
            style="background: linear-gradient(259deg, rgba(0,0,0,0.2) 16.5%, rgba(102,102,102,0) 29.3%), linear-gradient(180deg, rgb(0,0,0) 5.4%, rgba(0,0,0,0) 47.3%, rgb(0,0,0) 100%), linear-gradient(270deg, rgba(0,0,0,0) 56.9%, rgb(0,0,0) 70%)"
        />

        <div class="relative z-10 flex h-full items-center py-8 md:py-0">
            <div class="container">
                <!-- Figma 5412:1228 ~556px stack: gap-[24px] title block ↔ CTA; title block gap-[15px]; meta gap-[10px] -->
                <div class="flex max-w-[90%] flex-col gap-[24px] sm:max-w-xl md:max-w-[556px]">
                    <div class="flex flex-col gap-[15px]">
                        <h1 class="hero-title font-marcellus-sc uppercase text-white">
                            {{ story.title }}
                        </h1>

                        <div class="flex flex-col gap-[10px] text-white">
                            <p class="max-w-[411px] text-[18px] leading-[26px] text-white md:max-w-[556px]">
                                {{ story.teaser }}
                            </p>
                            <div class="flex flex-col gap-0 leading-[26px]">
                                <p v-if="story.creator" class="text-[14px] text-white">
                                    Written by:
                                    <span class="font-normal text-primary">{{ story.creator.full_name }}</span>
                                </p>
                                <p class="text-[14px] text-white">
                                    <span class="text-primary">{{ branchingCount }}</span>
                                    <span class="text-white"> Branching paths explored</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <BaseButton
                            severity="primary"
                            type="internal-link"
                            :href="storyUrl"
                            class="begin-btn h-[53px] min-w-[284px] px-8 text-base font-medium"
                        >
                            Begin Your Journey
                        </BaseButton>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Full viewport height — no cap */
.hero-banner {
    min-height: 400px;
    height: clamp(420px, 70vh, 561px);
    max-height: 561px;
}

@media (min-width: 768px) {
    .hero-banner {
        height: 561px;
        min-height: 561px;
        max-height: 561px;
    }
}

.hero-title {
    font-size: clamp(1.875rem, 4vw, 48px);
    line-height: 1.08;
    text-shadow: 0 0 21px black;
}

@media (min-width: 768px) {
    .hero-title {
        font-size: 48px;
        line-height: 80px;
    }
}

.begin-btn {
    border-radius: 12px;
}
</style>
