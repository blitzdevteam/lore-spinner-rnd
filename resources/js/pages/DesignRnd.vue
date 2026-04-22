<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Design R&D — Orb" />

    <main class="scene">
        <!-- Deep-ocean / night-sea base gradient -->
        <div class="ocean-depth" />

        <!-- Caustic ripples: light refracting through an unseen surface far above -->
        <svg class="ocean-caustics" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <defs>
                <filter id="causticsFilter" x="0%" y="0%" width="100%" height="100%">
                    <feTurbulence type="fractalNoise" baseFrequency="0.010 0.018" numOctaves="2" seed="4" result="noise">
                        <animate attributeName="baseFrequency" dur="34s"
                            values="0.010 0.018;0.014 0.022;0.010 0.018" repeatCount="indefinite" />
                    </feTurbulence>
                    <feColorMatrix in="noise" type="matrix" values="
                        0 0 0 0 0.10
                        0 0 0 0 0.32
                        0 0 0 0 0.62
                        0 0 0 1.1 -0.35" />
                </filter>
                <linearGradient id="causticsMask" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="white" stop-opacity="0.55" />
                    <stop offset="60%" stop-color="white" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="white" stop-opacity="0.05" />
                </linearGradient>
                <mask id="causticsVignette">
                    <rect width="100%" height="100%" fill="url(#causticsMask)" />
                </mask>
            </defs>
            <rect width="100%" height="100%" filter="url(#causticsFilter)" mask="url(#causticsVignette)" />
        </svg>

        <!-- Distant particles / starfield — ambiguous between deep sea and cosmos -->
        <div class="starfield" />
        <div class="starfield starfield--sparse" />

        <!-- The orb -->
        <div class="orb-stage">
            <svg viewBox="0 0 400 400" class="orb" aria-hidden="true">
                <defs>
                    <!-- Outer rim ripple: water under pressure, slightly irregular -->
                    <filter id="rimRipple" x="-15%" y="-15%" width="130%" height="130%">
                        <feTurbulence type="fractalNoise" baseFrequency="0.022" numOctaves="3" seed="7" result="rim">
                            <animate attributeName="baseFrequency" dur="14s"
                                values="0.020;0.028;0.020" repeatCount="indefinite" />
                            <animate attributeName="seed" dur="22s"
                                values="3;27;3" repeatCount="indefinite" />
                        </feTurbulence>
                        <feDisplacementMap in="SourceGraphic" in2="rim" scale="10"
                            xChannelSelector="R" yChannelSelector="G" />
                    </filter>

                    <!-- Slow, large-scale interior smoke (layered blues) -->
                    <filter id="smokeDeep" x="0%" y="0%" width="100%" height="100%">
                        <feTurbulence type="fractalNoise" baseFrequency="0.0055" numOctaves="4" seed="2">
                            <animate attributeName="seed" dur="60s"
                                values="2;120;2" repeatCount="indefinite" />
                            <animate attributeName="baseFrequency" dur="40s"
                                values="0.0055;0.0075;0.0055" repeatCount="indefinite" />
                        </feTurbulence>
                        <feColorMatrix type="matrix" values="
                            0 0 0 0 0.18
                            0 0 0 0 0.45
                            0 0 0 0 0.78
                            0 0 0 1.6 -0.35" />
                    </filter>

                    <!-- Finer highlight wisps (teal / white) -->
                    <filter id="smokeHighlight" x="0%" y="0%" width="100%" height="100%">
                        <feTurbulence type="fractalNoise" baseFrequency="0.014" numOctaves="5" seed="9">
                            <animate attributeName="seed" dur="45s"
                                values="9;200;9" repeatCount="indefinite" />
                            <animate attributeName="baseFrequency" dur="28s"
                                values="0.012;0.018;0.012" repeatCount="indefinite" />
                        </feTurbulence>
                        <feColorMatrix type="matrix" values="
                            0 0 0 0 0.55
                            0 0 0 0 0.90
                            0 0 0 0 1.00
                            0 0 0 2.4 -1.55" />
                    </filter>

                    <!-- Base colour of the orb interior: darker blue core -->
                    <radialGradient id="orbBase" cx="50%" cy="42%" r="62%">
                        <stop offset="0%"  stop-color="#0e3358" />
                        <stop offset="55%" stop-color="#071f3a" />
                        <stop offset="100%" stop-color="#020b17" />
                    </radialGradient>

                    <!-- The sacred beam: concentrated glow at bottom radiating upward -->
                    <radialGradient id="sacredGlow" cx="50%" cy="86%" r="52%">
                        <stop offset="0%"  stop-color="rgba(230, 252, 255, 1)" />
                        <stop offset="10%" stop-color="rgba(170, 238, 255, 0.85)" />
                        <stop offset="28%" stop-color="rgba(110, 205, 250, 0.45)" />
                        <stop offset="55%" stop-color="rgba(60, 150, 220, 0.15)" />
                        <stop offset="100%" stop-color="rgba(30, 80, 150, 0)" />
                    </radialGradient>

                    <!-- Outer aura around the orb -->
                    <radialGradient id="aura" cx="50%" cy="50%" r="50%">
                        <stop offset="68%"  stop-color="rgba(70, 150, 220, 0)" />
                        <stop offset="83%"  stop-color="rgba(90, 180, 240, 0.22)" />
                        <stop offset="100%" stop-color="rgba(120, 210, 255, 0)" />
                    </radialGradient>

                    <!-- Rim shine: just a hair of brightness on the water-edge -->
                    <radialGradient id="rimShine" cx="50%" cy="50%" r="50%">
                        <stop offset="88%" stop-color="rgba(140, 215, 255, 0)" />
                        <stop offset="97%" stop-color="rgba(170, 230, 255, 0.55)" />
                        <stop offset="100%" stop-color="rgba(200, 245, 255, 0)" />
                    </radialGradient>

                    <clipPath id="orbClip">
                        <circle cx="200" cy="200" r="168" />
                    </clipPath>
                </defs>

                <!-- Soft aura behind the orb -->
                <circle cx="200" cy="200" r="198" fill="url(#aura)" />

                <!-- The rippling bubble. Everything inside this <g> is displaced by the rim filter. -->
                <g filter="url(#rimRipple)">
                    <!-- Dark base -->
                    <circle cx="200" cy="200" r="168" fill="url(#orbBase)" />

                    <g clip-path="url(#orbClip)">
                        <!-- Deep, slow-moving cloud body -->
                        <rect x="32" y="32" width="336" height="336"
                            filter="url(#smokeDeep)"
                            opacity="0.85"
                            style="mix-blend-mode: screen" />

                        <!-- Brighter teal/white highlights -->
                        <rect x="32" y="32" width="336" height="336"
                            filter="url(#smokeHighlight)"
                            opacity="0.55"
                            style="mix-blend-mode: screen" />

                        <!-- Light beam piercing up from the bottom -->
                        <rect x="32" y="32" width="336" height="336"
                            fill="url(#sacredGlow)"
                            style="mix-blend-mode: screen" />

                        <!-- Subtle vignette so the edges feel deeper than the core -->
                        <circle cx="200" cy="200" r="168" fill="url(#aura)" opacity="0.6" />
                    </g>

                    <!-- Rim shine -->
                    <circle cx="200" cy="200" r="168" fill="url(#rimShine)" />
                </g>
            </svg>
        </div>
    </main>
</template>

<style scoped>
.scene {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    background: #000308;
    color: white;
    isolation: isolate;
}

/* ---------- BACKGROUND: deep ocean / night cosmos ---------- */
.ocean-depth {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 80% 60% at 50% 32%, #0a2744 0%, #051731 38%, #020a18 72%, #000205 100%),
        linear-gradient(180deg, #04182d 0%, #01070f 100%);
    background-blend-mode: screen;
}

.ocean-caustics {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    mix-blend-mode: screen;
    opacity: 0.28;
    pointer-events: none;
}

/* ---------- STARFIELD / distant particles ---------- */
.starfield {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image:
        radial-gradient(1px 1px at 7% 14%, rgba(255, 255, 255, 0.75), transparent 55%),
        radial-gradient(1px 1px at 18% 68%, rgba(210, 235, 255, 0.6), transparent 55%),
        radial-gradient(1.2px 1.2px at 29% 22%, rgba(255, 255, 255, 0.7), transparent 55%),
        radial-gradient(1px 1px at 44% 9%, rgba(220, 240, 255, 0.55), transparent 55%),
        radial-gradient(0.8px 0.8px at 53% 78%, rgba(255, 255, 255, 0.55), transparent 55%),
        radial-gradient(1px 1px at 62% 46%, rgba(200, 230, 255, 0.5), transparent 55%),
        radial-gradient(0.9px 0.9px at 71% 28%, rgba(255, 255, 255, 0.6), transparent 55%),
        radial-gradient(1px 1px at 82% 84%, rgba(220, 240, 255, 0.55), transparent 55%),
        radial-gradient(0.8px 0.8px at 91% 38%, rgba(255, 255, 255, 0.5), transparent 55%),
        radial-gradient(1px 1px at 96% 62%, rgba(200, 230, 255, 0.55), transparent 55%),
        radial-gradient(0.8px 0.8px at 12% 88%, rgba(255, 255, 255, 0.45), transparent 55%),
        radial-gradient(0.9px 0.9px at 37% 56%, rgba(220, 240, 255, 0.45), transparent 55%);
    background-size: 100% 100%;
    opacity: 0.85;
    animation: twinkle 6s ease-in-out infinite;
}

.starfield--sparse {
    background-image:
        radial-gradient(1.4px 1.4px at 23% 41%, rgba(255, 255, 255, 0.85), transparent 55%),
        radial-gradient(1.2px 1.2px at 67% 66%, rgba(210, 235, 255, 0.7), transparent 55%),
        radial-gradient(1.4px 1.4px at 88% 14%, rgba(255, 255, 255, 0.75), transparent 55%),
        radial-gradient(1.3px 1.3px at 9% 55%, rgba(220, 240, 255, 0.65), transparent 55%);
    opacity: 0.9;
    animation: twinkle 9s ease-in-out -2s infinite;
}

@keyframes twinkle {
    0%, 100% { opacity: 0.85; }
    50%      { opacity: 0.55; }
}

/* ---------- THE ORB ---------- */
.orb-stage {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    z-index: 10;
    pointer-events: none;
}

.orb {
    width: 28vmin;
    height: 28vmin;
    min-width: 280px;
    min-height: 280px;
    max-width: 560px;
    max-height: 560px;
    filter:
        drop-shadow(0 0 30px rgba(90, 180, 240, 0.28))
        drop-shadow(0 0 80px rgba(40, 120, 200, 0.20))
        drop-shadow(0 0 160px rgba(20, 80, 160, 0.12));
    animation: orbFloat 9s ease-in-out infinite;
}

@keyframes orbFloat {
    0%, 100% { transform: translate3d(0, 0, 0); }
    50%      { transform: translate3d(0, -14px, 0); }
}
</style>
