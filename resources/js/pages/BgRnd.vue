<script setup lang="ts">
/**
 * Background R&D — a static demo of the Chaos Mode gameplay screen so the
 * ambient AuroraBackground can be previewed (with sample narration + choices)
 * without a live game session. Switch story to see the dynamic colour mapping:
 * the deep base anchors the story while the dominant tone drifts subtly
 * between the story's signature and the LoreSpinner brand colours.
 */
import AuroraBackground from '@/components/AuroraBackground.vue';
import { Head } from '@inertiajs/vue3';
import { LucideArrowUp, LucideChevronLeft, LucidePlay, LucideRefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: null });

const BRAND_TIFFANY = '#08cee6';
const BRAND_AMBER = '#e5ad53';
// LoreSpinner brand UI sits on near-black; the aurora is only a faint glow.
const BRAND_BLACK = '#050409';

const STORY_THEMES: Record<string, { title: string; from: string; via: string; accent: string }> = {
    'tell-tale-heart': { title: 'The Tell-Tale Heart', from: '#7a1a1a', via: '#0d0505', accent: '#f87171' },
    nocturne: { title: 'Nocturne', from: '#0a2a4a', via: '#051015', accent: '#38bdf8' },
    'anima-machina': { title: 'Anima Machina', from: '#4a1a5a', via: '#0d050e', accent: '#e879f9' },
    wonderland: { title: "Alice's Adventures in Wonderland", from: '#3b4a8f', via: '#1a1a2e', accent: '#a78bfa' },
    driftheart: { title: 'Driftheart', from: '#1a3a6a', via: '#050d15', accent: '#67e8f9' },
};

const selectedSlug = ref<keyof typeof STORY_THEMES>('tell-tale-heart');
const theme = computed(() => STORY_THEMES[selectedSlug.value]);

function mixHex(a: string, b: string, t: number): string {
    const parse = (h: string) => {
        const n = h.replace('#', '');
        const v = n.length === 3 ? n.split('').map((c) => c + c).join('') : n;
        const num = Number.parseInt(v, 16);
        return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
    };
    const [r1, g1, b1] = parse(a);
    const [r2, g2, b2] = parse(b);
    const ch = (x: number, y: number) => Math.round(x + (y - x) * t).toString(16).padStart(2, '0');
    return `#${ch(r1, r2)}${ch(g1, g2)}${ch(b1, b2)}`;
}

const gameAurora = computed(() => {
    const t = theme.value;
    // Deep base sits on a darker, brand-leaning black so the screen reads as
    // refined UI. The aurora stays present but dim: its drifting tones are
    // pulled well toward the dark base, lowering their saturation/opacity so
    // the haze is a soft, low-key glow rather than a vivid wash.
    const base = mixHex(t.via, BRAND_BLACK, 0.38);
    return {
        deep: base,
        mids: [
            mixHex(t.from, base, 0.55),
            mixHex(BRAND_TIFFANY, base, 0.66),
            mixHex(t.from, base, 0.55),
            mixHex(BRAND_AMBER, base, 0.7),
        ],
        accent: mixHex(t.accent, base, 0.5),
        highlight: mixHex('#fdf5e4', base, 0.35),
    };
});

const narration = `<p>The lantern guttered as the corridor narrowed, throwing long shapes against the damp stone. Somewhere ahead, water dripped in slow, deliberate beats — as if the dark itself were counting your steps.</p><p>You pressed a palm to the wall and felt it answer: a faint, rhythmic <strong>thud</strong>, deep beneath the mortar. Not water. Something patient. Something that had been waiting a very long time for a visitor to lose their nerve.</p><p>A door waited at the end, its iron handle catching what little light remained.</p>`;

const choices = ['Press your ear to the door and listen.', 'Lift the lantern and search for another way.', 'Call out into the dark — let it know you are here.'];
</script>

<template>
    <Head title="Gameplay Demo — Aurora" />

    <div class="bg-rnd-root relative h-svh overflow-hidden" :style="{ '--chaos-brand': BRAND_AMBER }">
        <div class="relative z-[1] flex h-full flex-col overflow-hidden" :style="{ background: gameAurora.deep }">
            <AuroraBackground
                class="pointer-events-none absolute inset-0 z-0"
                :deep="gameAurora.deep"
                :mids="gameAurora.mids"
                :accent="gameAurora.accent"
                :seconds-per-color="14"
                :highlight="gameAurora.highlight"
                :intensity="0.62"
            />

            <!-- Sticky header -->
            <div class="bg-rnd-header sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between px-4 sm:px-8">
                <button class="grid size-10 place-items-center rounded-full border border-white/10 bg-white/5 backdrop-blur">
                    <LucideChevronLeft class="size-5 text-gray-300" :stroke-width="1.5" />
                </button>

                <div class="flex flex-col items-center">
                    <p class="text-center text-[10px] uppercase tracking-widest text-[color:rgba(229,173,83,0.7)]">Chaos Mode</p>
                    <p class="text-center text-xs text-gray-400">{{ theme.title }} · 1 / 3</p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="hidden rounded-full border px-3 py-1 text-[10px] sm:block border-[rgba(229,173,83,0.28)] bg-[rgba(229,173,83,0.07)] text-[rgba(229,173,83,0.8)]">
                        Claude Sonnet <span class="opacity-60">· 0.90</span>
                    </span>
                    <button class="grid size-10 place-items-center rounded-full border border-white/10 bg-white/5 backdrop-blur">
                        <LucideRefreshCw class="size-4 text-gray-400" :stroke-width="1.5" />
                    </button>
                </div>
            </div>

            <!-- Scrollable story -->
            <div class="relative z-[1] flex-1 overflow-hidden">
                <div class="bg-rnd-scroll absolute inset-0 overflow-y-auto">
                    <div class="mx-auto flex max-w-3xl flex-col divide-y divide-gray-100/10 px-4 pb-8 sm:px-8">
                        <!-- Narrator turn -->
                        <div class="bg-rnd-prose py-8">
                            <div class="mb-3 flex items-center gap-3">
                                <span class="text-[10px] uppercase tracking-widest text-[rgba(229,173,83,0.55)]">Narrator</span>
                                <button class="bg-rnd-tts flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-medium">
                                    <LucidePlay class="size-3" fill="currentColor" />
                                    <span>Listen</span>
                                </button>
                            </div>
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <div v-html="narration" />
                        </div>

                        <!-- Player turn -->
                        <div class="flex items-baseline gap-3 py-4">
                            <span class="shrink-0 text-[10px] uppercase tracking-widest text-gray-600">You</span>
                            <p class="text-sm italic text-gray-500">I steady the lantern and step toward the sound.</p>
                        </div>

                        <!-- Narrator turn 2 -->
                        <div class="bg-rnd-prose py-8">
                            <div class="mb-3 flex items-center gap-3">
                                <span class="text-[10px] uppercase tracking-widest text-[rgba(229,173,83,0.55)]">Narrator</span>
                            </div>
                            <p>The thudding quickens to meet you, eager now, almost glad. Your own pulse falls into step with it until you can no longer tell which heart is yours.</p>
                            <p>The handle is cold. Beyond it, the beating swells — and waits for your hand.</p>
                        </div>

                        <!-- Inline choices -->
                        <div class="py-5">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(choice, i) in choices"
                                    :key="i"
                                    class="bg-rnd-choice rounded-xl border border-[rgba(229,173,83,0.22)] bg-white/5 px-4 py-2.5 text-left text-sm text-[rgba(250,243,228,0.82)] backdrop-blur-sm transition-all hover:border-[rgba(229,173,83,0.55)] hover:text-[rgba(250,243,228,1)] focus:outline-none"
                                >
                                    {{ choice }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky input -->
            <div class="sticky bottom-0 z-10 shrink-0">
                <div class="bg-rnd-input grid h-24 place-items-center px-4">
                    <div class="flex w-full max-w-3xl items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 backdrop-blur">
                        <span class="flex-1 text-sm text-gray-500">Describe what you do…</span>
                        <button class="grid size-9 shrink-0 place-items-center rounded-full bg-[rgba(229,173,83,0.9)] text-gray-950">
                            <LucideArrowUp class="size-4" :stroke-width="2.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Demo story switcher (showcases the dynamic colour mapping) -->
            <div class="pointer-events-auto absolute bottom-28 left-1/2 z-20 flex -translate-x-1/2 flex-wrap justify-center gap-2 px-4">
                <button
                    v-for="(s, slug) in STORY_THEMES"
                    :key="slug"
                    class="bg-rnd-storychip flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] transition-all"
                    :class="selectedSlug === slug ? 'bg-rnd-storychip--active' : ''"
                    @click="selectedSlug = slug"
                >
                    <span class="size-2 rounded-full" :style="{ background: s.accent }" />
                    {{ s.title.split(/[':]/)[0].replace('The ', '').trim() }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-rnd-root {
    --chaos-brand-rgb: 229, 173, 83;
    background: #050409;
}

.bg-rnd-header {
    background: linear-gradient(180deg, rgba(5, 4, 9, 0.97) 0%, rgba(5, 4, 9, 0.84) 60%, rgba(229, 173, 83, 0.02) 100%);
    border-bottom: 1px solid rgba(229, 173, 83, 0.1);
}

.bg-rnd-prose {
    line-height: 1.85;
    color: rgba(250, 243, 228, 0.9);
    font-size: 1rem;
    text-shadow: 0 1px 10px rgba(0, 0, 0, 0.48), 0 0 22px rgba(3, 7, 18, 0.26);
}
.bg-rnd-prose :deep(p) { margin-bottom: 1em; }
.bg-rnd-prose :deep(p:last-child) { margin-bottom: 0; }
.bg-rnd-prose :deep(strong) { color: var(--chaos-brand); font-weight: 500; }

.bg-rnd-tts {
    border-color: rgba(229, 173, 83, 0.18);
    color: rgba(229, 173, 83, 0.5);
}
.bg-rnd-tts:hover {
    border-color: rgba(229, 173, 83, 0.45);
    color: rgba(229, 173, 83, 0.85);
    background: rgba(229, 173, 83, 0.07);
}

.bg-rnd-choice {
    transition: border-color 0.15s, color 0.15s, box-shadow 0.15s, transform 0.1s;
}
.bg-rnd-choice:hover {
    box-shadow: 0 0 0 1px rgba(229, 173, 83, 0.28), 0 4px 16px -4px rgba(229, 173, 83, 0.22);
    transform: translateY(-1px);
}

.bg-rnd-input {
    background: linear-gradient(0deg, rgba(5, 4, 9, 0.99) 0%, rgba(5, 4, 9, 0.92) 60%, transparent 100%);
}

.bg-rnd-storychip {
    border-color: rgba(255, 255, 255, 0.1);
    background: rgba(10, 14, 24, 0.55);
    color: rgba(250, 235, 200, 0.6);
    backdrop-filter: blur(8px);
}
.bg-rnd-storychip:hover {
    border-color: rgba(229, 173, 83, 0.4);
    color: rgba(250, 235, 200, 0.9);
}
.bg-rnd-storychip--active {
    border-color: rgba(229, 173, 83, 0.7) !important;
    background: rgba(229, 173, 83, 0.14) !important;
    color: rgba(229, 173, 83, 0.95) !important;
}

.bg-rnd-scroll::-webkit-scrollbar { width: 4px; }
.bg-rnd-scroll::-webkit-scrollbar-track { background: transparent; }
.bg-rnd-scroll::-webkit-scrollbar-thumb { background: rgba(229, 173, 83, 0.2); border-radius: 2px; }
</style>
