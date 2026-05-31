import type { StoryPalette } from '@/types/story-atmosphere';
import {
    getStoryPalette,
    interpolateHex,
    LORE_SPINNER_DEFAULT_PALETTE,
} from '@/utils/extractStoryPalette';
import { inject, provide, ref, type InjectionKey, type MaybeRef, type Ref, toValue, watch } from 'vue';

const TRANSITION_MS = 1500;

export interface StoryAtmosphereContext {
    palette: Ref<StoryPalette>;
    isLoading: Ref<boolean>;
}

const STORY_ATMOSPHERE_KEY: InjectionKey<StoryAtmosphereContext> = Symbol('storyAtmosphere');

function easeInOutCubic(t: number): number {
    return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
}

function animatePalette(
    from: StoryPalette,
    to: StoryPalette,
    duration: number,
    onUpdate: (palette: StoryPalette) => void,
): () => void {
    const start = performance.now();
    let frameId = 0;

    const step = (now: number) => {
        const progress = Math.min(1, (now - start) / duration);
        const eased = easeInOutCubic(progress);

        onUpdate({
            primary: interpolateHex(from.primary, to.primary, eased),
            secondary: interpolateHex(from.secondary, to.secondary, eased),
            accent: interpolateHex(from.accent, to.accent, eased),
        });

        if (progress < 1) {
            frameId = requestAnimationFrame(step);
        }
    };

    frameId = requestAnimationFrame(step);

    return () => cancelAnimationFrame(frameId);
}

export function provideStoryAtmosphere(
    storyId: MaybeRef<number | undefined>,
    coverUrl: MaybeRef<string | null | undefined>,
): StoryAtmosphereContext {
    const palette = ref<StoryPalette>({ ...LORE_SPINNER_DEFAULT_PALETTE });
    const isLoading = ref(false);
    let cancelTransition: (() => void) | null = null;
    let requestId = 0;

    watch(
        [() => toValue(storyId), () => toValue(coverUrl)],
        async ([id, url]) => {
            if (!id) {
                return;
            }

            const currentRequest = ++requestId;
            isLoading.value = true;

            const nextPalette = await getStoryPalette(id, url?.trim() ?? '');

            if (currentRequest !== requestId) {
                return;
            }

            isLoading.value = false;
            cancelTransition?.();

            const fromPalette = { ...palette.value };
            cancelTransition = animatePalette(fromPalette, nextPalette, TRANSITION_MS, (value) => {
                palette.value = value;
            });
        },
        { immediate: true },
    );

    const context: StoryAtmosphereContext = { palette, isLoading };
    provide(STORY_ATMOSPHERE_KEY, context);

    return context;
}

export function useStoryAtmosphere(): StoryAtmosphereContext {
    const context = inject(STORY_ATMOSPHERE_KEY);

    if (!context) {
        throw new Error('useStoryAtmosphere must be used within StoryAtmosphereProvider');
    }

    return context;
}
