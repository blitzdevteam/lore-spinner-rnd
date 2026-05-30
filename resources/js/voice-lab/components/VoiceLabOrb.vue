<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import orbDefaultSrc from '../assets/orb/Mask group.png';

const props = withDefaults(
    defineProps<{
        state?: 'idle' | 'listening' | 'thinking' | 'speaking' | 'error';
        size?: number;
        audioLevel?: number;
        imageSrc?: string;
    }>(),
    {
        state: 'idle',
        size: 280,
        audioLevel: 0,
        imageSrc: orbDefaultSrc,
    },
);

const frame = ref<number | null>(null);
const startedAt = ref(0);

// Outer dynamics
const scale = ref(1);
const glowOpacity = ref(0.55);
const auraScale = ref(1.04);
const driftX = ref(0);
const driftY = ref(0);
const rotateDeg = ref(0);
const shimmerOpacity = ref(0.16);

// Internal dynamics
const smokeRotA = ref(0);
const smokeRotB = ref(0);
const smokeOpacityA = ref(0.12);
const smokeOpacityB = ref(0.08);
const smokeScaleA = ref(1);
const smokeScaleB = ref(1);
const coreX = ref(50);
const coreY = ref(50);
const coreIntensity = ref(0.15);
const coreRadius = ref(18);
const causticRot = ref(0);
const causticOpacity = ref(0.06);
const ringAngle = ref(0);
const ringOpacity = ref(0);
const ringSpread = ref(20);

const clamp = (n: number, min: number, max: number) => Math.min(max, Math.max(min, n));
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

interface StateConfig {
    baseScale: number;
    pulseSpeed: number;
    pulseAmount: number;
    glow: number;
    aura: number;
    rotate: number;
    shimmer: number;
    drift: number;
    hue: string;
    smokeSpeed: number;
    smokeAlpha: number;
    coreGlow: number;
    coreSize: number;
    causticAlpha: number;
    ringAlpha: number;
    ringSpeed: number;
}

const stateMap: Record<string, StateConfig> = {
    idle: {
        baseScale: 1,
        pulseSpeed: 0.9,
        pulseAmount: 0.018,
        glow: 0.5,
        aura: 1.04,
        rotate: 0.8,
        shimmer: 0.12,
        drift: 2.5,
        // Tiffany blue family — anchored on design primary (#00C6DE).
        hue: '188, 92%, 52%',
        smokeSpeed: 0.15,
        smokeAlpha: 0.1,
        coreGlow: 0.12,
        coreSize: 18,
        causticAlpha: 0.04,
        ringAlpha: 0,
        ringSpeed: 0.3,
    },
    listening: {
        baseScale: 1.015,
        pulseSpeed: 1.35,
        pulseAmount: 0.028,
        glow: 0.72,
        aura: 1.08,
        rotate: 1.1,
        shimmer: 0.2,
        drift: 3.5,
        hue: '188, 100%, 54%',
        smokeSpeed: 0.35,
        smokeAlpha: 0.18,
        coreGlow: 0.22,
        coreSize: 22,
        causticAlpha: 0.08,
        ringAlpha: 0.12,
        ringSpeed: 0.6,
    },
    thinking: {
        baseScale: 1.01,
        pulseSpeed: 0.65,
        pulseAmount: 0.022,
        glow: 0.62,
        aura: 1.06,
        rotate: 2.8,
        shimmer: 0.28,
        drift: 5,
        // Slightly bluer tiffany variant for "thinking" — keeps the family
        // identity but reads cooler than the active idle/speaking states.
        hue: '195, 78%, 58%',
        smokeSpeed: 0.5,
        smokeAlpha: 0.22,
        coreGlow: 0.18,
        coreSize: 15,
        causticAlpha: 0.14,
        ringAlpha: 0.2,
        ringSpeed: 1.2,
    },
    speaking: {
        baseScale: 1.02,
        pulseSpeed: 2.1,
        pulseAmount: 0.04,
        glow: 0.88,
        aura: 1.12,
        rotate: 1.4,
        shimmer: 0.24,
        drift: 4,
        hue: '188, 100%, 56%',
        smokeSpeed: 0.7,
        smokeAlpha: 0.3,
        coreGlow: 0.4,
        coreSize: 28,
        causticAlpha: 0.16,
        ringAlpha: 0.35,
        ringSpeed: 1.8,
    },
    error: {
        baseScale: 0.985,
        pulseSpeed: 3.0,
        pulseAmount: 0.035,
        glow: 0.7,
        aura: 1.02,
        rotate: 0.3,
        shimmer: 0.06,
        drift: 1.2,
        hue: '0, 75%, 60%',
        smokeSpeed: 0.8,
        smokeAlpha: 0.25,
        coreGlow: 0.5,
        coreSize: 32,
        causticAlpha: 0.05,
        ringAlpha: 0.1,
        ringSpeed: 2.5,
    },
};

const currentState = computed(() => stateMap[props.state] ?? stateMap.idle);

const containerStyle = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
}));

const orbTransformStyle = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    transform: `translate3d(${driftX.value}px, ${driftY.value}px, 0) scale(${scale.value}) rotate(${rotateDeg.value}deg)`,
    filter: `
        brightness(${1 + glowOpacity.value * 0.18})
        saturate(${1.08 + glowOpacity.value * 0.28})
        drop-shadow(0 0 ${18 + glowOpacity.value * 16}px hsla(${currentState.value.hue}, ${0.24 + glowOpacity.value * 0.22}))
        drop-shadow(0 0 ${48 + glowOpacity.value * 24}px hsla(${currentState.value.hue}, ${0.10 + glowOpacity.value * 0.16}))
    `
        .replace(/\s+/g, ' ')
        .trim(),
}));

const innerWrapStyle = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    transform: `translate3d(${driftX.value}px, ${driftY.value}px, 0) scale(${scale.value})`,
}));

const auraStyle = computed(() => ({
    transform: `scale(${auraScale.value})`,
    opacity: glowOpacity.value,
    '--orb-hue': currentState.value.hue,
}));

const shimmerStyle = computed(() => ({
    opacity: shimmerOpacity.value,
    transform: `translate3d(${-driftX.value * 1.4}px, ${-driftY.value * 1.2}px, 0) scale(${1.02 + (scale.value - 1) * 0.6})`,
}));

// Smoke layer A: slow nebula counter-rotation
const smokeAStyle = computed(() => ({
    opacity: smokeOpacityA.value,
    transform: `rotate(${smokeRotA.value}deg) scale(${smokeScaleA.value})`,
}));

// Smoke layer B: faster wispy swirl
const smokeBStyle = computed(() => ({
    opacity: smokeOpacityB.value,
    transform: `rotate(${smokeRotB.value}deg) scale(${smokeScaleB.value})`,
}));

// Core energy glow — shifts position inside the orb
const coreStyle = computed(() => ({
    opacity: coreIntensity.value,
    background: `radial-gradient(circle at ${coreX.value}% ${coreY.value}%, hsla(${currentState.value.hue}, ${0.6 + coreIntensity.value * 0.4}) 0%, hsla(${currentState.value.hue}, 0.1) ${coreRadius.value}%, transparent ${coreRadius.value * 2}%)`,
}));

// Caustic light refraction crawling across surface
const causticStyle = computed(() => ({
    opacity: causticOpacity.value,
    transform: `rotate(${causticRot.value}deg)`,
}));

// Energy ring arc — orbits inside during active states
const ringStyle = computed(() => {
    const x = 50 + Math.cos((ringAngle.value * Math.PI) / 180) * 25;
    const y = 50 + Math.sin((ringAngle.value * Math.PI) / 180) * 25;
    return {
        opacity: ringOpacity.value,
        background: `radial-gradient(ellipse ${ringSpread.value}% 8% at ${x}% ${y}%, hsla(${currentState.value.hue}, 0.7) 0%, hsla(${currentState.value.hue}, 0.2) 40%, transparent 70%)`,
    };
});

function animate(ts: number) {
    if (!startedAt.value) startedAt.value = ts;
    const t = (ts - startedAt.value) / 1000;

    const cfg = currentState.value;
    const audioBoost =
        props.state === 'speaking' || props.state === 'listening'
            ? clamp(props.audioLevel, 0, 1)
            : 0;

    const pulse = Math.sin(t * Math.PI * 2 * cfg.pulseSpeed);
    const wobbleA = Math.sin(t * 0.9) * cfg.drift;
    const wobbleB = Math.cos(t * 1.13) * cfg.drift * 0.7;
    const wobbleC = Math.sin(t * cfg.rotate * 0.6) * cfg.rotate;

    // ── Outer dynamics ──
    const targetScale = cfg.baseScale + pulse * cfg.pulseAmount + audioBoost * 0.055;
    const targetGlow = cfg.glow + Math.max(0, pulse) * 0.08 + audioBoost * 0.32;
    const targetAura = cfg.aura + Math.max(0, pulse) * 0.025 + audioBoost * 0.05;
    const targetShimmer = cfg.shimmer + Math.max(0, Math.sin(t * 1.8)) * 0.05 + audioBoost * 0.08;

    scale.value = lerp(scale.value, targetScale, 0.12);
    glowOpacity.value = lerp(glowOpacity.value, targetGlow, 0.1);
    auraScale.value = lerp(auraScale.value, targetAura, 0.1);
    driftX.value = lerp(driftX.value, wobbleA, 0.05);
    driftY.value = lerp(driftY.value, wobbleB, 0.05);
    rotateDeg.value = lerp(rotateDeg.value, wobbleC, 0.04);
    shimmerOpacity.value = lerp(shimmerOpacity.value, targetShimmer, 0.08);

    // ── Internal: Smoke layers ──
    const smokeSpd = cfg.smokeSpeed + audioBoost * 0.6;
    smokeRotA.value += smokeSpd * 0.4;
    smokeRotB.value -= smokeSpd * 0.65;

    const targetSmokeA = cfg.smokeAlpha + audioBoost * 0.15 + Math.max(0, Math.sin(t * 1.1)) * 0.06;
    const targetSmokeB = cfg.smokeAlpha * 0.7 + audioBoost * 0.12 + Math.max(0, Math.cos(t * 0.8)) * 0.05;
    smokeOpacityA.value = lerp(smokeOpacityA.value, targetSmokeA, 0.08);
    smokeOpacityB.value = lerp(smokeOpacityB.value, targetSmokeB, 0.08);

    const breatheA = 1 + Math.sin(t * 0.7) * 0.06 + audioBoost * 0.08;
    const breatheB = 1 + Math.cos(t * 0.55) * 0.05 + audioBoost * 0.1;
    smokeScaleA.value = lerp(smokeScaleA.value, breatheA, 0.06);
    smokeScaleB.value = lerp(smokeScaleB.value, breatheB, 0.06);

    // ── Internal: Core energy ──
    const coreWanderX = 50 + Math.sin(t * 0.6) * 12 + Math.cos(t * 1.3) * 6 + audioBoost * Math.sin(t * 4) * 8;
    const coreWanderY = 50 + Math.cos(t * 0.45) * 10 + Math.sin(t * 1.1) * 5 + audioBoost * Math.cos(t * 3.5) * 6;
    coreX.value = lerp(coreX.value, coreWanderX, 0.06);
    coreY.value = lerp(coreY.value, coreWanderY, 0.06);

    const targetCoreIntensity = cfg.coreGlow + audioBoost * 0.35 + Math.max(0, pulse) * 0.08;
    coreIntensity.value = lerp(coreIntensity.value, targetCoreIntensity, 0.1);

    const targetCoreRadius = cfg.coreSize + audioBoost * 14 + Math.max(0, Math.sin(t * 2.2)) * 4;
    coreRadius.value = lerp(coreRadius.value, targetCoreRadius, 0.08);

    // ── Internal: Caustic light ──
    causticRot.value += (0.3 + audioBoost * 0.8) * (cfg.smokeSpeed + 0.2);
    const targetCaustic = cfg.causticAlpha + audioBoost * 0.1 + Math.max(0, Math.sin(t * 1.5)) * 0.04;
    causticOpacity.value = lerp(causticOpacity.value, targetCaustic, 0.08);

    // ── Internal: Energy ring arc ──
    ringAngle.value += cfg.ringSpeed + audioBoost * 3;
    const targetRingOpacity = cfg.ringAlpha + audioBoost * 0.25;
    ringOpacity.value = lerp(ringOpacity.value, targetRingOpacity, 0.1);
    const targetRingSpread = 20 + audioBoost * 20 + Math.sin(t * 1.6) * 5;
    ringSpread.value = lerp(ringSpread.value, targetRingSpread, 0.08);

    frame.value = requestAnimationFrame(animate);
}

onMounted(() => {
    frame.value = requestAnimationFrame(animate);
});

onBeforeUnmount(() => {
    if (frame.value) cancelAnimationFrame(frame.value);
});
</script>

<template>
    <div class="orb-root" :style="containerStyle">
        <!-- Outer aura glow -->
        <div class="orb-aura" :style="auraStyle" />
        <!-- Outer shimmer highlights -->
        <div class="orb-shimmer" :style="shimmerStyle" />

        <!-- The orb image with outer transforms -->
        <img
            :src="props.imageSrc"
            alt=""
            class="orb-image"
            :style="orbTransformStyle"
            draggable="false"
        />

        <!-- Internal dynamics — clipped to circle, overlaid on the image -->
        <div class="orb-inner-wrap" :style="innerWrapStyle">
            <!-- Smoke nebula layer A — slow counter-rotating wisps -->
            <div class="orb-inner-layer orb-smoke-a" :style="smokeAStyle" />
            <!-- Smoke nebula layer B — faster swirl in opposite direction -->
            <div class="orb-inner-layer orb-smoke-b" :style="smokeBStyle" />
            <!-- Caustic light refraction -->
            <div class="orb-inner-layer orb-caustics" :style="causticStyle" />
            <!-- Core energy glow — wanders inside the sphere -->
            <div class="orb-inner-layer orb-core" :style="coreStyle" />
            <!-- Energy ring arc — orbiting light streak -->
            <div class="orb-inner-layer orb-ring" :style="ringStyle" />
        </div>
    </div>
</template>

<style scoped>
.orb-root {
    position: relative;
    display: grid;
    place-items: center;
    isolation: isolate;
}

/*
 * Tiffany Blue family — design anchor #00C6DE, lighter step #5FDBF0 (primary-300).
 * Soft step #9FECF7 (primary-200) used for translucent washes.
 */
.orb-aura {
    position: absolute;
    inset: 8%;
    border-radius: 999px;
    background: radial-gradient(
        circle at 50% 50%,
        rgba(95, 219, 240, 0.34) 0%,
        rgba(95, 219, 240, 0.22) 22%,
        rgba(0, 198, 222, 0.1) 44%,
        rgba(0, 198, 222, 0.03) 60%,
        rgba(0, 198, 222, 0) 75%
    );
    filter: blur(20px);
    pointer-events: none;
}

.orb-shimmer {
    position: absolute;
    inset: 16%;
    border-radius: 999px;
    background: radial-gradient(circle at 34% 30%, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0) 28%),
        radial-gradient(circle at 62% 40%, rgba(95, 219, 240, 0.14), rgba(95, 219, 240, 0) 34%),
        radial-gradient(circle at 44% 68%, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0) 30%);
    filter: blur(18px);
    mix-blend-mode: screen;
    pointer-events: none;
}

.orb-image {
    position: relative;
    z-index: 2;
    display: block;
    object-fit: contain;
    user-select: none;
    will-change: transform, filter;
    backface-visibility: hidden;
}

/* Internal overlay container — sits on top of image, clipped to circle */
.orb-inner-wrap {
    position: absolute;
    z-index: 3;
    border-radius: 50%;
    overflow: hidden;
    pointer-events: none;
    will-change: transform;
}

.orb-inner-layer {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    pointer-events: none;
    will-change: transform, opacity;
}

/* Smoke A: rotating nebula wisps — tiffany */
.orb-smoke-a {
    background:
        radial-gradient(ellipse 60% 40% at 30% 35%, rgba(95, 219, 240, 0.3) 0%, transparent 60%),
        radial-gradient(ellipse 50% 55% at 70% 60%, rgba(35, 202, 232, 0.2) 0%, transparent 55%),
        radial-gradient(ellipse 45% 35% at 55% 25%, rgba(255, 255, 255, 0.08) 0%, transparent 50%);
    mix-blend-mode: screen;
    filter: blur(12px);
}

/* Smoke B: counter-swirl — tiffany with #9FECF7 highlight */
.orb-smoke-b {
    background:
        radial-gradient(ellipse 55% 45% at 65% 30%, rgba(95, 219, 240, 0.22) 0%, transparent 55%),
        radial-gradient(ellipse 40% 60% at 35% 65%, rgba(159, 236, 247, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse 35% 30% at 50% 50%, rgba(255, 255, 255, 0.06) 0%, transparent 45%);
    mix-blend-mode: screen;
    filter: blur(16px);
}

/* Caustic refraction — sharp light crawling */
.orb-caustics {
    background:
        conic-gradient(from 0deg at 40% 40%, transparent 0deg, rgba(255, 255, 255, 0.12) 30deg, transparent 60deg, transparent 120deg, rgba(0, 198, 222, 0.1) 150deg, transparent 180deg, transparent 240deg, rgba(255, 255, 255, 0.08) 270deg, transparent 300deg, transparent 360deg);
    mix-blend-mode: overlay;
    filter: blur(6px);
}

/* Core energy — driven entirely by JS position/size */
.orb-core {
    mix-blend-mode: screen;
    filter: blur(8px);
}

/* Energy ring arc — driven by JS angle/spread */
.orb-ring {
    mix-blend-mode: screen;
    filter: blur(4px);
}
</style>
