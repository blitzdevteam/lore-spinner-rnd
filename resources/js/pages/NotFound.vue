<script setup lang="ts">
import notFoundLogo from '@/assets/logo/404-logo.png';
import { index } from '@/wayfinder/routes';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({ layout: null });
</script>

<template>
    <Head title="Page Not Found" />

    <div class="not-found-root relative flex min-h-svh items-center justify-center overflow-hidden bg-black">
        <div
            class="not-found-glow not-found-glow--left pointer-events-none absolute"
            aria-hidden="true"
        />
        <div
            class="not-found-glow not-found-glow--right pointer-events-none absolute"
            aria-hidden="true"
        />

        <div
            class="not-found-logo pointer-events-none absolute inset-0 z-[1] select-none"
            :style="{ backgroundImage: `url(${notFoundLogo})` }"
            aria-hidden="true"
        />

        <div class="relative z-10 flex flex-col items-center px-6 text-center">
            <h1 class="text-[5rem] font-medium leading-none text-white sm:text-[7.5rem]">
                404
            </h1>
            <p class="mt-4 max-w-[27rem] text-base font-medium text-white sm:mt-5 sm:text-xl">
                We couldn’t find the site you were looking for
            </p>
            <Link
                :href="index().url"
                class="mt-6 inline-flex items-center justify-center rounded-xl border border-white px-6 py-3 text-base font-medium leading-6 text-white transition-colors hover:bg-white/5"
            >
                Back to Home
            </Link>
        </div>
    </div>
</template>

<style scoped>
.not-found-glow {
    width: 37.6875rem;
    height: 64.0625rem;
    transform: rotate(27.45deg);
}

.not-found-glow::before {
    content: '';
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 9999px;
    background: linear-gradient(
        126.15deg,
        rgba(63, 173, 217, 0.5) 1.97%,
        rgba(0, 0, 0, 0.5) 70.15%
    );
    filter: blur(250px);
}

.not-found-glow--left {
    left: 1rem;
    top: -13.5625rem;
}

.not-found-glow--right {
    right: -9.5rem;
    bottom: -6rem;
}

.not-found-logo {
    background-repeat: no-repeat;
    background-position: center 38%;
    background-size: min(58.6875rem, 92vw);
    /* opacity: 1; */
    filter: brightness(0) invert(1);
}

@media (min-width: 48rem) {
    .not-found-glow--left {
        left: 1rem;
        top: -13.5625rem;
    }

    .not-found-glow--right {
        right: 0;
        bottom: 0;
    }
}
</style>
