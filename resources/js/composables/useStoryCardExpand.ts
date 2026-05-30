import { onUnmounted, ref, watch, type Ref } from 'vue';

export type ExpandOrigin = 'start' | 'center' | 'end';

export function expandOriginForIndex(index: number, total: number): ExpandOrigin {
    if (index === 0) return 'start';
    if (index === total - 1) return 'end';
    return 'center';
}

export function useStoryCardExpand(enabled: Ref<boolean>) {
    const hoveredId = ref<string | null>(null);

    let hideTimer: ReturnType<typeof setTimeout> | null = null;

    function onCardEnter(id: string) {
        if (!enabled.value) return;
        if (hideTimer) clearTimeout(hideTimer);
        hoveredId.value = id;
    }

    function onCardLeave() {
        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            hoveredId.value = null;
        }, 80);
    }

    function isExpanded(id: string): boolean {
        return enabled.value && hoveredId.value === id;
    }

    function isDimmed(id: string): boolean {
        return enabled.value && hoveredId.value !== null && hoveredId.value !== id;
    }

    watch(enabled, (active) => {
        if (!active) hoveredId.value = null;
    });

    onUnmounted(() => {
        if (hideTimer) clearTimeout(hideTimer);
    });

    return {
        hoveredId,
        onCardEnter,
        onCardLeave,
        isExpanded,
        isDimmed,
    };
}
