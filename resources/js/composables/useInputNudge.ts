import { onUnmounted } from 'vue';

/**
 * Escalating input-bar nudge loop.
 *
 * Starts after choice buttons appear. Fires a twinkle glow pulse at an
 * increasing delay: 3 s → 4 s → 5 s … up to and including a final nudge at
 * the 10 s interval, then stops. The player never feels nagged — just gently
 * reminded that the text bar exists for custom input.
 *
 * Usage:
 *   const nudge = useInputNudge({ onTwinkle: () => { ... } });
 *   nudge.start();   // call on choices-ready (gated externally)
 *   nudge.stop();    // call on focus / submit / new turn / AI loading
 */

const DELAY_START = 3;
const DELAY_MAX   = 10;
const DELAY_STEP  = 1;

export function useInputNudge(options: { onTwinkle: () => void }) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let currentDelaySec = DELAY_START;
    let running = false;

    function clearTimer() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function schedule() {
        if (!running) return;

        timer = setTimeout(() => {
            if (!running) return;

            options.onTwinkle();

            if (currentDelaySec >= DELAY_MAX) {
                running = false;
                return;
            }

            currentDelaySec += DELAY_STEP;
            schedule();
        }, currentDelaySec * 1000);
    }

    function start() {
        stop();
        currentDelaySec = DELAY_START;
        running = true;
        schedule();
    }

    function stop() {
        running = false;
        clearTimer();
    }

    onUnmounted(stop);

    return { start, stop };
}
