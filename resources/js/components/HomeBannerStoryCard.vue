<script setup lang="ts">
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, withDefaults } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        cover: string;
        category: string;
        rating: string;
        themes: string[];
        teaser?: string | null;
        branches?: string | null;
        playable: boolean;
        slug?: string;
        focused: boolean;
        isDesktopHover: boolean;
        /** Carousel hover reveal — disable on mood pages. */
        expandOnHover?: boolean;
    }>(),
    {
        expandOnHover: true,
    },
);

const emit = defineEmits<{ preview: [] }>();

const storyUrl = computed(() =>
    props.playable && props.slug ? storyShow(props.slug).url : undefined,
);

const statusLabel = computed(() => (props.playable ? 'Published' : 'Coming soon'));
const ctaLabel = computed(() => (props.playable ? 'Play' : 'Coming Soon'));
</script>

<template>
    <component
        :is="isDesktopHover ? (storyUrl ? Link : 'div') : 'button'"
        :href="isDesktopHover ? storyUrl : undefined"
        type="button"
        class="hb-card block w-full border-0 bg-transparent p-0 text-left outline-none"
        :class="[
            expandOnHover && focused && 'hb-card--focused',
            !expandOnHover && 'hb-card--static',
            !isDesktopHover ? 'cursor-pointer' : storyUrl ? 'cursor-pointer no-underline' : 'cursor-default',
        ]"
        :aria-label="isDesktopHover && storyUrl ? `Open ${title}` : `Preview ${title}`"
        @click="!isDesktopHover && emit('preview')"
    >
        <div class="hb-card__inner">
            <div class="hb-card__cover">
                <img :src="cover" :alt="title" class="hb-card__cover-img" />
            </div>

            <p class="hb-card__title">{{ title }}</p>

            <p class="hb-card__subtitle">
                {{ category }} | {{ rating }} | {{ statusLabel }}
            </p>

            <div
                v-if="expandOnHover"
                class="hb-card__reveal"
                :class="focused && 'hb-card__reveal--open'"
            >
                <div class="hb-card__reveal-inner">
                    <div v-if="themes.length" class="hb-card__themes">
                        <template v-for="(theme, i) in themes" :key="theme">
                            <span v-if="i > 0" class="hb-card__theme-dot" aria-hidden="true">●</span>
                            <span class="hb-card__theme">{{ theme }}</span>
                        </template>
                    </div>
                    <p v-if="teaser" class="hb-card__teaser">{{ teaser }}</p>
                    <p v-if="branches" class="hb-card__branches">
                        {{ branches }} Branches explored
                    </p>
                    <div
                        class="hb-card__cta"
                        :class="playable ? 'hb-card__cta--active' : 'hb-card__cta--disabled'"
                    >
                        {{ ctaLabel }}
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>

<style scoped>
.hb-card {
    text-decoration: none;
}

.hb-card__inner {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    width: 100%;
    border-radius: 0.5rem;
    border: 1px solid #373737;
    background: #262626;
    padding: 0.25rem;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.hb-card__cover {
    position: relative;
    width: 100%;
    aspect-ratio: 450 / 262;
    overflow: hidden;
    border-radius: 0.4375rem;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .hb-card__cover {
        aspect-ratio: unset;
        height: 16.375rem;
    }
}

.hb-card__cover-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.22s ease;
}

.hb-card__title {
    padding: 0 1px;
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.4;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hb-card__subtitle {
    padding: 0 1px;
    font-size: 0.9375rem;
    line-height: 1.4;
    color: #8f8f8f;
}

.hb-card__reveal {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(6px);
    transition:
        grid-template-rows 0.22s ease,
        opacity 0.2s ease,
        transform 0.2s ease;
}

.hb-card__reveal--open {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
}

.hb-card__reveal-inner {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-height: 0;
    padding: 0 1px 2px;
}

.hb-card__themes {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.375rem;
}

.hb-card__theme-dot {
    font-size: 0.5rem;
    line-height: 1;
    color: rgba(255, 255, 255, 0.25);
}

.hb-card__theme {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--color-primary, #6fafba);
}

.hb-card__teaser {
    font-size: 0.8125rem;
    line-height: 1.55;
    color: #9a9a9a;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
}

.hb-card__branches {
    font-size: 0.875rem;
    font-weight: 500;
    color: #ffbe58;
}

.hb-card__cta {
    display: flex;
    height: 2.25rem;
    width: 100%;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    font-size: 1.0625rem;
    font-weight: 600;
}

.hb-card__cta--active {
    background: var(--color-cta-fill, #6fafba);
    color: var(--color-cta-text, #000);
}

.hb-card__cta--disabled {
    border: 1px solid #4d4d4d;
    background: #3f3f3f;
    color: #8e8e8e;
}

@media (min-width: 1024px) {
    .hb-card--focused .hb-card__inner {
        border-color: rgba(111, 175, 186, 0.55);
        box-shadow:
            0 20px 44px rgba(0, 0, 0, 0.58),
            0 0 36px rgba(111, 175, 186, 0.32),
            0 0 12px rgba(111, 175, 186, 0.22);
    }

    .hb-card--focused .hb-card__cover-img {
        transform: scale(1.05);
    }

    .hb-card--static:hover .hb-card__inner {
        border-color: rgba(111, 175, 186, 0.45);
        box-shadow:
            0 10px 28px rgba(0, 0, 0, 0.45),
            0 0 18px rgba(111, 175, 186, 0.18);
        transform: scale(1.02);
    }

    .hb-card--static:hover .hb-card__cover-img {
        transform: scale(1.04);
    }
}

.hb-card--static .hb-card__inner {
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.2s ease;
}
</style>
