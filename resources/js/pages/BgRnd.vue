<script setup lang="ts">
/**
 * Background R&D — a static demo of the Chaos Mode gameplay screen so the
 * ambient AuroraBackground can be previewed (with sample narration + choices)
 * without a live game session. Switch story to see the dynamic colour mapping:
 * the deep base anchors the story while the dominant tone drifts subtly
 * between the story's signature and the LoreSpinner brand colours.
 */
import AuroraBackground from '@/components/AuroraBackground.vue';
import GameplayChatCard from '@/components/GameplayChatCard.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import { Head } from '@inertiajs/vue3';
import { LucideArrowUp, LucideAudioLines, LucideChevronLeft, LucideNotebookText, LucidePlay, LucideSettings, LucideZap } from 'lucide-vue-next';
import type { PromptInterface } from '@/types';
import { computed, ref } from 'vue';

defineOptions({ layout: null });

const BRAND_TIFFANY = '#08cee6';
const BRAND_AMBER = '#e5ad53';
// LoreSpinner brand UI sits on near-black; the aurora is only a faint glow.
const BRAND_BLACK = '#050409';

// Palette sources for cross-story colour swaps in the demo switcher.
const PALETTE_TELL_TALE = { from: '#7a1a1a', via: '#0d0505', accent: '#f87171' };
const PALETTE_ALICE = { from: '#3b4a8f', via: '#1a1a2e', accent: '#a78bfa' };
const PALETTE_DRIFTHEART = { from: '#1a3a6a', via: '#050d15', accent: '#67e8f9' };
/** Deep umber / plague-earth — Masque of the Red Death */
const PALETTE_MASQUE = { from: '#5c3a22', via: '#0f0a06', accent: '#c4784a' };

const STORY_THEMES: Record<string, { title: string; from: string; via: string; accent: string }> = {
    'tell-tale-heart': { title: 'The Tell-Tale Heart', ...PALETTE_ALICE },
    sherlock: { title: 'Sherlock Holmes', ...PALETTE_TELL_TALE },
    oz: { title: 'The Wonderful Wizard of Oz', ...PALETTE_DRIFTHEART },
    'masque-red-death': { title: 'The Masque of the Red Death', ...PALETTE_MASQUE },
    nocturne: { title: 'Nocturne', from: '#0a2a4a', via: '#051015', accent: '#38bdf8' },
    'anima-machina': { title: 'Anima Machina', from: '#4a1a5a', via: '#0d050e', accent: '#e879f9' },
    driftheart: { title: 'Driftheart', ...PALETTE_DRIFTHEART },
    wonderland: { title: "Alice's Adventures in Wonderland", ...PALETTE_ALICE },
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

const prompts = ref<PromptInterface[]>([
    {
        id: 'bg-rnd-opening',
        game_id: 'bg-rnd',
        session_number: 1,
        prompt: null,
        response:
            `<p>The lantern guttered as the corridor narrowed, throwing long shapes against the damp stone. Somewhere ahead, water dripped in slow, deliberate beats — as if the dark itself were counting your steps.</p>` +
            `<p>You pressed a palm to the wall and felt it answer: a faint, rhythmic <strong>thud</strong>, deep beneath the mortar. Not water. Something patient. Something that had been waiting a very long time for a visitor to lose their nerve.</p>` +
            `<p>A door waited at the end, its iron handle catching what little light remained.</p>`,
        choices: ['Press your ear to the door and listen.', 'Lift the lantern and search for another way.', 'Call out into the dark — let it know you are here.'],
        created_at: null,
        updated_at: null,
    },
    {
        id: 'bg-rnd-second',
        game_id: 'bg-rnd',
        session_number: 1,
        prompt: 'I steady the lantern and step toward the sound.',
        response:
            `<p>The thudding quickens to meet you, eager now, almost glad. Your own pulse falls into step with it until you can no longer tell which heart is yours.</p>` +
            `<p>The handle is cold. Beyond it, the beating swells — and waits for your hand.</p>`,
        choices: [],
        created_at: null,
        updated_at: null,
    },
]);

const pendingSelection = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const selectChoice = (promptId: string, choice: string) => {
    pendingSelection.value[promptId] = choice;
};
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

            <!-- Sticky header (mirrors new gameplay chrome) -->
            <div class="bg-rnd-header sticky top-0 right-0 left-0 z-30 w-full">
                <div class="flex h-20 items-center justify-between gap-3 bg-linear-to-b from-gray-950 via-gray-950/60 to-transparent px-4 sm:px-8 md:h-24">
                    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                        <button class="bg-rnd-glass-btn size-11!">
                            <LucideChevronLeft class="size-6 text-gray-50" :stroke-width="1.75" />
                        </button>
                        <button class="bg-rnd-glass-btn hidden size-11! md:grid">
                            <LucideSettings class="size-5 text-secondary-300" />
                        </button>
                    </div>

                    <div class="hidden min-w-0 flex-1 items-center justify-center md:flex">
                        <div class="bg-rnd-media-pill">
                            <span class="size-2 rounded-full bg-primary-400/85"></span>
                            <span class="text-xs text-gray-300">Narration Ready</span>
                        </div>
                    </div>

                    <div class="hidden shrink-0 items-center gap-2 sm:gap-3 md:flex">
                        <button class="bg-rnd-glass-btn size-11!">
                            <LucideZap class="size-5 text-primary fill-primary" />
                        </button>
                        <button class="bg-rnd-glass-btn size-11!">
                            <LucideAudioLines class="size-5 text-gray-300" />
                        </button>
                        <button class="bg-rnd-glass-btn size-11!">
                            <LucideNotebookText class="size-5 text-secondary-300" />
                        </button>
                    </div>

                    <div class="mobile-pill flex md:hidden">
                        <button class="mobile-pill__btn"><LucideSettings class="size-5 text-gray-300" /></button>
                        <button class="mobile-pill__btn"><LucideZap class="size-5 text-primary fill-primary" /></button>
                        <button class="mobile-pill__btn"><LucideAudioLines class="size-5 text-gray-300" /></button>
                        <button class="mobile-pill__btn"><LucideNotebookText class="size-5 text-gray-300" /></button>
                    </div>
                </div>
            </div>

            <!-- Scrollable story (mirrors GameplayLayout structure) -->
            <div class="relative z-[1] flex-1 overflow-hidden">
                <div class="bg-rnd-scroll absolute inset-0 overflow-y-auto">
                    <div class="z-5 mx-auto flex max-w-3xl flex-col p-4 transition-colors duration-300 sm:px-8">
                        <div class="mb-3 flex flex-col items-center gap-3 pt-2 pb-4">
                            <h1 class="text-center text-2xl font-semibold text-white md:text-[28px]">
                                {{ theme.title }}
                            </h1>
                            <GameplayOrnamentDivider label="Episode 1" color="#ffffff" />
                            <span class="rounded-full bg-gray-800 px-2 py-1 text-sm text-gray-300">Session 1</span>
                        </div>

                        <div class="flex flex-col gap-8">
                            <GameplayChatCard
                                v-for="(prompt, index) in prompts"
                                :key="prompt.id"
                                :prompt="prompt"
                                game-id="bg-rnd"
                                :is-latest="index === prompts.length - 1"
                                :pending-choice="pendingSelection[prompt.id]"
                                :is-submitting="isSubmitting"
                                :animate="false"
                                @choice-selected="selectChoice"
                                @continue="() => {}"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky input -->
            <div class="sticky bottom-0 z-10 shrink-0">
                <div class="bg-rnd-input flex flex-col items-center gap-3 px-4 pt-10 pb-6 md:px-0">
                    <GameplayInput :disabled="false" @submit="() => {}" />
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

.narration-action-pill {
    transition:
        transform 150ms ease,
        color 150ms ease;
}

.narration-action-pill:hover {
    transform: scale(1.02);
}

.bg-rnd-glass-btn {
    display: grid;
    place-items: center;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.bg-rnd-media-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    padding: 8px 12px;
}

.mobile-pill {
    align-items: center;
    gap: 2px;
    padding: 6px;
    border-radius: 60px;
    background-color: rgba(51, 51, 51, 0.45);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow:
        inset 3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.5),
        inset -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.55),
        inset 1px 1px 1px -0.5px rgba(255, 255, 255, 0.3),
        inset -1px -1px 1px -0.5px rgba(255, 255, 255, 0.3),
        inset 0 0 1px 1px rgba(153, 153, 153, 0.15),
        0 4px 24px rgba(0, 0, 0, 0.3);
}

.mobile-pill__btn {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    background: transparent;
}
</style>
