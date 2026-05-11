<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

// ── Types ──────────────────────────────────────────────────────────────────
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

interface ChoiceOption { text: string; consequence?: string }
interface BranchingChoice {
    choice_id?: string;
    source_moment?: string;
    what_this_choice_tracks?: string;
    choice_question?: string;
    option_a?: ChoiceOption;
    option_b?: ChoiceOption;
    option_c?: ChoiceOption;
}
interface SessionChoiceDesign { [key: string]: BranchingChoice }

interface SessionAdaptation {
    session_number: number;
    session_status: string;
    cold_open: string | null;
    start_event_id: number | null;
    beat_map: any;
    session_choice_design: SessionChoiceDesign | null;
    choice_consequence_map: any;
    session_close_design: any;
}

interface ActiveDraft {
    id: number;
    type: string;
    status: string;
    source_event_ids: number[] | null;
    beat_type: string | null;
    requires_choice: boolean;
    rewritten_content: string | null;
    created_at: string;
}

interface Story { id: number; title: string; slug: string }
interface Chapter { id: number; position: number; title: string }

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps<{
    story: Story;
    chapter: Chapter;
    prevChapter: { id: number; title: string } | null;
    nextChapter: { id: number; title: string } | null;
    events: Event[];
    sessionAdaptations: Record<number, SessionAdaptation>;
    activeDrafts: ActiveDraft[];
}>();

// ── CSRF helper ────────────────────────────────────────────────────────────
const csrf = (): string =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const apiPost = async (url: string, body: Record<string, unknown> = {}) => {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            Accept: 'application/json',
        },
        body: JSON.stringify(body),
    });
    return res.json();
};

// ── Focus state (single event open for edit) ───────────────────────────────
const focusedEvent = ref<Event | null>(null);

// Batch-select state (checkboxes, for combine/split/reorder)
const selectedIds = ref<number[]>([]);
const isSelected  = (id: number) => selectedIds.value.includes(id);
const toggleSelect = (id: number, e: Event) => {
    e.stopPropagation();
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
};

const focusEvent = (event: Event) => {
    if (focusedEvent.value?.id === event.id) return; // already focused
    focusedEvent.value = event;
    resetEditState(event);
    resetPreview();
    resetSuggestion();
    saveDraftId.value = null;
};

// ── Right panel mode ───────────────────────────────────────────────────────
const rightPanelMode = computed<'event' | 'session' | 'empty'>(() => {
    if (focusedEvent.value) return 'event';
    if (activeSession.value !== null) return 'session';
    return 'empty';
});

// ── Event editor state ─────────────────────────────────────────────────────
const editContent        = ref('');
const editRequiresChoice = ref(true);
const editBeatType       = ref('');
const editDirty          = ref(false);
const saving             = ref(false);
const saveError          = ref<string | null>(null);
const saveDraftId        = ref<number | null>(null);

const resetEditState = (event: Event) => {
    // If there's an existing edit draft for this event, load its content
    const existing = props.activeDrafts.find(
        d => d.type === 'edit' && d.source_event_ids?.includes(event.id)
    );
    editContent.value        = existing?.rewritten_content ?? event.content;
    editRequiresChoice.value = existing?.requires_choice ?? event.requires_choice;
    editBeatType.value       = existing?.beat_type ?? '';
    editDirty.value          = false;
    saveDraftId.value        = existing?.id ?? null;
    saveError.value          = null;
};

watch(editContent, () => { editDirty.value = true; });
watch(editRequiresChoice, () => { editDirty.value = true; });
watch(editBeatType, () => { editDirty.value = true; });

const saveEdit = async () => {
    if (!focusedEvent.value) return;
    saving.value    = true;
    saveError.value = null;

    const url = `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/edit`;
    const data = await apiPost(url, {
        event_id:        focusedEvent.value.id,
        content:         editContent.value,
        requires_choice: editRequiresChoice.value,
        beat_type:       editBeatType.value || null,
    });

    saving.value = false;

    if (data.error) {
        saveError.value = data.error;
    } else {
        saveDraftId.value = data.draft_id;
        editDirty.value   = false;
        // Refresh the activeDrafts list without leaving the page
        router.reload({ only: ['activeDrafts'] });
    }
};

// ── Preview ────────────────────────────────────────────────────────────────
const previewing     = ref(false);
const previewHtml    = ref<string | null>(null);
const previewChoices = ref<string[]>([]);
const previewError   = ref<string | null>(null);

const resetPreview = () => {
    previewHtml.value    = null;
    previewChoices.value = [];
    previewError.value   = null;
};

const runPreview = async () => {
    if (!saveDraftId.value) return;
    previewing.value = true;
    resetPreview();

    const url = `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${saveDraftId.value}/preview`;
    const data = await apiPost(url);

    previewing.value = false;
    if (data.error) previewError.value = data.error;
    else {
        previewHtml.value    = data.response;
        previewChoices.value = data.choices ?? [];
    }
};

// ── Script change impact analysis ──────────────────────────────────────────
interface ImpactAnalysis {
    severity: 'clean' | 'minor' | 'moderate' | 'significant';
    summary: string;
    objectives_needs_update: boolean;
    objectives_revised: string;
    attributes_needs_update: boolean;
    attributes_revised: string[];
    beat_map_needs_update: boolean;
    beat_moment_revised: string;
    beat_type_revised: string;
    choice_design_needs_update: boolean;
    choice_slot_affected: string;
    choice_question_revised: string;
    choice_option_a_revised: string;
    choice_option_b_revised: string;
    choice_option_c_revised: string;
    choice_tracked_dimension: string;
    consequence_map_needs_review: boolean;
    consequence_map_note: string;
    cross_session_concern: boolean;
    cross_session_note: string;
}

const analysing      = ref(false);
const impact         = ref<ImpactAnalysis | null>(null);
const impactError    = ref<string | null>(null);

// Track which sections the writer has accepted
const acceptedSections = reactive<Record<string, boolean>>({
    metadata: false,
    beat_map: false,
    choice_design: false,
});

const resetImpact = () => {
    impact.value    = null;
    impactError.value = null;
    acceptedSections.metadata     = false;
    acceptedSections.beat_map     = false;
    acceptedSections.choice_design = false;
};

const resetSuggestion = () => resetImpact();

const analyseImpact = async () => {
    if (!saveDraftId.value) return;
    analysing.value   = true;
    impact.value      = null;
    impactError.value = null;

    const url = `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${saveDraftId.value}/analyse-impact`;
    const data = await apiPost(url);

    analysing.value = false;
    if (data.error) impactError.value = data.error;
    else impact.value = data.impact;
};

const patchDraft = async (patch: Record<string, unknown>) => {
    if (!saveDraftId.value) return;
    await fetch(
        `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${saveDraftId.value}`,
        {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                Accept: 'application/json',
            },
            body: JSON.stringify(patch),
        }
    );
};

const acceptMetadata = async () => {
    if (!impact.value) return;
    await patchDraft({
        derived_objectives: impact.value.objectives_revised,
        derived_attributes: impact.value.attributes_revised,
    });
    acceptedSections.metadata = true;
};

const acceptBeatMap = async () => {
    if (!impact.value || !focusedEvent.value?.session_number) return;
    const sa          = props.sessionAdaptations[focusedEvent.value.session_number];
    const currentMap  = sa?.beat_map ?? [];
    // Append a structured note — beat_map is an array and we don't know exact index; store as patch note
    await patchDraft({
        adaptation_patch: {
            beat_map_patch: {
                event_position:    focusedEvent.value.position,
                moment_revised:    impact.value.beat_moment_revised,
                beat_type_revised: impact.value.beat_type_revised,
            },
        },
    });
    acceptedSections.beat_map = true;
};

const acceptChoiceDesign = async () => {
    if (!impact.value || !focusedEvent.value?.session_number) return;
    const sa            = props.sessionAdaptations[focusedEvent.value.session_number];
    const currentDesign = sa?.session_choice_design ?? {};
    const slot          = impact.value.choice_slot_affected;

    const updatedDesign = {
        ...currentDesign,
        [slot]: {
            ...(currentDesign[slot] ?? {}),
            what_this_choice_tracks: impact.value.choice_tracked_dimension,
            choice_question:         impact.value.choice_question_revised,
            option_a: { ...(currentDesign[slot]?.option_a ?? {}), text: impact.value.choice_option_a_revised },
            option_b: { ...(currentDesign[slot]?.option_b ?? {}), text: impact.value.choice_option_b_revised },
            option_c: { ...(currentDesign[slot]?.option_c ?? {}), text: impact.value.choice_option_c_revised },
        },
    };

    await patchDraft({ adaptation_patch: { session_choice_design: updatedDesign } });
    acceptedSections.choice_design = true;
};

const severityColor = (s: string) => ({
    clean:       'bg-emerald-900/30 text-emerald-300 border-emerald-700/30',
    minor:       'bg-gray-800 text-gray-300 border-gray-700',
    moderate:    'bg-yellow-900/30 text-yellow-300 border-yellow-700/30',
    significant: 'bg-red-900/30 text-red-300 border-red-700/30',
}[s] ?? 'bg-gray-800 text-gray-300 border-gray-700');

const severityIcon = (s: string) => ({ clean: '✓', minor: '△', moderate: '⚠', significant: '⚡' }[s] ?? '?');

// ── Session adaptation editing ─────────────────────────────────────────────
const sessionNumbers = computed(() =>
    [...new Set(props.events.map(e => e.session_number).filter((n): n is number => n !== null))].sort()
);
const activeSession = ref<number | null>(sessionNumbers.value[0] ?? null);

const sessionAdaptation = computed(() =>
    activeSession.value !== null ? props.sessionAdaptations[activeSession.value] ?? null : null
);

// Cold open editing
const coldOpenEdit  = ref('');
const coldOpenDirty = ref(false);
const savingColdOpen = ref(false);

watch(activeSession, (n) => {
    const sa = n !== null ? props.sessionAdaptations[n] ?? null : null;
    coldOpenEdit.value  = sa?.cold_open ?? '';
    coldOpenDirty.value = false;
    initChoiceEdits(sa?.session_choice_design ?? null);
    adaptationTab.value = 'cold_open';
});

// Choice design editing
const adaptationTab = ref<'cold_open' | 'choices' | 'close'>('cold_open');

interface ChoiceEditState {
    question: string;
    option_a: string;
    option_b: string;
    option_c: string;
    tracked_dimension: string;
}
const choiceEdits  = reactive<Record<string, ChoiceEditState>>({});
const choicesDirty = ref(false);

const initChoiceEdits = (design: SessionChoiceDesign | null) => {
    for (const key of ['branching_choice_1', 'branching_choice_2', 'branching_choice_3']) {
        const slot = design?.[key];
        choiceEdits[key] = {
            question:          slot?.choice_question ?? '',
            option_a:          slot?.option_a?.text ?? '',
            option_b:          slot?.option_b?.text ?? '',
            option_c:          slot?.option_c?.text ?? '',
            tracked_dimension: slot?.what_this_choice_tracks ?? '',
        };
    }
    choicesDirty.value = false;
};

// Initialize on mount
if (activeSession.value !== null) {
    const sa = props.sessionAdaptations[activeSession.value] ?? null;
    coldOpenEdit.value = sa?.cold_open ?? '';
    initChoiceEdits(sa?.session_choice_design ?? null);
}

const saveColdOpen = async () => {
    if (activeSession.value === null) return;
    savingColdOpen.value = true;

    const url = `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/adaptation`;
    const data = await apiPost(url, {
        session_number:   activeSession.value,
        adaptation_patch: { entry_point_diagnosis: { cold_open: coldOpenEdit.value } },
    });

    savingColdOpen.value = false;
    if (!data.error) {
        coldOpenDirty.value = false;
        router.reload({ only: ['activeDrafts'] });
    }
};

const saveChoices = async () => {
    if (activeSession.value === null) return;
    const sa            = sessionAdaptation.value;
    const currentDesign = sa?.session_choice_design ?? {};

    const updatedDesign: SessionChoiceDesign = {};
    for (const key of ['branching_choice_1', 'branching_choice_2', 'branching_choice_3']) {
        const orig = currentDesign[key] ?? {};
        const edit = choiceEdits[key];
        if (!edit || (!edit.question && !edit.option_a)) continue;
        updatedDesign[key] = {
            ...orig,
            what_this_choice_tracks: edit.tracked_dimension,
            choice_question:         edit.question,
            option_a: { ...(orig.option_a ?? {}), text: edit.option_a },
            option_b: { ...(orig.option_b ?? {}), text: edit.option_b },
            option_c: { ...(orig.option_c ?? {}), text: edit.option_c },
        };
    }

    const url  = `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/adaptation`;
    const data = await apiPost(url, {
        session_number:   activeSession.value,
        adaptation_patch: { session_choice_design: updatedDesign },
    });

    if (!data.error) {
        choicesDirty.value = false;
        router.reload({ only: ['activeDrafts'] });
    }
};

// ── Reorder drag-and-drop ──────────────────────────────────────────────────
const reorderMode    = ref(false);
const orderedEvents  = ref<Event[]>([...props.events]);
const draggedIndex   = ref<number | null>(null);

const enableReorder  = () => { orderedEvents.value = [...props.events]; reorderMode.value = true; selectedIds.value = []; };
const cancelReorder  = () => { reorderMode.value = false; };
const onDragStart    = (i: number) => { draggedIndex.value = i; };
const onDragOver     = (e: DragEvent, i: number) => {
    e.preventDefault();
    if (draggedIndex.value === null || draggedIndex.value === i) return;
    const arr = [...orderedEvents.value];
    const [m] = arr.splice(draggedIndex.value, 1);
    arr.splice(i, 0, m);
    orderedEvents.value = arr;
    draggedIndex.value  = i;
};
const onDragEnd = () => { draggedIndex.value = null; };
const submitReorder = () => {
    router.post(`/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/reorder`, {
        event_order: orderedEvents.value.map((e, i) => ({ event_id: e.id, new_position: i + 1 })),
    });
};

// ── Combine / Split (multi-select) ─────────────────────────────────────────
const canCombine = computed(() => selectedIds.value.length >= 2);
const canSplit   = computed(() => selectedIds.value.length === 1);
const combining  = ref(false);
const splitting  = ref(false);

const submitCombine = () => {
    combining.value = true;
    router.post(`/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/combine`,
        { event_ids: selectedIds.value },
        { onFinish: () => { combining.value = false; } }
    );
};
const submitSplit = () => {
    splitting.value = true;
    router.post(`/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/split`,
        { event_id: selectedIds.value[0] },
        { onFinish: () => { splitting.value = false; } }
    );
};

// ── Helpers ────────────────────────────────────────────────────────────────
const draftStatusColor = (s: string) => ({
    draft: 'bg-gray-700 text-gray-300',
    ai_written: 'bg-blue-900 text-blue-300',
    writer_approved: 'bg-yellow-900 text-yellow-300',
    activated: 'bg-emerald-900 text-emerald-300',
}[s] ?? 'bg-gray-700 text-gray-300');

const BEAT_TYPES = ['setup', 'escalation', 'breath', 'twist', 'resolution'];

const choiceSlots = ['branching_choice_1', 'branching_choice_2', 'branching_choice_3'] as const;
const choiceLabel = (key: string) => key.replace('branching_choice_', 'Choice ');

const eventDraftLink = (eventId: number) => {
    const d = props.activeDrafts.find(d => d.source_event_ids?.includes(eventId));
    return d ? `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${d.id}` : null;
};
</script>

<template>
    <div class="flex h-screen flex-col overflow-hidden bg-gray-950 text-white">

        <!-- ── Top bar ──────────────────────────────────────────────────────── -->
        <header class="flex-none border-b border-gray-800 px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 text-sm">
                    <Link :href="`/writer/writer-lab/${story.id}`" class="text-gray-400 hover:text-white transition-colors">
                        {{ story.title }}
                    </Link>
                    <span class="text-gray-700">/</span>
                    <span class="font-medium">{{ chapter.title }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <Link v-if="prevChapter" :href="`/writer/writer-lab/${story.id}/chapters/${prevChapter.id}`"
                        class="rounded px-3 py-1.5 text-gray-400 hover:bg-gray-800 hover:text-white transition-colors">← Prev</Link>
                    <Link v-if="nextChapter" :href="`/writer/writer-lab/${story.id}/chapters/${nextChapter.id}`"
                        class="rounded px-3 py-1.5 text-gray-400 hover:bg-gray-800 hover:text-white transition-colors">Next →</Link>
                    <Link :href="`/writer/writer-lab/${story.id}/versions`"
                        class="rounded px-3 py-1.5 text-gray-500 hover:bg-gray-800 hover:text-gray-300 transition-colors">Versions</Link>
                </div>
            </div>
        </header>

        <!-- ── Toolbar ─────────────────────────────────────────────────────── -->
        <div class="flex-none border-b border-gray-800 bg-gray-900/40 px-6 py-2">
            <div class="flex items-center gap-2 text-sm">
                <template v-if="!reorderMode">
                    <span class="text-xs text-gray-600 mr-1">Batch:</span>
                    <button :disabled="!canCombine || combining"
                        class="rounded-lg px-3 py-1.5 font-medium transition-all disabled:opacity-30"
                        :class="canCombine ? 'bg-primary-600 hover:bg-primary-500 text-white' : 'bg-gray-800 text-gray-500'"
                        @click="submitCombine">
                        <span v-if="combining">Combining…</span>
                        <span v-else>⊕ Combine ({{ selectedIds.length }})</span>
                    </button>
                    <button :disabled="!canSplit || splitting"
                        class="rounded-lg px-3 py-1.5 font-medium transition-all disabled:opacity-30"
                        :class="canSplit ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-800 text-gray-500'"
                        @click="submitSplit">
                        <span v-if="splitting">Splitting…</span>
                        <span v-else>⊘ Split</span>
                    </button>
                    <button v-if="selectedIds.length > 0"
                        class="text-xs text-gray-600 hover:text-gray-400 transition-colors ml-1"
                        @click="selectedIds = []">clear</button>
                    <div class="flex-1"></div>
                    <button class="rounded-lg bg-gray-800 px-3 py-1.5 text-gray-400 hover:bg-gray-700 hover:text-white transition-all"
                        @click="enableReorder">⇅ Reorder</button>
                </template>
                <template v-else>
                    <span class="text-yellow-300 text-xs">Drag events to reorder</span>
                    <div class="flex-1"></div>
                    <button class="rounded-lg bg-primary-600 px-4 py-1.5 font-medium text-white hover:bg-primary-500 transition-all" @click="submitReorder">Save Reorder</button>
                    <button class="rounded-lg bg-gray-800 px-3 py-1.5 text-gray-400 hover:text-white transition-all" @click="cancelReorder">Cancel</button>
                </template>
            </div>
        </div>

        <!-- ── Two-panel body ──────────────────────────────────────────────── -->
        <div class="flex flex-1 overflow-hidden">

            <!-- ── LEFT: Event list ───────────────────────────────────────── -->
            <div class="flex w-2/5 flex-col overflow-y-auto border-r border-gray-800">

                <!-- Reorder mode -->
                <template v-if="reorderMode">
                    <div v-for="(event, index) in orderedEvents" :key="event.id"
                        draggable="true"
                        class="flex cursor-grab items-start gap-3 border-b border-gray-800/50 px-5 py-3 active:cursor-grabbing hover:bg-gray-900/60 transition-colors"
                        :class="draggedIndex === index ? 'bg-gray-800/60 opacity-60' : ''"
                        @dragstart="onDragStart(index)" @dragover="onDragOver($event, index)" @dragend="onDragEnd">
                        <span class="mt-0.5 select-none text-gray-600">⠿</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-xs font-mono text-gray-700">{{ index + 1 }}</span>
                                <span class="text-sm font-medium truncate">{{ event.title }}</span>
                            </div>
                            <p class="text-xs text-gray-600 line-clamp-1">{{ event.content }}</p>
                        </div>
                    </div>
                </template>

                <!-- Normal mode -->
                <template v-else>
                    <div v-for="event in events" :key="event.id"
                        class="group flex items-start gap-3 border-b border-gray-800/50 px-5 py-3 cursor-pointer transition-colors hover:bg-gray-900/50"
                        :class="focusedEvent?.id === event.id ? 'bg-primary-950/30 border-l-2 border-l-primary-500' : ''"
                        @click="focusEvent(event)">

                        <!-- Checkbox (multi-select for batch ops) -->
                        <div class="mt-0.5 flex-none opacity-0 group-hover:opacity-100 transition-opacity"
                            :class="isSelected(event.id) ? '!opacity-100' : ''"
                            @click.stop="toggleSelect(event.id, $event as unknown as Event)">
                            <div class="h-4 w-4 rounded border transition-all"
                                :class="isSelected(event.id) ? 'bg-primary-500 border-primary-500' : 'border-gray-700'">
                                <svg v-if="isSelected(event.id)" viewBox="0 0 10 10" class="h-full w-full p-0.5 text-white fill-current">
                                    <path d="M1.5 5L4 7.5L8.5 2.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" />
                                </svg>
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 mb-0.5 flex-wrap">
                                <span class="text-xs font-mono text-gray-700">{{ event.position }}</span>
                                <span class="text-sm font-medium leading-snug">{{ event.title }}</span>
                                <template v-if="event.session_number !== null">
                                    <span class="rounded-full bg-gray-800 px-1.5 py-0.5 text-xs text-gray-500">S{{ event.session_number }}</span>
                                </template>
                                <span v-if="!event.requires_choice" class="rounded-full bg-blue-900/40 px-1.5 py-0.5 text-xs text-blue-400">flow</span>
                                <!-- Draft indicator -->
                                <span v-if="activeDrafts.some(d => d.source_event_ids?.includes(event.id))"
                                    class="rounded-full bg-yellow-900/40 px-1.5 py-0.5 text-xs text-yellow-400">draft</span>
                            </div>
                            <p class="text-xs text-gray-600 line-clamp-2">{{ event.content }}</p>
                        </div>
                    </div>
                </template>
            </div>

            <!-- ── RIGHT: Context-sensitive editor panel ──────────────────── -->
            <div class="flex w-3/5 flex-col overflow-y-auto">

                <!-- ─── MODE: Event editor ────────────────────────────────── -->
                <template v-if="rightPanelMode === 'event' && focusedEvent">
                    <div class="flex-1 p-6 space-y-5">

                        <!-- Event header -->
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-mono text-gray-600">{{ focusedEvent.position }}</span>
                            <h2 class="text-base font-semibold leading-tight">{{ focusedEvent.title }}</h2>
                            <template v-if="focusedEvent.session_number !== null">
                                <span class="rounded-full bg-gray-800 px-2 py-0.5 text-xs text-gray-400">S{{ focusedEvent.session_number }}</span>
                            </template>
                        </div>

                        <!-- Content editor -->
                        <div>
                            <label class="mb-1.5 block text-xs uppercase tracking-widest text-gray-500">Event Content</label>
                            <textarea
                                v-model="editContent"
                                rows="10"
                                class="w-full rounded-xl border border-gray-700 bg-gray-900 px-4 py-3 text-sm text-gray-200 leading-relaxed focus:border-primary-500 focus:outline-none resize-none"
                                placeholder="Edit the event's screenplay content…"
                            ></textarea>
                        </div>

                        <!-- Controls row -->
                        <div class="flex items-center gap-6 flex-wrap">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <div class="relative h-5 w-9 rounded-full transition-colors duration-200"
                                    :class="editRequiresChoice ? 'bg-primary-500' : 'bg-gray-700'"
                                    @click="editRequiresChoice = !editRequiresChoice">
                                    <div class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform duration-200"
                                        :class="editRequiresChoice ? 'translate-x-4' : 'translate-x-0.5'"></div>
                                </div>
                                <span class="text-sm text-gray-300">Requires player choice</span>
                            </label>

                            <div class="flex items-center gap-2">
                                <label class="text-xs text-gray-500">Beat</label>
                                <select v-model="editBeatType"
                                    class="rounded-lg border border-gray-700 bg-gray-900 px-2 py-1 text-sm text-gray-300 focus:border-primary-500 focus:outline-none">
                                    <option value="">—</option>
                                    <option v-for="bt in BEAT_TYPES" :key="bt" :value="bt">{{ bt }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Save + Action row -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <button
                                class="rounded-lg px-4 py-2 text-sm font-medium transition-all disabled:opacity-40"
                                :class="editDirty ? 'bg-primary-600 hover:bg-primary-500 text-white' : 'bg-gray-800 text-gray-400'"
                                :disabled="saving || !editDirty"
                                @click="saveEdit">
                                <span v-if="saving">Saving…</span>
                                <span v-else-if="saveDraftId && !editDirty">✓ Saved as Draft #{{ saveDraftId }}</span>
                                <span v-else>Save as Draft</span>
                            </button>

                            <button v-if="saveDraftId"
                                class="rounded-lg bg-gray-800 px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-all disabled:opacity-40"
                                :disabled="previewing"
                                @click="runPreview">
                                <span v-if="previewing">Generating…</span>
                                <span v-else>▶ Preview Narration</span>
                            </button>

                            <button v-if="saveDraftId"
                                class="rounded-lg border border-primary-700/50 bg-primary-950/30 px-4 py-2 text-sm text-primary-300 hover:bg-primary-900/40 transition-all disabled:opacity-40"
                                :disabled="analysing"
                                @click="analyseImpact">
                                <span v-if="analysing">Analysing layers…</span>
                                <span v-else>✦ Analyse Script Changes</span>
                            </button>

                            <Link v-if="saveDraftId"
                                :href="`/writer/writer-lab/${story.id}/chapters/${chapter.id}/drafts/${saveDraftId}`"
                                class="text-xs text-gray-500 hover:text-gray-300 transition-colors ml-auto">
                                Open full draft →
                            </Link>
                        </div>

                        <p v-if="saveError" class="text-xs text-red-400">{{ saveError }}</p>

                        <!-- ── Preview result ───────────────────────────────── -->
                        <div v-if="previewHtml || previewError" class="rounded-xl border border-gray-700 bg-gray-900 p-5">
                            <h3 class="mb-3 text-xs uppercase tracking-widest text-gray-500">Narrator Preview</h3>
                            <div v-if="previewError" class="text-sm text-red-400">{{ previewError }}</div>
                            <template v-else>
                                <div class="prose prose-invert prose-sm max-w-none" v-html="previewHtml"></div>
                                <div v-if="previewChoices.length" class="mt-4 grid grid-cols-3 gap-2">
                                    <div v-for="(c, i) in previewChoices" :key="i"
                                        class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-xs text-gray-300">
                                        {{ i + 1 }}. {{ c }}
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- ── Choice alignment suggestion ─────────────────── -->
                        <!-- ── Impact analysis error ──────────────────────── -->
                        <div v-if="impactError" class="rounded-xl border border-red-800/40 bg-red-950/20 p-4 text-sm text-red-400">
                            {{ impactError }}
                        </div>

                        <!-- ── Full impact analysis panel ──────────────────── -->
                        <div v-if="impact" class="rounded-xl border border-gray-700 bg-gray-900/60 overflow-hidden">

                            <!-- Header row -->
                            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-700/50">
                                <h3 class="text-xs uppercase tracking-widest text-gray-400">✦ Script Change Impact</h3>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                        :class="severityColor(impact.severity)">
                                        {{ severityIcon(impact.severity) }} {{ impact.severity }}
                                    </span>
                                    <button class="text-xs text-gray-600 hover:text-gray-400 transition-colors" @click="impact = null">dismiss</button>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div class="px-5 py-3 border-b border-gray-800/50">
                                <p class="text-sm text-gray-300 leading-relaxed">{{ impact.summary }}</p>
                            </div>

                            <!-- ── Section: Event Metadata ──────────────────── -->
                            <div v-if="impact.objectives_needs_update || impact.attributes_needs_update"
                                class="border-b border-gray-800/50 px-5 py-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-semibold uppercase tracking-widest text-blue-400">Event Metadata</h4>
                                    <button v-if="!acceptedSections.metadata"
                                        class="rounded-lg bg-blue-900/40 px-3 py-1 text-xs text-blue-300 hover:bg-blue-800/50 transition-all border border-blue-700/30"
                                        @click="acceptMetadata">Accept</button>
                                    <span v-else class="text-xs text-emerald-400">✓ Applied to draft</span>
                                </div>

                                <div v-if="impact.objectives_needs_update" class="space-y-1">
                                    <p class="text-xs text-gray-500">Objectives</p>
                                    <p class="text-sm text-gray-200 rounded-lg bg-gray-800/50 px-3 py-2">{{ impact.objectives_revised }}</p>
                                </div>

                                <div v-if="impact.attributes_needs_update" class="space-y-1">
                                    <p class="text-xs text-gray-500">Attributes</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="attr in impact.attributes_revised" :key="attr"
                                            class="rounded-full bg-gray-800 border border-gray-700 px-2.5 py-0.5 text-xs text-gray-300">
                                            {{ attr }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Section: Beat Map ────────────────────────── -->
                            <div v-if="impact.beat_map_needs_update"
                                class="border-b border-gray-800/50 px-5 py-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-semibold uppercase tracking-widest text-orange-400">Beat Map</h4>
                                    <button v-if="!acceptedSections.beat_map"
                                        class="rounded-lg bg-orange-900/30 px-3 py-1 text-xs text-orange-300 hover:bg-orange-800/40 transition-all border border-orange-700/30"
                                        @click="acceptBeatMap">Accept</button>
                                    <span v-else class="text-xs text-emerald-400">✓ Applied to draft</span>
                                </div>
                                <div class="rounded-lg bg-gray-800/50 px-3 py-2 space-y-1">
                                    <p class="text-sm text-gray-200">{{ impact.beat_moment_revised }}</p>
                                    <p class="text-xs text-gray-500">Beat type → <span class="text-orange-300">{{ impact.beat_type_revised }}</span></p>
                                </div>
                            </div>

                            <!-- ── Section: Choice Design ───────────────────── -->
                            <div v-if="impact.choice_design_needs_update && impact.choice_slot_affected !== 'none'"
                                class="border-b border-gray-800/50 px-5 py-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-xs font-semibold uppercase tracking-widest text-primary-400">Choice Design</h4>
                                        <span class="text-xs text-gray-600">{{ choiceLabel(impact.choice_slot_affected) }} · {{ impact.choice_tracked_dimension }}</span>
                                    </div>
                                    <button v-if="!acceptedSections.choice_design"
                                        class="rounded-lg bg-primary-900/40 px-3 py-1 text-xs text-primary-300 hover:bg-primary-800/50 transition-all border border-primary-700/30"
                                        @click="acceptChoiceDesign">Accept</button>
                                    <span v-else class="text-xs text-emerald-400">✓ Applied to draft</span>
                                </div>
                                <div class="rounded-lg bg-gray-800/50 px-3 py-2.5 space-y-2">
                                    <p class="font-medium text-sm text-gray-200">{{ impact.choice_question_revised }}</p>
                                    <p class="text-xs text-gray-400">A: {{ impact.choice_option_a_revised }}</p>
                                    <p class="text-xs text-gray-400">B: {{ impact.choice_option_b_revised }}</p>
                                    <p class="text-xs text-gray-400">C: {{ impact.choice_option_c_revised }}</p>
                                </div>
                            </div>

                            <!-- ── Section: Consequence map flag (read-only) ── -->
                            <div v-if="impact.consequence_map_needs_review"
                                class="border-b border-gray-800/50 px-5 py-3">
                                <div class="flex items-start gap-2">
                                    <span class="text-yellow-500 mt-0.5 flex-none">⚠</span>
                                    <div>
                                        <p class="text-xs text-yellow-300 font-medium mb-0.5">Consequence Map — manual review needed</p>
                                        <p class="text-xs text-gray-400">{{ impact.consequence_map_note }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Section: Cross-session concern (read-only) ─ -->
                            <div v-if="impact.cross_session_concern" class="px-5 py-3">
                                <div class="flex items-start gap-2">
                                    <span class="text-red-400 mt-0.5 flex-none">⚡</span>
                                    <div>
                                        <p class="text-xs text-red-300 font-medium mb-0.5">Cross-session anchor at risk</p>
                                        <p class="text-xs text-gray-400">{{ impact.cross_session_note }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Clean result -->
                            <div v-if="impact.severity === 'clean'" class="px-5 py-4 text-center">
                                <p class="text-sm text-emerald-400">✓ All adaptation layers are still accurate. No changes needed.</p>
                            </div>

                        </div>

                    </div>
                </template>

                <!-- ─── MODE: Session adaptation cards ────────────────────── -->
                <template v-else-if="rightPanelMode === 'session'">
                    <!-- Session tabs -->
                    <div v-if="sessionNumbers.length > 0" class="flex-none border-b border-gray-800 px-5 pt-4 pb-0 flex gap-1">
                        <button v-for="n in sessionNumbers" :key="n"
                            class="rounded-t-lg px-4 py-2 text-xs font-medium transition-all border-b-2"
                            :class="activeSession === n
                                ? 'border-primary-500 text-white bg-gray-900'
                                : 'border-transparent text-gray-500 hover:text-gray-300 hover:bg-gray-900/50'"
                            @click="activeSession = n">
                            Session {{ n }}
                        </button>
                    </div>

                    <div v-if="sessionAdaptation" class="flex-1 p-5">
                        <!-- Sub-tabs: Cold Open | Choices | Close -->
                        <div class="flex gap-1 mb-5">
                            <button v-for="tab in (['cold_open', 'choices', 'close'] as const)" :key="tab"
                                class="rounded-lg px-3 py-1.5 text-xs transition-all"
                                :class="adaptationTab === tab
                                    ? 'bg-gray-700 text-white'
                                    : 'text-gray-500 hover:bg-gray-900 hover:text-gray-300'"
                                @click="adaptationTab = tab">
                                {{ tab === 'cold_open' ? 'Cold Open' : tab === 'choices' ? 'Choice Design' : 'Session Close' }}
                            </button>
                        </div>

                        <!-- Cold Open tab -->
                        <template v-if="adaptationTab === 'cold_open'">
                            <div class="space-y-3">
                                <label class="block text-xs uppercase tracking-widest text-gray-500">Cold Open</label>
                                <p class="text-xs text-gray-600">The authored opening beat the narrator delivers verbatim on session start.</p>
                                <textarea
                                    v-model="coldOpenEdit"
                                    rows="10"
                                    class="w-full rounded-xl border border-gray-700 bg-gray-900 px-4 py-3 text-sm text-gray-200 leading-relaxed focus:border-primary-500 focus:outline-none resize-none"
                                    @input="coldOpenDirty = true"
                                ></textarea>
                                <button
                                    class="rounded-lg px-4 py-2 text-sm font-medium transition-all disabled:opacity-40"
                                    :class="coldOpenDirty ? 'bg-primary-600 hover:bg-primary-500 text-white' : 'bg-gray-800 text-gray-500'"
                                    :disabled="!coldOpenDirty || savingColdOpen"
                                    @click="saveColdOpen">
                                    <span v-if="savingColdOpen">Saving…</span>
                                    <span v-else>Save Cold Open as Draft</span>
                                </button>
                            </div>
                        </template>

                        <!-- Choice Design tab -->
                        <template v-else-if="adaptationTab === 'choices'">
                            <div class="space-y-5">
                                <div v-for="slot in choiceSlots" :key="slot"
                                    class="rounded-xl border border-gray-800 bg-gray-900 p-5 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-xs uppercase tracking-widest text-gray-500">{{ choiceLabel(slot) }}</h3>
                                        <span class="text-xs text-gray-600">{{ sessionAdaptation.session_choice_design?.[slot]?.choice_id ?? '' }}</span>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Tracked Dimension</label>
                                        <input v-model="choiceEdits[slot].tracked_dimension" type="text"
                                            class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-1.5 text-sm text-gray-300 focus:border-primary-500 focus:outline-none"
                                            placeholder="e.g. impulse_vs_deliberation"
                                            @input="choicesDirty = true" />
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Choice Question</label>
                                        <input v-model="choiceEdits[slot].question" type="text"
                                            class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-1.5 text-sm text-gray-300 focus:border-primary-500 focus:outline-none"
                                            placeholder="How do you…?"
                                            @input="choicesDirty = true" />
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        <div v-for="opt in ['option_a', 'option_b', 'option_c'] as const" :key="opt">
                                            <label class="mb-1 block text-xs text-gray-600">{{ opt.replace('_', ' ').toUpperCase() }}</label>
                                            <textarea v-model="choiceEdits[slot][opt]" rows="3"
                                                class="w-full rounded-lg border border-gray-700 bg-gray-950 px-2 py-1.5 text-xs text-gray-300 focus:border-primary-500 focus:outline-none resize-none"
                                                @input="choicesDirty = true"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    class="rounded-lg px-4 py-2 text-sm font-medium transition-all disabled:opacity-40"
                                    :class="choicesDirty ? 'bg-primary-600 hover:bg-primary-500 text-white' : 'bg-gray-800 text-gray-500'"
                                    :disabled="!choicesDirty"
                                    @click="saveChoices">
                                    Save Choice Design as Draft
                                </button>
                            </div>
                        </template>

                        <!-- Session Close tab (read-only for now) -->
                        <template v-else-if="adaptationTab === 'close'">
                            <div class="space-y-3">
                                <p class="text-xs text-gray-500">Session close design is read-only. It contains the authored hook transition and session-end choice.</p>
                                <pre v-if="sessionAdaptation.session_close_design"
                                    class="rounded-xl bg-gray-900 p-4 text-xs text-gray-400 overflow-auto max-h-96">{{ JSON.stringify(sessionAdaptation.session_close_design, null, 2) }}</pre>
                                <p v-else class="text-gray-600 text-sm">No session close design yet.</p>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- ─── MODE: Empty ────────────────────────────────────────── -->
                <template v-else>
                    <div class="flex flex-1 items-center justify-center text-center p-10">
                        <div class="space-y-2">
                            <p class="text-gray-500 text-sm">Click any event to edit it</p>
                            <p class="text-gray-700 text-xs">or select a session tab above to edit the cold open and choice design</p>
                        </div>
                    </div>
                    <!-- Session tabs still visible at top when no event focused -->
                    <div v-if="sessionNumbers.length > 0 && rightPanelMode === 'empty'"
                        class="absolute bottom-0 left-2/5 right-0 border-t border-gray-800 px-5 py-3 flex gap-1 bg-gray-950">
                        <button v-for="n in sessionNumbers" :key="n"
                            class="rounded-lg px-3 py-1.5 text-xs transition-all"
                            :class="'text-gray-400 hover:bg-gray-800 hover:text-white'"
                            @click="activeSession = n; focusedEvent = null">
                            Session {{ n }}
                        </button>
                    </div>
                </template>

                <!-- ── Active drafts bar (always visible at bottom of right panel) -->
                <div v-if="activeDrafts.length > 0" class="flex-none border-t border-gray-800 bg-gray-900/30 px-5 py-3">
                    <div class="flex items-center gap-2 overflow-x-auto">
                        <span class="flex-none text-xs text-gray-600">Drafts:</span>
                        <Link v-for="draft in activeDrafts" :key="draft.id"
                            :href="`/writer/writer-lab/${story.id}/chapters/${chapter.id}/drafts/${draft.id}`"
                            class="flex flex-none items-center gap-1.5 rounded-lg bg-gray-900 border border-gray-800 px-3 py-1 text-xs hover:border-gray-600 transition-all">
                            <span class="text-gray-400">{{ draft.type }}</span>
                            <span :class="['rounded px-1.5 py-0.5 text-xs', draftStatusColor(draft.status)]">
                                {{ draft.status.replace('_', ' ') }}
                            </span>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.prose :deep(p) { margin-bottom: 0.75rem; }
</style>
