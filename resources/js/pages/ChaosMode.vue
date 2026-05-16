<script setup lang="ts">
import { nextTick, ref } from 'vue';

defineOptions({ layout: null });

interface WorldState {
    size_condition: string;
    items: string[];
    location: string;
    notes: string[];
}

interface Turn {
    role: 'narrator' | 'player';
    html?: string;
    text: string;
}

const props = defineProps<{
    storyTitle: string;
    storyExists: boolean;
}>();

const MODELS = [
    { value: 'gpt-5.5', label: 'GPT-5.5', provider: 'OpenAI' },
    { value: 'gpt-5.4', label: 'GPT-5.4', provider: 'OpenAI' },
    { value: 'gpt-5.2', label: 'GPT-5.2', provider: 'OpenAI' },
    { value: 'gpt-4.1', label: 'GPT-4.1', provider: 'OpenAI' },
    { value: 'claude-opus-4-7', label: 'Claude Opus 4.7', provider: 'Anthropic' },
    { value: 'claude-sonnet-4-6', label: 'Claude Sonnet 4.6', provider: 'Anthropic' },
];

const selectedModel = ref('gpt-5.5');
const started = ref(false);
const loading = ref(false);
const playerInput = ref('');
const sceneNote = ref('Chapter I — Down the Rabbit-Hole');
const turns = ref<Turn[]>([]);
const worldState = ref<WorldState>({ size_condition: '', items: [], location: '', notes: [] });
const errorMessage = ref('');
const storyContainer = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

function getCsrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function stripHtml(html: string): string {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent ?? '';
}

function historyForApi(): Array<{ role: string; text: string }> {
    return turns.value.map((t) => ({ role: t.role, text: t.text }));
}

function applyWorldUpdate(update: Partial<WorldState>): void {
    if (update.size_condition !== undefined && update.size_condition !== '') {
        worldState.value.size_condition = update.size_condition;
    }
    if (update.items !== undefined) {
        worldState.value.items = update.items;
    }
    if (update.location !== undefined && update.location !== '') {
        worldState.value.location = update.location;
    }
    if (update.notes !== undefined && update.notes.length > 0) {
        worldState.value.notes = [...worldState.value.notes, ...update.notes];
    }
}

async function scrollToBottom(): Promise<void> {
    await nextTick();
    if (storyContainer.value) {
        storyContainer.value.scrollTo({ top: storyContainer.value.scrollHeight, behavior: 'smooth' });
    }
}

async function startAdventure(): Promise<void> {
    if (loading.value) return;
    loading.value = true;
    errorMessage.value = '';

    try {
        const res = await fetch('/chaos-mode/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ model: selectedModel.value }),
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        if (data.error) throw new Error(data.error);

        turns.value = [{ role: 'narrator', html: data.response, text: stripHtml(data.response) }];
        sceneNote.value = data.scene_note ?? sceneNote.value;
        if (data.world_update) applyWorldUpdate(data.world_update);
        started.value = true;

        await scrollToBottom();
        await nextTick();
        inputEl.value?.focus();
    } catch (e: any) {
        errorMessage.value = e?.message?.includes('unavailable')
            ? e.message
            : 'Wonderland is currently unavailable. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function takeTurn(action: string): Promise<void> {
    const trimmed = action.trim();
    if (!trimmed || loading.value) return;

    playerInput.value = '';
    loading.value = true;
    errorMessage.value = '';

    turns.value.push({ role: 'player', text: trimmed });
    await scrollToBottom();

    try {
        const res = await fetch('/chaos-mode/turn', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({
                player_action: trimmed,
                model: selectedModel.value,
                conversation_history: historyForApi().slice(-12),
                world_state: worldState.value,
            }),
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        if (data.error) throw new Error(data.error);

        turns.value.push({ role: 'narrator', html: data.response, text: stripHtml(data.response) });
        sceneNote.value = data.scene_note ?? sceneNote.value;
        if (data.world_update) applyWorldUpdate(data.world_update);

        await scrollToBottom();
        await nextTick();
        inputEl.value?.focus();
    } catch (e: any) {
        turns.value.pop();
        errorMessage.value = e?.message?.includes('hiccuped')
            ? e.message
            : 'Wonderland hiccuped. Your action was preserved — please try again.';
    } finally {
        loading.value = false;
    }
}

function lastChoices(): string[] {
    for (let i = turns.value.length - 1; i >= 0; i--) {
        if (turns.value[i].role === 'narrator') {
            return [];
        }
    }
    return [];
}

function getCurrentChoices(): string[] {
    // Find the most recent narrator turn and extract its choices from the last API response
    // We store them separately since they're in the API response, not the turn itself.
    return choicesBuffer.value;
}

const choicesBuffer = ref<string[]>([]);

// Override takeTurn to also capture choices
async function takeTurnWithChoices(action: string): Promise<void> {
    const trimmed = action.trim();
    if (!trimmed || loading.value) return;

    playerInput.value = '';
    loading.value = true;
    errorMessage.value = '';

    turns.value.push({ role: 'player', text: trimmed });
    choicesBuffer.value = [];
    await scrollToBottom();

    try {
        const res = await fetch('/chaos-mode/turn', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({
                player_action: trimmed,
                model: selectedModel.value,
                conversation_history: historyForApi().slice(-12),
                world_state: worldState.value,
            }),
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        if (data.error) throw new Error(data.error);

        turns.value.push({ role: 'narrator', html: data.response, text: stripHtml(data.response) });
        sceneNote.value = data.scene_note ?? sceneNote.value;
        choicesBuffer.value = data.choices ?? [];
        if (data.world_update) applyWorldUpdate(data.world_update);

        await scrollToBottom();
        await nextTick();
        inputEl.value?.focus();
    } catch (e: any) {
        turns.value.pop();
        errorMessage.value = e?.message?.includes('hiccuped')
            ? e.message
            : 'Wonderland hiccuped. Your action was preserved — please try again.';
    } finally {
        loading.value = false;
    }
}

async function startWithChoices(): Promise<void> {
    if (loading.value) return;
    loading.value = true;
    errorMessage.value = '';
    choicesBuffer.value = [];

    try {
        const res = await fetch('/chaos-mode/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ model: selectedModel.value }),
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        if (data.error) throw new Error(data.error);

        turns.value = [{ role: 'narrator', html: data.response, text: stripHtml(data.response) }];
        sceneNote.value = data.scene_note ?? sceneNote.value;
        choicesBuffer.value = data.choices ?? [];
        if (data.world_update) applyWorldUpdate(data.world_update);
        started.value = true;

        await scrollToBottom();
        await nextTick();
        inputEl.value?.focus();
    } catch (e: any) {
        errorMessage.value = e?.message?.includes('unavailable')
            ? e.message
            : 'Wonderland is currently unavailable. Please try again.';
    } finally {
        loading.value = false;
    }
}

function resetAdventure(): void {
    started.value = false;
    turns.value = [];
    choicesBuffer.value = [];
    worldState.value = { size_condition: '', items: [], location: '', notes: [] };
    sceneNote.value = 'Chapter I — Down the Rabbit-Hole';
    errorMessage.value = '';
    playerInput.value = '';
}
</script>

<template>
    <div class="chaos-root">
        <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=IM+Fell+English+SC&display=swap"
        />

        <!-- Ambient background -->
        <div class="chaos-ambient" aria-hidden="true">
            <div class="chaos-ambient-glow chaos-ambient-glow--top" />
            <div class="chaos-ambient-glow chaos-ambient-glow--bottom" />
        </div>

        <!-- Header -->
        <header class="chaos-header">
            <div class="chaos-header-brand">
                <span class="chaos-suit chaos-suit--spade">♠</span>
                <span class="chaos-header-title">Chaos Mode</span>
                <span class="chaos-suit chaos-suit--heart">♥</span>
            </div>

            <div class="chaos-header-controls">
                <div class="chaos-model-picker">
                    <label class="chaos-model-label" for="model-select">Narrator</label>
                    <select
                        id="model-select"
                        v-model="selectedModel"
                        class="chaos-model-select"
                        :disabled="started"
                    >
                        <option v-for="m in MODELS" :key="m.value" :value="m.value">
                            {{ m.label }}
                        </option>
                    </select>
                </div>

                <button v-if="started" class="chaos-reset-btn" @click="resetAdventure">
                    New Adventure
                </button>
            </div>
        </header>

        <!-- Start screen -->
        <main v-if="!started" class="chaos-start">
            <div class="chaos-start-inner">
                <div class="chaos-start-suits" aria-hidden="true">♠ ♥ ♦ ♣</div>

                <h1 class="chaos-start-title">Alice's Adventures<br />in Wonderland</h1>
                <p class="chaos-start-edition">Chaos Mode — Full Agency Edition</p>

                <p class="chaos-start-desc">
                    Type any action. Wonderland absorbs everything.<br />
                    The story has no rails — only gravity.
                </p>

                <div class="chaos-start-model-display">
                    Narrated by
                    <strong>{{ MODELS.find((m) => m.value === selectedModel)?.label }}</strong>
                    &nbsp;&middot;&nbsp;
                    {{ MODELS.find((m) => m.value === selectedModel)?.provider }}
                </div>

                <button class="chaos-start-btn" :disabled="loading" @click="startWithChoices">
                    <span v-if="loading" class="chaos-start-btn-loading">
                        <span class="chaos-loading-dot" />
                        <span class="chaos-loading-dot" />
                        <span class="chaos-loading-dot" />
                        Falling down the rabbit-hole...
                    </span>
                    <span v-else>Begin the Adventure ♦</span>
                </button>

                <p v-if="errorMessage" class="chaos-start-error">{{ errorMessage }}</p>
            </div>
        </main>

        <!-- Game screen -->
        <div v-else class="chaos-game">
            <!-- Scene note -->
            <div class="chaos-scene-note">
                <span class="chaos-scene-divider">♦</span>
                {{ sceneNote }}
                <span class="chaos-scene-divider">♦</span>
            </div>

            <!-- Story scroll -->
            <div ref="storyContainer" class="chaos-story">
                <template v-for="(turn, idx) in turns" :key="idx">
                    <!-- Narrator turn -->
                    <div v-if="turn.role === 'narrator'" class="chaos-narrator-turn">
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <div class="chaos-narrator-prose" v-html="turn.html ?? turn.text" />
                    </div>

                    <!-- Player action -->
                    <div v-else class="chaos-player-turn">
                        <span class="chaos-player-label">Alice</span>
                        <span class="chaos-player-text">{{ turn.text }}</span>
                    </div>
                </template>

                <!-- Loading indicator -->
                <div v-if="loading" class="chaos-thinking">
                    <span class="chaos-loading-dot chaos-loading-dot--gold" />
                    <span class="chaos-loading-dot chaos-loading-dot--gold" />
                    <span class="chaos-loading-dot chaos-loading-dot--gold" />
                </div>
            </div>

            <!-- World state hint -->
            <div
                v-if="worldState.size_condition || worldState.items.length || worldState.location"
                class="chaos-world-state"
            >
                <span v-if="worldState.location" class="chaos-world-pill">
                    ◈ {{ worldState.location }}
                </span>
                <span v-if="worldState.size_condition" class="chaos-world-pill">
                    ◈ {{ worldState.size_condition }}
                </span>
                <template v-for="item in worldState.items" :key="item">
                    <span class="chaos-world-pill chaos-world-pill--item">{{ item }}</span>
                </template>
            </div>

            <!-- Error -->
            <div v-if="errorMessage" class="chaos-error">{{ errorMessage }}</div>

            <!-- Input area -->
            <div class="chaos-input-area">
                <!-- Choice pills -->
                <div v-if="choicesBuffer.length && !loading" class="chaos-choices">
                    <button
                        v-for="(choice, i) in choicesBuffer"
                        :key="i"
                        class="chaos-choice-pill"
                        :disabled="loading"
                        @click="takeTurnWithChoices(choice)"
                    >
                        {{ choice }}
                    </button>
                </div>

                <!-- Free input -->
                <div class="chaos-free-input">
                    <input
                        ref="inputEl"
                        v-model="playerInput"
                        class="chaos-input"
                        type="text"
                        placeholder="Or type your own action..."
                        :disabled="loading"
                        maxlength="500"
                        @keydown.enter="takeTurnWithChoices(playerInput)"
                    />
                    <button
                        class="chaos-send-btn"
                        :disabled="loading || !playerInput.trim()"
                        @click="takeTurnWithChoices(playerInput)"
                    >
                        ↩
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ─── Root ─────────────────────────────────────────────────────────────────── */
.chaos-root {
    min-height: 100dvh;
    background: #07070f;
    color: #e5d9c0;
    font-family: 'IM Fell English', Georgia, serif;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

/* ─── Ambient glow ──────────────────────────────────────────────────────────── */
.chaos-ambient {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}
.chaos-ambient-glow {
    position: absolute;
    width: 600px;
    height: 600px;
    border-radius: 50%;
    filter: blur(140px);
    opacity: 0.08;
}
.chaos-ambient-glow--top {
    top: -200px;
    left: 50%;
    transform: translateX(-50%);
    background: radial-gradient(circle, #c9973a, transparent 70%);
}
.chaos-ambient-glow--bottom {
    bottom: -200px;
    right: -100px;
    background: radial-gradient(circle, #6b2020, transparent 70%);
}

/* ─── Header ────────────────────────────────────────────────────────────────── */
.chaos-header {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    border-bottom: 1px solid rgba(201, 151, 58, 0.18);
    background: rgba(7, 7, 15, 0.85);
    backdrop-filter: blur(8px);
    flex-shrink: 0;
}

.chaos-header-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: 0.12em;
    font-size: 13px;
    text-transform: uppercase;
    color: #c9973a;
    font-family: 'IM Fell English SC', Georgia, serif;
}

.chaos-header-title {
    color: #c9973a;
}

.chaos-suit {
    font-size: 16px;
    opacity: 0.7;
}
.chaos-suit--spade { color: #b8cce0; }
.chaos-suit--heart { color: #c0504d; }

.chaos-header-controls {
    display: flex;
    align-items: center;
    gap: 14px;
}

.chaos-model-picker {
    display: flex;
    align-items: center;
    gap: 8px;
}

.chaos-model-label {
    font-size: 11px;
    color: rgba(229, 217, 192, 0.45);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-family: Georgia, serif;
}

.chaos-model-select {
    background: rgba(201, 151, 58, 0.08);
    border: 1px solid rgba(201, 151, 58, 0.25);
    color: #c9973a;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-family: Georgia, serif;
    outline: none;
    transition: border-color 0.2s;
}
.chaos-model-select:hover:not(:disabled) {
    border-color: rgba(201, 151, 58, 0.5);
}
.chaos-model-select:disabled {
    opacity: 0.4;
    cursor: default;
}

.chaos-reset-btn {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(229, 217, 192, 0.4);
    background: none;
    border: 1px solid rgba(229, 217, 192, 0.12);
    padding: 4px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-family: Georgia, serif;
    transition: color 0.2s, border-color 0.2s;
}
.chaos-reset-btn:hover {
    color: rgba(229, 217, 192, 0.75);
    border-color: rgba(229, 217, 192, 0.3);
}

/* ─── Start screen ──────────────────────────────────────────────────────────── */
.chaos-start {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
    padding: 40px 20px;
}

.chaos-start-inner {
    text-align: center;
    max-width: 520px;
}

.chaos-start-suits {
    font-size: 22px;
    letter-spacing: 18px;
    color: rgba(201, 151, 58, 0.3);
    margin-bottom: 32px;
}

.chaos-start-title {
    font-size: clamp(28px, 5vw, 46px);
    font-family: 'IM Fell English', Georgia, serif;
    font-weight: normal;
    line-height: 1.2;
    color: #ede0c4;
    margin: 0 0 12px;
    letter-spacing: 0.02em;
}

.chaos-start-edition {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    color: #c9973a;
    margin: 0 0 28px;
    font-family: 'IM Fell English SC', Georgia, serif;
}

.chaos-start-desc {
    font-size: 16px;
    line-height: 1.7;
    color: rgba(229, 217, 192, 0.55);
    margin: 0 0 24px;
    font-style: italic;
}

.chaos-start-model-display {
    font-size: 12px;
    color: rgba(229, 217, 192, 0.35);
    margin: 0 0 36px;
    font-family: Georgia, serif;
    letter-spacing: 0.04em;
}
.chaos-start-model-display strong {
    color: rgba(201, 151, 58, 0.7);
    font-weight: normal;
}

.chaos-start-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 42px;
    background: rgba(201, 151, 58, 0.1);
    border: 1px solid rgba(201, 151, 58, 0.4);
    color: #c9973a;
    font-family: 'IM Fell English', Georgia, serif;
    font-size: 17px;
    letter-spacing: 0.06em;
    border-radius: 2px;
    cursor: pointer;
    transition: background 0.25s, border-color 0.25s, color 0.25s;
    min-width: 260px;
    min-height: 52px;
}
.chaos-start-btn:hover:not(:disabled) {
    background: rgba(201, 151, 58, 0.18);
    border-color: rgba(201, 151, 58, 0.7);
    color: #deb96a;
}
.chaos-start-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.chaos-start-btn-loading {
    display: flex;
    align-items: center;
    gap: 6px;
    font-style: italic;
    font-size: 15px;
    color: rgba(201, 151, 58, 0.7);
}

.chaos-start-error {
    margin-top: 20px;
    font-size: 13px;
    color: #c0504d;
    font-style: italic;
}

/* ─── Game layout ───────────────────────────────────────────────────────────── */
.chaos-game {
    flex: 1;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
    overflow: hidden;
}

/* ─── Scene note ────────────────────────────────────────────────────────────── */
.chaos-scene-note {
    text-align: center;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: rgba(201, 151, 58, 0.45);
    padding: 10px 20px;
    border-bottom: 1px solid rgba(201, 151, 58, 0.08);
    font-family: 'IM Fell English SC', Georgia, serif;
    flex-shrink: 0;
}
.chaos-scene-divider {
    margin: 0 12px;
    opacity: 0.5;
}

/* ─── Story scroll ──────────────────────────────────────────────────────────── */
.chaos-story {
    flex: 1;
    overflow-y: auto;
    padding: 32px 24px 16px;
    max-width: 720px;
    width: 100%;
    margin: 0 auto;
    scroll-behavior: smooth;
}

.chaos-story::-webkit-scrollbar {
    width: 4px;
}
.chaos-story::-webkit-scrollbar-track {
    background: transparent;
}
.chaos-story::-webkit-scrollbar-thumb {
    background: rgba(201, 151, 58, 0.2);
    border-radius: 2px;
}

/* ─── Narrator turn ─────────────────────────────────────────────────────────── */
.chaos-narrator-turn {
    margin-bottom: 24px;
}

.chaos-narrator-prose {
    font-size: clamp(16px, 2vw, 18px);
    line-height: 1.85;
    color: #e5d9c0;
    font-family: 'IM Fell English', Georgia, serif;
}

/* Prose element styles */
.chaos-narrator-prose :deep(p) {
    margin: 0 0 1.1em;
}
.chaos-narrator-prose :deep(p:last-child) {
    margin-bottom: 0;
}
.chaos-narrator-prose :deep(em) {
    font-style: italic;
    color: #d4c9ad;
}
.chaos-narrator-prose :deep(strong) {
    font-weight: normal;
    color: #c9973a;
    letter-spacing: 0.02em;
}

/* ─── Player turn ───────────────────────────────────────────────────────────── */
.chaos-player-turn {
    display: flex;
    align-items: baseline;
    gap: 10px;
    margin: 8px 0 20px;
    padding-left: 16px;
    border-left: 2px solid rgba(201, 151, 58, 0.25);
}

.chaos-player-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: rgba(201, 151, 58, 0.5);
    font-family: Georgia, serif;
    flex-shrink: 0;
}

.chaos-player-text {
    font-size: 14px;
    color: rgba(229, 217, 192, 0.5);
    font-style: italic;
    line-height: 1.5;
}

/* ─── Loading ───────────────────────────────────────────────────────────────── */
.chaos-thinking {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 12px 0;
}

.chaos-loading-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(201, 151, 58, 0.35);
    animation: chaos-pulse 1.2s infinite ease-in-out;
}
.chaos-loading-dot:nth-child(2) { animation-delay: 0.2s; }
.chaos-loading-dot:nth-child(3) { animation-delay: 0.4s; }
.chaos-loading-dot--gold {
    background: rgba(201, 151, 58, 0.5);
}

@keyframes chaos-pulse {
    0%, 100% { opacity: 0.25; transform: scale(0.85); }
    50% { opacity: 1; transform: scale(1); }
}

/* ─── World state ───────────────────────────────────────────────────────────── */
.chaos-world-state {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 24px;
    max-width: 720px;
    width: 100%;
    margin: 0 auto;
    flex-shrink: 0;
}

.chaos-world-pill {
    font-size: 10px;
    color: rgba(229, 217, 192, 0.3);
    border: 1px solid rgba(229, 217, 192, 0.1);
    padding: 2px 8px;
    border-radius: 2px;
    font-family: Georgia, serif;
    letter-spacing: 0.04em;
    text-transform: lowercase;
}
.chaos-world-pill--item {
    color: rgba(201, 151, 58, 0.4);
    border-color: rgba(201, 151, 58, 0.15);
}

/* ─── Error ─────────────────────────────────────────────────────────────────── */
.chaos-error {
    font-size: 12px;
    color: #c0504d;
    font-style: italic;
    text-align: center;
    padding: 6px 24px;
    flex-shrink: 0;
}

/* ─── Input area ────────────────────────────────────────────────────────────── */
.chaos-input-area {
    flex-shrink: 0;
    border-top: 1px solid rgba(201, 151, 58, 0.1);
    padding: 14px 24px 20px;
    max-width: 720px;
    width: 100%;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* ─── Choice pills ──────────────────────────────────────────────────────────── */
.chaos-choices {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.chaos-choice-pill {
    font-family: 'IM Fell English', Georgia, serif;
    font-size: 13px;
    color: rgba(229, 217, 192, 0.65);
    background: rgba(229, 217, 192, 0.04);
    border: 1px solid rgba(229, 217, 192, 0.14);
    padding: 7px 16px;
    border-radius: 2px;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
    text-align: left;
    line-height: 1.4;
}
.chaos-choice-pill:hover:not(:disabled) {
    background: rgba(201, 151, 58, 0.1);
    border-color: rgba(201, 151, 58, 0.35);
    color: #ddc898;
}
.chaos-choice-pill:disabled {
    opacity: 0.3;
    cursor: default;
}

/* ─── Free input ────────────────────────────────────────────────────────────── */
.chaos-free-input {
    display: flex;
    gap: 8px;
    align-items: center;
}

.chaos-input {
    flex: 1;
    background: rgba(229, 217, 192, 0.04);
    border: 1px solid rgba(229, 217, 192, 0.12);
    color: #e5d9c0;
    font-family: 'IM Fell English', Georgia, serif;
    font-size: 15px;
    font-style: italic;
    padding: 10px 14px;
    border-radius: 2px;
    outline: none;
    transition: border-color 0.2s;
}
.chaos-input::placeholder {
    color: rgba(229, 217, 192, 0.22);
}
.chaos-input:focus {
    border-color: rgba(201, 151, 58, 0.4);
}
.chaos-input:disabled {
    opacity: 0.4;
}

.chaos-send-btn {
    background: rgba(201, 151, 58, 0.1);
    border: 1px solid rgba(201, 151, 58, 0.3);
    color: #c9973a;
    font-size: 18px;
    width: 42px;
    height: 42px;
    border-radius: 2px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s, border-color 0.2s;
    flex-shrink: 0;
}
.chaos-send-btn:hover:not(:disabled) {
    background: rgba(201, 151, 58, 0.2);
    border-color: rgba(201, 151, 58, 0.55);
}
.chaos-send-btn:disabled {
    opacity: 0.25;
    cursor: default;
}

/* ─── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
    .chaos-story {
        padding: 20px 16px 12px;
    }
    .chaos-input-area {
        padding: 10px 16px 16px;
    }
    .chaos-world-state {
        padding: 6px 16px;
    }
    .chaos-choices {
        flex-direction: column;
    }
    .chaos-choice-pill {
        width: 100%;
    }
}
</style>
