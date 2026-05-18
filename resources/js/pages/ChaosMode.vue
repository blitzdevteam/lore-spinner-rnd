<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import { useChaosTextToSpeech } from '@/composables/useChaosTextToSpeech';
import { LucideChevronLeft, LucideLoader, LucidePause, LucidePlay, LucideRefreshCw, LucideX } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

defineOptions({ layout: null });

interface WorldState {
    location: string;
    conditions: string[];
    items: string[];
    relationships: string[];
    knowledge: string[];
    notes: string[];
}

interface Turn {
    role: 'narrator' | 'player';
    html?: string;
    text: string;
}

defineProps<{
    storyTitle: string;
    storyExists: boolean;
}>();

const MODELS = [
    { value: 'gpt-5.5',          label: 'GPT-5.5',          provider: 'OpenAI',    est: '~$1.70' },
    { value: 'gpt-5.4',          label: 'GPT-5.4',          provider: 'OpenAI',    est: '~$0.85' },
    { value: 'gpt-5.2',          label: 'GPT-5.2',          provider: 'OpenAI',    est: '~$0.45' },
    { value: 'gpt-4.1',          label: 'GPT-4.1',          provider: 'OpenAI',    est: '~$0.65' },
    { value: 'claude-opus-4-6',  label: 'Claude Opus 4.6',  provider: 'Anthropic', est: '~$1.65' },
    { value: 'claude-sonnet-4-5', label: 'Claude Sonnet 4.5', provider: 'Anthropic', est: '~$1.00' },
];

const selectedModelMeta = computed(() => MODELS.find((m) => m.value === selectedModel.value));

const tts = useChaosTextToSpeech();

const selectedModel = ref('gpt-5.5');
const started = ref(false);
const loading = ref(false);
const sessionId = ref<string | null>(null);
const sessionComplete = ref(false);
const turns = ref<Turn[]>([]);
const choicesBuffer = ref<string[]>([]);
const worldState = ref<WorldState>(emptyWorldState());
const errorMessage = ref('');
const scrollEl   = ref<HTMLElement | null>(null);
const topShadow  = ref(0);
const botShadow  = ref(0);

const updateShadows = () => {
    const el = scrollEl.value;
    if (!el) return;
    const { scrollTop, scrollHeight, clientHeight } = el;
    const maxScroll = scrollHeight - clientHeight;
    if (maxScroll <= 0) { topShadow.value = 0; botShadow.value = 0; return; }
    topShadow.value = Math.min(scrollTop / 80, 1);
    botShadow.value = Math.min((maxScroll - scrollTop) / 80, 1);
};

// Initialise shadows once the game screen mounts
watch(scrollEl, (el) => {
    if (el) updateShadows();
});

// Maps each turn array index → narrator-only index (for TTS endpoint)
const narratorIndexMap = computed(() => {
    const map = new Map<number, number>();
    let count = 0;
    turns.value.forEach((turn, idx) => {
        if (turn.role === 'narrator') {
            map.set(idx, count++);
        }
    });
    return map;
});

function emptyWorldState(): WorldState {
    return {
        location: '',
        conditions: [],
        items: [],
        relationships: [],
        knowledge: [],
        notes: [],
    };
}

// ─── CSRF: read Laravel's XSRF-TOKEN cookie, send as X-XSRF-TOKEN ─────────────
function getCsrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function apiFetch(url: string, body: object): Promise<any> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrf(),
        },
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new Error(text || `HTTP ${res.status}`);
    }
    return res.json();
}

function stripHtml(html: string): string {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent ?? '';
}

async function scrollToBottom(): Promise<void> {
    await nextTick();
    if (scrollEl.value) {
        scrollEl.value.scrollTo({ top: scrollEl.value.scrollHeight, behavior: 'smooth' });
    }
    updateShadows();
}

function applyResponse(data: any): void {
    if (data.session_id) sessionId.value = data.session_id;
    if (data.world_state) worldState.value = { ...emptyWorldState(), ...data.world_state };
    if (typeof data.session_complete === 'boolean') sessionComplete.value = data.session_complete;
    turns.value.push({ role: 'narrator', html: data.response, text: stripHtml(data.response) });
    choicesBuffer.value = data.choices ?? [];
}

async function startWithChoices(): Promise<void> {
    if (loading.value) return;
    loading.value = true;
    errorMessage.value = '';
    choicesBuffer.value = [];

    try {
        const data = await apiFetch('/chaos-mode/start', { model: selectedModel.value });
        turns.value = [];
        applyResponse(data);
        started.value = true;
        await scrollToBottom();
    } catch {
        errorMessage.value = 'Wonderland is currently unavailable. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function takeTurn(action: string): Promise<void> {
    const trimmed = action.trim();
    if (!trimmed || loading.value || sessionComplete.value || !sessionId.value) return;

    loading.value = true;
    errorMessage.value = '';
    turns.value.push({ role: 'player', text: trimmed });
    choicesBuffer.value = [];
    await scrollToBottom();

    try {
        const data = await apiFetch('/chaos-mode/turn', {
            session_id: sessionId.value,
            player_action: trimmed,
            model: selectedModel.value,
        });
        applyResponse(data);
        await scrollToBottom();
    } catch {
        turns.value.pop();
        errorMessage.value = 'Wonderland hiccuped — please try again.';
    } finally {
        loading.value = false;
    }
}

function resetAdventure(): void {
    tts.dismiss();
    started.value = false;
    sessionId.value = null;
    sessionComplete.value = false;
    turns.value = [];
    choicesBuffer.value = [];
    worldState.value = emptyWorldState();
    errorMessage.value = '';
}
</script>

<template>
    <div class="chaos-mode-root relative h-svh overflow-hidden">
        <BaseBackgroundGradient />
        <!-- Chaos Mode brand tint (#E5AD53) — landing + playthrough -->
        <div class="chaos-mode-brand-bg pointer-events-none absolute inset-0" aria-hidden="true" />

        <!-- ── Start screen ──────────────────────────────────────────────────── -->
        <div v-if="!started" class="relative z-[1] flex h-full flex-col items-center justify-center px-4">
            <div
                class="chaos-mode-config-card w-full max-w-md rounded-2xl border p-8 text-center backdrop-blur-sm"
            >
                <p class="chaos-mode-eyebrow mb-3 text-xs uppercase tracking-widest">
                    Experimental — Chaos Mode
                </p>

                <h1 class="chaos-mode-title mb-1 text-2xl font-medium sm:text-3xl">
                    Alice's Adventures<br />in Wonderland
                </h1>
                <p class="chaos-mode-lede mb-6 text-sm">
                    Full agency. Type anything; Wonderland absorbs everything.
                </p>

                <div class="mb-6 text-left">
                    <div class="mb-2 flex items-baseline justify-between">
                        <label class="chaos-mode-field-label text-xs uppercase tracking-widest">
                            Narrator model
                        </label>
                        <span class="chaos-mode-cost-est text-[10px]">
                            {{ selectedModelMeta?.est }} / session
                        </span>
                    </div>
                    <select
                        v-model="selectedModel"
                        class="chaos-mode-select w-full rounded-lg border px-3 py-2.5 text-sm outline-none"
                    >
                        <optgroup v-for="provider in ['OpenAI', 'Anthropic']" :key="provider" :label="provider">
                            <option
                                v-for="m in MODELS.filter((x) => x.provider === provider)"
                                :key="m.value"
                                :value="m.value"
                            >
                                {{ m.label }} — {{ m.est }}
                            </option>
                        </optgroup>
                    </select>
                    <p class="chaos-mode-cost-note mt-1.5 text-[10px] leading-relaxed">
                        Estimate based on ~20 turns with full session context. Actual cost varies.
                    </p>
                </div>

                <BaseButton
                    class="chaos-mode-cta w-full"
                    severity="primary"
                    :disabled="loading"
                    @click="startWithChoices"
                >
                    <span v-if="loading" class="flex items-center justify-center gap-2 text-[#1f160d]">
                        <span class="chaos-mode-cta-spinner size-4 animate-spin rounded-full border-2 border-[#1f160d]/25 border-t-[#1f160d]/85" />
                        Falling down the rabbit-hole...
                    </span>
                    <span v-else>Begin the Adventure</span>
                </BaseButton>

                <p v-if="errorMessage" class="mt-4 text-sm text-red-400">{{ errorMessage }}</p>

                <a href="/" class="chaos-mode-back-link mt-6 block text-xs transition-colors">← Back to LoreSpinner</a>
            </div>
        </div>

        <!-- ── Game screen ───────────────────────────────────────────────────── -->
        <div v-else class="relative z-[1] flex h-full flex-col">

            <!-- Sticky header -->
            <div
                class="chaos-mode-game-header sticky top-0 z-10 flex h-20 shrink-0 items-center justify-between px-4 sm:px-8"
            >
                <BaseButton severity="glass" :icon-only="true" class="size-12!" @click="resetAdventure">
                    <LucideChevronLeft class="size-6 text-gray-300" :stroke-width="1.5" />
                </BaseButton>

                <p class="text-center text-xs uppercase tracking-widest text-[color:rgba(229,173,83,0.65)]">
                    {{ sessionComplete ? 'Session Complete' : 'Down the Rabbit-Hole' }}
                </p>

                <div class="flex items-center gap-2">
                    <span
                        class="hidden rounded-full border px-3 py-1 text-xs sm:block border-[rgba(229,173,83,0.25)] bg-[rgba(229,173,83,0.08)] text-[rgba(229,173,83,0.85)]"
                    >
                        {{ MODELS.find((m) => m.value === selectedModel)?.label }}
                    </span>
                    <BaseButton severity="glass" :icon-only="true" class="size-10!" title="New adventure" @click="resetAdventure">
                        <LucideRefreshCw class="size-4 text-gray-400" :stroke-width="1.5" />
                    </BaseButton>
                </div>
            </div>

            <!-- Floating TTS media player -->
            <div class="pointer-events-none sticky top-20 z-20 flex justify-center">
                <Transition name="player-slide">
                    <div
                        v-if="tts.isActive.value"
                        class="pointer-events-auto relative flex items-center gap-3 overflow-hidden rounded-full border border-gray-700/60 py-2 pe-3 ps-2 shadow-2xl backdrop-blur-xl bg-gray-900/80! border-[rgba(229,173,83,0.25)]!"
                    >
                        <!-- Play / Pause -->
                        <button
                            class="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-full transition-transform hover:scale-105 active:scale-95 bg-[var(--chaos-brand)]"
                            @click="tts.togglePause"
                        >
                            <LucidePause v-if="tts.isPlaying.value" class="size-4 text-[#1f160d]" fill="currentColor" />
                            <LucidePlay v-else-if="!tts.isLoading.value" class="size-4 text-[#1f160d]" fill="currentColor" />
                            <LucideLoader v-else class="size-4 animate-spin text-[#1f160d]" />
                        </button>
                        <!-- Time -->
                        <span class="min-w-16 text-sm font-medium tabular-nums text-gray-200">
                            {{ tts.formattedCurrentTime.value }}
                            <span class="text-gray-500">/</span>
                            {{ tts.formattedDuration.value }}
                        </span>
                        <!-- Speed -->
                        <button
                            class="rounded-full border px-2.5 py-0.5 text-xs font-semibold tabular-nums transition-colors border-[rgba(229,173,83,0.35)] text-[rgba(229,173,83,0.85)] hover:border-[rgba(229,173,83,0.6)] hover:bg-[rgba(229,173,83,0.1)]"
                            @click="tts.cycleSpeed"
                        >
                            {{ tts.playbackRate.value }}x
                        </button>
                        <!-- Close -->
                        <button
                            class="grid size-7 shrink-0 place-items-center rounded-full text-gray-400 transition-colors hover:bg-gray-700 hover:text-gray-200"
                            @click="tts.dismiss"
                        >
                            <LucideX class="size-4" />
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- Scrollable story with glass fade shadows -->
            <div class="relative flex-1 overflow-hidden">
                <div ref="scrollEl" class="chaos-scroll absolute inset-0 overflow-y-auto" @scroll.passive="updateShadows">
                <div class="mx-auto flex max-w-3xl flex-col divide-y divide-gray-100/20 px-4 pb-4 sm:px-8">

                    <template v-for="(turn, idx) in turns" :key="idx">
                        <div v-if="turn.role === 'narrator'" class="chaos-prose py-8">
                            <div class="mb-3 flex items-center gap-3">
                                <span class="text-[10px] uppercase tracking-widest text-[rgba(229,173,83,0.45)]">Narrator</span>
                                <button
                                    v-if="sessionId"
                                    class="chaos-tts-btn flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-medium transition-all"
                                    :class="{
                                        'active': tts.activeKey.value === `${sessionId}:${narratorIndexMap.get(idx)}`,
                                    }"
                                    @click="tts.toggle(sessionId!, narratorIndexMap.get(idx)!)"
                                >
                                    <LucideLoader
                                        v-if="tts.isLoading.value && tts.activeKey.value === `${sessionId}:${narratorIndexMap.get(idx)}`"
                                        class="size-3 animate-spin"
                                    />
                                    <LucidePause
                                        v-else-if="tts.isPlaying.value && tts.activeKey.value === `${sessionId}:${narratorIndexMap.get(idx)}`"
                                        class="size-3"
                                        fill="currentColor"
                                    />
                                    <LucidePlay v-else class="size-3" fill="currentColor" />
                                    <span>{{ tts.isPlaying.value && tts.activeKey.value === `${sessionId}:${narratorIndexMap.get(idx)}` ? 'Pause' : 'Listen' }}</span>
                                </button>
                            </div>
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <div v-html="turn.html ?? turn.text" />
                        </div>

                        <div v-else class="flex items-baseline gap-3 py-4">
                            <span class="shrink-0 text-[10px] uppercase tracking-widest text-gray-600">Alice</span>
                            <p class="text-sm italic text-gray-500">{{ turn.text }}</p>
                        </div>
                    </template>

                    <div v-if="loading" class="py-8">
                        <div class="flex animate-pulse flex-col gap-4">
                            <div class="h-4 w-full rounded bg-gray-700/50" />
                            <div class="h-4 w-5/6 rounded bg-gray-700/50" />
                            <div class="h-4 w-3/4 rounded bg-gray-700/50" />
                            <div class="h-4 w-4/6 rounded bg-gray-700/40" />
                        </div>
                    </div>

                    <!-- Session complete banner — only when AI returns session_complete:true -->
                    <div
                        v-if="sessionComplete"
                        class="my-8 rounded-2xl border p-6 text-center backdrop-blur-sm border-[rgba(229,173,83,0.35)] bg-[rgba(229,173,83,0.07)]"
                    >
                        <p class="mb-1 text-[10px] uppercase tracking-widest text-[rgba(229,173,83,0.55)]">Session Complete</p>
                        <p class="mb-2 text-xl font-medium text-[var(--chaos-brand)]">Alice has crossed the threshold.</p>
                        <p class="mb-6 text-sm leading-relaxed text-gray-400">
                            The first arc is complete. Wonderland's logic has taken hold. Session 2 awaits beyond the looking glass.
                        </p>
                        <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                            <BaseButton class="chaos-mode-cta w-full sm:w-auto" severity="primary" @click="resetAdventure">
                                Begin a New Adventure
                            </BaseButton>
                            <BaseButton severity="glass" class="w-full sm:w-auto" @click="resetAdventure">
                                Continue to Session 2
                            </BaseButton>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Top shadow — fades in as you scroll down -->
                <div
                    class="pointer-events-none absolute top-0 right-0 left-0 h-14 bg-gradient-to-b from-gray-950 to-transparent transition-opacity duration-200"
                    :style="{ opacity: topShadow }"
                />
                <!-- Bottom shadow — fades in when there's more content to scroll -->
                <div
                    class="pointer-events-none absolute right-0 bottom-0 left-0 h-20 bg-gradient-to-t from-gray-950 to-transparent transition-opacity duration-200"
                    :style="{ opacity: botShadow }"
                />
            </div>

            <!-- World state chips -->
            <div
                v-if="worldState.location || worldState.conditions.length || worldState.items.length"
                class="mx-auto flex w-full max-w-3xl flex-wrap gap-1.5 px-4 pb-1 sm:px-8"
            >
                <span v-if="worldState.location" class="rounded-full border border-gray-800 px-2 py-0.5 text-[10px] text-gray-600">
                    {{ worldState.location }}
                </span>
                <span v-for="cond in worldState.conditions" :key="`c-${cond}`" class="rounded-full border border-gray-800 px-2 py-0.5 text-[10px] text-gray-600">
                    {{ cond }}
                </span>
                <span
                    v-for="item in worldState.items"
                    :key="`i-${item}`"
                    class="rounded-full border px-2 py-0.5 text-[10px] border-[rgba(229,173,83,0.35)] text-[var(--chaos-brand)]"
                >
                    {{ item }}
                </span>
            </div>

            <p v-if="errorMessage" class="px-4 pb-1 text-center text-xs text-red-400 sm:px-8">{{ errorMessage }}</p>

            <!-- Sticky bottom: choices + input -->
            <div v-if="!sessionComplete" class="sticky bottom-0 z-10 shrink-0">
                <div v-if="choicesBuffer.length && !loading" class="mx-auto max-w-3xl px-4 pb-2 sm:px-8">
                    <div class="flex flex-wrap gap-2">
                        <BaseButton
                            v-for="(choice, i) in choicesBuffer"
                            :key="i"
                            severity="glass"
                            class="text-left text-xs!"
                            :disabled="loading"
                            @click="takeTurn(choice)"
                        >
                            {{ choice }}
                        </BaseButton>
                    </div>
                </div>

                <div class="grid h-28 place-items-center px-4">
                    <GameplayInput :disabled="loading" @submit="takeTurn" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Chaos Mode brand: #E5AD53 */
.chaos-mode-root {
    --chaos-brand: #e5ad53;
    --chaos-brand-rgb: 229, 173, 83;
}

.chaos-mode-brand-bg {
    background:
        radial-gradient(ellipse 120% 80% at 50% -20%, rgba(var(--chaos-brand-rgb), 0.18), transparent 55%),
        radial-gradient(ellipse 90% 70% at 100% 50%, rgba(var(--chaos-brand-rgb), 0.1), transparent 50%),
        radial-gradient(ellipse 80% 60% at 0% 80%, rgba(var(--chaos-brand-rgb), 0.12), transparent 45%);
}

.chaos-mode-config-card {
    border-color: rgba(var(--chaos-brand-rgb), 0.35);
    background: linear-gradient(
        165deg,
        rgba(var(--chaos-brand-rgb), 0.14) 0%,
        rgba(14, 12, 9, 0.82) 42%,
        rgba(10, 9, 7, 0.72) 100%
    );
    box-shadow:
        0 0 0 1px rgba(var(--chaos-brand-rgb), 0.08) inset,
        0 28px 56px -28px rgba(0, 0, 0, 0.55);
}

.chaos-mode-eyebrow {
    color: rgba(var(--chaos-brand-rgb), 0.92);
    text-shadow: 0 0 24px rgba(var(--chaos-brand-rgb), 0.25);
}

.chaos-mode-title {
    color: #faf6ef;
    text-shadow: 0 1px 0 rgba(0, 0, 0, 0.25);
}

.chaos-mode-lede {
    color: rgba(245, 236, 216, 0.58);
}

.chaos-mode-field-label {
    color: rgba(var(--chaos-brand-rgb), 0.62);
}

.chaos-mode-cost-est {
    color: rgba(var(--chaos-brand-rgb), 0.5);
    font-variant-numeric: tabular-nums;
}

.chaos-mode-cost-note {
    color: rgba(229, 217, 192, 0.28);
    font-style: italic;
}

.chaos-mode-select {
    color: #f3ebe0;
    background-color: rgba(8, 7, 5, 0.92);
    border-color: rgba(var(--chaos-brand-rgb), 0.32);
}

.chaos-mode-select:focus {
    border-color: rgba(var(--chaos-brand-rgb), 0.65);
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.28);
}

.chaos-mode-select option,
.chaos-mode-select optgroup {
    background-color: #141210;
    color: #f3ebe0;
}

/* Gold CTA — replaces default primary-400 so the card stays on-brand */
.chaos-mode-config-card :deep(.chaos-mode-cta) {
    background-color: var(--chaos-brand) !important;
    color: #1f160d !important;
    border-color: rgba(31, 22, 13, 0.12) !important;
    outline-color: rgba(var(--chaos-brand-rgb), 0.35) !important;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.chaos-mode-config-card :deep(.chaos-mode-cta:not(.pointer-events-none)) {
    opacity: 1;
}

.chaos-mode-config-card :deep(.chaos-mode-cta:not(.pointer-events-none):hover) {
    filter: brightness(1.06);
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.45), 0 12px 28px -8px rgba(var(--chaos-brand-rgb), 0.35);
}

.chaos-mode-config-card :deep(.chaos-mode-cta.pointer-events-none) {
    background-color: rgba(var(--chaos-brand-rgb), 0.42) !important;
    color: rgba(31, 22, 13, 0.72) !important;
    opacity: 1 !important;
}

.chaos-mode-back-link {
    color: rgba(var(--chaos-brand-rgb), 0.45);
}

.chaos-mode-back-link:hover {
    color: rgba(var(--chaos-brand-rgb), 0.88);
}

.chaos-mode-game-header {
    background: linear-gradient(
        180deg,
        rgba(3, 7, 18, 0.97) 0%,
        rgba(3, 7, 18, 0.88) 55%,
        rgba(var(--chaos-brand-rgb), 0.04) 100%
    );
    border-bottom: 1px solid rgba(var(--chaos-brand-rgb), 0.12);
}

.chaos-prose {
    line-height: 1.85;
    color: inherit;
}

.chaos-prose :deep(p) {
    margin-bottom: 1em;
}
.chaos-prose :deep(p:last-child) {
    margin-bottom: 0;
}
.chaos-prose :deep(em) {
    font-style: italic;
}
.chaos-prose :deep(strong) {
    color: var(--chaos-brand);
    font-weight: 500;
}

/* TTS listen button */
.chaos-tts-btn {
    border-color: rgba(229, 173, 83, 0.18);
    color: rgba(229, 173, 83, 0.5);
}
.chaos-tts-btn:hover {
    border-color: rgba(229, 173, 83, 0.45);
    color: rgba(229, 173, 83, 0.85);
    background: rgba(229, 173, 83, 0.07);
}
.chaos-tts-btn.active {
    border-color: rgba(229, 173, 83, 0.45);
    color: rgba(229, 173, 83, 0.9);
    background: rgba(229, 173, 83, 0.1);
}

/* Thin gold-tinted scrollbar — mirrors Index.vue pattern */
.chaos-scroll::-webkit-scrollbar {
    width: 4px;
}
.chaos-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.chaos-scroll::-webkit-scrollbar-thumb {
    background: rgba(229, 173, 83, 0.18);
    border-radius: 2px;
}
.chaos-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(229, 173, 83, 0.38);
}

/* Floating player slide */
.player-slide-enter-active,
.player-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.player-slide-enter-from,
.player-slide-leave-to {
    opacity: 0;
    transform: translateY(-12px) scale(0.95);
}
</style>
