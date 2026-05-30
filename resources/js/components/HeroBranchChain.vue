<script setup lang="ts">
import branchChainImg from '@/assets/brach-chain.png';
</script>

<template>
    <div
        class="hero-branch-chain pointer-events-none absolute inset-x-0 bottom-0 z-[5] flex justify-end overflow-visible"
        aria-hidden="true"
    >
        <div
            class="relative w-[min(118vw,640px)] max-w-none sm:w-[min(92vw,580px)] md:w-[520px] lg:w-[580px]"
        >
            <div class="relative w-full">
                <figure class="relative m-0 w-full">
                    <img
                        :src="branchChainImg"
                        alt=""
                        class="hero-branch-chain__base relative z-[1] block h-auto w-full object-contain object-bottom"
                    />

                    <!-- Electrical flow: expanding waves from the LS logo, clipped to the PNG lines. -->
                    <div
                        class="hero-branch-chain__mask absolute inset-0 z-[2] overflow-hidden"
                        :style="{
                            maskImage: `url(${branchChainImg})`,
                            WebkitMaskImage: `url(${branchChainImg})`,
                            maskSize: 'contain',
                            WebkitMaskSize: 'contain',
                            maskRepeat: 'no-repeat',
                            WebkitMaskRepeat: 'no-repeat',
                            maskPosition: 'bottom',
                            WebkitMaskPosition: 'bottom',
                        }"
                    >
                        <div class="hero-flow hero-flow--cyan" />
                        <div class="hero-flow hero-flow--gold" />
                        <div class="hero-filaments hero-filaments--left" />
                        <div class="hero-filaments hero-filaments--right" />
                    </div>
                </figure>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hero-branch-chain__mask {
    mix-blend-mode: plus-lighter;
}

.hero-flow {
    position: absolute;
    left: 50%;
    bottom: 0;
    width: 185%;
    aspect-ratio: 1;
    border-radius: 9999px;
    transform: translate(-50%, 58%) scale(0.08);
    mix-blend-mode: plus-lighter;
    pointer-events: none;
    will-change: transform, opacity;
}

.hero-flow--cyan {
    background:
        radial-gradient(circle, rgba(255, 255, 255, 0.95) 0 1.6%, rgba(0, 198, 222, 0.75) 2.1%, transparent 4%),
        radial-gradient(circle, transparent 23%, rgba(0, 198, 222, 0.08) 29%, rgba(0, 198, 222, 0.32) 38%, rgba(245, 255, 255, 1) 42%, rgba(0, 198, 222, 0.7) 45%, transparent 52%);
    animation: hero-flow-out 1.05s cubic-bezier(0.15, 0.78, 0.18, 1) infinite;
}

.hero-flow--gold {
    background:
        radial-gradient(circle, rgba(255, 255, 255, 0.85) 0 1.4%, rgba(255, 190, 88, 0.58) 2%, transparent 4%),
        radial-gradient(circle, transparent 24%, rgba(255, 190, 88, 0.06) 31%, rgba(255, 190, 88, 0.26) 39%, rgba(255, 255, 255, 0.88) 42%, rgba(255, 190, 88, 0.58) 45%, transparent 53%);
    mix-blend-mode: screen;
    filter: drop-shadow(0 0 6px rgba(255, 190, 88, 0.7));
    animation: hero-flow-out 1.05s cubic-bezier(0.15, 0.78, 0.18, 1) infinite;
    animation-delay: 0.34s;
}

.hero-filaments {
    position: absolute;
    inset: 0;
    background-repeat: no-repeat;
    opacity: 0.6;
    mix-blend-mode: plus-lighter;
    pointer-events: none;
    will-change: background-position, opacity;
}

.hero-filaments--left {
    background-image: repeating-linear-gradient(250deg, transparent 0 42px, rgba(255, 255, 255, 0.78) 43px 45px, rgba(0, 198, 222, 0.24) 46px 58px, transparent 59px 92px);
    background-size: 170% 170%;
    animation: hero-filaments-left 0.74s linear infinite;
}

.hero-filaments--right {
    background-image: repeating-linear-gradient(112deg, transparent 0 46px, rgba(255, 255, 255, 0.72) 47px 49px, rgba(255, 190, 88, 0.22) 50px 62px, transparent 63px 98px);
    background-size: 180% 180%;
    animation: hero-filaments-right 0.82s linear infinite;
    animation-delay: 0.16s;
}

@keyframes hero-flow-out {
    0% {
        opacity: 0;
        transform: translate(-50%, 58%) scale(0.08);
    }
    9% {
        opacity: 1;
    }
    74% {
        opacity: 0.95;
    }
    100% {
        opacity: 0;
        transform: translate(-50%, 58%) scale(1.38);
    }
}

@keyframes hero-filaments-left {
    0% {
        background-position: 58% 100%;
        opacity: 0.15;
    }
    45% {
        opacity: 0.7;
    }
    100% {
        background-position: -45% -16%;
        opacity: 0.15;
    }
}

@keyframes hero-filaments-right {
    0% {
        background-position: 42% 100%;
        opacity: 0.1;
    }
    48% {
        opacity: 0.55;
    }
    100% {
        background-position: 145% -18%;
        opacity: 0.1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-flow,
    .hero-filaments {
        animation: none !important;
        opacity: 0.22;
    }

    .hero-flow {
        transform: translate(-50%, 58%) scale(0.9);
    }
}
</style>
