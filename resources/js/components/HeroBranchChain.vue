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

                    <!-- Electrical flow: masked to branch lines only -->
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
                        <div class="hero-branch-chain__flow-a absolute inset-[-120%] opacity-90" />
                        <div class="hero-branch-chain__flow-b absolute inset-[-120%] opacity-75" />
                        <div class="hero-branch-chain__burst absolute inset-0 overflow-hidden">
                            <div class="hero-branch-chain__burst-cone" />
                        </div>
                    </div>
                </figure>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hero-branch-chain__flow-a {
    background: linear-gradient(
        118deg,
        transparent 0%,
        transparent 42%,
        rgba(0, 198, 222, 0) 44%,
        rgba(0, 198, 222, 0.85) 49.2%,
        rgba(255, 255, 255, 0.95) 50%,
        rgba(0, 198, 222, 0.85) 50.8%,
        rgba(0, 198, 222, 0) 56%,
        transparent 100%
    );
    background-size: 240% 240%;
    animation: hero-flow-scan-a 3.2s ease-in-out infinite;
    mix-blend-mode: plus-lighter;
    will-change: background-position;
}

.hero-branch-chain__flow-b {
    background: linear-gradient(
        52deg,
        transparent 0%,
        transparent 48%,
        rgba(255, 190, 88, 0) 49%,
        rgba(255, 190, 88, 0.75) 49.8%,
        rgba(255, 255, 255, 0.65) 50%,
        rgba(255, 190, 88, 0.75) 50.2%,
        rgba(255, 190, 88, 0) 51.5%,
        transparent 100%
    );
    background-size: 280% 280%;
    animation: hero-flow-scan-b 2.7s ease-in-out infinite;
    animation-delay: 0.6s;
    mix-blend-mode: screen;
    will-change: background-position;
}

.hero-branch-chain__burst {
    pointer-events: none;
    mix-blend-mode: plus-lighter;
}

.hero-branch-chain__burst-cone {
    position: absolute;
    width: 220%;
    height: 220%;
    left: -60%;
    bottom: -90%;
    background: conic-gradient(
        from 200deg at 50% 100%,
        transparent 0deg,
        transparent 32deg,
        rgba(0, 198, 222, 0.55) 38deg,
        rgba(255, 255, 255, 0.5) 42deg,
        rgba(255, 190, 88, 0.45) 46deg,
        transparent 52deg,
        transparent 120deg,
        rgba(0, 198, 222, 0.35) 132deg,
        transparent 145deg,
        transparent 360deg
    );
    animation: hero-burst-spin 4.8s linear infinite;
    opacity: 0.85;
    filter: blur(0.5px);
}

@keyframes hero-flow-scan-a {
    0% {
        background-position: 130% 80%;
    }
    100% {
        background-position: -30% -20%;
    }
}

@keyframes hero-flow-scan-b {
    0% {
        background-position: -40% 140%;
    }
    100% {
        background-position: 60% -40%;
    }
}

@keyframes hero-burst-spin {
    0% {
        transform: rotate(0deg) scale(1);
        opacity: 0.65;
    }
    50% {
        opacity: 0.95;
    }
    100% {
        transform: rotate(360deg) scale(1.02);
        opacity: 0.65;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-branch-chain__flow-a,
    .hero-branch-chain__flow-b,
    .hero-branch-chain__burst-cone {
        animation: none !important;
    }

    .hero-branch-chain__flow-a,
    .hero-branch-chain__flow-b {
        opacity: 0.25;
    }

    .hero-branch-chain__burst-cone {
        opacity: 0.35;
        transform: none;
    }
}
</style>
