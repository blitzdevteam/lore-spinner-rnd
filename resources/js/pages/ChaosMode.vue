<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import { LucideChevronLeft, LucideRefreshCw } from 'lucide-vue-next';
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
const sceneNote = ref('Chapter I — Down the Rabbit-Hole');
const turns = ref<Turn[]>([]);
const choicesBuffer = ref<string[]>([]);
const worldState = ref<WorldState>({ size_condition: '', items: [], location: '', notes: [] });
const errorMessage = ref('');
const scrollEl = ref<HTMLElement | null>(null);

// ─── CSRF: read Laravel's XSRF-TOKEN cookie, send as X-XSRF-TOKEN ─────────────
// app.blade.php has no csrf meta tag; this is the correct approach for raw fetch
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

function historyForApi(): Array<{ role: string; text: string }> {
    return turns.value.map((t) => ({ role: t.role, text: t.text }));
}

function applyWorldUpdate(update: Partial<WorldState>): void {
    if (update.size_condition) worldState.value.size_condition = update.size_condition;
    if (update.items !== undefined) worldState.value.items = update.items;
    if (update.location) worldState.value.location = update.location;
    if (update.notes?.length) worldState.value.notes = [...worldState.value.notes, ...update.notes];
}

async function scrollToBottom(): Promise<void> {
    await nextTick();
    if (scrollEl.value) {
        scrollEl.value.scrollTo({ top: scrollEl.value.scrollHeight, behavior: 'smooth' });
    }
}

async function startWithChoices(): Promise<void> {
    if (loading.value) return;
    loading.value = true;
    errorMessage.value = '';
    choicesBuffer.value = [];

    try {
        const data = await apiFetch('/chaos-mode/start', { model: selectedModel.value });
        turns.value = [{ role: 'narrator', html: data.response, text: stripHtml(data.response) }];
        sceneNote.value = data.scene_note ?? sceneNote.value;
        choicesBuffer.value = data.choices ?? [];
        if (data.world_update) applyWorldUpdate(data.world_update);
        started.value = true;
        await scrollToBottom();
    } catch (e: any) {
        errorMessage.value = 'Wonderland is currently unavailable. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function takeTurn(action: string): Promise<void> {
    const trimmed = action.trim();
    if (!trimmed || loading.value) return;

    loading.value = true;
    errorMessage.value = '';
    turns.value.push({ role: 'player', text: trimmed });
    choicesBuffer.value = [];
    await scrollToBottom();

    try {
        const data = await apiFetch('/chaos-mode/turn', {
            player_action: trimmed,
            model: selectedModel.value,
            conversation_history: historyForApi().slice(-12),
            world_state: worldState.value,
        });

        turns.value.push({ role: 'narrator', html: data.response, text: stripHtml(data.response) });
        sceneNote.value = data.scene_note ?? sceneNote.value;
        choicesBuffer.value = data.choices ?? [];
        if (data.world_update) applyWorldUpdate(data.world_update);
        await scrollToBottom();
    } catch (e: any) {
        turns.value.pop();
        errorMessage.value = 'Wonderland hiccuped — please try again.';
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
}
</script>

<template>
    <div class="relative h-svh overflow-hidden">
        <BaseBackgroundGradient />

        <!-- ── Start screen ──────────────────────────────────────────────────── -->
        <div v-if="!started" class="relative flex h-full flex-col items-center justify-center px-4">
            <div class="w-full max-w-md rounded-2xl border border-gray-700/50 bg-gray-800/40 p-8 text-center backdrop-blur-sm">
                <p class="mb-3 text-xs uppercase tracking-widest text-primary-400">Experimental — Chaos Mode</p>

                <h1 class="mb-1 text-2xl font-medium text-gray-100 sm:text-3xl">
                    Alice's Adventures<br />in Wonderland
                </h1>
                <p class="mb-6 text-sm text-gray-500">Full Agency Edition — type any action, Wonderland absorbs everything.</p>

                <!-- Model selector -->
                <div class="mb-6">
                    <label class="mb-2 block text-left text-xs uppercase tracking-widest text-gray-500">
                        Narrator model
                    </label>
                    <select
                        v-model="selectedModel"
                        class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2.5 text-sm text-gray-200 outline-none focus:border-primary-500"
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
                        <span class="size-4 animate-spin rounded-full border-2 border-gray-900 border-t-transparent" />
                        Falling down the rabbit-hole...
                    </span>
                    <span v-else>Begin the Adventure</span>
                </BaseButton>

                <p v-if="errorMessage" class="mt-4 text-sm text-red-400">{{ errorMessage }}</p>

                <a href="/" class="mt-6 block text-xs text-gray-600 hover:text-gray-400 transition-colors">← Back to LoreSpinner</a>
            </div>
        </div>

        <!-- ── Game screen ───────────────────────────────────────────────────── -->
        <div v-else class="flex h-full flex-col">

            <!-- Sticky header -->
            <div class="sticky top-0 z-10 flex h-20 shrink-0 items-center justify-between px-4 bg-linear-to-b from-gray-950 via-gray-950/80 to-transparent sm:px-8">
                <!-- Left: back -->
                <BaseButton severity="glass" :icon-only="true" class="size-12!" @click="resetAdventure">
                    <LucideChevronLeft class="size-6 text-gray-300" :stroke-width="1.5" />
                </BaseButton>

                <!-- Centre: scene note -->
                <p class="text-center text-xs uppercase tracking-widest text-gray-500">{{ sceneNote }}</p>

                <!-- Right: model chip + reset -->
                <div class="flex items-center gap-2">
                    <span class="hidden rounded-full border border-gray-700/50 bg-gray-800/60 px-3 py-1 text-xs text-gray-500 sm:block">
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
                        <!-- Narrator turn -->
                        <div v-if="turn.role === 'narrator'" class="chaos-prose py-8">
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <div v-html="turn.html ?? turn.text" />
                        </div>

                        <!-- Player turn -->
                        <div v-else class="flex items-baseline gap-3 py-4">
                            <span class="shrink-0 text-[10px] uppercase tracking-widest text-gray-600">Alice</span>
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
                </div>
            </div>

            <!-- World state + error -->
            <div v-if="worldState.location || worldState.size_condition || worldState.items.length" class="mx-auto flex w-full max-w-3xl flex-wrap gap-1.5 px-4 pb-1 sm:px-8">
                <span v-if="worldState.location" class="rounded-full border border-gray-800 px-2 py-0.5 text-[10px] text-gray-600">
                    {{ worldState.location }}
                </span>
                <span v-if="worldState.size_condition" class="rounded-full border border-gray-800 px-2 py-0.5 text-[10px] text-gray-600">
                    {{ worldState.size_condition }}
                </span>
                <span v-for="item in worldState.items" :key="item" class="rounded-full border border-primary-900/40 px-2 py-0.5 text-[10px] text-primary-700">
                    {{ item }}
                </span>
            </div>

            <p v-if="errorMessage" class="px-4 pb-1 text-center text-xs text-red-400 sm:px-8">{{ errorMessage }}</p>

            <!-- Sticky bottom: choices + input -->
            <div class="sticky bottom-0 z-10 shrink-0">
                <!-- Choice pills -->
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

                <!-- Text input -->
                <div class="grid h-28 place-items-center px-4">
                    <GameplayInput :disabled="loading" @submit="takeTurn" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Carroll's prose — inherits game typography settings */
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
    color: var(--color-primary-300, #e9d5a0);
    font-weight: 500;
}
</style>
