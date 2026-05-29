<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({ layout: null });

const partners = ['virgin atlantic', 'miro', 'Rakuten', 'WHOOP', 'cisco'];
</script>

<template>
    <Head title="Background R&D" />

    <main class="codex-rnd-page" aria-label="Codex background reproduction">
        <div class="codex-background" aria-hidden="true">
            <div class="codex-background__base" />
            <div class="codex-background__milk" />
            <div class="codex-background__violet" />
            <div class="codex-background__azure" />
            <div class="codex-background__focal" />
            <div class="codex-background__texture" />
            <div class="codex-background__vignette" />
        </div>

        <header class="codex-nav">
            <a class="codex-brand" href="/" aria-label="LoreSpinner home">
                <svg viewBox="0 0 40 40" class="codex-brand__mark" aria-hidden="true">
                    <path
                        d="M20 4.5c3.3 0 5.9 1.4 7.7 3.8 2.9.4 5.4 2.2 6.8 4.9 1.5 2.8 1.4 5.9-.1 8.5.9 2.8.4 5.9-1.5 8.3-2 2.6-4.9 3.8-7.9 3.6-2.3 1.9-5.4 2.6-8.3 1.8-3.1-.8-5.4-2.9-6.4-5.7-2.7-1.1-4.8-3.5-5.4-6.5-.6-3.1.4-6.1 2.6-8.1.1-2.9 1.6-5.7 4.2-7.6 2.5-1.9 5.6-2.3 8.3-1Z"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.1"
                        stroke-linejoin="round"
                    />
                    <path d="M13 16.2 20 12l7 4.2v7.6L20 28l-7-4.2v-7.6Z" fill="none" stroke="currentColor" stroke-width="2.1" />
                </svg>
                <span>ChatGPT</span>
            </a>

            <div class="codex-nav__actions">
                <a class="codex-cloud-button" href="/chaos-mode">Go to Cloud</a>
                <button class="codex-icon-button" type="button" aria-label="Toggle view">
                    <span />
                    <span />
                </button>
            </div>
        </header>

        <section class="codex-hero">
            <div class="codex-app-icon" aria-hidden="true">
                <span class="codex-app-icon__core">
                    <svg viewBox="0 0 36 36">
                        <path d="M13.2 9.6 19 18l-5.8 8.4" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M19.5 24h7" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" />
                    </svg>
                </span>
            </div>

            <h1>Codex</h1>
            <p>Your AI assistant for work.</p>
            <a class="codex-download" href="/chaos-mode">Download for macOS</a>
        </section>

        <section class="codex-trust" aria-label="Trusted by top teams">
            <p>Trusted by top teams</p>
            <div class="codex-logos">
                <span v-for="partner in partners" :key="partner">{{ partner }}</span>
            </div>
        </section>
    </main>
</template>

<style scoped>
.codex-rnd-page {
    position: relative;
    min-height: 100dvh;
    overflow: hidden;
    color: #070914;
    background: #eef4ff;
    font-family:
        ui-sans-serif,
        -apple-system,
        BlinkMacSystemFont,
        "SF Pro Display",
        "Helvetica Neue",
        Arial,
        sans-serif;
}

.codex-background,
.codex-background > * {
    position: absolute;
    pointer-events: none;
}

.codex-background {
    inset: 0;
    overflow: hidden;
    isolation: isolate;
}

.codex-background__base {
    inset: -18vmax;
    background:
        radial-gradient(ellipse 78% 86% at 56% 44%, rgba(255, 255, 255, 0.98) 0 18%, rgba(230, 238, 255, 0.74) 38%, transparent 66%),
        radial-gradient(ellipse 82% 72% at 24% 68%, rgba(41, 103, 255, 0.76) 0 22%, rgba(68, 139, 255, 0.36) 46%, transparent 72%),
        radial-gradient(ellipse 68% 64% at 84% 36%, rgba(106, 91, 255, 0.56) 0 24%, rgba(136, 120, 255, 0.34) 48%, transparent 75%),
        linear-gradient(125deg, #1f61f2 0%, #c9ddff 33%, #fbfbff 52%, #938cff 100%);
    filter: blur(24px) saturate(1.12) contrast(1.02);
    transform: scale(1.09);
    animation: codex-field-drift 24s ease-in-out infinite alternate;
}

.codex-background__milk {
    inset: -10vmax;
    background:
        radial-gradient(ellipse 56% 78% at 47% 42%, rgba(255, 255, 255, 0.96), rgba(247, 249, 255, 0.68) 34%, transparent 68%),
        radial-gradient(ellipse 34% 44% at 67% 40%, rgba(241, 238, 255, 0.72), transparent 72%);
    filter: blur(16px);
    mix-blend-mode: screen;
    opacity: 0.94;
    animation: codex-milk-breathe 18s cubic-bezier(0.45, 0, 0.25, 1) infinite alternate;
}

.codex-background__violet {
    width: 48vmax;
    height: 40vmax;
    top: -12vmax;
    right: -7vmax;
    border-radius: 44% 56% 62% 38% / 44% 42% 58% 56%;
    background:
        radial-gradient(circle at 42% 40%, rgba(65, 51, 236, 0.5), rgba(112, 96, 255, 0.38) 38%, rgba(147, 128, 255, 0.16) 62%, transparent 78%);
    filter: blur(20px) saturate(1.22);
    opacity: 0.82;
    transform: rotate(-12deg);
    animation: codex-violet-float 28s ease-in-out infinite alternate;
}

.codex-background__azure {
    width: 62vmax;
    height: 54vmax;
    left: -22vmax;
    bottom: -21vmax;
    border-radius: 58% 42% 48% 52% / 48% 48% 52% 52%;
    background:
        radial-gradient(ellipse at 62% 38%, rgba(16, 91, 244, 0.74), rgba(48, 117, 255, 0.56) 34%, rgba(106, 170, 255, 0.22) 58%, transparent 76%);
    filter: blur(18px) saturate(1.16);
    opacity: 0.88;
    animation: codex-azure-float 30s ease-in-out infinite alternate;
}

.codex-background__focal {
    inset: -8vmax;
    background:
        radial-gradient(ellipse 42% 34% at 46% 43%, rgba(255, 255, 255, 0.9), transparent 68%),
        radial-gradient(ellipse 30% 24% at 34% 46%, rgba(117, 218, 255, 0.18), transparent 72%),
        radial-gradient(ellipse 32% 28% at 67% 48%, rgba(127, 112, 255, 0.18), transparent 70%);
    mix-blend-mode: screen;
    filter: blur(8px);
    opacity: 0.9;
    animation: codex-focus-shift 20s ease-in-out infinite alternate;
}

.codex-background__texture {
    inset: 0;
    z-index: 4;
    opacity: 0.18;
    mix-blend-mode: overlay;
    background-image:
        url("data:image/svg+xml,%3Csvg viewBox='0 0 420 420' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.62' numOctaves='5' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.52'/%3E%3C/svg%3E"),
        linear-gradient(105deg, transparent 0 36%, rgba(255, 255, 255, 0.18) 44%, transparent 58% 100%);
    background-size: 420px 420px, 130% 130%;
    animation: codex-texture-drift 16s ease-in-out infinite alternate;
}

.codex-background__vignette {
    inset: 0;
    z-index: 5;
    background:
        radial-gradient(ellipse 80% 78% at 50% 44%, transparent 0 48%, rgba(25, 46, 110, 0.2) 100%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.2), transparent 20%, rgba(47, 66, 130, 0.2) 100%);
}

.codex-nav {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: clamp(18px, 3vw, 30px) clamp(22px, 4vw, 58px);
}

.codex-brand,
.codex-nav__actions {
    display: inline-flex;
    align-items: center;
}

.codex-brand {
    gap: 9px;
    color: rgba(3, 7, 18, 0.84);
    text-decoration: none;
    font-size: clamp(17px, 2vw, 21px);
    font-weight: 720;
    letter-spacing: -0.035em;
}

.codex-brand__mark {
    width: 30px;
    height: 30px;
}

.codex-nav__actions {
    gap: 10px;
}

.codex-cloud-button,
.codex-download {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #050508;
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 650;
    letter-spacing: -0.02em;
    box-shadow: 0 12px 35px rgba(7, 9, 20, 0.16);
}

.codex-cloud-button {
    height: 42px;
    padding: 0 20px;
}

.codex-icon-button {
    display: grid;
    width: 43px;
    height: 43px;
    place-items: center;
    border: 0;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.86);
    box-shadow: inset 0 0 0 1px rgba(8, 13, 35, 0.07), 0 12px 35px rgba(7, 9, 20, 0.08);
}

.codex-icon-button span {
    grid-area: 1 / 1;
    width: 13px;
    height: 18px;
    border: 1.8px solid rgba(5, 7, 18, 0.76);
    border-radius: 999px;
}

.codex-icon-button span:first-child {
    transform: translateX(-4px);
}

.codex-icon-button span:last-child {
    transform: translateX(4px);
}

.codex-hero {
    position: relative;
    z-index: 8;
    display: grid;
    min-height: min(55dvh, 520px);
    place-items: center;
    padding: clamp(24px, 5vw, 64px) 24px 0;
    text-align: center;
}

.codex-app-icon {
    display: grid;
    width: clamp(78px, 8vw, 104px);
    height: clamp(78px, 8vw, 104px);
    margin-bottom: 34px;
    place-items: center;
    border-radius: 26%;
    background: rgba(255, 255, 255, 0.66);
    box-shadow:
        inset 0 1px 1px rgba(255, 255, 255, 0.85),
        0 18px 50px rgba(42, 84, 255, 0.22),
        0 2px 10px rgba(7, 9, 20, 0.08);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}

.codex-app-icon__core {
    display: grid;
    width: 68%;
    height: 68%;
    place-items: center;
    border-radius: 28%;
    color: rgba(255, 255, 255, 0.94);
    background:
        radial-gradient(circle at 72% 24%, #9d8dff, transparent 32%),
        radial-gradient(circle at 35% 74%, #4ccaff, transparent 38%),
        linear-gradient(135deg, #5974ff, #6647d9);
    box-shadow:
        inset 0 1px 6px rgba(255, 255, 255, 0.28),
        0 10px 28px rgba(67, 75, 231, 0.32);
}

.codex-app-icon svg {
    width: 62%;
    height: 62%;
}

.codex-hero h1 {
    margin: 0;
    color: #070914;
    font-size: clamp(56px, 7vw, 82px);
    font-weight: 650;
    letter-spacing: -0.075em;
    line-height: 0.9;
}

.codex-hero p {
    margin: 30px 0 34px;
    color: rgba(7, 9, 20, 0.76);
    font-size: clamp(19px, 2.1vw, 25px);
    font-weight: 470;
    letter-spacing: -0.035em;
}

.codex-download {
    height: 52px;
    padding: 0 25px;
}

.codex-trust {
    position: relative;
    z-index: 8;
    margin-top: clamp(34px, 7vh, 78px);
    padding: 0 6vw 38px;
    text-align: center;
}

.codex-trust p {
    margin: 0 0 48px;
    color: rgba(7, 9, 20, 0.52);
    font-size: 18px;
    font-weight: 520;
    letter-spacing: -0.025em;
}

.codex-logos {
    display: grid;
    max-width: 1120px;
    margin: 0 auto;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    align-items: center;
    gap: clamp(20px, 5vw, 66px);
}

.codex-logos span {
    color: rgba(7, 9, 20, 0.5);
    font-size: clamp(22px, 3vw, 36px);
    font-weight: 760;
    letter-spacing: -0.08em;
    line-height: 1;
    filter: blur(0.1px);
}

.codex-logos span:first-child {
    font-weight: 430;
    letter-spacing: -0.07em;
}

.codex-logos span:nth-child(4) {
    font-weight: 360;
    letter-spacing: 0.06em;
}

.codex-logos span:last-child {
    font-size: clamp(20px, 2.8vw, 30px);
    letter-spacing: 0.12em;
}

@keyframes codex-field-drift {
    0% { transform: translate3d(-1.8%, -1.2%, 0) scale(1.09) rotate(0deg); }
    50% { transform: translate3d(1.2%, 1.4%, 0) scale(1.14) rotate(4deg); }
    100% { transform: translate3d(-0.6%, 2.1%, 0) scale(1.11) rotate(-3deg); }
}

@keyframes codex-milk-breathe {
    from { transform: translate3d(-1%, 0.5%, 0) scale(1.02) rotate(-2deg); opacity: 0.82; }
    to { transform: translate3d(1.5%, -1%, 0) scale(1.08) rotate(3deg); opacity: 0.98; }
}

@keyframes codex-violet-float {
    from { transform: translate3d(-1%, -1%, 0) rotate(-12deg) scale(1); }
    to { transform: translate3d(-7%, 6%, 0) rotate(-2deg) scale(1.12); }
}

@keyframes codex-azure-float {
    from { transform: translate3d(0, 0, 0) rotate(-10deg) scale(1.04); }
    to { transform: translate3d(8%, -5%, 0) rotate(4deg) scale(1.12); }
}

@keyframes codex-focus-shift {
    0% { transform: translate3d(-2%, 0, 0) scale(1); }
    50% { transform: translate3d(1.5%, 2%, 0) scale(1.06); }
    100% { transform: translate3d(3%, -1%, 0) scale(1.03); }
}

@keyframes codex-texture-drift {
    from { transform: translate3d(-1%, -1%, 0); background-position: 0 0, 0 0; }
    to { transform: translate3d(1%, 1%, 0); background-position: 42px -28px, 100% 20%; }
}

@media (max-width: 780px) {
    .codex-nav {
        padding: 18px;
    }

    .codex-cloud-button {
        display: none;
    }

    .codex-hero {
        min-height: 58dvh;
    }

    .codex-logos {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 28px 36px;
    }

    .codex-logos span:last-child {
        grid-column: 1 / -1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .codex-background__base,
    .codex-background__milk,
    .codex-background__violet,
    .codex-background__azure,
    .codex-background__focal,
    .codex-background__texture {
        animation: none;
    }
}
</style>
