<script setup lang="ts">
// import HeroBranchChain from '@/components/HeroBranchChain.vue';
import FallbackBannerImage from '@/assets/banner.jpg';
import BaseButton from '@/components/BaseButton.vue';
import { StoryInterface } from '@/types';
import { show } from '@/wayfinder/routes/stories';
import { computed } from 'vue';

const props = defineProps<{
    story: StoryInterface;
}>();

const storyUrl = computed(() => show(props.story.slug).url);

/** DB media URLs; bundled asset when missing (e.g. other DB without Spatie media / files). */
const heroBackgroundSrc = computed(() => props.story.banner || props.story.cover || FallbackBannerImage);

/** Figma stat uses dot thousands separator (e.g. 1.267). */
const branchingCount = computed(() => {
    const n = props.story.chapters_count ?? 0;
    return new Intl.NumberFormat('de-DE').format(n * 47 + 312);
});

/** Title lines: ALICE'S ADVENTURES / IN WONDERLAND — break before “In …”. */
const heroTitleLines = computed((): [string, string] | null => {
    const t = props.story.title.trim();
    const m = t.match(/^(.+?)\s+in\s+(.+)$/i);
    if (!m) return null;
    return [m[1].trim(), `In ${m[2].trim()}`];
});

/** Teaser lines per design: after “…bends and” / “curiosity…”. */
const heroTeaserLines = computed((): [string, string] | null => {
    const s = props.story.teaser?.trim() ?? '';
    const needle = ' bends and ';
    const i = s.indexOf(needle);
    if (i === -1) return null;
    const first = (s.slice(0, i) + ' bends and').trimEnd();
    const second = s.slice(i + needle.length).trim();
    if (!second) return null;
    return [first, second];
});
</script>

<template>
    <section class="hero-banner relative overflow-hidden bg-black">
        <!-- Background: story banner or cover, else same fallback as HeroFallback -->
        <div class="absolute inset-0 overflow-hidden">
            <img
                :src="heroBackgroundSrc"
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

        <!-- <HeroBranchChain /> -->

        <!-- Copy block: vertically offset to match design; horizontal position from .container + .container-content -->
        <div
            class="relative z-10 flex h-full w-full items-center py-8 md:items-start md:pb-10 md:pt-[89px]"
        >
            <div class="container w-full">
                <div class="container-content">
                    <div class="flex w-full max-w-[min(556px,100%)] flex-col gap-[24px] md:max-w-[556px] md:pr-6">
                        <!-- gap-[24px] title block ↔ CTA; title block gap-[15px]; meta gap-[10px] -->
                        <div class="flex flex-col gap-[15px]">
                            <h1 class="hero-title max-w-[492px] font-marcellus-sc uppercase text-white">
                                <template v-if="heroTitleLines">
                                    {{ heroTitleLines[0] }}<br />
                                    {{ heroTitleLines[1] }}
                                </template>
                                <template v-else>{{ story.title }}</template>
                            </h1>

                            <div class="font-[Inter] flex flex-col gap-[10px] text-white">
                                <p class="max-w-[411px] text-[18px] leading-[26px] text-white">
                                    <template v-if="heroTeaserLines">
                                        {{ heroTeaserLines[0] }}<br />
                                        {{ heroTeaserLines[1] }}
                                    </template>
                                    <template v-else>{{ story.teaser }}</template>
                                </p>
                                <div class="flex flex-col gap-0">
                                    <p v-if="story.creator" class="text-[14px] leading-[26px] text-white">
                                        Written by: <span class="font-normal leading-[26px] text-[#00c6de]">{{ story.creator.full_name }}</span>
                                    </p>
                                    <p class="text-[14px] leading-[26px] text-white">
                                        <span class="leading-[26px] text-[#00c6de]">{{ branchingCount }}</span>
                                        <span class="leading-[26px] text-white"> Branching paths explored</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <BaseButton
                                severity="primary"
                                type="internal-link"
                                :href="storyUrl"
                                class="begin-btn font-[Inter] !h-[53px] w-full max-w-[284px] px-8 text-[16px] font-medium leading-normal text-black"
                            >
                                Begin Your Journey
                            </BaseButton>
                        </div>
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
}
</style>
