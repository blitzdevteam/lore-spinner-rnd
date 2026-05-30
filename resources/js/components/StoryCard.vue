<script setup lang="ts">
import { StoryStatusEnum } from '@/types/enum';
import { show } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export type StoryCardCta = 'play' | 'continue' | 'read-again' | 'coming-soon';

const props = withDefaults(
    defineProps<{
        title: string;
        coverImage?: string | null;
        slug: string;
        status?: StoryStatusEnum | string;
        mood?: string;
        isComingSoon?: boolean;
        cta?: StoryCardCta;
        dimmed?: boolean;
    }>(),
    {
        coverImage: null,
        status: undefined,
        mood: undefined,
        isComingSoon: false,
        cta: undefined,
        dimmed: false,
    },
);

const storyUrl = computed(() => show(props.slug).url);

const isPublished = computed(() => {
    if (props.isComingSoon) return false;
    return props.status === StoryStatusEnum.PUBLISHED || props.status === 'published';
});

const resolvedCta = computed((): StoryCardCta => {
    if (props.cta) return props.cta;
    if (props.isComingSoon || !isPublished.value) return 'coming-soon';
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

const isInteractive = computed(() => resolvedCta.value !== 'coming-soon');
</script>

<template>
    <article
        class="story-card group min-w-0 transition-opacity duration-200"
        :class="dimmed ? 'opacity-[0.35]' : 'opacity-100'"
        :data-mood="mood || undefined"
    >
        <div
            class="story-card__frame flex h-full flex-col rounded-lg border border-[#373737] bg-[#1c1c1c] p-3 shadow-[0_4px_20px_rgba(0,0,0,0.22)] transition-[border-color,box-shadow,transform] duration-300 md:group-hover:-translate-y-0.5 md:group-hover:border-primary/35 md:group-hover:shadow-[0_10px_36px_rgba(111,175,186,0.22)]"
        >
            <component
                :is="isInteractive ? Link : 'div'"
                :href="isInteractive ? storyUrl : undefined"
                class="story-card__cover-link block min-w-0 outline-none"
            >
                <div
                    class="story-card__cover relative aspect-[2/3] w-full overflow-hidden rounded-md border border-white/5 bg-[#141414]"
                >
                    <img
                        v-if="coverImage"
                        :src="coverImage"
                        :alt="title"
                        class="absolute inset-0 size-full object-cover transition-transform duration-500 md:group-hover:scale-[1.02]"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
                    >
                        <span class="text-3xl font-bold text-primary/50 md:text-4xl">
                            {{ title.charAt(0).toUpperCase() }}
                        </span>
                    </div>
                </div>
            </component>

            <h3
                class="story-card__title mt-3 line-clamp-2 text-[0.9375rem] font-semibold leading-snug text-white md:mt-4 md:text-base"
            >
                {{ title }}
            </h3>

            <div class="story-card__cta mt-3 md:mt-4">
                <Link
                    v-if="isInteractive"
                    :href="storyUrl"
                    class="story-card__btn story-card__btn--active flex h-[2.25rem] w-full items-center justify-center rounded-[0.375rem] bg-cta-fill px-1 text-center text-[1.125rem] font-medium text-cta-text no-underline transition-colors hover:bg-cta-hover active:bg-cta-active"
                >
                    {{ ctaLabel }}
                </Link>
                <div
                    v-else
                    class="story-card__btn flex h-[2.25rem] w-full cursor-default items-center justify-center rounded-[0.375rem] border border-[#4d4d4d] bg-[#3f3f3f] text-center text-[1.125rem] font-medium text-[#8e8e8e]"
                >
                    {{ ctaLabel }}
                </div>
            </div>
        </div>
    </article>
</template>

<style scoped>
.story-card__btn--active {
    text-decoration: none;
}
</style>
