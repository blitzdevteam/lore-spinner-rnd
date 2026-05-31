<script setup lang="ts">
import {
    DEFAULT_LIBRARY_BANNER,
    getMoodBannerConfig,
    type ResolvedMoodBanner,
} from '@/data/moodBanners';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Raw mood from URL, route, or database — normalized internally. */
        mood?: string | null;
        /** Show title and subtitle over the banner. Defaults to true when a mood is active. */
        showText?: boolean;
    }>(),
    {
        mood: null,
        showText: undefined,
    },
);

const resolved = computed((): ResolvedMoodBanner => getMoodBannerConfig(props.mood));

const showHeroText = computed(() => {
    if (props.showText !== undefined) return props.showText;
    return resolved.value.mood !== null;
});

const imageSrc = ref(resolved.value.banner);
const usingFallbackImage = ref(false);

watch(
    resolved,
    (next) => {
        usingFallbackImage.value = false;
        imageSrc.value = next.banner;
    },
    { immediate: true },
);

function onImageError(): void {
    if (usingFallbackImage.value) return;
    usingFallbackImage.value = true;
    imageSrc.value = DEFAULT_LIBRARY_BANNER;
}

const imageAlt = computed(() => {
    if (resolved.value.mood) {
        return `${resolved.value.label} stories banner`;
    }
    return 'Story library banner';
});
</script>

<template>
    <section
        class="mood-hero relative z-10 w-full shrink-0 overflow-hidden"
        :aria-label="resolved.mood ? `${resolved.label} stories` : 'Story library'"
    >
        <img
            :key="imageSrc"
            :src="imageSrc"
            :alt="imageAlt"
            class="mood-hero__image pointer-events-none absolute inset-0 size-full object-cover select-none"
            :style="{ objectPosition: resolved.objectPosition }"
            width="1920"
            height="640"
            decoding="async"
            fetchpriority="high"
            @error="onImageError"
        />

        <!-- Cinematic overlays for text readability -->
        <div class="mood-hero__overlay mood-hero__overlay--vignette" aria-hidden="true" />
        <div class="mood-hero__overlay mood-hero__overlay--cinematic" aria-hidden="true" />
        <div class="mood-hero__overlay mood-hero__overlay--fade" aria-hidden="true" />

        <div
            v-if="showHeroText"
            class="mood-hero__content relative z-[1] flex h-full w-full items-end"
        >
            <div class="container w-full pb-6 pt-10 md:pb-8 md:pt-12">
                <div class="container-content">
                    <div class="flex max-w-[40rem] flex-col gap-2 md:gap-3">
                        <h2
                            class="mood-hero__title font-marcellus-sc uppercase text-white"
                        >
                            {{ resolved.title }}
                        </h2>
                        <p class="mood-hero__subtitle max-w-[36rem] text-[0.9375rem] leading-[1.5] text-white/85 md:text-[1.0625rem] md:leading-[1.6]">
                            {{ resolved.subtitle }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.mood-hero {
    position: relative;
    width: 100%;
    height: clamp(16rem, 44vh, 22rem);
    min-height: 16rem;
    background: #000;
}

.mood-hero__image {
    display: block;
}

.mood-hero__overlay {
    pointer-events: none;
    position: absolute;
    inset: 0;
}

.mood-hero__overlay--vignette {
    background: radial-gradient(ellipse 120% 100% at 50% 40%, transparent 35%, rgba(0, 0, 0, 0.45) 100%);
}

.mood-hero__overlay--cinematic {
    background: linear-gradient(
        180deg,
        rgba(0, 0, 0, 0.65) 0%,
        rgba(0, 0, 0, 0.28) 32%,
        rgba(0, 0, 0, 0.12) 58%,
        rgba(0, 0, 0, 0.55) 100%
    );
}

.mood-hero__overlay--fade {
    background: linear-gradient(to bottom, transparent 72%, rgba(0, 0, 0, 0.88) 100%);
}

.mood-hero__title {
    font-size: clamp(1.375rem, 3.2vw, 2.25rem);
    line-height: 1.12;
    text-shadow: 0 2px 24px rgba(0, 0, 0, 0.85);
}

.mood-hero__subtitle {
    text-shadow: 0 1px 12px rgba(0, 0, 0, 0.75);
}

@media (min-width: 768px) {
    .mood-hero {
        height: clamp(22rem, 50vh, 32rem);
        min-height: 22rem;
    }
}

@media (min-width: 1024px) {
    .mood-hero {
        height: clamp(26rem, 56vh, 38rem);
        min-height: 26rem;
    }
}
</style>
