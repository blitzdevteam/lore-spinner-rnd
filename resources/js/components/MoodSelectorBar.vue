<script setup lang="ts">
import { MOOD_CARD_CONFIGS } from '@/data/moodContent';
import { getMoodNavLinks, type MoodId } from '@/data/moodBanners';
import { useSliderEdgeShadows } from '@/composables/useSliderEdgeShadows';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    activeMood: MoodId;
}>();

const scrollEl = ref<HTMLElement | null>(null);
const { leftShadowVisible, rightShadowVisible } = useSliderEdgeShadows(scrollEl);

const moodLinks = computed(() => {
    const hrefBySlug = new Map(getMoodNavLinks(storiesIndex().url).map((l) => [l.slug, l.href]));
    return MOOD_CARD_CONFIGS.map((card) => ({
        ...card,
        href: hrefBySlug.get(card.id) ?? `${storiesIndex().url}?mood=${card.id}`,
        isActive: props.activeMood === card.id,
    }));
});
</script>

<template>
    <section class="mood-selector" aria-label="Browse by mood">
        <div class="relative">
            <div
                ref="scrollEl"
                class="mood-selector__scroll flex w-full max-w-full flex-nowrap items-center gap-[0.625rem] overflow-x-auto pb-1 md:w-max md:overflow-visible"
            >
                <Link
                    v-for="mood in moodLinks"
                    :key="mood.id"
                    :href="mood.href"
                    class="mood-card relative z-0 block h-[9.25rem] w-[12.1875rem] shrink-0 overflow-hidden rounded-lg border border-transparent bg-black p-0 no-underline transition-[transform,border-color,box-shadow] duration-200 hover:scale-[1.02] active:scale-[0.99]"
                    :class="[
                        `mood-card--${mood.variant}`,
                        mood.isActive && 'mood-card--active',
                    ]"
                    :aria-current="mood.isActive ? 'page' : undefined"
                >
                    <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                        <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                        <div
                            class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                        />
                    </div>
                    <div
                        class="mood-card-glow pointer-events-none absolute inset-0 z-[1] rounded-lg"
                        :class="`mood-card-glow--${mood.variant}`"
                        aria-hidden="true"
                    />

                    <div class="mood-icon-orbit absolute left-1/2 top-[1.0625rem] z-[2] size-[4.75rem] -translate-x-1/2 overflow-hidden rounded-[9.375rem]">
                        <div
                            class="mood-icon-orbit-bg pointer-events-none absolute inset-0 rounded-[9.375rem]"
                            aria-hidden="true"
                        >
                            <div class="absolute inset-0 rounded-[9.375rem] bg-[rgba(255,175,175,0.03)]" />
                            <div
                                class="absolute inset-0 rounded-[9.375rem] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                :class="`mood-icon-orbit-bg--${mood.variant}`"
                            />
                        </div>
                        <div class="mood-icon-orbit__icon-wrap absolute inset-0 flex items-center justify-center">
                            <img
                                :src="mood.icon"
                                alt=""
                                width="32"
                                height="32"
                                class="mood-icon-orbit__icon block size-8 max-w-none"
                                :class="[
                                    `mood-icon-orbit__icon--${mood.variant}`,
                                    mood.rotateIcon && 'mood-icon-orbit__icon--rotated',
                                ]"
                            />
                        </div>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                        />
                    </div>

                    <p
                        class="mood-card__label absolute bottom-[1.125rem] left-1/2 z-[2] -translate-x-1/2 whitespace-nowrap text-[0.9375rem] font-medium capitalize leading-none not-italic"
                        :style="{ color: mood.labelColor }"
                    >
                        {{ mood.label }}
                    </p>

                    <div
                        class="pointer-events-none absolute inset-0 z-[3] rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                    />
                </Link>
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
    </section>
</template>

<style scoped>
.mood-selector__scroll::-webkit-scrollbar {
    display: none;
}

.mood-selector__scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.mood-icon-orbit-bg {
    transition: opacity 0.3s ease;
}

.mood-card:not(:hover):not(.mood-card--active) .mood-icon-orbit-bg {
    opacity: 0.42;
}

.mood-card:hover .mood-icon-orbit-bg,
.mood-card.mood-card--active .mood-icon-orbit-bg {
    opacity: 1;
}

.mood-icon-orbit__icon {
    flex-shrink: 0;
}

/* Optical nudges — SVG viewBoxes carry uneven visual weight. */
.mood-icon-orbit__icon--heartfelt {
    transform: translateY(-1px);
}

.mood-icon-orbit__icon--adventurous {
    transform: translateY(-2px);
}

.mood-icon-orbit__icon--mysterious {
    transform: translateY(-0.5px);
}

.mood-icon-orbit__icon--epic.mood-icon-orbit__icon--rotated {
    transform: rotate(-45deg) translateY(-1px);
}

.mood-icon-orbit__icon--whimsical {
    transform: translateY(0.5px);
}

.mood-card-glow {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mood-card:hover .mood-card-glow,
.mood-card.mood-card--active .mood-card-glow {
    opacity: 1;
}

.mood-card:hover,
.mood-card.mood-card--active {
    box-shadow:
        0px 4px 5rem 0px rgba(0, 0, 0, 0.2),
        inset 0 0 3rem var(--mood-glow-inset),
        0 0 1.25rem var(--mood-glow-outer);
}

.mood-card--heartfelt {
    --mood-glow-inset: rgba(201, 52, 52, 0.14);
    --mood-glow-outer: rgba(201, 52, 52, 0.1);
}

.mood-card--heartfelt:hover,
.mood-card--heartfelt.mood-card--active {
    border-color: #c93434;
}

.mood-card--heartfelt .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(201, 52, 52, 0.38);
}

.mood-card--heartfelt:hover .mood-icon-orbit,
.mood-card--heartfelt.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 7.8563rem 0px #792020,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-icon-orbit-bg--heartfelt {
    background: rgba(121, 13, 13, 0.16);
}

.mood-card-glow--heartfelt {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(201, 52, 52, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--adventurous {
    --mood-glow-inset: rgba(236, 200, 99, 0.14);
    --mood-glow-outer: rgba(236, 200, 99, 0.1);
}

.mood-card--adventurous:hover,
.mood-card--adventurous.mood-card--active {
    border-color: #ecc863;
}

.mood-card--adventurous .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(236, 200, 99, 0.4);
}

.mood-card--adventurous:hover .mood-icon-orbit,
.mood-card--adventurous.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 6.9938rem 0px #7c5e0b,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-icon-orbit-bg--adventurous {
    background: rgba(247, 179, 8, 0.07);
}

.mood-card-glow--adventurous {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(236, 200, 99, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--mysterious {
    --mood-glow-inset: rgba(98, 232, 219, 0.14);
    --mood-glow-outer: rgba(98, 232, 219, 0.1);
}

.mood-card--mysterious:hover,
.mood-card--mysterious.mood-card--active {
    border-color: #62e8db;
}

.mood-card--mysterious .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(98, 232, 219, 0.38);
}

.mood-card--mysterious:hover .mood-icon-orbit,
.mood-card--mysterious.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 8.8812rem 0px #248077,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-icon-orbit-bg--mysterious {
    background: rgba(98, 232, 219, 0.09);
}

.mood-card-glow--mysterious {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(98, 232, 219, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--epic {
    --mood-glow-inset: rgba(88, 217, 161, 0.14);
    --mood-glow-outer: rgba(88, 217, 161, 0.1);
}

.mood-card--epic:hover,
.mood-card--epic.mood-card--active {
    border-color: #58d9a1;
}

.mood-card--epic .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(88, 217, 161, 0.4);
}

.mood-card--epic:hover .mood-icon-orbit,
.mood-card--epic.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 5.9875rem 0px #257351,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-icon-orbit-bg--epic {
    background: rgba(88, 217, 161, 0.15);
}

.mood-card-glow--epic {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(88, 217, 161, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--whimsical {
    --mood-glow-inset: rgba(169, 121, 194, 0.14);
    --mood-glow-outer: rgba(169, 121, 194, 0.1);
}

.mood-card--whimsical:hover,
.mood-card--whimsical.mood-card--active {
    border-color: #a979c2;
}

.mood-card--whimsical .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(169, 121, 194, 0.4);
}

.mood-card--whimsical:hover .mood-icon-orbit,
.mood-card--whimsical.mood-card--active .mood-icon-orbit {
    box-shadow:
        0px 0px 7.3625rem 0px #7e5296,
        0px 3px 3.75rem 0px rgba(0, 0, 0, 0.2);
}

.mood-icon-orbit-bg--whimsical {
    background: rgba(169, 121, 194, 0.07);
}

.mood-card-glow--whimsical {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(169, 121, 194, 0.22) 0%,
        transparent 62%
    );
}
</style>
