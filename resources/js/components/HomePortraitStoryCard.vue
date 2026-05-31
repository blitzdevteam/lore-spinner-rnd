<script setup lang="ts">
import { show as storyShow } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed, withDefaults } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        cover: string;
        themes: string[];
        teaser?: string | null;
        branches?: string | null;
        playable: boolean;
        slug?: string;
        focused: boolean;
        isDesktopHover: boolean;
        /** Carousel hover reveal — disable in grid layouts. */
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

const ctaLabel = computed(() => (props.playable ? 'Play' : 'Coming soon'));
</script>

<template>
    <component
        :is="isDesktopHover ? (storyUrl ? Link : 'div') : 'button'"
        :href="isDesktopHover ? storyUrl : undefined"
        type="button"
        class="hp-card block border-0 bg-transparent p-0 text-left outline-none"
        :class="[
            expandOnHover && focused && 'hp-card--focused',
            !expandOnHover && 'hp-card--static',
            !isDesktopHover ? 'cursor-pointer' : storyUrl ? 'cursor-pointer no-underline' : 'cursor-default',
        ]"
        :aria-label="isDesktopHover && storyUrl ? `Open ${title}` : `Preview ${title}`"
        @click="!isDesktopHover && emit('preview')"
    >
        <div class="hp-card__inner">
            <div class="hp-card__content">
                <div class="hp-card__cover">
                    <img :src="cover" :alt="title" class="hp-card__cover-img" />
                </div>

                <p class="hp-card__title">{{ title }}</p>

                <div
                    v-if="expandOnHover"
                    class="hp-card__reveal"
                    :class="focused && 'hp-card__reveal--open'"
                >
                    <div class="hp-card__reveal-inner">
                        <div v-if="themes.length" class="hp-card__themes">
                            <template v-for="(theme, i) in themes" :key="theme">
                                <span v-if="i > 0" class="hp-card__theme-dot" aria-hidden="true">●</span>
                                <span class="hp-card__theme">{{ theme }}</span>
                            </template>
                        </div>
                        <p v-if="teaser" class="hp-card__teaser">{{ teaser }}</p>
                        <p v-if="branches" class="hp-card__branches">
                            {{ branches }} Branches explored
                        </p>
                    </div>
                </div>

                <div
                    class="hp-card__cta"
                    :class="playable ? 'hp-card__cta--active' : 'hp-card__cta--disabled'"
                >
                    {{ ctaLabel }}
                </div>
            </div>
        </div>
    </component>
</template>

<style scoped>
.hp-card {
    text-decoration: none;
}

.hp-card__inner {
    border-radius: 0.5rem;
    border: 1px solid #373737;
    background: #262626;
    padding: 0.375rem;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.hp-card__content {
    display: flex;
    width: min(12rem, 78vw);
    flex-direction: column;
    gap: 0.5rem;
}

@media (min-width: 768px) {
    .hp-card__content {
        width: 12rem;
    }
}

/* Cover: Figma 192 × 287 */
.hp-card__cover {
    position: relative;
    height: 17.9375rem;
    width: 100%;
    overflow: hidden;
    border-radius: 0.3125rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
    flex-shrink: 0;
}

.hp-card__cover-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.22s ease;
}

.hp-card__title {
    padding: 0 1px;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.4;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex-shrink: 0;
}

.hp-card__reveal {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(6px);
    transition:
        grid-template-rows 0.22s ease,
        opacity 0.2s ease,
        transform 0.2s ease;
}

.hp-card__reveal--open {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
}

.hp-card__reveal-inner {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-height: 0;
}

.hp-card__themes {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.375rem;
}

.hp-card__theme-dot {
    font-size: 0.5rem;
    line-height: 1;
    color: rgba(255, 255, 255, 0.25);
}

.hp-card__theme {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--color-primary, #6fafba);
}

.hp-card__teaser {
    font-size: 0.8125rem;
    line-height: 1.55;
    color: #9a9a9a;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
}

.hp-card__branches {
    font-size: 0.875rem;
    font-weight: 500;
    color: #ffbe58;
}

.hp-card__cta {
    display: flex;
    height: 2.25rem;
    width: 100%;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    font-size: 1.125rem;
    font-weight: 500;
}

.hp-card__cta--active {
    background: var(--color-cta-fill, #6fafba);
    color: var(--color-cta-text, #000);
    transition: background 0.18s ease;
}

.hp-card__cta--disabled {
    border: 1px solid #4d4d4d;
    background: #3f3f3f;
    color: #8e8e8e;
}

@media (min-width: 1024px) {
    .hp-card--focused .hp-card__inner {
        border-color: rgba(111, 175, 186, 0.55);
        box-shadow:
            0 20px 44px rgba(0, 0, 0, 0.58),
            0 0 36px rgba(111, 175, 186, 0.32),
            0 0 12px rgba(111, 175, 186, 0.22);
    }

    .hp-card--focused .hp-card__cover-img {
        transform: scale(1.05);
    }

    .hp-card--static:hover .hp-card__inner {
        border-color: rgba(111, 175, 186, 0.45);
        box-shadow:
            0 10px 28px rgba(0, 0, 0, 0.45),
            0 0 18px rgba(111, 175, 186, 0.18);
    }

    .hp-card--static:hover .hp-card__cover-img {
        transform: scale(1.04);
    }
}
</style>
