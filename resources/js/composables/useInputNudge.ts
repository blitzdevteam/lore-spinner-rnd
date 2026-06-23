import { onUnmounted } from 'vue';

/**
 * Escalating input-bar nudge — one twinkle (2 CSS pulses) per turn.
 *
 * Each time choices appear (choices-ready), start() is called:
 *   - Waits `currentDelaySec` seconds
 *   - Fires ONE twinkle, then stops for this turn
 *   - Increments delay by 1 s for next turn
 *
 * Turn 1 → wait 3 s → twinkle → stop
 * Turn 2 → wait 4 s → twinkle → stop
 * …
 * Turn 8 → wait 10 s → twinkle → stop forever
 *
 * `currentDelaySec` persists across turns (never resets on start).
 * Calling stop() (focus / submit / AI loading) cancels the pending twinkle
 * for that turn but does NOT roll back the delay counter.
 */

const DELAY_START = 3;
const DELAY_MAX   = 10;
const DELAY_STEP  = 1;

export function useInputNudge(options: { onTwinkle: () => void }) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let currentDelaySec = DELAY_START;
    let exhausted = false;

    function clearTimer() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    /** Call each time choices-ready fires. Schedules one twinkle for this turn. */
    function start() {
        clearTimer();
        if (exhausted) return;

        timer = setTimeout(() => {
            timer = null;
            options.onTwinkle();

            if (currentDelaySec >= DELAY_MAX) {
                exhausted = true;
                return;
            }

            currentDelaySec += DELAY_STEP;
        }, currentDelaySec * 1000);
    }

    /** Cancel pending twinkle for the current turn (player acted / AI loading). */
    function stop() {
        clearTimer();
    }

    onUnmounted(stop);

    return { start, stop };
}
