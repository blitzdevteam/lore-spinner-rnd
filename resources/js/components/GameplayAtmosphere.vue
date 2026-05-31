<script setup lang="ts">
import { useStoryAtmosphere } from '@/composables/useStoryAtmosphere';
import { computed } from 'vue';

const { palette } = useStoryAtmosphere();

const atmosphereStyle = computed(() => ({
    '--atmosphere-primary': palette.value.primary,
    '--atmosphere-secondary': palette.value.secondary,
    '--atmosphere-accent': palette.value.accent,
}));
</script>

<template>
    <div
        aria-hidden="true"
        class="gameplay-atmosphere pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-black"
        :style="atmosphereStyle"
    >
        <!-- Layer 1: dark base -->
        <div class="absolute inset-0 bg-black" />

        <!-- Layer 2: primary glow (left) -->
        <div class="glow glow--primary" />

        <!-- Layer 3: secondary glow (right) -->
        <div class="glow glow--secondary" />

        <!-- Layer 4: accent glow (center) -->
        <div class="glow glow--accent" />

        <!-- Layer 5: film grain -->
        <div class="noise-layer" />

        <!-- Layer 6: vignette -->
        <div class="vignette-layer" />

        <!-- Bottom grounding fade -->
        <div class="bottom-fade-layer" />
    </div>
</template>

<style scoped>
.gameplay-atmosphere {
    --glow-angle: 126deg;
}

.glow {
    position: absolute;
    pointer-events: none;
    border-radius: 50%;
    will-change: transform;
}

.glow--primary {
    top: clamp(2rem, 4.7vh, 5rem);
    left: clamp(-28rem, -27vw, -12rem);
    width: clamp(22rem, 42vw, 38rem);
    height: clamp(28rem, 66vh, 64rem);
    background: linear-gradient(
        var(--glow-angle),
        color-mix(in srgb, var(--atmosphere-primary) 72%, transparent) 1.97%,
        rgb(0, 0, 0) 70.15%
    );
    filter: blur(clamp(120px, 17vw, 250px));
    animation: atmosphere-drift-left 46s ease-in-out infinite;
}

.glow--secondary {
    top: clamp(4rem, 10vh, 10rem);
    right: clamp(-16rem, -12vw, -4rem);
    width: clamp(20rem, 40vw, 36rem);
    height: clamp(26rem, 64vh, 61rem);
    background: linear-gradient(
        var(--glow-angle),
        color-mix(in srgb, var(--atmosphere-secondary) 68%, transparent) 1.97%,
        rgb(0, 0, 0) 70.15%
    );
    filter: blur(clamp(120px, 17vw, 250px));
    transform: rotate(180deg);
    animation: atmosphere-drift-right 52s ease-in-out infinite;
}

.glow--accent {
    top: 50%;
    left: 50%;
    width: clamp(18rem, 36vw, 32rem);
    height: clamp(16rem, 32vh, 28rem);
    transform: translate(-50%, -50%);
    background: radial-gradient(
        ellipse at center,
        color-mix(in srgb, var(--atmosphere-accent) 22%, transparent) 0%,
        transparent 68%
    );
    filter: blur(clamp(80px, 12vw, 160px));
    opacity: 0.85;
    animation: atmosphere-breathe 34s ease-in-out infinite;
}

.noise-layer {
    position: absolute;
    inset: 0;
    opacity: 0.04;
    mix-blend-mode: overlay;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
    background-repeat: repeat;
    background-size: 16rem 16rem;
}

.vignette-layer {
    position: absolute;
    inset: 0;
    background: radial-gradient(
        ellipse 85% 75% at 50% 45%,
        transparent 0%,
        transparent 42%,
        rgba(0, 0, 0, 0.35) 72%,
        rgba(0, 0, 0, 0.72) 100%
    );
}

.bottom-fade-layer {
    position: absolute;
    inset-inline: 0;
    bottom: 0;
    height: clamp(8rem, 14vh, 12.5rem);
    background: linear-gradient(to bottom, transparent 0.8%, #1a1a1a 90.7%);
    opacity: 0.85;
}

@keyframes atmosphere-drift-left {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }

    33% {
        transform: translate(2.5%, -1.8%) scale(1.04);
    }

    66% {
        transform: translate(-1.8%, 2.2%) scale(0.97);
    }
}

@keyframes atmosphere-drift-right {
    0%,
    100% {
        transform: rotate(180deg) translate(0, 0) scale(1);
    }

    40% {
        transform: rotate(180deg) translate(-2%, 1.6%) scale(1.03);
    }

    75% {
        transform: rotate(180deg) translate(1.5%, -2%) scale(0.98);
    }
}

@keyframes atmosphere-breathe {
    0%,
    100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 0.75;
    }

    50% {
        transform: translate(-50%, -50%) scale(1.08);
        opacity: 0.95;
    }
}

@media (max-width: 1024px) {
    .glow--primary,
    .glow--secondary {
        filter: blur(clamp(72px, 14vw, 120px));
    }

    .glow--accent {
        filter: blur(clamp(56px, 10vw, 96px));
    }
}

@media (prefers-reduced-motion: reduce) {
    .glow {
        animation: none;
    }
}
</style>
