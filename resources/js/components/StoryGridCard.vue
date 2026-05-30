<script setup lang="ts">
import { StoryInterface } from '@/types';
import { StoryStatusEnum } from '@/types/enum';
import { show } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export type StoryGridCta = 'play' | 'continue' | 'read-again' | 'coming-soon';

const props = withDefaults(
    defineProps<{
        story: StoryInterface;
        progress?: number | null;
        cta?: StoryGridCta;
        dimmed?: boolean;
    }>(),
    {
        progress: null,
        cta: undefined,
        dimmed: false,
    },
);

const isPublished = computed(() => props.story.status?.value === StoryStatusEnum.PUBLISHED);
const storyUrl = computed(() => show(props.story.slug).url);

const resolvedCta = computed((): StoryGridCta => {
    if (props.cta) return props.cta;
    if (!isPublished.value) return 'coming-soon';
    if (props.progress != null && props.progress > 0 && props.progress < 100) return 'continue';
    if (props.progress != null && props.progress >= 100) return 'read-again';
    return 'play';
});

const ctaLabel = computed(() => {
    switch (resolvedCta.value) {
        case 'continue':
            return 'Continue';
        case 'read-again':
            return 'Read Again';
        case 'coming-soon':
            return 'Coming Soon';
        default:
            return 'Play';
    }
});

const showProgress = computed(
    () => props.progress != null && props.progress > 0 && props.progress < 100 && isPublished.value,
);

const progressPercent = computed(() => Math.min(100, Math.max(0, props.progress ?? 0)));

const isInteractive = computed(() => resolvedCta.value !== 'coming-soon');
</script>

<template>
    <article
        class="story-grid-card group flex min-w-0 flex-col"
        :class="dimmed ? 'opacity-[0.35]' : 'opacity-100'"
    >
        <component
            :is="isInteractive ? Link : 'div'"
            :href="isInteractive ? storyUrl : undefined"
            class="story-grid-card__cover-link block min-w-0 outline-none"
        >
            <div
                class="story-grid-card__cover relative aspect-[2/3] w-full overflow-hidden rounded-lg border border-[#373737] bg-[#1c1c1c] transition-[border-color,box-shadow,transform] duration-300 group-hover:border-primary/40 group-hover:shadow-[0_0_28px_rgba(111,175,186,0.35)]"
            >
                <img
                    v-if="story.cover"
                    :src="story.cover"
                    :alt="story.title"
                    class="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                />
                <div
                    v-else
                    class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                >
                    <span class="text-3xl font-bold text-primary/50 md:text-4xl">
                        {{ story.title?.charAt(0)?.toUpperCase() }}
                    </span>
                </div>

                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-60"
                    aria-hidden="true"
                />

                <div
                    v-if="showProgress"
                    class="absolute inset-x-0 bottom-0 h-1 bg-black/60"
                    role="progressbar"
                    :aria-valuenow="progressPercent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`${story.title} reading progress`"
                >
                    <div
                        class="h-full bg-primary transition-[width] duration-300"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
            </div>
        </component>

        <h3
            class="story-grid-card__title mt-2 line-clamp-2 min-h-[2.5rem] text-[0.875rem] font-semibold leading-snug text-white md:min-h-[2.75rem] md:text-[0.9375rem]"
        >
            {{ story.title }}
        </h3>

        <div class="story-grid-card__cta mt-2">
            <Link
                v-if="isInteractive"
                :href="storyUrl"
                class="story-grid-card__btn story-grid-card__btn--active flex h-9 w-full items-center justify-center rounded-md bg-cta-fill text-[0.875rem] font-medium text-cta-text no-underline transition-colors hover:bg-cta-hover active:bg-cta-active md:h-10 md:text-[0.9375rem]"
            >
                {{ ctaLabel }}
            </Link>
            <div
                v-else
                class="story-grid-card__btn flex h-9 w-full cursor-default items-center justify-center rounded-md border border-[#4d4d4d] bg-[#3f3f3f] text-[0.875rem] font-medium text-[#8e8e8e] md:h-10 md:text-[0.9375rem]"
            >
                {{ ctaLabel }}
            </div>
        </div>
    </article>
</template>
