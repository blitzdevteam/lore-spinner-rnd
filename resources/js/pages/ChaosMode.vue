<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import { useChaosTextToSpeech } from '@/composables/useChaosTextToSpeech';
import { LucideChevronLeft, LucideLoader, LucidePause, LucidePlay, LucideRefreshCw, LucideX } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

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

const selectedStory = computed(() => props.stories.find((s) => s.slug === selectedStorySlug.value));

const storyTheme = (slug: string) => STORY_THEMES[slug] ?? STORY_THEMES['__default__'];

onMounted(() => document.body.classList.add('chaos-mode-active'));
onUnmounted(() => document.body.classList.remove('chaos-mode-active'));

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
        const payload = await res.json().catch(() => null);
        if (payload?.debug) {
            const err = new Error(payload.debug.message || payload.error || `HTTP ${res.status}`) as Error & { debug?: unknown };
            err.debug = payload.debug;
            throw err;
        }
        throw new Error(payload?.error || `HTTP ${res.status}`);
    }
    return res.json();
}

function describeError(fallback: string, err: unknown): string {
    const e = err as { message?: string; debug?: { exception?: string; message?: string } } | undefined;
    if (e?.debug?.message) {
        return `${fallback}\n\n[debug] ${e.debug.exception ?? 'Exception'}: ${e.debug.message}`;
    }
    return fallback;
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
    } catch (err) {
        errorMessage.value = describeError('The narration engine is currently unavailable. Please try again.', err);
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
    } catch (err) {
        turns.value.pop();
        errorMessage.value = describeError('The narration hiccuped — please try again.', err);
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
    } catch (err) {
        errorMessage.value = describeError('Could not open the next session. Please try again.', err);
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
        <template v-if="!started">
        <div class="chaos-start relative z-[1] h-full overflow-y-auto">

            <!-- Top nav bar -->
            <div class="chaos-start-nav flex shrink-0 items-center justify-between px-5 py-4 sm:px-8 sm:py-5">
                <a href="/" class="chaos-mode-back-link flex items-center gap-1 text-xs transition-colors">
                    <LucideChevronLeft class="size-3.5" :stroke-width="2" />
                    LoreSpinner
                </a>
                <span class="chaos-mode-eyebrow text-[10px] uppercase tracking-[0.18em]">
                    Experimental · Chaos Mode
                </span>
            </div>

            <!-- Two-column content -->
            <div class="flex flex-col lg:flex-row lg:items-start">

                <!-- ── Left panel: hero + config ── -->
                <div class="chaos-start-left order-last flex flex-col px-5 pb-28 pt-2 sm:px-8 sm:pb-10 lg:order-1 lg:sticky lg:top-0 lg:h-[calc(100vh-60px)] lg:w-[370px] lg:shrink-0 lg:overflow-hidden lg:pb-10 lg:pt-4 xl:w-[400px]">

                    <!-- Hero text -->
                    <div class="chaos-enter-anim mb-4 sm:mb-6 lg:mb-8" style="--anim-delay: 0ms">
                        <h1 class="chaos-start-title mb-2 text-[1.85rem] font-medium leading-[1.05] tracking-tight sm:text-[2.5rem] lg:text-[2.8rem] xl:text-[3.2rem]">
                            Step into<br>the story.
                        </h1>
                        <p class="chaos-mode-lede hidden max-w-[38ch] text-sm leading-relaxed sm:block">
                            Explore. Live it.
                        </p>
                    </div>

                    <!-- Selected story preview strip — desktop only (mobile sees selection in card) -->
                    <Transition name="preview-fade">
                        <div
                            v-if="selectedStory"
                            class="chaos-story-preview-strip chaos-enter-anim mb-5 hidden rounded-xl border p-3.5 lg:mb-6 lg:block"
                            style="--anim-delay: 50ms"
                        >
                            <div class="mb-1.5 flex items-center gap-2">
                                <span
                                    class="size-1.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: storyTheme(selectedStory.slug).accent }"
                                />
                                <span
                                    class="text-[10px] uppercase tracking-wider"
                                    :style="{ color: storyTheme(selectedStory.slug).accent }"
                                >
                                    As {{ selectedStory.protagonist }}
                                </span>
                            </div>
                            <p class="text-[12px] leading-relaxed text-gray-400">{{ selectedStory.tagline }}</p>
                        </div>
                    </Transition>

                    <!-- Spacer pushes config to bottom on desktop -->
                    <div class="hidden lg:block lg:flex-1" />

                    <!-- Config block -->
                    <div class="chaos-enter-anim" style="--anim-delay: 110ms">

                        <!-- Model selector — pill chips, grouped by provider -->
                        <div class="mb-5">
                            <div class="mb-2.5 flex items-baseline justify-between">
                                <label class="chaos-mode-field-label text-[10px] uppercase tracking-[0.18em]">Narrator Model</label>
                                <span class="chaos-mode-cost-est text-[10px]">{{ selectedModelMeta?.est }} / session</span>
                            </div>

                            <div class="mb-2 flex flex-wrap gap-1.5">
                                <span class="chaos-provider-label">OpenAI</span>
                                <button
                                    v-for="m in MODELS.filter((x) => x.provider === 'OpenAI')"
                                    :key="m.value"
                                    class="chaos-model-pill"
                                    :class="{ 'chaos-model-pill--active': selectedModel === m.value }"
                                    @click="selectedModel = m.value"
                                >
                                    {{ m.label }}
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="chaos-provider-label">Anthropic</span>
                                <button
                                    v-for="m in MODELS.filter((x) => x.provider === 'Anthropic')"
                                    :key="m.value"
                                    class="chaos-model-pill"
                                    :class="{ 'chaos-model-pill--active': selectedModel === m.value }"
                                    @click="selectedModel = m.value"
                                >
                                    {{ m.label }}
                                </button>
                            </div>
                            <p class="chaos-mode-cost-note mt-1.5 text-[10px] leading-relaxed">
                                Estimate per ~20-turn session. Actual cost varies.
                            </p>
                        </div>

                        <!-- Tone slider -->
                        <div class="mb-6">
                            <div class="mb-2 flex items-center justify-between">
                                <label class="chaos-mode-field-label text-[10px] uppercase tracking-[0.18em]">Tone</label>
                                <span class="chaos-mode-temp-value text-[11px] font-medium tabular-nums">
                                    {{ selectedTemperature <= 0.7 ? 'Focused' : selectedTemperature >= 1.15 ? 'Wild' : selectedTemperature >= 0.95 ? 'Creative' : 'Balanced' }}
                                    <span class="opacity-45">· {{ selectedTemperature.toFixed(2) }}</span>
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
                            <div class="mt-1 flex justify-between">
                                <span class="chaos-mode-cost-note text-[10px]">Consistent</span>
                                <span class="chaos-mode-cost-note text-[10px]">Inventive</span>
                            </div>
                        </div>

                        <!-- CTA — desktop only; mobile uses fixed bottom bar -->
                        <div class="hidden lg:block">
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
                            <p v-if="errorMessage" class="mt-3 whitespace-pre-wrap text-xs text-red-400">{{ errorMessage }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Right panel: story grid ── -->
                <div class="order-first flex-1 px-5 pb-4 pt-3 sm:px-8 sm:pt-2 lg:order-2 lg:pb-10 lg:pt-6">
                    <label
                        class="chaos-mode-field-label chaos-enter-anim mb-4 block text-[10px] uppercase tracking-[0.18em]"
                        style="--anim-delay: 70ms"
                    >
                        Choose Your Story
                    </label>
                    <div class="chaos-stories-grid">
                        <button
                            v-for="(story, i) in stories"
                            :key="story.slug"
                            :disabled="!story.available"
                            class="story-card chaos-enter-anim relative overflow-hidden rounded-2xl border text-left transition-all focus:outline-none"
                            :class="{
                                'story-card--selected': selectedStorySlug === story.slug && story.available,
                                'story-card--unavailable': !story.available,
                            }"
                            :style="{ '--anim-delay': `${90 + i * 50}ms` }"
                            @click="story.available && (selectedStorySlug = story.slug)"
                        >
                            <!-- Theme gradient bg -->
                            <div
                                class="story-card-bg absolute inset-0"
                                :style="{
                                    background: `radial-gradient(ellipse 140% 110% at 50% -10%, ${storyTheme(story.slug).from}ee 0%, ${storyTheme(story.slug).via} 65%)`,
                                }"
                            />
                            <!-- Cover image -->
                            <img
                                v-if="story.cover"
                                :src="story.cover"
                                :alt="story.title"
                                class="absolute inset-0 h-full w-full object-cover object-center transition-opacity duration-500"
                                :class="selectedStorySlug === story.slug ? 'opacity-35' : 'opacity-20'"
                            />
                            <!-- Accent top stripe -->
                            <div
                                class="absolute inset-x-0 top-0 h-px transition-opacity duration-300"
                                :style="{ background: storyTheme(story.slug).accent, opacity: selectedStorySlug === story.slug ? 1 : 0.22 }"
                            />
                            <!-- Bottom gradient on selected -->
                            <div
                                class="absolute inset-x-0 bottom-0 h-20 transition-opacity duration-300"
                                :class="selectedStorySlug === story.slug ? 'opacity-100' : 'opacity-0'"
                                :style="{ background: `linear-gradient(to top, ${storyTheme(story.slug).from}99, transparent)` }"
                            />

                            <!-- Card content -->
                            <div class="relative z-[1] flex h-full flex-col justify-between p-3 sm:p-4 lg:p-5">
                                <div>
                                    <h3
                                        class="story-card-title font-gill-sans mb-1 text-sm font-semibold leading-snug sm:mb-1.5 sm:text-base"
                                        :style="{ color: story.available ? '#faf6ef' : 'rgba(250,246,239,0.38)' }"
                                    >
                                        {{ story.title }}
                                    </h3>
                                    <p
                                        class="story-card-tagline line-clamp-2 text-[10px] leading-relaxed sm:text-[11px] lg:line-clamp-3"
                                        :style="{ color: story.available ? 'rgba(229,217,192,0.65)' : 'rgba(229,217,192,0.22)' }"
                                    >
                                        {{ story.tagline }}
                                    </p>
                                </div>
                                <div class="mt-3 flex items-center justify-between sm:mt-4">
                                    <span
                                        class="text-[10px] uppercase tracking-wider"
                                        :style="{ color: story.available ? storyTheme(story.slug).accent : 'rgba(255,255,255,0.15)' }"
                                    >
                                        As {{ story.protagonist }}
                                    </span>
                                    <span
                                        v-if="!story.available"
                                        class="rounded-full border border-gray-700/50 px-2 py-0.5 text-[9px] uppercase tracking-wider text-gray-700"
                                    >
                                        Soon
                                    </span>
                                    <span
                                        v-else-if="selectedStorySlug === story.slug"
                                        class="story-card-check flex size-5 items-center justify-center rounded-full text-[10px] text-[#1f160d]"
                                        :style="{ backgroundColor: storyTheme(story.slug).accent }"
                                    >✓</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Mobile CTA bar — fixed at bottom, visible only on start screen ── -->
        <Transition name="mobile-cta-slide">
            <div class="chaos-mobile-cta-bar lg:hidden">
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
                <p v-if="errorMessage" class="mt-2 whitespace-pre-wrap text-center text-xs text-red-400">{{ errorMessage }}</p>
            </div>
        </Transition>
        </template>

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

            </div>

            <!-- Error -->
            <p v-if="errorMessage" class="whitespace-pre-wrap px-4 pb-1 text-center text-xs text-red-400 sm:px-8">{{ errorMessage }}</p>

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
}

.chaos-mode-brand-bg {
    background:
        radial-gradient(ellipse 120% 80% at 50% -20%, rgba(var(--chaos-brand-rgb), 0.16), transparent 55%),
        radial-gradient(ellipse 90% 70% at 100% 50%, rgba(var(--chaos-brand-rgb), 0.08), transparent 50%),
        radial-gradient(ellipse 80% 60% at 0% 80%, rgba(var(--chaos-brand-rgb), 0.10), transparent 45%);
}

/* ── Entrance animation ── */
@keyframes chaos-fade-up {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.chaos-enter-anim {
    animation: chaos-fade-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--anim-delay, 0ms);
}

/* ── Start screen layout ── */
.chaos-start-nav {
    border-bottom: 1px solid rgba(var(--chaos-brand-rgb), 0.07);
}

.chaos-start-left {
    border-right: none;
}
@media (min-width: 1024px) {
    .chaos-start-left {
        border-right: 1px solid rgba(var(--chaos-brand-rgb), 0.08);
    }
}

.chaos-start-title { color: #faf6ef; }

/* ── Selected story preview strip ── */
.chaos-story-preview-strip {
    border-color: rgba(var(--chaos-brand-rgb), 0.15);
    background: rgba(var(--chaos-brand-rgb), 0.04);
}

/* ── Preview transition ── */
.preview-fade-enter-active { transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1); }
.preview-fade-leave-active { transition: all 0.2s ease; }
.preview-fade-enter-from   { opacity: 0; transform: translateY(6px); }
.preview-fade-leave-to     { opacity: 0; }

/* ── Header text ── */
.chaos-mode-eyebrow {
    color: rgba(var(--chaos-brand-rgb), 0.75);
    letter-spacing: 0.18em;
}

.chaos-mode-lede { color: rgba(250, 235, 200, 0.52); }

.chaos-mode-field-label { color: rgba(var(--chaos-brand-rgb), 0.6); }

.chaos-mode-cost-est { color: rgba(var(--chaos-brand-rgb), 0.52); font-variant-numeric: tabular-nums; }

.chaos-mode-cost-note { color: rgba(250, 225, 175, 0.26); font-style: italic; }

/* ── Provider label ── */
.chaos-provider-label {
    display: inline-flex;
    align-items: center;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: rgba(var(--chaos-brand-rgb), 0.35);
    align-self: center;
    padding-right: 2px;
}

/* ── Model pills ── */
.chaos-model-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(250, 235, 200, 0.55);
    font-size: 10px;
    padding: 3px 9px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
    white-space: nowrap;
}
@media (min-width: 640px) {
    .chaos-model-pill { font-size: 11px; padding: 3px 10px; }
}
.chaos-model-pill:hover {
    border-color: rgba(var(--chaos-brand-rgb), 0.38);
    color: rgba(250, 235, 200, 0.85);
    background: rgba(var(--chaos-brand-rgb), 0.06);
}
.chaos-model-pill--active {
    border-color: rgba(var(--chaos-brand-rgb), 0.7) !important;
    background: rgba(var(--chaos-brand-rgb), 0.12) !important;
    color: rgba(var(--chaos-brand-rgb), 0.95) !important;
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.18);
}

/* ── Stories grid ── */
.chaos-stories-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
@media (min-width: 640px) {
    .chaos-stories-grid { gap: 14px; }
}
@media (min-width: 1280px) {
    .chaos-stories-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ── Mobile CTA bar ── */
.chaos-mobile-cta-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 30;
    padding: 12px 20px;
    padding-bottom: max(14px, env(safe-area-inset-bottom, 14px));
    background: linear-gradient(
        0deg,
        rgba(3, 7, 18, 1) 0%,
        rgba(3, 7, 18, 0.97) 55%,
        rgba(3, 7, 18, 0.8) 80%,
        transparent 100%
    );
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

/* ── Mobile CTA slide transition ── */
.mobile-cta-slide-enter-active { transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease; }
.mobile-cta-slide-leave-active { transition: transform 0.2s ease, opacity 0.15s ease; }
.mobile-cta-slide-enter-from,
.mobile-cta-slide-leave-to   { transform: translateY(100%); opacity: 0; }

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
    min-height: 9.5rem;
}
@media (min-width: 390px) {
    .story-card { min-height: 10.5rem; }
}
@media (min-width: 640px) {
    .story-card { min-height: 12rem; }
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
:deep(.chaos-mode-cta) {
    background-color: var(--chaos-brand) !important;
    color: #1a0f00 !important;
    border-color: rgba(26, 15, 0, 0.15) !important;
    outline-color: rgba(var(--chaos-brand-rgb), 0.35) !important;
    font-weight: 600;
    letter-spacing: 0.02em;
}
:deep(.chaos-mode-cta:not(.pointer-events-none):hover) {
    filter: brightness(1.08);
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.5), 0 12px 28px -8px rgba(var(--chaos-brand-rgb), 0.4);
}
:deep(.chaos-mode-cta.pointer-events-none) {
    background-color: rgba(var(--chaos-brand-rgb), 0.35) !important;
    color: rgba(26, 15, 0, 0.6) !important;
    opacity: 1 !important;
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

<!-- Non-scoped: overrides primary palette on <body> so teleported components
     (FeedbackWidget, toasts) also receive the Chaos Mode amber branding. -->
<style>
body.chaos-mode-active {
    --color-primary-50:  #fdf5e4;
    --color-primary-100: #fae8c0;
    --color-primary-200: #f5d496;
    --color-primary-300: #edba68;
    --color-primary-400: #e5ad53;
    --color-primary-500: #d49830;
    --color-primary-600: #b87b1a;
    --color-primary-700: #8f5c0d;
    --color-primary-800: #6b4009;
    --color-primary-900: #4a2b06;
    --color-primary-950: #2a1803;
    --color-primary:     #e5ad53;
}
</style>
