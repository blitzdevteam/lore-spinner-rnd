import { nextTick, onMounted, onUnmounted, ref, watch, type Ref } from 'vue';

export const SLIDER_SHADOW_THRESHOLD = 8;

export function useSliderEdgeShadows(
    sliderEl: Ref<HTMLElement | null>,
    options?: { enabled?: Ref<boolean> },
) {
    const leftShadowVisible = ref(false);
    const rightShadowVisible = ref(true);

    function updateShadows() {
        const el = sliderEl.value;
        if (!el) return;
        leftShadowVisible.value = el.scrollLeft > SLIDER_SHADOW_THRESHOLD;
        rightShadowVisible.value =
            el.scrollLeft + el.clientWidth < el.scrollWidth - SLIDER_SHADOW_THRESHOLD;
    }

    let resizeObserver: ResizeObserver | null = null;
    let intersectionObserver: IntersectionObserver | null = null;

    function observeScrollTargets(el: HTMLElement) {
        resizeObserver = new ResizeObserver(updateShadows);
        resizeObserver.observe(el);

        const track = el.querySelector('.story-slider-track');
        if (track instanceof HTMLElement) {
            resizeObserver.observe(track);
        }
    }

    function attach() {
        const el = sliderEl.value;
        if (!el) return;

        updateShadows();
        el.addEventListener('scroll', updateShadows, { passive: true });
        el.addEventListener('scrollend', updateShadows, { passive: true });
        window.addEventListener('resize', updateShadows, { passive: true });

        observeScrollTargets(el);

        intersectionObserver = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    requestAnimationFrame(updateShadows);
                }
            },
            { threshold: 0.01 },
        );
        intersectionObserver.observe(el);
    }

    function detach() {
        const el = sliderEl.value;
        if (el) {
            el.removeEventListener('scroll', updateShadows);
            el.removeEventListener('scrollend', updateShadows);
        }
        resizeObserver?.disconnect();
        resizeObserver = null;
        intersectionObserver?.disconnect();
        intersectionObserver = null;
        window.removeEventListener('resize', updateShadows);
    }

    async function attachWhenReady() {
        await nextTick();
        requestAnimationFrame(() => {
            updateShadows();
            requestAnimationFrame(updateShadows);
        });
        attach();
    }

    onMounted(() => {
        if (options?.enabled) {
            watch(
                options.enabled,
                (isEnabled) => {
                    detach();
                    if (isEnabled) void attachWhenReady();
                },
                { immediate: true },
            );
        } else {
            void attachWhenReady();
        }
    });

    onUnmounted(detach);

    return { leftShadowVisible, rightShadowVisible, updateShadows };
}
