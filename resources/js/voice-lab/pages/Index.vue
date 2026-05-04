<script setup lang="ts">
import VoiceLabLayout from '../layouts/VoiceLabLayout.vue';
import VoiceLabOrb from '../components/VoiceLabOrb.vue';
import { ref } from 'vue';
import { useVoiceLab, type VoiceLabIntro } from '../composables/useVoiceLab';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    intro?: VoiceLabIntro;
}>();

const introConfig: VoiceLabIntro = props.intro ?? { enabled: false, audioUrl: '', choices: [] };

const { state, audioLevel, errorMessage, choices, hasStartedIntro, activate, holdStart, holdEnd, sendChoice, clearHistory } =
    useVoiceLab(introConfig);

// True once a touch event fires — used to show "Hold" vs "Tap" label.
const isTouchDevice = ref(false);

function onTouchStart(e: TouchEvent) {
    isTouchDevice.value = true;
    e.preventDefault(); // suppress ghost click on touch devices
    holdStart();
}

function onTouchEnd() {
    holdEnd();
}

function onTouchCancel() {
    holdEnd();
}

const handleBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
};

const handleClearHistory = async () => {
    await clearHistory();
};
</script>

<template>
    <VoiceLabLayout @back="handleBack" class="xen-tint">
        <template #header>
            <div class="flex flex-col gap-1">
                <h1 class="xen-brand-title text-xl uppercase tracking-[0.4em] md:text-3xl">XEN</h1>
            </div>
        </template>

        <template #actions>
            <button
                class="xen-reset-btn rounded-full border px-3 py-1.5 text-xs transition-all"
                @click="handleClearHistory"
            >
                Reset
            </button>
        </template>

        <template #orb>
            <div
                class="flex flex-col items-center gap-6"
                @click="activate"
                @touchstart.prevent="onTouchStart"
                @touchend="onTouchEnd"
                @touchcancel="onTouchCancel"
            >
                <VoiceLabOrb
                    :state="state"
                    :audio-level="audioLevel"
                    :size="260"
                    class="cursor-pointer select-none"
                />

                <!-- State label -->
                <Transition name="label-fade" mode="out-in">
                    <p
                        v-if="state === 'error'"
                        key="error"
                        class="xen-label xen-label--error"
                    >
                        {{ errorMessage ?? 'Something went wrong' }}
                    </p>
                    <p
                        v-else-if="state === 'listening'"
                        key="listening"
                        class="xen-label xen-label--listening flex items-center gap-2"
                    >
                        <span class="xen-pulse-dot" />
                        {{ isTouchDevice ? 'Release to send...' : 'Listening...' }}
                    </p>
                    <p
                        v-else-if="state === 'thinking'"
                        key="thinking"
                        class="xen-label xen-label--thinking"
                    >
                        Thinking...
                    </p>
                    <p
                        v-else-if="state === 'speaking'"
                        key="speaking"
                        class="xen-label xen-label--speaking"
                    >
                        Speaking...
                    </p>
                    <p
                        v-else
                        key="idle"
                        class="xen-label xen-label--idle"
                    >
                        {{ isTouchDevice ? 'Hold Orb to speak to Xen' : 'Tap Orb to speak to Xen' }}
                    </p>
                </Transition>
            </div>
        </template>

        <template #controls>
            <TransitionGroup
                name="choice-pop"
                tag="div"
                class="flex flex-col gap-2"
            >
                <button
                    v-for="(choice, i) in choices"
                    :key="choice"
                    :disabled="state !== 'idle'"
                    class="xen-choice-btn w-full"
                    :style="{ transitionDelay: `${i * 60}ms` }"
                    @click="sendChoice(choice)"
                >
                    {{ choice }}
                </button>
            </TransitionGroup>
        </template>
    </VoiceLabLayout>
</template>

<style scoped>
/*
 * XEN experience — Tiffany Blue identity.
 *
 * The full primary-* spectrum is defined globally in resources/css/app.css
 * (Pantone 1837 #0ABAB5 as 500 anchor), so every primary-* Tailwind class
 * already resolves to tiffany across the whole app.
 *
 * This class pins convenience aliases used by the gradient effects below.
 * Shervin: fine-tune only these tokens to shift the XEN-specific look.
 */
.xen-tint {
    --xen-bright: var(--color-primary-300);   /* #5dede0 — highlight */
    --xen-mid:    var(--color-primary-500);   /* #0abab5 — Pantone 1837 */
    --xen-deep:   var(--color-primary-600);   /* #089490 — depth */
    --xen-text:   var(--color-primary-200);   /* #8beee8 — light text */
    --xen-dark:   var(--color-primary-950);   /* #042e2c — deep bg */
}

/* ─── BRAND TITLE ───────────────────────────────────────────────────────── */
.xen-brand-title {
    background: linear-gradient(
        135deg,
        var(--xen-text)  0%,
        var(--xen-bright) 40%,
        var(--xen-deep)  100%
    );
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    filter: drop-shadow(0 0 18px color-mix(in srgb, var(--xen-mid) 35%, transparent));
}

/* ─── RESET BUTTON ──────────────────────────────────────────────────────── */
.xen-reset-btn {
    border-color: color-mix(in srgb, var(--xen-mid) 30%, transparent);
    color: color-mix(in srgb, var(--xen-bright) 70%, #9ca3af);
    background: transparent;
}
.xen-reset-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary-400) 60%, transparent);
    color: var(--xen-text);
    background: color-mix(in srgb, var(--xen-mid) 8%, transparent);
}

/* ─── STATE LABELS ──────────────────────────────────────────────────────── */
.xen-label {
    font-size: 0.875rem;
    letter-spacing: 0.03em;
}

.xen-label--idle {
    color: color-mix(in srgb, var(--xen-bright) 55%, #6b7280);
}

.xen-label--listening {
    background: linear-gradient(90deg, var(--xen-bright), var(--xen-mid));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.xen-label--thinking {
    background: linear-gradient(90deg, var(--xen-bright), var(--xen-deep));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    opacity: 0.85;
}

.xen-label--speaking {
    background: linear-gradient(90deg, var(--xen-text), var(--xen-mid));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.xen-label--error {
    color: #f87171;
}

/* ─── LISTENING PULSE DOT ───────────────────────────────────────────────── */
.xen-pulse-dot {
    display: inline-block;
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    background: var(--color-primary-400);
    box-shadow: 0 0 6px 2px color-mix(in srgb, var(--color-primary-400) 60%, transparent);
    animation: xen-pulse 1.1s ease-in-out infinite;
}

@keyframes xen-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.45; transform: scale(0.8); }
}

/* ─── CHOICE BUTTONS ────────────────────────────────────────────────────── */
.xen-choice-btn {
    position: relative;
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.875rem;
    border-radius: 0.75rem;
    border: 1px solid color-mix(in srgb, var(--xen-mid) 22%, transparent);
    background: color-mix(in srgb, var(--xen-dark) 60%, #030712);
    color: color-mix(in srgb, var(--xen-text) 80%, #f3f4f6);
    backdrop-filter: blur(8px);
    transition: border-color 0.2s ease, background 0.2s ease, color 0.15s ease, box-shadow 0.2s ease;
    overflow: hidden;
}

/* Subtle tiffany gradient wash behind text */
.xen-choice-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--xen-mid) 10%, transparent) 0%,
        transparent 60%
    );
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
}

.xen-choice-btn:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--color-primary-400) 55%, transparent);
    background: color-mix(in srgb, var(--color-primary-900) 45%, #030712);
    color: #fff;
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--xen-mid) 18%, transparent),
        0 4px 16px color-mix(in srgb, var(--xen-mid) 14%, transparent);
}
.xen-choice-btn:hover:not(:disabled)::before {
    opacity: 1;
}

.xen-choice-btn:disabled {
    pointer-events: none;
    opacity: 0.4;
}

/* ─── TRANSITIONS ───────────────────────────────────────────────────────── */
.label-fade-enter-active,
.label-fade-leave-active {
    transition: opacity 0.2s ease;
}
.label-fade-enter-from,
.label-fade-leave-to {
    opacity: 0;
}

.choice-pop-enter-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.choice-pop-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.choice-pop-enter-from {
    opacity: 0;
    transform: translateY(8px) scale(0.96);
}
.choice-pop-leave-to {
    opacity: 0;
    transform: translateY(-4px) scale(0.98);
}
</style>
