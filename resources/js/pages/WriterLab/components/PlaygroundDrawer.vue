<script setup lang="ts">
/**
 * Multi-event Playground drawer.
 *
 * Mirrors the runtime narrator turn-by-turn through a writer-selected sequence
 * of events. Auto-advances when the AI returns advance_event=true.
 *
 * Stateless backend — this component owns the conversation history and the
 * accumulated world_state (state_delta merges).
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { wlError, wlTrace } from '@/lib/wlTrace';

interface PlaygroundEvent {
    id: number;
    position: number;
    title: string;
    session_number: number | null;
    has_draft: boolean;
}

interface TurnMessage {
    role: 'narrator' | 'player' | 'system';
    text: string;
    choices?: string[];
    event_id?: number;
}

interface StateDelta {
    objects_acquired?: Array<{ name: string; qualifier?: string; contains?: string[] }>;
    objects_lost?: string[];
    objects_transformed?: Array<{ name: string; new_qualifier: string }>;
    conditions_added?: Array<{ name: string; note?: string }>;
    conditions_removed?: string[];
    location_changed?: string;
    knowledge_gained?: string[];
    relationship_changes?: Array<{ character: string; shift: string }>;
    tracked_path_update?: Array<{ dimension: string; path: string }>;
    flags_set?: string[];
}

interface WorldState {
    objects?: Record<string, { qualifier?: string; contains?: string[] }>;
    conditions?: Record<string, string>;
    location?: string;
    knowledge?: string[];
    relationships?: Record<string, string>;
    flags?: string[];
}

const props = defineProps<{
    storyId: number;
    chapterId: number;
    eventIds: number[];
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const csrf = (): string =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const apiPost = async (url: string, body: Record<string, unknown> = {}, signal?: AbortSignal) => {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
        signal,
    });
    const raw = await res.text();
    let data: Record<string, unknown> = {};
    try {
        data = raw ? (JSON.parse(raw) as Record<string, unknown>) : {};
    } catch {
        data = { error: raw.slice(0, 280) || `HTTP ${res.status}` };
    }
    if (!res.ok && data.error === undefined) {
        data.error = `Request failed (${res.status})`;
    }
    return data;
};

// ── State ──────────────────────────────────────────────────────────────────
const sequence       = ref<PlaygroundEvent[]>([]);
const pointer        = ref(0);             // index into sequence
const transcript     = ref<TurnMessage[]>([]);
const turnCount      = ref(0);             // turns elapsed in CURRENT event
const playerInput    = ref('');
const running        = ref(false);         // a turn is in flight
const status         = ref<'starting' | 'running' | 'complete' | 'error'>('starting');
const errorText      = ref<string | null>(null);
const worldState     = ref<WorldState>({});
const transcriptScrollEl = ref<HTMLDivElement | null>(null);

let inFlight: AbortController | null = null;

const currentEvent = computed<PlaygroundEvent | null>(() => sequence.value[pointer.value] ?? null);
const progressText = computed(() =>
    sequence.value.length > 0 ? `Event ${pointer.value + 1} of ${sequence.value.length}` : ''
);

// ── World state merge (mirrors runtime narration controller) ───────────────
const mergeStateDelta = (delta: StateDelta) => {
    const ws = { ...worldState.value };

    if (delta.objects_acquired?.length) {
        ws.objects = { ...(ws.objects ?? {}) };
        for (const o of delta.objects_acquired) {
            ws.objects[o.name] = { qualifier: o.qualifier, contains: o.contains };
        }
    }
    if (delta.objects_lost?.length && ws.objects) {
        for (const name of delta.objects_lost) delete ws.objects[name];
    }
    if (delta.objects_transformed?.length && ws.objects) {
        for (const t of delta.objects_transformed) {
            if (ws.objects[t.name]) ws.objects[t.name].qualifier = t.new_qualifier;
        }
    }
    if (delta.conditions_added?.length) {
        ws.conditions = { ...(ws.conditions ?? {}) };
        for (const c of delta.conditions_added) ws.conditions[c.name] = c.note ?? '';
    }
    if (delta.conditions_removed?.length && ws.conditions) {
        for (const name of delta.conditions_removed) delete ws.conditions[name];
    }
    if (delta.location_changed) ws.location = delta.location_changed;
    if (delta.knowledge_gained?.length) {
        ws.knowledge = [...(ws.knowledge ?? []), ...delta.knowledge_gained];
    }
    if (delta.relationship_changes?.length) {
        ws.relationships = { ...(ws.relationships ?? {}) };
        for (const r of delta.relationship_changes) ws.relationships[r.character] = r.shift;
    }
    if (delta.flags_set?.length) {
        const set = new Set([...(ws.flags ?? []), ...delta.flags_set]);
        ws.flags = [...set];
    }

    worldState.value = ws;
};

// ── Network ────────────────────────────────────────────────────────────────
const start = async () => {
    status.value    = 'starting';
    errorText.value = null;

    const data = await apiPost(
        `/writer/writer-lab/${props.storyId}/chapters/${props.chapterId}/playground/start`,
        { event_ids: props.eventIds }
    );

    if (data.error) {
        status.value    = 'error';
        errorText.value = data.error;
        wlError('playground.start.failed', { error: data.error });
        return;
    }

    sequence.value = data.sequence as PlaygroundEvent[];
    pointer.value  = 0;
    turnCount.value = 0;
    transcript.value = [];
    worldState.value = {};

    wlTrace('playground.start.ok', { sequence_count: sequence.value.length });

    if (sequence.value.length === 0) {
        status.value    = 'error';
        errorText.value = 'No events to play.';
        return;
    }

    status.value = 'running';
    // Auto-fire the opening turn for event[0]
    await runTurn('');
};

const runTurn = async (action: string) => {
    if (!currentEvent.value) return;

    // Record the player's line BEFORE building history so the LLM sees the latest user turn.
    if (action !== '') {
        transcript.value.push({ role: 'player', text: action, event_id: currentEvent.value.id });
    }

    // Build conversation history of this current-event run only (matches runtime)
    const history = transcript.value
        .filter(m => m.event_id === currentEvent.value!.id)
        .map(m => ({
            role: m.role === 'narrator' ? 'assistant' : 'user',
            text: m.text,
        }));

    running.value = true;

    if (inFlight) inFlight.abort();
    inFlight = new AbortController();

    const payload = {
        event_id:             currentEvent.value.id,
        conversation_history: history,
        world_state:          worldState.value,
        player_action:        action,
        turn_count:           turnCount.value,
    };

    wlTrace('playground.turn.send', {
        event_id:    payload.event_id,
        turn_count:  payload.turn_count,
        history_len: history.length,
        action_len:  action.length,
    });

    let data;
    try {
        data = await apiPost(
            `/writer/writer-lab/${props.storyId}/chapters/${props.chapterId}/playground/turn`,
            payload,
            inFlight.signal,
        );
    } catch (e: any) {
        if (e?.name === 'AbortError') return;          // user quit
        running.value   = false;
        status.value    = 'error';
        errorText.value = 'Request failed.';
        wlError('playground.turn.failed', { error: String(e) });
        return;
    }

    running.value = false;

    if (data.error) {
        status.value    = 'error';
        errorText.value = data.error;
        wlError('playground.turn.error', { error: data.error });
        return;
    }

    transcript.value.push({
        role:     'narrator',
        text:     data.response ?? '',
        choices:  data.choices ?? [],
        event_id: currentEvent.value.id,
    });

    if (data.state_delta) mergeStateDelta(data.state_delta);

    turnCount.value += 1;
    await scrollToBottom();

    wlTrace('playground.turn.received', {
        advance_event:    data.advance_event,
        choices_count:    (data.choices ?? []).length,
        state_delta_keys: Object.keys(data.state_delta ?? {}),
    });

    if (data.advance_event) {
        if (pointer.value < sequence.value.length - 1) {
            // Move to the next selected event and auto-fire its opening turn
            const fromId = currentEvent.value.id;
            pointer.value += 1;
            turnCount.value = 0;
            transcript.value.push({
                role: 'system',
                text: `Moved to Event ${pointer.value + 1}: ${currentEvent.value!.title}`,
                event_id: currentEvent.value!.id,
            });
            wlTrace('playground.advance', { from_id: fromId, to_id: currentEvent.value!.id });
            await scrollToBottom();
            await runTurn('');
        } else {
            // Reached end of selection — stay open, show completion footer.
            status.value = 'complete';
            transcript.value.push({
                role: 'system',
                text: 'Playground sequence complete. Replay or quit.',
            });
            wlTrace('playground.complete', { total_events: sequence.value.length });
            await scrollToBottom();
        }
    }
};

const send = () => {
    const action = playerInput.value.trim();
    if (!action || running.value) return;
    playerInput.value = '';
    void runTurn(action);
};

const pickChoice = (text: string) => {
    if (running.value) return;
    playerInput.value = text;
    send();
};

const replay = async () => {
    pointer.value    = 0;
    turnCount.value  = 0;
    transcript.value = [];
    worldState.value = {};
    status.value     = 'running';
    errorText.value  = null;
    wlTrace('playground.replay', {});
    await runTurn('');
};

const quit = () => {
    if (inFlight) inFlight.abort();
    wlTrace('playground.quit', { pointer: pointer.value, transcript_len: transcript.value.length });
    emit('close');
};

const scrollToBottom = async () => {
    await nextTick();
    if (transcriptScrollEl.value) {
        transcriptScrollEl.value.scrollTop = transcriptScrollEl.value.scrollHeight;
    }
};

// Boot on mount
watch(() => props.eventIds, () => { void start(); }, { immediate: true });

onBeforeUnmount(() => { if (inFlight) inFlight.abort(); });
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-stretch justify-end bg-black/60 backdrop-blur-sm">
        <div class="flex h-full w-full max-w-3xl flex-col border-l border-gray-700 bg-gray-950 text-white shadow-2xl">

            <!-- Header -->
            <header class="flex-none border-b border-gray-800 px-6 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="rounded-full bg-primary-900/60 px-2 py-0.5 text-xs uppercase tracking-widest text-primary-300">
                            Playground
                        </span>
                        <span v-if="currentEvent" class="truncate text-sm">
                            <span class="font-mono text-xs text-gray-600 mr-1">{{ currentEvent.position }}</span>
                            <span class="font-medium">{{ currentEvent.title }}</span>
                            <span v-if="currentEvent.has_draft" class="ml-2 rounded bg-yellow-900/40 px-1.5 py-0.5 text-xs text-yellow-400">draft overlay</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded bg-gray-800 px-2 py-1 text-gray-400">{{ progressText }}</span>
                        <button class="rounded-lg px-3 py-1.5 text-gray-400 hover:bg-gray-800 hover:text-white transition-colors" @click="quit">
                            Quit ✕
                        </button>
                    </div>
                </div>
            </header>

            <!-- Transcript -->
            <div ref="transcriptScrollEl" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                <div v-if="status === 'starting' && transcript.length === 0" class="text-center text-gray-600 text-sm py-12">
                    Loading playground…
                </div>

                <div v-if="errorText" class="rounded-xl border border-red-700/50 bg-red-950/30 px-4 py-3 text-sm text-red-300">
                    {{ errorText }}
                </div>

                <template v-for="(msg, i) in transcript" :key="i">
                    <!-- System divider -->
                    <div v-if="msg.role === 'system'" class="flex items-center gap-3 py-1">
                        <div class="h-px flex-1 bg-gray-800"></div>
                        <span class="text-xs uppercase tracking-widest text-gray-600">{{ msg.text }}</span>
                        <div class="h-px flex-1 bg-gray-800"></div>
                    </div>

                    <!-- Narrator bubble -->
                    <div v-else-if="msg.role === 'narrator'" class="space-y-2">
                        <div class="rounded-2xl border border-gray-800 bg-gray-900/50 px-4 py-3 text-sm leading-relaxed text-gray-200 prose prose-invert prose-sm max-w-none"
                             v-html="msg.text"></div>
                        <div v-if="msg.choices && msg.choices.length > 0" class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <button v-for="(c, ci) in msg.choices" :key="ci"
                                    class="rounded-lg border border-gray-800 bg-gray-900/30 px-3 py-2 text-left text-xs text-gray-300 hover:border-primary-600 hover:bg-gray-900 transition-colors disabled:opacity-40"
                                    :disabled="running || status === 'complete'"
                                    @click="pickChoice(c)">
                                <span class="text-gray-600 mr-1.5">{{ ci + 1 }}.</span>{{ c }}
                            </button>
                        </div>
                    </div>

                    <!-- Player bubble -->
                    <div v-else class="flex justify-end">
                        <div class="rounded-2xl bg-primary-900/40 border border-primary-700/40 px-4 py-2 text-sm text-primary-100 max-w-[80%]">
                            {{ msg.text }}
                        </div>
                    </div>
                </template>

                <div v-if="running" class="text-xs text-gray-600 italic flex items-center gap-2">
                    <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-gray-600"></span>
                    Narrator thinking…
                </div>
            </div>

            <!-- Composer / Footer -->
            <footer class="flex-none border-t border-gray-800 px-6 py-3">
                <template v-if="status === 'complete'">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-500">
                            Played all {{ sequence.length }} selected events. Stay here to review, or quit.
                        </p>
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-gray-800 px-3 py-1.5 text-sm text-gray-300 hover:bg-gray-700 transition-colors" @click="replay">
                                ↻ Replay
                            </button>
                            <button class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm text-white hover:bg-primary-500 transition-colors" @click="quit">
                                Quit
                            </button>
                        </div>
                    </div>
                </template>
                <template v-else-if="status === 'error'">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-red-400">{{ errorText }}</p>
                        <button class="rounded-lg bg-gray-800 px-3 py-1.5 text-sm text-gray-300 hover:bg-gray-700 transition-colors" @click="quit">
                            Close
                        </button>
                    </div>
                </template>
                <template v-else>
                    <form class="flex items-center gap-2" @submit.prevent="send">
                        <input v-model="playerInput" type="text"
                               placeholder="What does the player do or say?"
                               :disabled="running"
                               class="flex-1 rounded-lg border border-gray-700 bg-gray-900 px-4 py-2 text-sm text-gray-200 focus:border-primary-500 focus:outline-none disabled:opacity-50" />
                        <button type="submit"
                                :disabled="running || !playerInput.trim()"
                                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-40 transition-colors">
                            Send
                        </button>
                    </form>
                </template>
            </footer>
        </div>
    </div>
</template>
