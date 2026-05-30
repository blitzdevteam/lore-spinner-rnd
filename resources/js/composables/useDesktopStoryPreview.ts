import { onMounted, onUnmounted, ref, type Ref } from 'vue';

/** Desktop hover previews: >= 1024px with fine pointer; touch uses bottom sheet. */
const DESKTOP_PREVIEW_QUERY = '(min-width: 1024px) and (hover: hover)';

export function useDesktopStoryPreview(): Ref<boolean> {
    const isDesktopHover = ref(false);

    let mql: MediaQueryList | null = null;
    let sync: (() => void) | null = null;

    onMounted(() => {
        if (typeof window === 'undefined') return;
        mql = window.matchMedia(DESKTOP_PREVIEW_QUERY);
        sync = () => {
            isDesktopHover.value = mql!.matches;
        };
        sync();
        mql.addEventListener('change', sync);
    });

    onUnmounted(() => {
        if (mql && sync) mql.removeEventListener('change', sync);
    });

    return isDesktopHover;
}
