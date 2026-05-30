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

    function attach() {
        const el = sliderEl.value;
        if (!el) return;

        updateShadows();
        el.addEventListener('scroll', updateShadows, { passive: true });
        window.addEventListener('resize', updateShadows, { passive: true });

        resizeObserver = new ResizeObserver(updateShadows);
        resizeObserver.observe(el);
        for (const child of el.children) {
            resizeObserver.observe(child);
        }
    }

    function detach() {
        const el = sliderEl.value;
        if (el) el.removeEventListener('scroll', updateShadows);
        resizeObserver?.disconnect();
        resizeObserver = null;
        window.removeEventListener('resize', updateShadows);
    }

    async function attachWhenReady() {
        await nextTick();
        requestAnimationFrame(updateShadows);
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
