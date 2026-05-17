<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import { LucideChevronLeft, LucideRefreshCw } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

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
const sessionId = ref<string | null>(null);
const sessionComplete = ref(false);
const turns = ref<Turn[]>([]);
const choicesBuffer = ref<string[]>([]);
const worldState = ref<WorldState>(emptyWorldState());
const errorMessage = ref('');
const scrollEl = ref<HTMLElement | null>(null);

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
                <p class="mb-3 text-xs uppercase tracking-widest text-[var(--chaos-brand)]">Experimental — Chaos Mode</p>

                <h1 class="mb-1 text-2xl font-medium text-gray-100 sm:text-3xl">
                    Alice's Adventures<br />in Wonderland
                </h1>
                <p class="mb-6 text-sm text-gray-500">Session 1 — full agency. Type anything; Wonderland absorbs everything.</p>

                <div class="mb-6">
                    <label class="mb-2 block text-left text-xs uppercase tracking-widest text-gray-500">
                        Narrator model
                    </label>
                    <select
                        v-model="selectedModel"
                        class="chaos-mode-select w-full rounded-lg border bg-gray-900/90 px-3 py-2.5 text-sm text-gray-200 outline-none"
                    >
                        <optgroup v-for="provider in ['OpenAI', 'Anthropic']" :key="provider" :label="provider">
                            <option
                                v-for="m in MODELS.filter((x) => x.provider === provider)"
                                :key="m.value"
                                :value="m.value"
                            >
                                {{ m.label }}
                            </option>
                        </optgroup>
                    </select>
                </div>

                <BaseButton
                    class="w-full"
                    severity="primary"
                    :disabled="loading"
                    @click="startWithChoices"
                >
                    <span v-if="loading" class="flex items-center justify-center gap-2">
                        <span class="size-4 animate-spin rounded-full border-2 border-[rgba(229,173,83,0.25)] border-t-[var(--chaos-brand)]" />
                        Falling down the rabbit-hole...
                    </span>
                    <span v-else>Begin the Adventure</span>
                </BaseButton>

                <p v-if="errorMessage" class="mt-4 text-sm text-red-400">{{ errorMessage }}</p>

                <a href="/" class="mt-6 block text-xs text-gray-600 hover:text-gray-400 transition-colors">← Back to LoreSpinner</a>
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
                    {{ sessionComplete ? 'Session 1 — Complete' : 'Session 1 — Down the Rabbit-Hole' }}
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

            <!-- Scrollable story -->
            <div ref="scrollEl" class="flex-1 overflow-y-auto">
                <div class="mx-auto flex max-w-3xl flex-col divide-y divide-gray-100/20 px-4 pb-4 sm:px-8">

                    <template v-for="(turn, idx) in turns" :key="idx">
                        <div v-if="turn.role === 'narrator'" class="chaos-prose py-8">
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

                    <!-- Session complete banner -->
                    <div
                        class="my-8 rounded-2xl border p-6 text-center backdrop-blur-sm border-[rgba(229,173,83,0.35)] bg-[rgba(229,173,83,0.07)]"
                    >
                        <p class="mb-2 text-xs uppercase tracking-widest text-[var(--chaos-brand)]">Session 1 — Complete</p>
                        <p class="mb-4 text-sm text-gray-400">Alice has reached the end of this session's arc. Session 2 awaits.</p>
                        <BaseButton severity="primary" :disabled="true" class="opacity-60">
                            Continue to Session 2 (coming soon)
                        </BaseButton>
                    </div>
                </div>
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
        rgba(var(--chaos-brand-rgb), 0.12) 0%,
        rgba(17, 24, 39, 0.72) 38%,
        rgba(17, 24, 39, 0.55) 100%
    );
    box-shadow:
        0 0 0 1px rgba(var(--chaos-brand-rgb), 0.06) inset,
        0 24px 48px -24px rgba(0, 0, 0, 0.5);
}

.chaos-mode-select {
    border-color: rgba(var(--chaos-brand-rgb), 0.3);
}

.chaos-mode-select:focus {
    border-color: rgba(var(--chaos-brand-rgb), 0.65);
    box-shadow: 0 0 0 1px rgba(var(--chaos-brand-rgb), 0.25);
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
</style>
