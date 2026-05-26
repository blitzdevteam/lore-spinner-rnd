<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const emit = defineEmits<{ begin: [] }>();

const showHeadphones = ref(false);
const showStory      = ref(false);
const showShape      = ref(false);
const showBg         = ref(false);
const showCard       = ref(false);
const atCardPhase    = ref(false); // true once text phases are done

const timers: ReturnType<typeof setTimeout>[] = [];
let emitted = false;

function schedule(fn: () => void, ms: number) {
    timers.push(setTimeout(fn, ms));
}

function doEmit() {
    if (emitted) return;
    emitted = true;
    emit('begin');
}

function skipToCard() {
    if (atCardPhase.value) return;
    timers.forEach(clearTimeout);
    timers.length = 0;
    showHeadphones.value = false;
    showStory.value      = false;
    showShape.value      = false;
    atCardPhase.value    = true;
    // Small pause so opacity transitions can settle before card appears
    schedule(() => { showBg.value = true; },   150);
    schedule(() => { showCard.value = true; },  350);
    schedule(() => doEmit(),                   3800);
}

onMounted(() => {
    // ── Phase 1: "Headphones Recommended."
    // Fade in at t=400ms (CSS 1100ms), linger, fade out
    schedule(() => { showHeadphones.value = true;  },  400);
    schedule(() => { showHeadphones.value = false; }, 3800); // linger ~2.3s

    // ── Phase 2: "Let Me Tell You a Story…"
    // Gap after phase 1 fade-out (1100ms) → starts at t=5200ms
    schedule(() => { showStory.value = true;  }, 5300);
    schedule(() => { showStory.value = false; }, 9700); // linger ~3.3s

    // ── Phase 3: "You Help Shape What Happens Next."
    // Starts at t=11100ms
    schedule(() => { showShape.value = true;  }, 11100);
    schedule(() => { showShape.value = false; }, 14500); // linger ~2.3s

    // ── Phase 4: Background + Showcard — fire together so bg establishes with the card
    schedule(() => { atCardPhase.value = true; }, 15500);
    schedule(() => { showBg.value   = true; },    15600);
    schedule(() => { showCard.value = true; },    15600);

    // ── Auto-begin — fires while showcard is still visible (user transitions into game)
    schedule(() => doEmit(), 20500);
});

onUnmounted(() => {
    timers.forEach(clearTimeout);
});
</script>

<template>
    <div
        class="co-root"
        tabindex="0"
        aria-label="Story opening. Click or tap to skip to title card."
        @click="skipToCard"
        @keydown.space.prevent="skipToCard"
        @keydown.enter.prevent="skipToCard"
    >
        <!-- ── Atmospheric background — fades in with showcard ── -->
        <Transition name="co-bg">
            <div v-if="showBg" class="co-bg" />
        </Transition>

        <!-- ── Text phases — absolutely centred, one at a time ── -->

        <Transition name="co-phrase">
            <p v-if="showHeadphones" key="h" class="co-phrase co-phrase--small">
                Headphones Recommended.
            </p>
        </Transition>

        <Transition name="co-phrase">
            <p v-if="showStory" key="s" class="co-phrase">
                Let Me Tell You a Story&hellip;
            </p>
        </Transition>

        <Transition name="co-phrase">
            <p v-if="showShape" key="sh" class="co-phrase co-phrase--wide">
                You Help Shape What Happens Next.
            </p>
        </Transition>

        <!-- ── Showcard ── -->
        <Transition name="co-card">
            <div v-if="showCard" class="co-card-wrap">
                <img
                    src="/images/opening/showcard.webp"
                    alt="LoreSpinner"
                    class="co-card-img"
                    draggable="false"
                />
            </div>
        </Transition>

        <!-- ── Skip hint (disappears once card appears) ── -->
        <Transition name="co-hint">
            <p v-if="!atCardPhase" class="co-skip-hint">tap anywhere to skip</p>
        </Transition>
    </div>
</template>

<style scoped>
/* ── Root ────────────────────────────────────────────────────── */
.co-root {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    outline: none;
    cursor: default;
    /* Subtle grain overlay for cinematic warmth */
    isolation: isolate;
}

.co-root::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
    background-size: 180px;
    opacity: 0.4;
    z-index: 1;
}

/* ── Atmospheric BG ─────────────────────────────────────────── */
.co-bg {
    position: absolute;
    inset: 0;
    background: url('/images/opening/bg.webp') center center / cover no-repeat;
    opacity: 0.14;
    filter: blur(12px) saturate(0.6);
    transform: scale(1.05); /* prevent blur edge bleed */
}

/* ── Text phrases ───────────────────────────────────────────── */
.co-phrase {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 clamp(1.5rem, 8vw, 5rem);
    text-align: center;
    font-family: 'Gill Sans', sans-serif;
    font-weight: 300;
    font-size: clamp(2rem, 4.5vw, 3.8rem);
    line-height: 1.2;
    letter-spacing: 0.01em;
    color: rgba(252, 248, 240, 0.93);
    text-shadow:
        0 0 60px rgba(252, 248, 240, 0.12),
        0 2px 24px rgba(0, 0, 0, 0.6);
    z-index: 10;
    pointer-events: none;
}

/* "Headphones Recommended." — practical note, understated */
.co-phrase--small {
    font-size: clamp(0.75rem, 1.6vw, 1.1rem);
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: rgba(252, 248, 240, 0.42);
    font-weight: 400;
    text-shadow: none;
}

/* "You Help Shape…" — same weight as story phrase, no width constraint */
.co-phrase--wide {
    font-size: clamp(1.85rem, 4vw, 3.4rem);
}

/* ── Showcard ───────────────────────────────────────────────── */
.co-card-wrap {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 20;
    padding: 2rem;
}

.co-card-img {
    max-width: min(88vw, 520px);
    max-height: 80vh;
    width: 100%;
    height: auto;
    object-fit: contain;
    border-radius: 4px;
    filter: drop-shadow(0 8px 48px rgba(0, 0, 0, 0.9)) drop-shadow(0 2px 16px rgba(0,0,0,0.6));
    user-select: none;
}

/* ── Skip hint ──────────────────────────────────────────────── */
.co-skip-hint {
    position: absolute;
    bottom: 2.5rem;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.65rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.2);
    z-index: 30;
    pointer-events: none;
    white-space: nowrap;
}

/* ── Transitions ────────────────────────────────────────────── */

/* Text phrase: pure dissolve — no movement, text should feel grounded */
.co-phrase-enter-active { transition: opacity 1.2s cubic-bezier(0.4, 0, 0.2, 1); }
.co-phrase-leave-active { transition: opacity 1.1s cubic-bezier(0.4, 0, 1, 1); }
.co-phrase-enter-from   { opacity: 0; }
.co-phrase-enter-to     { opacity: 1; }
.co-phrase-leave-from   { opacity: 1; }
.co-phrase-leave-to     { opacity: 0; }

/* Background: very slow atmospheric reveal */
.co-bg-enter-active { transition: opacity 2.5s ease; }
.co-bg-leave-active { transition: opacity 1.5s ease; }
.co-bg-enter-from   { opacity: 0; }
.co-bg-enter-to     { opacity: 1; }
.co-bg-leave-from   { opacity: 1; }
.co-bg-leave-to     { opacity: 0; }

/* Showcard: pure fade — image has its own bg baked in, no movement */
.co-card-enter-active { transition: opacity 1.8s cubic-bezier(0.16, 1, 0.3, 1); }
.co-card-leave-active { transition: opacity 0.8s ease; }
.co-card-enter-from   { opacity: 0; }
.co-card-enter-to     { opacity: 1; }
.co-card-leave-from   { opacity: 1; }
.co-card-leave-to     { opacity: 0; }

/* Skip hint */
.co-hint-enter-active { transition: opacity 1s ease 1.5s; }
.co-hint-leave-active { transition: opacity 0.6s ease; }
.co-hint-enter-from   { opacity: 0; }
.co-hint-enter-to     { opacity: 1; }
.co-hint-leave-from   { opacity: 1; }
.co-hint-leave-to     { opacity: 0; }
</style>
