import { onMounted, onUnmounted, ref, watch, type Ref } from 'vue';

const HORIZONTAL_PAD = 16;
const MAX_CONTENT_WIDTH = 768; // max-w-3xl

/**
 * Keeps the gameplay input bar docked above the on-screen keyboard on mobile.
 * Uses visualViewport so the anchor tracks iOS Safari / Chrome keyboard insets.
 */
export function useMobileInputAnchor(anchorEl: Ref<HTMLElement | null>, enabled: Ref<boolean>) {
    const anchorStyle = ref<Record<string, string>>({});
    const isDocked = ref(false);

    function syncPosition() {
        if (!enabled.value || !anchorEl.value || !isDocked.value) {
            anchorStyle.value = {};
            return;
        }

        const vv = window.visualViewport;
        if (!vv) return;

        const bottomInset = Math.max(0, window.innerHeight - vv.offsetTop - vv.height);
        const contentW = Math.min(vv.width - HORIZONTAL_PAD * 2, MAX_CONTENT_WIDTH);
        const left = vv.offsetLeft + (vv.width - contentW) / 2;

        anchorStyle.value = {
            position: 'fixed',
            left: `${left}px`,
            width: `${contentW}px`,
            bottom: `${bottomInset}px`,
            zIndex: '20',
        };
    }

    function onFocusIn(event: FocusEvent) {
        if (!enabled.value || !anchorEl.value) return;
        if (!anchorEl.value.contains(event.target as Node)) return;
        isDocked.value = true;
        syncPosition();
    }

    function onFocusOut(event: FocusEvent) {
        if (!anchorEl.value) return;
        const next = event.relatedTarget as Node | null;
        if (next && anchorEl.value.contains(next)) return;
        isDocked.value = false;
        anchorStyle.value = {};
    }

    function attachViewportListeners() {
        window.visualViewport?.addEventListener('resize', syncPosition);
        window.visualViewport?.addEventListener('scroll', syncPosition);
    }

    function detachViewportListeners() {
        window.visualViewport?.removeEventListener('resize', syncPosition);
        window.visualViewport?.removeEventListener('scroll', syncPosition);
    }

    onMounted(() => {
        document.addEventListener('focusin', onFocusIn);
        document.addEventListener('focusout', onFocusOut);
        attachViewportListeners();
    });

    onUnmounted(() => {
        document.removeEventListener('focusin', onFocusIn);
        document.removeEventListener('focusout', onFocusOut);
        detachViewportListeners();
    });

    watch(enabled, (active) => {
        if (!active) {
            isDocked.value = false;
            anchorStyle.value = {};
        }
    });

    return { anchorStyle, isDocked };
}
