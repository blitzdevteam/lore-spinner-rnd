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

interface ChaosStoryOption {
    slug: string;
    title: string;
    tagline: string;
    protagonist: string;
    available: boolean;
    total_sessions: number;
    cover?: string | null;
}

const STORY_THEMES: Record<string, { from: string; via: string; accent: string }> = {
    'alices-adventures-in-wonderland':   { from: '#3b4a8f', via: '#1a1a2e', accent: '#a78bfa' },
    'the-adventure-of-the-speckled-band':{ from: '#6b4c2a', via: '#1a1209', accent: '#d4a96a' },
    'the-tell-tale-heart':               { from: '#7a1a1a', via: '#0d0505', accent: '#f87171' },
    'nocturne':                          { from: '#0a2a4a', via: '#051015', accent: '#38bdf8' },
    'anima-machina':                     { from: '#4a1a5a', via: '#0d050e', accent: '#e879f9' },
    'driftheart':                        { from: '#1a3a6a', via: '#050d15', accent: '#67e8f9' },
    '__default__':                       { from: '#3a2800', via: '#0f0b00', accent: '#e5ad53' },
};

const props = defineProps<{
    stories: ChaosStoryOption[];
}>();

const MODELS = [
    { value: 'gpt-5.5',           label: 'GPT-5.5',           provider: 'OpenAI',    est: '~$1.70', defaultTemp: 0.9  },
    { value: 'gpt-5.4',           label: 'GPT-5.4',           provider: 'OpenAI',    est: '~$0.85', defaultTemp: 1.0  },
    { value: 'gpt-5.4-mini',      label: 'GPT-5.4 Mini',      provider: 'OpenAI',    est: '~$0.30', defaultTemp: 0.95 },
    { value: 'gpt-5.2',           label: 'GPT-5.2',           provider: 'OpenAI',    est: '~$0.45', defaultTemp: 1.0  },
    { value: 'gpt-4.1',           label: 'GPT-4.1',           provider: 'OpenAI',    est: '~$0.65', defaultTemp: 1.0  },
    { value: 'claude-opus-4-7',   label: 'Claude Opus 4.7',   provider: 'Anthropic', est: '~$1.85', defaultTemp: 1.0  },
    { value: 'claude-sonnet-4-6', label: 'Claude Sonnet 4.6', provider: 'Anthropic', est: '~$1.05', defaultTemp: 1.0  },
    { value: 'claude-haiku-4-5',  label: 'Claude Haiku 4.5',  provider: 'Anthropic', est: '~$0.30', defaultTemp: 0.95 },
];

const selectedModelMeta = computed(() => MODELS.find((m) => m.value === selectedModel.value));

const tts = useChaosTextToSpeech();

const availableStories = computed(() => props.stories.filter((s) => s.available));
const firstAvailableSlug = computed(() => availableStories.value[0]?.slug ?? props.stories[0]?.slug ?? '');

const selectedStorySlug = ref<string>(firstAvailableSlug.value);
const selectedModel = ref('gpt-5.5');
const selectedTemperature = ref(0.9);
const started = ref(false);
const loading = ref(false);
const sessionId = ref<string | null>(null);
const sessionComplete = ref(false);
const sessionNumber = ref(1);
const totalSessions = ref(1);
const hasNextSession = ref(false);
const protagonist = ref('the protagonist');
const storyTitle = ref('');
const turns = ref<Turn[]>([]);
const choicesBuffer = ref<string[]>([]);
const worldState = ref<WorldState>(emptyWorldState());
const errorMessage = ref('');
const scrollEl = ref<HTMLElement | null>(null);
const topShadow = ref(0);
const botShadow = ref(0);

const selectedStory = computed(() => props.stories.find((s) => s.slug === selectedStorySlug.value));

const storyTheme = (slug: string) => STORY_THEMES[slug] ?? STORY_THEMES['__default__'];

const updateShadows = () => {
    const el = scrollEl.value;
    if (!el) return;
    const { scrollTop, scrollHeight, clientHeight } = el;
    const maxScroll = scrollHeight - clientHeight;
    if (maxScroll <= 0) { topShadow.value = 0; botShadow.value = 0; return; }
    topShadow.value = Math.min(scrollTop / 80, 1);
    botShadow.value = Math.min((maxScroll - scrollTop) / 80, 1);
};

watch(scrollEl, (el) => {
    if (el) updateShadows();
});

watch(firstAvailableSlug, (val) => {
    if (!selectedStorySlug.value) selectedStorySlug.value = val;
});

watch(selectedModel, (model) => {
    const meta = MODELS.find((m) => m.value === model);
    if (meta) selectedTemperature.value = meta.defaultTemp;
});

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
    return { location: '', conditions: [], items: [], relationships: [], knowledge: [], notes: [] };
}

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
    if (typeof data.session_number === 'number') sessionNumber.value = data.session_number;
    if (typeof data.total_sessions === 'number') totalSessions.value = data.total_sessions;
    if (typeof data.has_next_session === 'boolean') hasNextSession.value = data.has_next_session;
    if (data.protagonist) protagonist.value = data.protagonist;
    if (data.story_title) storyTitle.value = data.story_title;
    turns.value.push({ role: 'narrator', html: data.response, text: stripHtml(data.response) });
    choicesBuffer.value = data.choices ?? [];
}

async function startWithChoices(): Promise<void> {
    if (loading.value || !selectedStorySlug.value) return;
    loading.value = true;
    errorMessage.value = '';
    choicesBuffer.value = [];
    try {
        const data = await apiFetch('/chaos-mode/start', {
            story_slug: selectedStorySlug.value,
            model: selectedModel.value,
            temperature: selectedTemperature.value,
        });
        turns.value = [];
        applyResponse(data);
        started.value = true;
        await scrollToBottom();
    } catch {
        errorMessage.value = 'The narration engine is currently unavailable. Please try again.';
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
            temperature: selectedTemperature.value,
        });
        applyResponse(data);
        await scrollToBottom();
    } catch {
        turns.value.pop();
        errorMessage.value = 'The narration hiccuped — please try again.';
    } finally {
        loading.value = false;
    }
}

async function continueToNextSession(): Promise<void> {
    if (loading.value || !sessionId.value || !hasNextSession.value) return;
    loading.value = true;
    errorMessage.value = '';
    tts.dismiss();
    try {
        const data = await apiFetch('/chaos-mode/continue', {
            session_id: sessionId.value,
            model: selectedModel.value,
            temperature: selectedTemperature.value,
        });
        turns.value = [];
        sessionComplete.value = false;
        choicesBuffer.value = [];
        applyResponse(data);
        await scrollToBottom();
    } catch {
        errorMessage.value = 'Could not open the next session. Please try again.';
    } finally {
        loading.value = false;
    }
}

function resetAdventure(): void {
    tts.dismiss();
    started.value = false;
    sessionId.value = null;
    sessionComplete.value = false;
    sessionNumber.value = 1;
    totalSessions.value = 1;
    hasNextSession.value = false;
    turns.value = [];
    choicesBuffer.value = [];
    worldState.value = emptyWorldState();
    errorMessage.value = '';
}
</script>

<template>
    <div class="chaos-mode-root relative h-svh overflow-hidden">
        <BaseBackgroundGradient />
        <div class="chaos-mode-brand-bg pointer-events-none absolute inset-0" aria-hidden="true" />

        <!-- ── Start screen ──────────────────────────────────────────────────── -->
        <div v-if="!started" class="relative z-[1] flex h-full flex-col items-center justify-center overflow-y-auto px-4 py-8">
            <div class="chaos-mode-config-card w-full max-w-2xl rounded-2xl border p-7 backdrop-blur-xl sm:p-8">

                <p class="chaos-mode-eyebrow mb-3 text-xs uppercase tracking-widest">
                    Experimental — Chaos Mode
                </p>
                <h1 class="chaos-mode-title mb-1 font-gill-sans text-2xl font-medium sm:text-3xl">
                    Step into the story.
                </h1>
                <p class="chaos-mode-lede mb-8 text-sm">
                    Full agency. Type anything; the world absorbs it.
                </p>

                <!-- Story selector cards -->
                <div class="mb-7">
                    <label class="chaos-mode-field-label mb-3 block text-xs uppercase tracking-widest">
                        Choose Your Story
                    </label>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <button
                            v-for="story in stories"
                            :key="story.slug"
                            :disabled="!story.available"
                            class="story-card relative overflow-hidden rounded-xl border text-left transition-all focus:outline-none"
                            :class="{
                                'story-card--selected': selectedStorySlug === story.slug && story.available,
                                'story-card--unavailable': !story.available,
                            }"
                            @click="story.available && (selectedStorySlug = story.slug)"
                        >
                            <!-- Theme gradient background -->
                            <div
                                class="story-card-bg absolute inset-0"
                                :style="{
                                    background: `radial-gradient(ellipse 120% 100% at 50% 0%, ${storyTheme(story.slug).from}cc 0%, ${storyTheme(story.slug).via} 70%)`,
                                }"
                            />
                            <!-- Cover image if available -->
                            <img
                                v-if="story.cover"
                                :src="story.cover"
                                :alt="story.title"
                                class="absolute inset-0 h-full w-full object-cover object-center opacity-30"
                            />
                            <!-- Accent top stripe -->
                            <div
                                class="absolute top-0 right-0 left-0 h-0.5 transition-opacity"
                                :style="{ background: storyTheme(story.slug).accent, opacity: selectedStorySlug === story.slug ? 1 : 0.3 }"
                            />
                            <!-- Content -->
                            <div class="relative z-[1] flex flex-col gap-1.5 p-4">
                                <h3
                                    class="story-card-title font-gill-sans text-sm font-semibold leading-snug sm:text-base"
                                    :style="{ color: story.available ? '#faf6ef' : 'rgba(250,246,239,0.45)' }"
                                >
                                    {{ story.title }}
                                </h3>
                                <p
                                    class="story-card-tagline line-clamp-2 text-[11px] leading-relaxed"
                                    :style="{ color: story.available ? 'rgba(229,217,192,0.72)' : 'rgba(229,217,192,0.3)' }"
                                >
                                    {{ story.tagline }}
                                </p>
                                <div class="mt-2 flex items-center justify-between">
                                    <span
                                        class="text-[10px] uppercase tracking-wider"
                                        :style="{ color: story.available ? storyTheme(story.slug).accent : 'rgba(255,255,255,0.2)' }"
                                    >
                                        As {{ story.protagonist }}
                                    </span>
                                    <span
                                        v-if="!story.available"
                                        class="rounded-full border border-gray-700 px-2 py-0.5 text-[9px] uppercase tracking-wider text-gray-600"
                                    >
                                        Soon
                                    </span>
                                    <span
                                        v-else-if="selectedStorySlug === story.slug"
                                        class="story-card-check flex size-4 items-center justify-center rounded-full text-[10px] text-[#1f160d]"
                                        :style="{ backgroundColor: storyTheme(story.slug).accent }"
                                    >✓</span>
                                </div>
                            </div>
                        </button>
                    </div>
                    <p v-if="selectedStory?.tagline" class="chaos-mode-tagline mt-2 text-[11px] italic opacity-0">
                        {{ selectedStory.tagline }}
                    </p>
                </div>

                <!-- Model selector -->
                <div class="mb-5">
                    <div class="mb-2 flex items-baseline justify-between">
                        <label class="chaos-mode-field-label text-xs uppercase tracking-widest">Narrator Model</label>
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

                <!-- Temperature slider -->
                <div class="mb-7">
                    <div class="mb-2 flex items-baseline justify-between">
                        <label class="chaos-mode-field-label text-xs uppercase tracking-widest">Temperature</label>
                        <span class="chaos-mode-temp-value tabular-nums text-[11px] font-medium">
                            {{ selectedTemperature.toFixed(2) }}
                        </span>
                    </div>
                    <input
                        v-model.number="selectedTemperature"
                        type="range"
                        min="0.5"
                        max="1.3"
                        step="0.05"
                        class="chaos-mode-temp-slider w-full"
                    />
                    <div class="mt-1.5 flex justify-between">
                        <span class="chaos-mode-cost-note text-[10px]">Focused</span>
                        <span class="chaos-mode-cost-note text-[10px]">Creative</span>
                    </div>
                    <p class="chaos-mode-cost-note mt-0.5 text-[10px] leading-relaxed">
                        Lower = more consistent narration. Higher = more surprising and inventive.
                    </p>
                </div>

                <BaseButton
                    class="chaos-mode-cta w-full"
                    severity="primary"
                    :disabled="loading || !selectedStory?.available"
                    @click="startWithChoices"
                >
                    <span v-if="loading" class="flex items-center justify-center gap-2 text-[#1a0f00]">
                        <span class="size-4 animate-spin rounded-full border-2 border-[#1a0f00]/25 border-t-[#1a0f00]/85" />
                        Opening the story...
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
            <div class="chaos-mode-game-header sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between px-4 sm:px-8">
                <BaseButton severity="glass" :icon-only="true" class="size-10!" @click="resetAdventure">
                    <LucideChevronLeft class="size-5 text-gray-300" :stroke-width="1.5" />
                </BaseButton>

                <div class="flex flex-col items-center">
                    <p class="text-center text-[10px] uppercase tracking-widest text-[color:rgba(229,173,83,0.7)]">
                        {{ sessionComplete ? 'Session Complete' : 'Chaos Mode' }}
                    </p>
                    <p v-if="storyTitle" class="font-gill-sans text-center text-xs text-gray-400">
                        {{ storyTitle }}<span v-if="totalSessions > 1"> · {{ sessionNumber }} / {{ totalSessions }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="hidden rounded-full border px-3 py-1 text-[10px] sm:block border-[rgba(229,173,83,0.28)] bg-[rgba(229,173,83,0.07)] text-[rgba(229,173,83,0.8)]">
                        {{ MODELS.find((m) => m.value === selectedModel)?.label }}
                        <span class="opacity-60">· {{ selectedTemperature.toFixed(2) }}</span>
                    </span>
                    <BaseButton severity="glass" :icon-only="true" class="size-10!" title="New adventure" @click="resetAdventure">
                        <LucideRefreshCw class="size-4 text-gray-400" :stroke-width="1.5" />
                    </BaseButton>
                </div>
            </div>

            <!-- Floating TTS media player — fixed so it overlays content without displacing it -->
            <div class="pointer-events-none fixed top-16 right-0 left-0 z-30 flex justify-center pt-3">
                <Transition name="player-slide">
                    <div
                        v-if="tts.isActive.value"
                        class="bg-glass-effect pointer-events-auto relative flex items-center gap-3 overflow-hidden rounded-full border border-[rgba(229,173,83,0.35)] py-2 pe-3 ps-2 shadow-2xl backdrop-blur-xl bg-gray-900/80!"
                    >
                        <button
                            class="bg-primary-glass-effect relative grid size-9 shrink-0 place-items-center overflow-hidden rounded-full transition-transform hover:scale-105 active:scale-95"
                            @click="tts.togglePause"
                        >
                            <LucidePause v-if="tts.isPlaying.value" class="size-3.5 text-gray-950" fill="currentColor" />
                            <LucidePlay v-else-if="!tts.isLoading.value" class="size-3.5 text-gray-950" fill="currentColor" />
                            <LucideLoader v-else class="size-3.5 animate-spin text-gray-950" />
                        </button>
                        <span class="min-w-16 text-sm font-medium tabular-nums text-gray-200">
                            {{ tts.formattedCurrentTime.value }}
                            <span class="text-gray-500">/</span>
                            {{ tts.formattedDuration.value }}
                        </span>
                        <button
                            class="rounded-full border px-2.5 py-0.5 text-xs font-semibold tabular-nums transition-colors border-[rgba(229,173,83,0.4)] text-[rgba(229,173,83,0.9)] hover:border-[rgba(229,173,83,0.7)] hover:bg-[rgba(229,173,83,0.12)]"
                            @click="tts.cycleSpeed"
                        >
                            {{ tts.playbackRate.value }}x
                        </button>
                        <button
                            class="grid size-7 shrink-0 place-items-center rounded-full text-gray-400 transition-colors hover:bg-gray-700/60 hover:text-gray-200"
                            @click="tts.dismiss"
                        >
                            <LucideX class="size-3.5" />
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- Scrollable story — narration + choices scroll together -->
            <div class="relative flex-1 overflow-hidden">
                <div
                    ref="scrollEl"
                    class="chaos-scroll absolute inset-0 overflow-y-auto"
                    @scroll.passive="updateShadows"
                >
                    <div class="mx-auto flex max-w-3xl flex-col divide-y divide-gray-100/10 px-4 pb-8 sm:px-8">

                        <template v-for="(turn, idx) in turns" :key="idx">
                            <!-- Narrator turn -->
                            <div v-if="turn.role === 'narrator'" class="chaos-prose py-8">
                                <div class="mb-3 flex items-center gap-3">
                                    <span class="text-[10px] uppercase tracking-widest text-[rgba(229,173,83,0.55)]">Narrator</span>
                                    <button
                                        v-if="sessionId"
                                        class="chaos-tts-btn flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-medium transition-all"
                                        :class="{ 'active': tts.activeKey.value === `${sessionId}:${narratorIndexMap.get(idx)}` }"
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

                            <!-- Player turn -->
                            <div v-else class="flex items-baseline gap-3 py-4">
                                <span class="shrink-0 text-[10px] uppercase tracking-widest text-gray-600">{{ protagonist }}</span>
                                <p class="text-sm italic text-gray-500">{{ turn.text }}</p>
                            </div>
                        </template>

                        <!-- Loading skeleton -->
                        <div v-if="loading" class="py-8">
                            <div class="flex animate-pulse flex-col gap-4">
                                <div class="h-4 w-full rounded bg-gray-700/50" />
                                <div class="h-4 w-5/6 rounded bg-gray-700/50" />
                                <div class="h-4 w-3/4 rounded bg-gray-700/50" />
                                <div class="h-4 w-4/6 rounded bg-gray-700/40" />
                            </div>
                        </div>

                        <!-- Inline choices — scroll with narration -->
                        <div
                            v-if="choicesBuffer.length && !loading && !sessionComplete"
                            class="py-5"
                        >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(choice, i) in choicesBuffer"
                                    :key="i"
                                    class="chaos-choice-btn bg-glass-effect rounded-xl border border-[rgba(229,173,83,0.22)] px-4 py-2.5 text-left text-sm text-[rgba(250,243,228,0.82)] backdrop-blur-sm transition-all hover:border-[rgba(229,173,83,0.55)] hover:text-[rgba(250,243,228,1)] focus:outline-none"
                                    :disabled="loading"
                                    @click="takeTurn(choice)"
                                >
                                    {{ choice }}
                                </button>
                            </div>
                        </div>

                        <!-- Session complete banner -->
                        <div
                            v-if="sessionComplete"
                            class="my-8 rounded-2xl border p-6 text-center backdrop-blur-sm border-[rgba(229,173,83,0.35)] bg-[rgba(229,173,83,0.06)]"
                        >
                            <p class="mb-1 text-[10px] uppercase tracking-widest text-[rgba(229,173,83,0.65)]">
                                Session {{ sessionNumber }} Complete
                            </p>
                            <p class="font-gill-sans mb-2 text-xl font-medium text-[var(--chaos-brand)]">
                                <span v-if="hasNextSession">A threshold has been crossed.</span>
                                <span v-else>The story has reached its end.</span>
                            </p>
                            <p class="mb-6 text-sm leading-relaxed text-gray-400">
                                <span v-if="hasNextSession">
                                    Session {{ sessionNumber + 1 }} awaits — what you carry, you carry across.
                                </span>
                                <span v-else>
                                    The arc is closed. You can begin again or step into a different story.
                                </span>
                            </p>
                            <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                                <BaseButton class="chaos-mode-cta w-full sm:w-auto" severity="primary" @click="resetAdventure">
                                    Begin a New Adventure
                                </BaseButton>
                                <BaseButton
                                    v-if="hasNextSession"
                                    severity="glass"
                                    class="w-full sm:w-auto"
                                    :disabled="loading"
                                    @click="continueToNextSession"
                                >
                                    Continue to Session {{ sessionNumber + 1 }}
                                </BaseButton>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Scroll fade shadows -->
                <div
                    class="pointer-events-none absolute top-0 right-0 left-0 h-14 bg-gradient-to-b from-gray-950 to-transparent transition-opacity duration-200"
                    :style="{ opacity: topShadow }"
                />
                <div
                    class="pointer-events-none absolute right-0 bottom-0 left-0 h-20 bg-gradient-to-t from-gray-950 to-transparent transition-opacity duration-200"
                    :style="{ opacity: botShadow }"
                />
            </div>

            <!-- Error -->
            <p v-if="errorMessage" class="px-4 pb-1 text-center text-xs text-red-400 sm:px-8">{{ errorMessage }}</p>

            <!-- Sticky input only -->
            <div v-if="!sessionComplete" class="sticky bottom-0 z-10 shrink-0">
                <div class="chaos-input-bar grid h-24 place-items-center px-4">
                    <GameplayInput :disabled="loading" @submit="takeTurn" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.chaos-mode-root {
    /* Brand: Amber Gold — Chaos Mode signature, distinct from Story Guard's Tiffany Blue */
    --chaos-brand: #e5ad53;
    --chaos-brand-rgb: 229, 173, 83;
    font-family: 'Source Sans 3', Inter, sans-serif;
}

.chaos-mode-brand-bg {
    background:
        radial-gradient(ellipse 120% 80% at 50% -20%, rgba(var(--chaos-brand-rgb), 0.16), transparent 55%),
        radial-gradient(ellipse 90% 70% at 100% 50%, rgba(var(--chaos-brand-rgb), 0.08), transparent 50%),
        radial-gradient(ellipse 80% 60% at 0% 80%, rgba(var(--chaos-brand-rgb), 0.10), transparent 45%);
}

/* ── Start screen config card ── */
.chaos-mode-config-card {
    border-color: rgba(var(--chaos-brand-rgb), 0.25);
    background: rgba(255, 255, 255, 0.04);
    box-shadow:
        0 0 0 1px rgba(var(--chaos-brand-rgb), 0.05) inset,
        0 0 2px 0 rgba(0, 0, 0, 0.15),
        0 1px 8px 0 rgba(0, 0, 0, 0.2),
        3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.5) inset,
        -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.5) inset,
        1px 1px 1px -0.5px rgba(255, 255, 255, 0.5) inset,
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.12),
        0 32px 64px -32px rgba(0, 0, 0, 0.65);
}

.chaos-mode-eyebrow {
    color: rgba(var(--chaos-brand-rgb), 0.9);
    text-shadow: 0 0 24px rgba(var(--chaos-brand-rgb), 0.3);
}

.chaos-mode-title { color: #faf6ef; }

.chaos-mode-lede { color: rgba(250, 235, 200, 0.55); }

.chaos-mode-field-label { color: rgba(var(--chaos-brand-rgb), 0.65); }

.chaos-mode-tagline { color: rgba(250, 230, 190, 0.52); }

.chaos-mode-cost-est { color: rgba(var(--chaos-brand-rgb), 0.55); font-variant-numeric: tabular-nums; }

.chaos-mode-cost-note { color: rgba(250, 225, 175, 0.28); font-style: italic; }

/* ── Story selection cards ── */
.story-card {
    border-color: rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.04);
    box-shadow:
        0 0 2px 0 rgba(0, 0, 0, 0.1),
        0 1px 8px 0 rgba(0, 0, 0, 0.15),
        1px 1px 1px -0.5px rgba(255, 255, 255, 0.18) inset,
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    min-height: 9rem;
}

.story-card:not(.story-card--unavailable):hover {
    border-color: rgba(var(--chaos-brand-rgb), 0.4);
    box-shadow:
        0 0 2px 0 rgba(0, 0, 0, 0.1),
        0 4px 20px -4px rgba(0, 0, 0, 0.3),
        1px 1px 1px -0.5px rgba(255, 255, 255, 0.2) inset,
        0 0 0 1px rgba(var(--chaos-brand-rgb), 0.15);
    transform: translateY(-1px);
}

.story-card--selected {
    border-color: rgba(var(--chaos-brand-rgb), 0.65) !important;
    box-shadow:
        0 0 2px 0 rgba(0, 0, 0, 0.1),
        0 4px 24px -4px rgba(0, 0, 0, 0.35),
        1px 1px 1px -0.5px rgba(255, 255, 255, 0.22) inset,
        0 0 0 1px rgba(var(--chaos-brand-rgb), 0.22),
        0 0 24px -4px rgba(var(--chaos-brand-rgb), 0.28) !important;
}

.story-card--unavailable {
    cursor: not-allowed;
    opacity: 0.6;
}

/* ── Model select ── */
.chaos-mode-select {
    color: #faf3e4;
    background-color: rgba(12, 9, 3, 0.92);
    border-color: rgba(var(--chaos-brand-rgb), 0.25);
}
.chaos-mode-select:focus {
    border-color: rgba(var(--chaos-brand-rgb), 0.6);
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.22);
}
.chaos-mode-select option,
.chaos-mode-select optgroup { background-color: #0e0a03; color: #faf3e4; }
.chaos-mode-select option:disabled { color: rgba(250, 230, 190, 0.3); }

/* ── Temperature slider ── */
.chaos-mode-temp-value {
    color: var(--chaos-brand);
}
.chaos-mode-temp-slider {
    -webkit-appearance: none;
    appearance: none;
    height: 4px;
    border-radius: 2px;
    background: linear-gradient(
        to right,
        rgba(var(--chaos-brand-rgb), 0.8) 0%,
        rgba(var(--chaos-brand-rgb), 0.8) calc((var(--v, 0.9) - 0.5) / 0.8 * 100%),
        rgba(var(--chaos-brand-rgb), 0.15) calc((var(--v, 0.9) - 0.5) / 0.8 * 100%),
        rgba(var(--chaos-brand-rgb), 0.15) 100%
    );
    outline: none;
    cursor: pointer;
}
.chaos-mode-temp-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--chaos-brand);
    border: 2px solid rgba(3, 7, 18, 0.9);
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.4), 0 2px 8px rgba(0,0,0,0.4);
    cursor: pointer;
    transition: box-shadow 0.15s;
}
.chaos-mode-temp-slider::-webkit-slider-thumb:hover {
    box-shadow: 0 0 0 3px rgba(var(--chaos-brand-rgb), 0.22), 0 2px 8px rgba(0,0,0,0.4);
}
.chaos-mode-temp-slider::-moz-range-thumb {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--chaos-brand);
    border: 2px solid rgba(3, 7, 18, 0.9);
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.4);
    cursor: pointer;
}
.chaos-mode-temp-slider::-moz-range-track {
    height: 4px;
    border-radius: 2px;
    background: rgba(var(--chaos-brand-rgb), 0.15);
}

/* ── CTA button ── */
.chaos-mode-config-card :deep(.chaos-mode-cta) {
    background-color: var(--chaos-brand) !important;
    color: #1a0f00 !important;
    border-color: rgba(26, 15, 0, 0.15) !important;
    outline-color: rgba(var(--chaos-brand-rgb), 0.35) !important;
    font-weight: 600;
    letter-spacing: 0.02em;
}
.chaos-mode-config-card :deep(.chaos-mode-cta:not(.pointer-events-none):hover) {
    filter: brightness(1.08);
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.5), 0 12px 28px -8px rgba(var(--chaos-brand-rgb), 0.4);
}
.chaos-mode-config-card :deep(.chaos-mode-cta.pointer-events-none) {
    background-color: rgba(var(--chaos-brand-rgb), 0.35) !important;
    color: rgba(26, 15, 0, 0.6) !important;
    opacity: 1 !important;
}
:deep(.chaos-mode-cta) {
    background-color: var(--chaos-brand) !important;
    color: #1a0f00 !important;
    font-weight: 600;
}

.chaos-mode-back-link { color: rgba(var(--chaos-brand-rgb), 0.45); }
.chaos-mode-back-link:hover { color: rgba(var(--chaos-brand-rgb), 0.9); }

/* ── Game screen header ── */
.chaos-mode-game-header {
    background: linear-gradient(
        180deg,
        rgba(3, 7, 18, 0.96) 0%,
        rgba(3, 7, 18, 0.82) 60%,
        rgba(var(--chaos-brand-rgb), 0.02) 100%
    );
    border-bottom: 1px solid rgba(var(--chaos-brand-rgb), 0.10);
}

/* ── Narration prose ── */
.chaos-prose {
    line-height: 1.85;
    color: rgba(250, 243, 228, 0.88);
    font-size: 1rem;
}
.chaos-prose :deep(p) { margin-bottom: 1em; }
.chaos-prose :deep(p:last-child) { margin-bottom: 0; }
.chaos-prose :deep(em) { font-style: italic; }
.chaos-prose :deep(strong) { color: var(--chaos-brand); font-weight: 500; }

/* ── TTS listen button ── */
.chaos-tts-btn {
    border-color: rgba(var(--chaos-brand-rgb), 0.18);
    color: rgba(var(--chaos-brand-rgb), 0.5);
}
.chaos-tts-btn:hover {
    border-color: rgba(var(--chaos-brand-rgb), 0.45);
    color: rgba(var(--chaos-brand-rgb), 0.85);
    background: rgba(var(--chaos-brand-rgb), 0.07);
}
.chaos-tts-btn.active {
    border-color: rgba(var(--chaos-brand-rgb), 0.5);
    color: rgba(var(--chaos-brand-rgb), 0.95);
    background: rgba(var(--chaos-brand-rgb), 0.12);
}

/* ── Inline choice buttons ── */
.chaos-choice-btn {
    transition: border-color 0.15s, color 0.15s, box-shadow 0.15s, transform 0.1s;
}
.chaos-choice-btn:hover:not(:disabled) {
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.28), 0 4px 16px -4px rgba(var(--chaos-brand-rgb), 0.22);
    transform: translateY(-1px);
}
.chaos-choice-btn:active:not(:disabled) {
    transform: translateY(0);
}

/* ── Input bar ── */
.chaos-input-bar {
    background: linear-gradient(
        0deg,
        rgba(3, 7, 18, 0.98) 0%,
        rgba(3, 7, 18, 0.90) 60%,
        transparent 100%
    );
}

/* ── Scrollbar ── */
.chaos-scroll::-webkit-scrollbar { width: 4px; }
.chaos-scroll::-webkit-scrollbar-track { background: transparent; }
.chaos-scroll::-webkit-scrollbar-thumb { background: rgba(var(--chaos-brand-rgb), 0.2); border-radius: 2px; }
.chaos-scroll::-webkit-scrollbar-thumb:hover { background: rgba(var(--chaos-brand-rgb), 0.42); }

/* ── TTS player transition ── */
.player-slide-enter-active,
.player-slide-leave-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.player-slide-enter-from,
.player-slide-leave-to { opacity: 0; transform: translateY(-12px) scale(0.95); }
</style>
