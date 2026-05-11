<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Event {
    id: number;
    position: number;
    title: string;
    content: string;
    objectives: string | null;
    attributes: string[] | null;
    session_number: number | null;
    requires_choice: boolean;
}

interface SessionAdaptation {
    session_number: number;
    session_status: string;
    cold_open: string | null;
    start_event_id: number | null;
    beat_map: any;
    session_choice_design: any;
}

interface Draft {
    id: number;
    type: string;
    status: string;
    source_event_ids: number[] | null;
    beat_type: string | null;
    created_at: string;
}

interface Story { id: number; title: string; slug: string }
interface Chapter { id: number; position: number; title: string }

const props = defineProps<{
    story: Story;
    chapter: Chapter;
    prevChapter: { id: number; title: string } | null;
    nextChapter: { id: number; title: string } | null;
    events: Event[];
    sessionAdaptations: Record<number, SessionAdaptation>;
    activeDrafts: Draft[];
}>();

// ── Drag-and-drop for reorder ───────────────────────────────────────────────
const reorderMode = ref(false);
const orderedEvents = ref<Event[]>([...props.events]);
const draggedIndex  = ref<number | null>(null);

const enableReorder = () => {
    orderedEvents.value = [...props.events];
    reorderMode.value = true;
    selectedIds.value = [];
};

const cancelReorder = () => {
    reorderMode.value = false;
    orderedEvents.value = [...props.events];
};

const onDragStart = (index: number) => { draggedIndex.value = index; };
const onDragOver  = (e: DragEvent, index: number) => {
    e.preventDefault();
    if (draggedIndex.value === null || draggedIndex.value === index) return;
    const arr = [...orderedEvents.value];
    const [moved] = arr.splice(draggedIndex.value, 1);
    arr.splice(index, 0, moved);
    orderedEvents.value = arr;
    draggedIndex.value  = index;
};
const onDragEnd = () => { draggedIndex.value = null; };

const submitReorder = () => {
    const eventOrder = orderedEvents.value.map((e, i) => ({
        event_id: e.id,
        new_position: i + 1,
    }));
    router.post(`/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/reorder`, {
        event_order: eventOrder,
    });
};

// ── Event selection (for combine / split) ──────────────────────────────────
const selectedIds  = ref<number[]>([]);
const focusedEvent = ref<Event | null>(null);

const toggleSelect = (event: Event) => {
    if (reorderMode.value) return;
    const idx = selectedIds.value.indexOf(event.id);
    if (idx === -1) {
        selectedIds.value.push(event.id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
    focusedEvent.value = event;
};

const isSelected = (id: number) => selectedIds.value.includes(id);

const canCombine = computed(() => selectedIds.value.length >= 2);
const canSplit   = computed(() => selectedIds.value.length === 1);

// ── Actions ────────────────────────────────────────────────────────────────
const combining = ref(false);
const splitting = ref(false);

const submitCombine = () => {
    if (!canCombine.value) return;
    combining.value = true;
    router.post(`/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/combine`, {
        event_ids: selectedIds.value,
    }, { onFinish: () => { combining.value = false; } });
};

const submitSplit = () => {
    if (!canSplit.value) return;
    splitting.value = true;
    router.post(`/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/split`, {
        event_id: selectedIds.value[0],
    }, { onFinish: () => { splitting.value = false; } });
};

// ── Right panel: session tab ────────────────────────────────────────────────
const sessionNumbers = computed(() =>
    [...new Set(props.events.map((e) => e.session_number).filter((n): n is number => n !== null))].sort()
);
const activeSession = ref<number | null>(sessionNumbers.value[0] ?? null);

const sessionAdaptation = computed(() =>
    activeSession.value !== null ? props.sessionAdaptations[activeSession.value] ?? null : null
);

// Status badge colours
const draftStatusColor = (status: string): string => {
    const map: Record<string, string> = {
        draft: 'bg-gray-700 text-gray-300',
        ai_written: 'bg-blue-900 text-blue-300',
        writer_approved: 'bg-yellow-900 text-yellow-300',
        activated: 'bg-emerald-900 text-emerald-300',
    };
    return map[status] ?? 'bg-gray-700 text-gray-300';
};

const draftTypeLabel = (type: string): string => {
    const map: Record<string, string> = { combine: '⊕ combine', split: '⊘ split', reorder: '⇅ reorder', edit: '✎ edit' };
    return map[type] ?? type;
};
</script>

<template>
    <div class="flex h-screen flex-col bg-gray-950 text-white overflow-hidden">
        <!-- Top bar -->
        <header class="flex-none border-b border-gray-800 px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 text-sm">
                    <Link
                        :href="`/writer/writer-lab/${story.id}`"
                        class="text-gray-400 hover:text-white transition-colors"
                    >
                        {{ story.title }}
                    </Link>
                    <span class="text-gray-700">/</span>
                    <span class="font-medium">{{ chapter.title }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <Link
                        v-if="prevChapter"
                        :href="`/writer/writer-lab/${story.id}/chapters/${prevChapter.id}`"
                        class="rounded px-3 py-1.5 text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
                    >
                        ← Prev
                    </Link>
                    <Link
                        v-if="nextChapter"
                        :href="`/writer/writer-lab/${story.id}/chapters/${nextChapter.id}`"
                        class="rounded px-3 py-1.5 text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
                    >
                        Next →
                    </Link>
                </div>
            </div>
        </header>

        <!-- Action toolbar -->
        <div class="flex-none border-b border-gray-800 bg-gray-900/50 px-6 py-2">
            <div class="flex items-center gap-2">
                <template v-if="!reorderMode">
                    <button
                        :disabled="!canCombine || combining"
                        class="rounded-lg px-4 py-1.5 text-sm font-medium transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                        :class="canCombine ? 'bg-primary-600 hover:bg-primary-500 text-white' : 'bg-gray-800 text-gray-400'"
                        @click="submitCombine"
                    >
                        <span v-if="combining">Combining…</span>
                        <span v-else>⊕ Combine ({{ selectedIds.length }})</span>
                    </button>

                    <button
                        :disabled="!canSplit || splitting"
                        class="rounded-lg px-4 py-1.5 text-sm font-medium transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                        :class="canSplit ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-800 text-gray-400'"
                        @click="submitSplit"
                    >
                        <span v-if="splitting">Splitting…</span>
                        <span v-else>⊘ Split Event</span>
                    </button>

                    <div class="flex-1"></div>

                    <button
                        class="rounded-lg bg-gray-800 px-4 py-1.5 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-all"
                        @click="enableReorder"
                    >
                        ⇅ Reorder Events
                    </button>

                    <button
                        v-if="selectedIds.length > 0"
                        class="text-sm text-gray-500 hover:text-gray-300 transition-colors"
                        @click="selectedIds = []"
                    >
                        Clear
                    </button>
                </template>

                <!-- Reorder mode toolbar -->
                <template v-else>
                    <span class="text-sm text-yellow-300">Drag events to reorder</span>
                    <div class="flex-1"></div>
                    <button
                        class="rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-500 transition-all"
                        @click="submitReorder"
                    >
                        Save Reorder
                    </button>
                    <button
                        class="rounded-lg bg-gray-800 px-4 py-1.5 text-sm text-gray-400 hover:text-white transition-all"
                        @click="cancelReorder"
                    >
                        Cancel
                    </button>
                </template>
            </div>
        </div>

        <!-- Two-panel body -->
        <div class="flex flex-1 overflow-hidden">

            <!-- LEFT: Event list -->
            <div class="flex w-3/5 flex-col border-r border-gray-800 overflow-y-auto">
                <!-- Reorder list -->
                <template v-if="reorderMode">
                    <div
                        v-for="(event, index) in orderedEvents"
                        :key="event.id"
                        draggable="true"
                        class="flex cursor-grab items-start gap-4 border-b border-gray-800/60 px-6 py-4 active:cursor-grabbing transition-colors hover:bg-gray-900/60"
                        :class="draggedIndex === index ? 'bg-gray-800/60 opacity-70' : ''"
                        @dragstart="onDragStart(index)"
                        @dragover="onDragOver($event, index)"
                        @dragend="onDragEnd"
                    >
                        <span class="mt-0.5 select-none text-lg text-gray-600">⠿</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-mono text-gray-600">{{ index + 1 }}</span>
                                <span class="text-sm font-medium truncate">{{ event.title }}</span>
                            </div>
                            <p class="text-xs text-gray-500 line-clamp-2">{{ event.content }}</p>
                        </div>
                    </div>
                </template>

                <!-- Normal selection list -->
                <template v-else>
                    <button
                        v-for="event in events"
                        :key="event.id"
                        class="flex items-start gap-4 border-b border-gray-800/60 px-6 py-4 text-left transition-colors hover:bg-gray-900/60 w-full"
                        :class="isSelected(event.id) ? 'bg-primary-950/30 border-l-2 border-l-primary-500' : ''"
                        @click="toggleSelect(event)"
                    >
                        <!-- Checkbox -->
                        <div class="mt-0.5 flex-none">
                            <div
                                class="h-4 w-4 rounded border transition-all"
                                :class="isSelected(event.id)
                                    ? 'bg-primary-500 border-primary-500'
                                    : 'border-gray-700 bg-transparent'"
                            >
                                <svg v-if="isSelected(event.id)" viewBox="0 0 10 10" class="h-full w-full p-0.5 text-white fill-current">
                                    <path d="M1.5 5L4 7.5L8.5 2.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" />
                                </svg>
                            </div>
                        </div>

                        <!-- Event content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-mono text-gray-600">{{ event.position }}</span>
                                <span class="text-sm font-medium truncate">{{ event.title }}</span>
                                <template v-if="event.session_number !== null">
                                    <span class="rounded-full bg-gray-800 px-2 py-0.5 text-xs text-gray-400">S{{ event.session_number }}</span>
                                </template>
                                <span
                                    v-if="!event.requires_choice"
                                    class="rounded-full bg-blue-900/50 px-2 py-0.5 text-xs text-blue-400"
                                >
                                    flow
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 line-clamp-2">{{ event.content }}</p>
                        </div>
                    </button>
                </template>
            </div>

            <!-- RIGHT: Adaptation panel -->
            <div class="flex w-2/5 flex-col overflow-y-auto">

                <!-- Session tabs -->
                <div v-if="sessionNumbers.length > 0" class="flex-none border-b border-gray-800 px-4 py-2 flex gap-1">
                    <button
                        v-for="n in sessionNumbers"
                        :key="n"
                        class="rounded-md px-3 py-1.5 text-xs font-medium transition-all"
                        :class="activeSession === n
                            ? 'bg-primary-600 text-white'
                            : 'text-gray-400 hover:bg-gray-800 hover:text-white'"
                        @click="activeSession = n"
                    >
                        Session {{ n }}
                    </button>
                </div>

                <!-- Session adaptation content -->
                <div v-if="sessionAdaptation" class="flex-1 overflow-y-auto p-5 space-y-5">
                    <!-- Cold open -->
                    <div v-if="sessionAdaptation.cold_open">
                        <h3 class="mb-2 text-xs uppercase tracking-widest text-gray-500">Cold Open</h3>
                        <p class="text-sm leading-relaxed text-gray-300">{{ sessionAdaptation.cold_open }}</p>
                    </div>

                    <!-- Beat map -->
                    <div v-if="sessionAdaptation.beat_map">
                        <h3 class="mb-2 text-xs uppercase tracking-widest text-gray-500">Beat Map</h3>
                        <pre class="rounded-lg bg-gray-900 p-3 text-xs text-gray-400 overflow-auto max-h-40">{{ JSON.stringify(sessionAdaptation.beat_map, null, 2) }}</pre>
                    </div>

                    <!-- Choice design -->
                    <div v-if="sessionAdaptation.session_choice_design">
                        <h3 class="mb-2 text-xs uppercase tracking-widest text-gray-500">Choice Design</h3>
                        <pre class="rounded-lg bg-gray-900 p-3 text-xs text-gray-400 overflow-auto max-h-40">{{ JSON.stringify(sessionAdaptation.session_choice_design, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Focused event detail -->
                <div v-else-if="focusedEvent" class="flex-1 overflow-y-auto p-5">
                    <h3 class="mb-3 text-xs uppercase tracking-widest text-gray-500">Event Detail</h3>
                    <p class="mb-1 font-medium text-sm">{{ focusedEvent.title }}</p>
                    <p class="text-xs text-gray-400 leading-relaxed mb-3">{{ focusedEvent.content }}</p>
                    <template v-if="focusedEvent.objectives">
                        <h4 class="text-xs uppercase tracking-wide text-gray-600 mb-1">Objectives</h4>
                        <p class="text-xs text-gray-400">{{ focusedEvent.objectives }}</p>
                    </template>
                </div>

                <div v-else class="flex-1 flex items-center justify-center text-gray-600 text-sm">
                    Select an event or session tab
                </div>

                <!-- Active drafts -->
                <div v-if="activeDrafts.length > 0" class="flex-none border-t border-gray-800 p-4">
                    <h3 class="mb-3 text-xs uppercase tracking-widest text-gray-500">Active Drafts</h3>
                    <div class="space-y-2">
                        <Link
                            v-for="draft in activeDrafts"
                            :key="draft.id"
                            :href="`/writer/writer-lab/${story.id}/chapters/${chapter.id}/drafts/${draft.id}`"
                            class="flex items-center justify-between rounded-lg bg-gray-900 px-3 py-2 hover:bg-gray-800 transition-colors"
                        >
                            <span class="text-xs text-gray-300">{{ draftTypeLabel(draft.type) }}</span>
                            <span :class="['rounded px-2 py-0.5 text-xs', draftStatusColor(draft.status)]">
                                {{ draft.status.replace('_', ' ') }}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
