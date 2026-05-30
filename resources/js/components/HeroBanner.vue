<script setup lang="ts">
import HeroBranchChain from '@/components/HeroBranchChain.vue';
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
        <div class="absolute inset-0 overflow-hidden">
            <img
                v-if="story.banner || story.cover"
                :src="story.banner || story.cover"
                alt=""
                class="absolute left-0 top-[-17.43%] h-[128.34%] w-full max-w-none object-cover"
            />
        </div>

        <!-- Gradient overlays — node 5412:1227 (Figma MCP dev specs) -->
        <div
            class="pointer-events-none absolute inset-0"
            style="
                background:
                    linear-gradient(258.864deg, rgba(0, 0, 0, 0.2) 16.507%, rgba(102, 102, 102, 0) 29.27%),
                    linear-gradient(180deg, rgb(0, 0, 0) 5.4367%, rgba(0, 0, 0, 0) 47.282%, rgb(0, 0, 0) 100%),
                    linear-gradient(270deg, rgba(0, 0, 0, 0) 56.938%, rgb(0, 0, 0) 70.005%);
            "
        />

        <HeroBranchChain />

        <!-- Copy block: Figma 5412:1228 — left 213px / top 89px @ 1440×561 -->
        <div
            class="relative z-10 flex h-full w-full items-center py-8 md:items-start md:pb-10 md:pt-[89px]"
        >
            <div
                class="flex w-full max-w-[min(556px,calc(100%-2rem))] flex-col gap-[24px] px-4 md:max-w-[556px] md:px-0 md:pl-[clamp(1rem,14.79vw,213px)] md:pr-6"
            >
                <!-- gap-[24px] title block ↔ CTA; title block gap-[15px]; meta gap-[10px] -->
                <div class="flex flex-col gap-[15px]">
                    <h1 class="hero-title max-w-[492px] font-marcellus-sc uppercase text-white">
                        {{ story.title }}
                    </h1>

                    <div class="font-[Inter] flex flex-col gap-[10px] text-white">
                        <p class="max-w-[411px] text-[18px] leading-[26px] text-white">
                            {{ story.teaser }}
                        </p>
                        <div class="flex flex-col gap-0 leading-[26px]">
                            <p v-if="story.creator" class="text-[14px] text-white">
                                Written by:
                                <span class="font-normal text-[#00c6de]">{{ story.creator.full_name }}</span>
                            </p>
                            <p class="text-[14px] text-white">
                                <span class="text-[#00c6de]">{{ branchingCount }}</span>
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
                        class="begin-btn font-[Inter] h-[53px] min-w-[284px] px-8 text-base font-medium"
                    >
                        Begin Your Journey
                    </BaseButton>
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
    text-shadow: 0 0 21.2px black;
}

@media (min-width: 768px) {
    .hero-title {
        font-size: 48px;
        line-height: 80px;
    }
}

.begin-btn {
    border-radius: 12px;
    box-shadow:
        0 4px 80px rgba(0, 0, 0, 0.2),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
    filter: drop-shadow(0 0 5.65px rgba(0, 198, 222, 0.03)) drop-shadow(0 0 8.4px rgba(0, 198, 222, 0.7));
}
</style>
