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

                    <!--
                      Traveling fronts (masked to PNG): start at bottom-center / logo and run to L & R edges
                      so every branch segment is swept by a bright edge, including tips.
                    -->
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
                        <div
                            class="hero-bolt hero-bolt--right hero-bolt--cyan"
                        />
                        <div
                            class="hero-bolt hero-bolt--left hero-bolt--cyan"
                        />
                        <div
                            class="hero-bolt hero-bolt--right hero-bolt--gold"
                        />
                        <div
                            class="hero-bolt hero-bolt--left hero-bolt--gold"
                        />
                    </div>
                </figure>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hero-bolt {
    position: absolute;
    bottom: 0;
    /* Tall enough to cover curved branches that arc upward */
    height: 96%;
    /* Wide enough that one sweep crosses every dashed segment, including curved arms */
    width: min(30%, 11rem);
    mix-blend-mode: plus-lighter;
    will-change: left, transform, opacity;
    pointer-events: none;
}

/* Leading edge is the outer side; feathery tail toward center / logo */
.hero-bolt--right {
    background: linear-gradient(
        90deg,
        rgba(0, 198, 222, 0) 0%,
        rgba(0, 198, 222, 0.35) 28%,
        rgba(0, 198, 222, 0.9) 62%,
        rgba(230, 255, 255, 1) 88%,
        rgba(255, 255, 255, 0.55) 96%,
        rgba(255, 255, 255, 0) 100%
    );
    animation: hero-bolt-right 0.75s cubic-bezier(0.22, 0.8, 0.2, 1) infinite;
}

.hero-bolt--left {
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.55) 4%,
        rgba(230, 255, 255, 1) 12%,
        rgba(0, 198, 222, 0.9) 38%,
        rgba(0, 198, 222, 0.35) 72%,
        rgba(0, 198, 222, 0) 100%
    );
    animation: hero-bolt-left 0.75s cubic-bezier(0.22, 0.8, 0.2, 1) infinite;
}

.hero-bolt--cyan {
    filter: drop-shadow(0 0 6px rgba(0, 198, 222, 0.75));
}

.hero-bolt--gold {
    mix-blend-mode: screen;
    animation-delay: 2ms;
    filter: drop-shadow(0 0 6px rgba(255, 190, 88, 0.65));
    opacity: 0.30;
}

.hero-bolt--gold.hero-bolt--right {
    background: linear-gradient(
        90deg,
        rgba(255, 190, 88, 0) 0%,
        rgba(255, 190, 88, 0.3) 30%,
        rgba(255, 210, 140, 0.95) 65%,
        rgba(255, 255, 255, 0.95) 88%,
        rgba(255, 230, 180, 0.45) 97%,
        rgba(255, 190, 88, 0) 100%
    );
}

.hero-bolt--gold.hero-bolt--left {
    background: linear-gradient(
        90deg,
        rgba(255, 190, 88, 0) 0%,
        rgba(255, 230, 180, 0.45) 3%,
        rgba(255, 255, 255, 0.92) 12%,
        rgba(255, 210, 140, 0.95) 35%,
        rgba(255, 190, 88, 0.35) 70%,
        rgba(255, 190, 88, 0) 100%
    );
}

/* Full lateral travel: stay bright until ~the outer tips, then fade */
@keyframes hero-bolt-right {
    0% {
        left: 50%;
        transform: translateX(-50%);
        opacity: 0;
    }
    5% {
        opacity: 1;
    }
    88% {
        opacity: 1;
    }
    96% {
        opacity: 0.55;
    }
    100% {
        left: 100%;
        transform: translateX(-100%);
        opacity: 0;
    }
}

@keyframes hero-bolt-left {
    0% {
        left: 50%;
        transform: translateX(-50%);
        opacity: 0;
    }
    5% {
        opacity: 1;
    }
    88% {
        opacity: 1;
    }
    96% {
        opacity: 0.55;
    }
    100% {
        left: 0%;
        transform: translateX(0);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-bolt {
        animation: none !important;
        opacity: 0.32;
    }

    .hero-bolt--right {
        left: 76% !important;
        transform: translateX(-50%) !important;
    }

    .hero-bolt--left {
        left: 24% !important;
        transform: translateX(-50%) !important;
    }

    .hero-bolt--gold {
        opacity: 0.22;
    }
}
</style>
