<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.png';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch, withDefaults } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        cover: string;
        category: string;
        rating: string;
        mood?: string;
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
        mood: undefined,
    },
);

const emit = defineEmits<{ preview: [] }>();

const storyUrl = computed(() =>
    props.playable && props.slug ? storyShow(props.slug).url : undefined,
);

const ctaLabel = computed(() => (props.playable ? 'Play' : 'Coming Soon'));

const metadataLine = computed(() => {
    const parts = [props.mood, props.category].filter(Boolean);
    return parts.length ? parts.join(' • ') : props.category;
});

const imageFailed = ref(false);

watch(
    () => props.cover,
    () => {
        imageFailed.value = false;
    },
);

const showImage = computed(() => Boolean(props.cover) && !imageFailed.value);

function onImageError(): void {
    imageFailed.value = true;
}
</script>

<template>
    <component
        :is="isDesktopHover ? (storyUrl ? Link : 'div') : 'button'"
        :href="isDesktopHover ? storyUrl : undefined"
        type="button"
        class="hb-card group block w-full border-0 bg-transparent p-0 text-left outline-none"
        :class="[
            expandOnHover && focused && 'hb-card--focused',
            !expandOnHover && 'hb-card--static',
            !isDesktopHover ? 'cursor-pointer' : storyUrl ? 'cursor-pointer no-underline' : 'cursor-default',
        ]"
        :aria-label="isDesktopHover && storyUrl ? `Open ${title}` : `Preview ${title}`"
        @click="!isDesktopHover && emit('preview')"
    >
        <div class="hb-card__frame">
            <div class="hb-card__cover">
                <img
                    v-if="showImage"
                    :src="cover"
                    :alt="title"
                    class="hb-card__cover-img hb-card__cover-img--zoom"
                    decoding="async"
                    @error="onImageError"
                />
                <div v-else class="hb-card__cover-fallback" aria-hidden="true">
                    <p class="hb-card__cover-fallback-title">{{ title }}</p>
                    <img :src="mainLogo" alt="" class="hb-card__cover-logo" />
                </div>
            </div>

            <div class="hb-card__body">
                <div class="hb-card__info">
                    <p class="hb-card__title">{{ title }}</p>
                    <p class="hb-card__meta">{{ metadataLine }}</p>
                </div>

                <div
                    v-if="expandOnHover"
                    class="hb-card__reveal"
                    :class="focused && 'hb-card__reveal--open'"
                >
                    <div class="hb-card__reveal-inner">
                        <div v-if="themes.length" class="hb-card__themes">
                            <template v-for="(theme, i) in themes" :key="theme">
                                <span v-if="i > 0" class="hb-card__theme-dot" aria-hidden="true">•</span>
                                <span class="hb-card__theme">{{ theme }}</span>
                            </template>
                        </div>
                        <p v-if="teaser" class="hb-card__teaser">{{ teaser }}</p>
                        <p v-if="branches" class="hb-card__branches">
                            {{ branches }} Branches explored
                        </p>
                    </div>
                </div>

                <div
                    class="hb-card__cta"
                    :class="playable ? 'hb-card__cta--active' : 'hb-card__cta--disabled'"
                >
                    {{ ctaLabel }}
                </div>
            </div>
        </div>
    </component>
</template>

<style scoped>
.hb-card {
    text-decoration: none;
}

.hb-card__frame {
    display: flex;
    height: 100%;
    width: 100%;
    flex-direction: column;
    overflow: hidden;
    border-radius: var(--story-card-radius);
    border: var(--story-card-border);
    background: var(--story-card-bg);
    box-shadow: var(--story-card-shadow);
    transition:
        transform 200ms ease,
        box-shadow 200ms ease;
}

.hb-card__cover {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    flex-shrink: 0;
    background: #0c0c0c;
}

.hb-card__cover-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 200ms ease;
}

.hb-card__cover-fallback {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    padding: 1rem;
    background: linear-gradient(165deg, #1a1a1f 0%, #0d0d10 45%, #141418 100%);
}

.hb-card__cover-fallback-title {
    margin: 0;
    max-width: 100%;
    font-size: clamp(0.875rem, 2.2vw, 1.0625rem);
    font-weight: 700;
    line-height: 1.35;
    text-align: center;
    color: rgba(255, 255, 255, 0.88);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.hb-card__cover-logo {
    width: auto;
    height: 1rem;
    opacity: 0.55;
    object-fit: contain;
}

.hb-card__body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--story-card-body-gap);
    padding: var(--story-card-body-pad-y) var(--story-card-body-pad-x)
        calc(var(--story-card-body-pad-y) + 0.125rem);
    min-height: 0;
}

.hb-card__info {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
}

.hb-card__title {
    margin: 0;
    font-size: var(--story-card-title-size);
    font-weight: 700;
    line-height: 1.3;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hb-card__meta {
    margin: 0;
    font-size: var(--story-card-meta-size);
    line-height: 1.35;
    color: var(--story-card-meta-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hb-card__reveal {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(4px);
    transition:
        grid-template-rows 200ms ease,
        opacity 200ms ease,
        transform 200ms ease;
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
    gap: 0.375rem;
    min-height: 0;
}

.hb-card__themes {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.375rem;
}

.hb-card__theme-dot {
    font-size: 0.625rem;
    line-height: 1;
    color: rgba(255, 255, 255, 0.28);
}

.hb-card__theme {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--color-primary, #6fafba);
}

.hb-card__teaser {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.52);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.hb-card__branches {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #ffbe58;
}

.hb-card__cta {
    display: flex;
    height: var(--story-card-btn-height);
    width: 100%;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: var(--story-card-btn-radius);
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1;
    margin-top: auto;
}

.hb-card__cta--active {
    background: var(--story-card-btn-bg);
    color: var(--story-card-btn-text);
    transition: background 180ms ease;
}

.hb-card__cta--active:hover {
    background: var(--story-card-btn-bg-hover);
}

.hb-card__cta--active:active {
    background: var(--story-card-btn-bg-active);
}

.hb-card__cta--disabled {
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.42);
}

@media (min-width: 1024px) {
    .hb-card--focused .hb-card__frame {
        box-shadow: var(--story-card-shadow-hover);
    }

    .hb-card--focused .hb-card__cover-img {
        transform: scale(1.05);
    }

    .hb-card--static:hover .hb-card__frame {
        transform: scale(1.03);
        box-shadow: var(--story-card-shadow-hover);
    }

    .hb-card--static:hover .hb-card__cover-img--zoom {
        transform: scale(1.05);
    }
}
</style>
