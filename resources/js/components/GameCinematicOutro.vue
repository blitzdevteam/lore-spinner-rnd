<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    /**  URL returned by StoryResource outro_poster — null means use the generic fallback. */
    outroPoster: string | null;
}>();

const emit = defineEmits<{
    // Fired when the full sequence has faded out → parent should unmount this component
    done: [];
}>();

const posterSrc = computed(() => props.outroPoster ?? '/images/opening/showcard.webp');

const showEnd        = ref(false);
const showChoices    = ref(false);
const showThankYou   = ref(false);
const showCard       = ref(false);
const atCardPhase    = ref(false);
const fadingOut      = ref(false);

const timers: ReturnType<typeof setTimeout>[] = [];
let doneEmitted = false;

function schedule(fn: () => void, ms: number) {
    timers.push(setTimeout(fn, ms));
}

function emitDone() {
    if (doneEmitted) return;
    doneEmitted = true;
    emit('done');
}

// ── Preload showcard so the first appearance is never a rough decode ──
const cardReady = ref(false);

function preloadCard() {
    const img = new window.Image();
    img.onload = img.onerror = () => { cardReady.value = true; };
    img.src = posterSrc.value;
}

// Poll until image is decoded, then reveal
function revealCardWhenReady() {
    if (cardReady.value) {
        doRevealCard();
    } else {
        timers.push(setTimeout(revealCardWhenReady, 80));
    }
}

function doRevealCard() {
    atCardPhase.value = true;
    showCard.value    = true;

    // After 5.2s of showcard visible, fade the whole screen to black then signal done
    schedule(() => beginFadeOut(), 5200);
}

function beginFadeOut() {
    fadingOut.value = true;
    schedule(() => emitDone(), 1100); // wait for fade-out CSS transition
}

// Click/tap: skip text phases, jump straight to showcard
function skipToCard() {
    if (atCardPhase.value) return;
    timers.forEach(clearTimeout);
    timers.length = 0;
    showEnd.value     = false;
    showChoices.value = false;
    showThankYou.value = false;
    // Tiny pause so current opacity transitions settle cleanly
    timers.push(setTimeout(() => revealCardWhenReady(), 250));
}

onMounted(() => {
    preloadCard(); // start loading immediately, in parallel with text phases

    // ── Phase 1: "The End."
    schedule(() => { showEnd.value = true;  },  400);
    schedule(() => { showEnd.value = false; }, 3800);

    // ── Phase 2: "Every story leaves something behind."
    schedule(() => { showChoices.value = true;  }, 5300);
    schedule(() => { showChoices.value = false; }, 10200);

    // ── Phase 3: "Thank you for playing."
    schedule(() => { showThankYou.value = true;  }, 11700);
    schedule(() => { showThankYou.value = false; }, 15100);

    // ── Phase 4: showcard
    schedule(() => revealCardWhenReady(), 16700);
});

onUnmounted(() => {
    timers.forEach(clearTimeout);
});
</script>

<template>
    <div
        class="co-root"
        :class="{ 'co-root--out': fadingOut }"
        tabindex="0"
        aria-label="Story ending sequence. Tap to skip."
        @click="skipToCard"
        @keydown.space.prevent="skipToCard"
        @keydown.enter.prevent="skipToCard"
    >
        <!-- ── Text phases ── -->
        <Transition name="co-phrase">
            <p v-if="showEnd" key="e" class="co-phrase co-phrase--large">
                The End.
            </p>
        </Transition>

        <Transition name="co-phrase">
            <p v-if="showChoices" key="c" class="co-phrase">
                Every story leaves something behind.
            </p>
        </Transition>

        <Transition name="co-phrase">
            <p v-if="showThankYou" key="t" class="co-phrase co-phrase--small">
                Thank you for playing.
            </p>
        </Transition>

        <!-- ── Showcard — full viewport, pure fade ── -->
        <Transition name="co-card">
            <div v-if="showCard" class="co-card-wrap">
                <img
                    :src="posterSrc"
                    alt="Story complete"
                    class="co-card-img"
                    draggable="false"
                    fetchpriority="high"
                />
            </div>
        </Transition>

        <!-- ── Skip hint ── -->
        <Transition name="co-hint">
            <p v-if="!atCardPhase" class="co-skip-hint">tap to skip</p>
        </Transition>
    </div>
</template>

<style scoped>
/* ── Root ─────────────────────────────────────────────────── */
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
    /* Fade-out of the entire screen back to black before handing off to game */
    transition: opacity 1s ease;
}

.co-root--out {
    opacity: 0;
    pointer-events: none;
}

/* Subtle film grain */
.co-root::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 50;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.72' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
    background-size: 160px;
    opacity: 0.35;
}

/* ── Text phrases ─────────────────────────────────────────── */
.co-phrase {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 clamp(2rem, 10vw, 6rem);
    text-align: center;
    font-family: 'Marcellus SC', serif;
    font-weight: 400;
    font-size: clamp(1.6rem, 3.8vw, 3.4rem);
    line-height: 1.25;
    letter-spacing: 0.04em;
    color: rgba(250, 246, 238, 0.9);
    text-shadow: 0 0 80px rgba(250, 246, 238, 0.08);
    z-index: 10;
    pointer-events: none;
}

/* "The End." — bigger, more cinematic */
.co-phrase--large {
    font-size: clamp(2.4rem, 6vw, 5.5rem);
    letter-spacing: 0.12em;
    text-shadow: 0 0 120px rgba(250, 246, 238, 0.12);
}

/* "Thank you for playing." — quiet whisper */
.co-phrase--small {
    font-size: clamp(0.7rem, 1.4vw, 1rem);
    letter-spacing: 0.28em;
    color: rgba(250, 246, 238, 0.35);
    text-shadow: none;
}

/* ── Showcard ─────────────────────────────────────────────── */
.co-card-wrap {
    position: absolute;
    inset: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Fill the full viewport; letterbox on non-16:9 screens (seamless on black) */
.co-card-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
    user-select: none;
    pointer-events: none;
}

/* ── Skip hint ────────────────────────────────────────────── */
.co-skip-hint {
    position: absolute;
    bottom: 2.5rem;
    left: 50%;
    transform: translateX(-50%);
    font-family: 'Marcellus SC', serif;
    font-size: 0.6rem;
    letter-spacing: 0.24em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.18);
    z-index: 60;
    pointer-events: none;
    white-space: nowrap;
}

/* ── Transitions ──────────────────────────────────────────── */

/* Text: pure dissolve — no movement, just light */
.co-phrase-enter-active { transition: opacity 1.2s ease; }
.co-phrase-leave-active { transition: opacity 1.0s ease; }
.co-phrase-enter-from   { opacity: 0; }
.co-phrase-enter-to     { opacity: 1; }
.co-phrase-leave-from   { opacity: 1; }
.co-phrase-leave-to     { opacity: 0; }

/* Showcard: clean dissolve in */
.co-card-enter-active { transition: opacity 2s cubic-bezier(0.4, 0, 0.2, 1); }
.co-card-leave-active { transition: opacity 0.6s ease; }
.co-card-enter-from   { opacity: 0; }
.co-card-enter-to     { opacity: 1; }
.co-card-leave-from   { opacity: 1; }
.co-card-leave-to     { opacity: 0; }

/* Skip hint */
.co-hint-enter-active { transition: opacity 0.8s ease 2s; }
.co-hint-leave-active { transition: opacity 0.4s ease; }
.co-hint-enter-from   { opacity: 0; }
.co-hint-enter-to     { opacity: 1; }
.co-hint-leave-from   { opacity: 1; }
.co-hint-leave-to     { opacity: 0; }
</style>
