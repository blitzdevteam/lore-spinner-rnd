<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const flashError = computed<string | null>(() => {
    const errs = (usePage().props as any).flash?.error as string[] | undefined;
    return (errs && errs.length > 0) ? errs[0] : null;
});
import PlaygroundDrawer from './components/PlaygroundDrawer.vue';
import SuggestionPills from './components/SuggestionPills.vue';
import HelpHint from './components/HelpHint.vue';
import NotesPanel from './components/NotesPanel.vue';
import { wlTrace } from '@/lib/wlTrace';

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
    // Extracted from session_architecture.beat_map via best text-overlap match
    beat_type: string | null;
    beat_moment: string | null;
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
    auth?: { writer?: { name?: string } | null };
}>();

// ── Notes drawer ───────────────────────────────────────────────────────────
const notesOpen = ref(false);
const openNotes = () => {
    notesOpen.value = true;
    wlTrace('chapter.notes.open', { chapter_id: props.chapter.id });
};
const closeNotes = () => { notesOpen.value = false; };

// ── CSRF helper ────────────────────────────────────────────────────────────
const xsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const apiPost = async (url: string, body: Record<string, unknown> = {}) => {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
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
const editObjectives     = ref('');
const editAttributes     = ref<string[]>([]);
const editDirty          = ref(false);
const saving             = ref(false);
const saveError          = ref<string | null>(null);
const saveDraftId        = ref<number | null>(null);

// The content as it was when the event was first focused (before any edits).
// "Analyse Changes" is only meaningful — and only costs money — when the
// script itself has been rewritten.  Other field edits (objectives, attributes,
// choice slots) are direct and need no AI.
const originalContent = ref('');
const scriptRewritten = computed(() =>
    editContent.value.trim() !== originalContent.value.trim()
);

// ── Per-field three-state AI suggestion tracking ──────────────────────────
//   clean    — no AI suggestion, field is in its baseline state
//   pending  — AI has filled this field with a new value; writer hasn't acted
//   accepted — writer clicked Accept; suggestion is now part of the edit
// A pending suggestion stashes the original so Undo restores it.
type AiState = 'pending' | 'accepted';
interface AiSuggestion { original: unknown; suggested: unknown; status: AiState }
const aiSuggestions = reactive<Record<string, AiSuggestion>>({});

const aiState = (key: string): 'clean' | AiState =>
    aiSuggestions[key]?.status ?? 'clean';

const aiBorderClass = (key: string, fallback = 'border-gray-700 focus:border-primary-500'): string => {
    const s = aiState(key);
    if (s === 'pending')  return 'border-sky-500/70 ring-1 ring-sky-500/30';
    if (s === 'accepted') return 'border-emerald-500/60 ring-1 ring-emerald-500/20';
    return fallback;
};

const acceptSuggestion = (key: string) => {
    const s = aiSuggestions[key];
    if (!s || s.status === 'accepted') return;
    s.status = 'accepted';
    editDirty.value = true;
    wlTrace('analyse.suggestion.accept', { key });
};

const undoSuggestion = (key: string) => {
    const s = aiSuggestions[key];
    if (!s) return;
    // Restore the original to whichever underlying ref this key points to
    if (key === 'content')                  editContent.value      = s.original as string;
    else if (key === 'objectives')          editObjectives.value   = s.original as string;
    else if (key === 'attributes')          editAttributes.value   = [...(s.original as string[])];
    else if (key === 'beat_type')           editBeatType.value     = s.original as string;
    else if (key === 'beat_moment')         editBeatMoment.value   = s.original as string;
    else if (key.startsWith('choice_'))     editChoiceSlots[key.replace('choice_', '')] = s.original as SlotEdit;
    else if (key.startsWith('consequence_')) editConsequences[key.replace('consequence_', '')] = s.original as ConsequenceEdit;
    else if (key === 'cross_session_seed')  editCrossSessionSeed.value = s.original as string;
    delete aiSuggestions[key];
    wlTrace('analyse.suggestion.undo', { key });
};

const recordSuggestion = (key: string, original: unknown, suggested: unknown) => {
    aiSuggestions[key] = { original: clone(original), suggested: clone(suggested), status: 'pending' };
};

const clone = <T>(v: T): T => {
    if (v === null || v === undefined) return v;
    if (typeof v !== 'object') return v;
    return JSON.parse(JSON.stringify(v)) as T;
};

// Inline attribute tag editing
const attrInput = ref('');
const addAttribute = () => {
    const v = attrInput.value.trim();
    if (v && !editAttributes.value.includes(v)) {
        editAttributes.value.push(v);
        editDirty.value = true;
    }
    attrInput.value = '';
};
const removeAttribute = (i: number) => {
    editAttributes.value.splice(i, 1);
    editDirty.value = true;
};

// Inline choice design editing for the event's session
// Keyed by slot name: branching_choice_1 / _2 / _3
interface SlotEdit {
    question: string;
    option_a: string;
    option_b: string;
    option_c: string;
    tracked_dimension: string;
}
const editChoiceSlots = reactive<Record<string, SlotEdit>>({});
const choiceSlotsDirty = ref(false);

// Beat-moment text (editorial description) — drawn from session_architecture.beat_map
const editBeatMoment = ref<string>('');

// Per-option consequence override for the slot AI flagged; not persisted unless edited.
interface ConsequenceEdit {
    option_a: string;
    option_b: string;
    option_c: string;
}
const editConsequences = reactive<Record<string, ConsequenceEdit>>({});
const consequencesDirty = ref(false);

// Optional revised "seed for next session" surfaced by analyse-impact
const editCrossSessionSeed = ref<string>('');
const editCrossSessionTarget = ref<number | null>(null);
const crossSessionDirty = ref(false);

const initChoiceSlots = (sessionNum: number | null) => {
    for (const slot of choiceSlots) {
        const s = sessionNum !== null
            ? props.sessionAdaptations[sessionNum]?.session_choice_design?.[slot]
            : undefined;
        editChoiceSlots[slot] = {
            question:          s?.choice_question ?? '',
            option_a:          s?.option_a?.text ?? '',
            option_b:          s?.option_b?.text ?? '',
            option_c:          s?.option_c?.text ?? '',
            tracked_dimension: s?.what_this_choice_tracks ?? '',
        };
    }
    choiceSlotsDirty.value = false;
};

const resetEditState = (event: Event) => {
    const existing = props.activeDrafts.find(
        d => d.type === 'edit' && d.source_event_ids?.includes(event.id)
    );
    const baseContent = existing?.rewritten_content ?? event.content;
    editContent.value        = baseContent;
    originalContent.value    = baseContent; // snapshot for scriptRewritten comparison
    editRequiresChoice.value = existing?.requires_choice ?? event.requires_choice;
    // Pre-fill beat type: prefer an existing draft override, otherwise use the
    // beat_type derived from the session_architecture.beat_map match for this event.
    editBeatType.value       = existing?.beat_type ?? event.beat_type ?? '';
    editObjectives.value     = (existing?.derived_objectives as string | null) ?? event.objectives ?? '';
    editAttributes.value     = [...((existing?.derived_attributes as string[] | null) ?? (event.attributes as string[] | null) ?? [])];
    editBeatMoment.value     = event.beat_moment ?? '';
    editDirty.value          = false;
    saveDraftId.value        = existing?.id ?? null;
    saveError.value          = null;
    Object.keys(aiSuggestions).forEach(k => { delete aiSuggestions[k]; });
    Object.keys(editConsequences).forEach(k => { delete editConsequences[k]; });
    consequencesDirty.value  = false;
    editCrossSessionSeed.value = '';
    editCrossSessionTarget.value = null;
    crossSessionDirty.value    = false;

    // Load choice design for this event's session
    initChoiceSlots(event.session_number);

    // If existing draft had an adaptation_patch with session_choice_design, overlay that
    const draftPatch = (existing?.adaptation_patch as Record<string, any> | null);
    if (draftPatch?.session_choice_design) {
        for (const slot of choiceSlots) {
            const p = draftPatch.session_choice_design[slot];
            if (p) {
                editChoiceSlots[slot] = {
                    question:          p.choice_question ?? editChoiceSlots[slot].question,
                    option_a:          p.option_a?.text ?? editChoiceSlots[slot].option_a,
                    option_b:          p.option_b?.text ?? editChoiceSlots[slot].option_b,
                    option_c:          p.option_c?.text ?? editChoiceSlots[slot].option_c,
                    tracked_dimension: p.what_this_choice_tracks ?? editChoiceSlots[slot].tracked_dimension,
                };
            }
        }
        choiceSlotsDirty.value = false;
    }
};

watch(editContent, () => {
    editDirty.value = true;
    // If writer manually edits a pending suggestion, drop the pending state
    if (aiSuggestions.content?.status === 'pending') delete aiSuggestions.content;
});
watch(editRequiresChoice, () => { editDirty.value = true; });
watch(editBeatType, () => {
    editDirty.value = true;
    if (aiSuggestions.beat_type?.status === 'pending') delete aiSuggestions.beat_type;
});
watch(editObjectives, () => {
    editDirty.value = true;
    if (aiSuggestions.objectives?.status === 'pending') delete aiSuggestions.objectives;
});
watch(editBeatMoment, () => {
    if (aiSuggestions.beat_moment?.status === 'pending') delete aiSuggestions.beat_moment;
});

const buildAdaptationPatch = () => {
    // Build a full updated session_choice_design from editChoiceSlots, plus any
    // edits to choice_consequence_map. Both columns live on session_adaptations
    // and the back-end activate() merges them recursively when the draft fires.
    const session    = focusedEvent.value?.session_number ?? null;
    const sa         = session !== null ? props.sessionAdaptations[session] ?? null : null;
    const original   = sa?.session_choice_design ?? {};
    const patch: Record<string, any> = {};

    if (choiceSlotsDirty.value) {
        const updated: Record<string, any> = {};
        for (const slot of choiceSlots) {
            const orig = original[slot] ?? {};
            const edit = editChoiceSlots[slot];
            if (!edit) continue;
            updated[slot] = {
                ...orig,
                what_this_choice_tracks: edit.tracked_dimension,
                choice_question:         edit.question,
                option_a: { ...(orig.option_a ?? {}), text: edit.option_a },
                option_b: { ...(orig.option_b ?? {}), text: edit.option_b },
                option_c: { ...(orig.option_c ?? {}), text: edit.option_c },
            };
        }
        patch.session_choice_design = updated;
    }

    if (consequencesDirty.value) {
        const originalCmap = sa?.choice_consequence_map ?? {};
        const updatedCmap: Record<string, any> = {};
        for (const slot of choiceSlots) {
            const edit = editConsequences[slot];
            if (!edit) continue;
            const orig = (originalCmap as any)[slot] ?? {};
            updatedCmap[slot] = {
                ...orig,
                option_a: { ...(orig.option_a ?? {}), consequence: edit.option_a },
                option_b: { ...(orig.option_b ?? {}), consequence: edit.option_b },
                option_c: { ...(orig.option_c ?? {}), consequence: edit.option_c },
            };
        }
        patch.choice_consequence_map = updatedCmap;
    }

    return patch;
};

// Legacy alias kept so previous callers compile
const buildChoicePatch = buildAdaptationPatch;

const saveEdit = async () => {
    if (!focusedEvent.value) return;
    saving.value    = true;
    saveError.value = null;

    const payload: Record<string, unknown> = {
        event_id:        focusedEvent.value.id,
        content:         editContent.value,
        requires_choice: editRequiresChoice.value,
        beat_type:       editBeatType.value || null,
        objectives:      editObjectives.value || null,
        attributes:      editAttributes.value,
    };

    if (choiceSlotsDirty.value || consequencesDirty.value) {
        const patch = buildAdaptationPatch();
        if (Object.keys(patch).length > 0) {
            payload.adaptation_patch = patch;
        }
    }

    wlTrace('chapter.save_edit', {
        event_id: focusedEvent.value.id,
        content_dirty:  editContent.value !== originalContent.value,
        choice_dirty:   choiceSlotsDirty.value,
        cons_dirty:     consequencesDirty.value,
        cross_seed_dirty: crossSessionDirty.value,
        accepted_keys: Object.keys(aiSuggestions).filter(k => aiSuggestions[k].status === 'accepted'),
    });

    const url = `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/edit`;
    const data = await apiPost(url, payload);

    saving.value = false;

    if (data.error) {
        saveError.value = data.error;
        return;
    }
    saveDraftId.value = data.draft_id;
    editDirty.value   = false;

    // If a cross-session seed was accepted, persist it onto the target session
    // as a separate adaptation-only draft so it joins the activate cycle there.
    if (crossSessionDirty.value
        && editCrossSessionSeed.value
        && editCrossSessionTarget.value !== null
        && aiSuggestions.cross_session_seed?.status !== 'pending') {
        await apiPost(`/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/adaptation`, {
            session_number:   editCrossSessionTarget.value,
            adaptation_patch: {
                session_architecture: {
                    next_session_awareness: {
                        seed_for_next_session: editCrossSessionSeed.value,
                    },
                },
            },
        });
        crossSessionDirty.value = false;
        wlTrace('chapter.cross_session_seed.persisted', {
            target_session: editCrossSessionTarget.value,
        });
    }

    // Refresh the activeDrafts list without leaving the page
    router.reload({ only: ['activeDrafts'] });
};

// ── Preview (now routes into the Playground drawer for the focused event) ──
// The old standalone preview panel below the editor is gone — Playground IS
// the preview experience because it uses the exact runtime narrator and
// supports multi-turn interaction.
const previewing = ref(false);
const resetPreview = () => { /* no-op kept so callers don't have to change */ };
const runPreview = () => {
    if (!focusedEvent.value) return;
    playgroundEventIds.value = [focusedEvent.value.id];
    playgroundOpen.value     = true;
    wlTrace('chapter.preview.open_via_playground', { event_id: focusedEvent.value.id });
};

// ── Script change impact analysis → fills form fields in place ─────────────
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
    consequence_option_a_revised: string;
    consequence_option_b_revised: string;
    consequence_option_c_revised: string;
    cross_session_concern: boolean;
    cross_session_note: string;
    cross_session_seed_revised: string;
    cross_session_target_session: number;
}

const analysing          = ref(false);
const impactError        = ref<string | null>(null);
const impactSummary      = ref<string | null>(null);
const impactSeverity     = ref<string | null>(null);
const impactWarnings     = ref<{ type: 'consequence' | 'cross_session'; note: string }[]>([]);

const resetImpact = () => {
    impactError.value    = null;
    impactSummary.value  = null;
    impactSeverity.value = null;
    impactWarnings.value = [];
};
const resetSuggestion = () => resetImpact();

const analyseImpact = async () => {
    if (!focusedEvent.value || !scriptRewritten.value) return;

    analysing.value = true;
    resetImpact();

    // Auto-save the draft first if not yet saved (or if content changed since last save)
    // This ensures the backend has the latest content + previous_state for diff
    if (!saveDraftId.value || editDirty.value) {
        await saveEdit();
        if (saveError.value) { analysing.value = false; return; }
    }

    const url = `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${saveDraftId.value}/analyse-impact`;
    const data = await apiPost(url);

    analysing.value = false;

    if (data.error) { impactError.value = data.error; return; }

    const r: ImpactAnalysis = data.impact;
    impactSummary.value  = r.summary;
    impactSeverity.value = r.severity;

    // ── Fill only the fields the AI flagged as stale ───────────────────────
    // Each fill stores the writer's existing value as `original` so Undo works.
    // Status starts as 'pending' — writer must Accept or Undo each field.

    if (r.objectives_needs_update && r.objectives_revised) {
        recordSuggestion('objectives', editObjectives.value, r.objectives_revised);
        editObjectives.value = r.objectives_revised;
        editDirty.value      = true;
    }
    if (r.attributes_needs_update && r.attributes_revised?.length) {
        recordSuggestion('attributes', [...editAttributes.value], [...r.attributes_revised]);
        editAttributes.value = [...r.attributes_revised];
        editDirty.value      = true;
    }
    if (r.beat_map_needs_update && r.beat_type_revised) {
        recordSuggestion('beat_type', editBeatType.value, r.beat_type_revised.toLowerCase());
        editBeatType.value = r.beat_type_revised.toLowerCase();
        editDirty.value    = true;
    }
    if (r.beat_map_needs_update && r.beat_moment_revised) {
        recordSuggestion('beat_moment', editBeatMoment.value, r.beat_moment_revised);
        editBeatMoment.value = r.beat_moment_revised;
    }

    // Choice design: ONLY the single slot whose source_moment ties to this event.
    if (r.choice_design_needs_update && r.choice_slot_affected && r.choice_slot_affected !== 'none') {
        const slot = r.choice_slot_affected;
        const before = clone(editChoiceSlots[slot]) ?? {
            question: '', option_a: '', option_b: '', option_c: '', tracked_dimension: '',
        };
        const after: SlotEdit = {
            question:          r.choice_question_revised,
            option_a:          r.choice_option_a_revised,
            option_b:          r.choice_option_b_revised,
            option_c:          r.choice_option_c_revised,
            tracked_dimension: r.choice_tracked_dimension || before.tracked_dimension || '',
        };
        recordSuggestion(`choice_${slot}`, before, after);
        editChoiceSlots[slot]  = after;
        choiceSlotsDirty.value = true;
        editDirty.value        = true;

        // Consequence per option — only for the same slot, only if review needed
        if (r.consequence_map_needs_review && (
            r.consequence_option_a_revised || r.consequence_option_b_revised || r.consequence_option_c_revised
        )) {
            const consBefore: ConsequenceEdit = clone(editConsequences[slot]) ?? { option_a: '', option_b: '', option_c: '' };
            const consAfter:  ConsequenceEdit = {
                option_a: r.consequence_option_a_revised || consBefore.option_a,
                option_b: r.consequence_option_b_revised || consBefore.option_b,
                option_c: r.consequence_option_c_revised || consBefore.option_c,
            };
            recordSuggestion(`consequence_${slot}`, consBefore, consAfter);
            editConsequences[slot]  = consAfter;
            consequencesDirty.value = true;
        }
    }

    // Cross-session seed: AI proposes the actual revised seed wording
    if (r.cross_session_concern && r.cross_session_seed_revised) {
        recordSuggestion('cross_session_seed', editCrossSessionSeed.value, r.cross_session_seed_revised);
        editCrossSessionSeed.value   = r.cross_session_seed_revised;
        editCrossSessionTarget.value = r.cross_session_target_session || null;
        crossSessionDirty.value      = true;
    }

    // ── Non-actionable warnings (kept as informational) ───────────────────
    if (r.consequence_map_needs_review && r.consequence_map_note) {
        impactWarnings.value.push({ type: 'consequence', note: r.consequence_map_note });
    }
    if (r.cross_session_concern && r.cross_session_note) {
        impactWarnings.value.push({ type: 'cross_session', note: r.cross_session_note });
    }

    wlTrace('analyse.suggestions.applied', {
        severity:        r.severity,
        suggested_keys:  Object.keys(aiSuggestions),
    });
};

const severityColor = (s: string) => ({
    clean:       'text-emerald-400',
    minor:       'text-gray-400',
    moderate:    'text-yellow-400',
    significant: 'text-red-400',
}[s] ?? 'text-gray-400');

const severityIcon = (s: string) =>
    ({ clean: '✓', minor: '△', moderate: '⚠', significant: '⚡' }[s] ?? '?');

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
    initCloseEdit(sa?.session_close_design ?? null);
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

// ── Session Close editing ──────────────────────────────────────────────────
interface CloseEditState {
    resolution_prose: string;
    hook_transition: string;
    choice_question: string;
    option_a_text: string;
    option_a_next: string;
    option_b_text: string;
    option_b_next: string;
    option_c_text: string;
    option_c_next: string;
    final_line: string;
}
const closeEdit: CloseEditState = reactive({
    resolution_prose: '',
    hook_transition:  '',
    choice_question:  '',
    option_a_text:    '', option_a_next: '',
    option_b_text:    '', option_b_next: '',
    option_c_text:    '', option_c_next: '',
    final_line:       '',
});
const closeDirty       = ref(false);
const savingClose      = ref(false);
const closeStickiness  = ref<Record<string, string> | null>(null);

const initCloseEdit = (design: any) => {
    closeEdit.resolution_prose = design?.resolution_prose ?? '';
    closeEdit.hook_transition  = design?.hook_transition  ?? '';
    closeEdit.choice_question  = design?.session_end_choice?.choice_question ?? '';
    closeEdit.option_a_text    = design?.session_end_choice?.option_a?.text ?? '';
    closeEdit.option_a_next    = design?.session_end_choice?.option_a?.next_session_opens ?? '';
    closeEdit.option_b_text    = design?.session_end_choice?.option_b?.text ?? '';
    closeEdit.option_b_next    = design?.session_end_choice?.option_b?.next_session_opens ?? '';
    closeEdit.option_c_text    = design?.session_end_choice?.option_c?.text ?? '';
    closeEdit.option_c_next    = design?.session_end_choice?.option_c?.next_session_opens ?? '';
    closeEdit.final_line       = design?.session_end_choice?.final_line ?? '';
    closeStickiness.value      = design?.stickiness_audit ?? null;
    closeDirty.value           = false;
};

const saveClose = async () => {
    if (activeSession.value === null) return;
    savingClose.value = true;

    // Build the full updated session_close_design structure.
    // Backend uses array_replace_recursive so unspecified sub-keys (e.g. stickiness_audit)
    // on the live row are preserved.
    const updatedClose = {
        resolution_prose: closeEdit.resolution_prose,
        hook_transition:  closeEdit.hook_transition,
        session_end_choice: {
            choice_question: closeEdit.choice_question,
            option_a: { text: closeEdit.option_a_text, next_session_opens: closeEdit.option_a_next },
            option_b: { text: closeEdit.option_b_text, next_session_opens: closeEdit.option_b_next },
            option_c: { text: closeEdit.option_c_text, next_session_opens: closeEdit.option_c_next },
            final_line: closeEdit.final_line,
        },
    };

    const url  = `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/adaptation`;
    const data = await apiPost(url, {
        session_number:   activeSession.value,
        adaptation_patch: { session_close_design: updatedClose },
    });

    savingClose.value = false;
    if (!data.error) {
        closeDirty.value = false;
        router.reload({ only: ['activeDrafts'] });
    }
};

// Initialize on mount
if (activeSession.value !== null) {
    const sa = props.sessionAdaptations[activeSession.value] ?? null;
    coldOpenEdit.value = sa?.cold_open ?? '';
    initChoiceEdits(sa?.session_choice_design ?? null);
    initCloseEdit(sa?.session_close_design ?? null);
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

// ── Multi-event Playground ─────────────────────────────────────────────────
// Opens a drawer that mirrors the runtime narrator, sequencing through the
// selected events (or the focused event if no selection). Auto-advances on
// AI advance_event=true. Stays open until the writer quits.
const playgroundOpen = ref(false);
const playgroundEventIds = ref<number[]>([]);

const canPlayground = computed(() =>
    selectedIds.value.length >= 1 || focusedEvent.value !== null
);

// ── Discard draft ──────────────────────────────────────────────────────────
const discardDraft = async (draftId: number) => {
    if (!confirm('Discard this draft? Any unsaved work in it will be lost.')) return;
    const res = await fetch(
        `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${draftId}`,
        {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-XSRF-TOKEN': xsrfToken(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }
    );
    const data = await res.json().catch(() => ({}));
    if (data?.error) {
        alert(data.error);
        return;
    }
    wlTrace('chapter.discard_draft', { draft_id: draftId });

    // If this was the open save-draft, clear local refs too
    if (saveDraftId.value === draftId) {
        saveDraftId.value = null;
    }
    router.reload({ only: ['activeDrafts'] });
};

const openPlayground = () => {
    let ids: number[];
    if (selectedIds.value.length > 0) {
        // Use selection in chapter order
        const sel = new Set(selectedIds.value);
        ids = props.events.filter(e => sel.has(e.id)).map(e => e.id);
    } else if (focusedEvent.value) {
        ids = [focusedEvent.value.id];
    } else {
        return;
    }
    playgroundEventIds.value = ids;
    playgroundOpen.value = true;
    wlTrace('chapter.playground.open', { event_ids: ids });
};

const closePlayground = () => {
    playgroundOpen.value = false;
    playgroundEventIds.value = [];
};

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

        <!-- Flash error banner (e.g. combine AI failure) -->
        <div v-if="flashError" class="flex-none border-b border-red-800/60 bg-red-950/50 px-6 py-2 text-sm text-red-300">
            {{ flashError }}
        </div>

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

                    <!-- Playground: runs the runtime narrator on the selected events (or focused) -->
                    <button :disabled="!canPlayground"
                        class="rounded-lg px-3 py-1.5 font-medium transition-all disabled:opacity-30"
                        :class="canPlayground ? 'bg-primary-700 hover:bg-primary-600 text-white' : 'bg-gray-800 text-gray-500'"
                        :title="canPlayground ? 'Open multi-event playground for the selected or focused event(s)' : 'Select or focus an event to play'"
                        @click="openPlayground">
                        ▶ Playground
                        <span v-if="selectedIds.length > 0" class="ml-1 text-xs text-primary-100/80">({{ selectedIds.length }})</span>
                    </button>

                    <div class="flex-1"></div>
                    <button class="rounded-lg bg-gray-800 px-3 py-1.5 text-gray-400 hover:bg-gray-700 hover:text-white transition-all"
                        @click="openNotes" title="Open collaboration notes for this chapter">
                        ✎ Notes
                    </button>
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
                    <div class="flex-1 overflow-y-auto p-5 space-y-5">

                        <!-- Action bar -->
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono text-gray-600">{{ focusedEvent.position }}</span>
                                <h2 class="text-sm font-semibold">{{ focusedEvent.title }}</h2>
                                <span v-if="focusedEvent.session_number !== null"
                                    class="rounded-full bg-gray-800 px-2 py-0.5 text-xs text-gray-400">S{{ focusedEvent.session_number }}</span>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <button class="rounded-lg px-3 py-1.5 text-xs font-medium transition-all disabled:opacity-40"
                                    :class="editDirty ? 'bg-primary-600 hover:bg-primary-500 text-white' : 'bg-gray-800 text-gray-500'"
                                    :disabled="saving || !editDirty" @click="saveEdit">
                                    <span v-if="saving">Saving…</span>
                                    <span v-else-if="saveDraftId && !editDirty">✓ Saved</span>
                                    <span v-else>Save Draft</span>
                                </button>
                                <!-- Preview = launch the Playground drawer with just this focused event -->
                                <button
                                    class="rounded-lg bg-gray-800 px-3 py-1.5 text-xs text-gray-300 hover:bg-gray-700 transition-all"
                                    title="Open the playground for this event (uses the live runtime narrator)"
                                    @click="runPreview">
                                    ▶ Preview
                                </button>
                                <!-- Only available after the script content itself was changed.
                                     Editing objectives/attributes/choices directly costs nothing — no AI needed. -->
                                <button v-if="scriptRewritten"
                                    class="rounded-lg border border-primary-700/40 bg-primary-950/20 px-3 py-1.5 text-xs text-primary-300 hover:bg-primary-900/30 transition-all disabled:opacity-40"
                                    :disabled="analysing"
                                    title="Script was rewritten — AI will check which adaptation layers are now stale"
                                    @click="analyseImpact">
                                    <span v-if="analysing">Analysing…</span>
                                    <span v-else>✦ Analyse script changes</span>
                                </button>
                                <Link v-if="saveDraftId"
                                    :href="`/writer/writer-lab/${story.id}/chapters/${chapter.id}/drafts/${saveDraftId}`"
                                    class="text-xs text-gray-600 hover:text-gray-400 transition-colors">full draft →</Link>
                            </div>
                        </div>

                        <p v-if="saveError" class="text-xs text-red-400">{{ saveError }}</p>

                        <!-- AI fill status bar -->
                        <div v-if="impactSummary" class="rounded-lg border border-gray-700/50 bg-gray-900/40 px-4 py-2.5 flex items-start gap-2.5">
                            <span :class="['flex-none mt-px text-sm', severityColor(impactSeverity ?? '')]">{{ severityIcon(impactSeverity ?? '') }}</span>
                            <div class="flex-1 min-w-0 space-y-0.5">
                                <p class="text-xs text-gray-300 leading-relaxed">{{ impactSummary }}</p>
                                <p v-if="impactSeverity !== 'clean'" class="text-xs text-amber-400">Fields updated by AI are highlighted in amber — edit freely, then save.</p>
                                <p v-else class="text-xs text-emerald-400">All adaptation layers are still accurate.</p>
                            </div>
                            <button class="text-xs text-gray-700 hover:text-gray-500 flex-none" @click="resetImpact">✕</button>
                        </div>
                        <div v-if="impactError" class="rounded-lg border border-red-800/40 bg-red-950/20 px-4 py-2 text-xs text-red-400">{{ impactError }}</div>

                        <!-- Manual-action warnings (consequence map / cross-session) -->
                        <div v-if="impactWarnings.length" class="space-y-2">
                            <div v-for="w in impactWarnings" :key="w.type"
                                class="rounded-lg px-4 py-2.5 flex items-start gap-2 text-xs"
                                :class="w.type === 'cross_session' ? 'bg-red-950/20 border border-red-800/30' : 'bg-yellow-950/20 border border-yellow-800/30'">
                                <span :class="w.type === 'cross_session' ? 'text-red-400' : 'text-yellow-400'">{{ w.type === 'cross_session' ? '⚡' : '⚠' }}</span>
                                <div>
                                    <p class="font-medium mb-0.5" :class="w.type === 'cross_session' ? 'text-red-300' : 'text-yellow-300'">
                                        {{ w.type === 'cross_session' ? 'Cross-session anchor at risk' : 'Consequence map — manual review needed' }}
                                    </p>
                                    <p class="text-gray-400">{{ w.note }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- ── SECTION 1: Event Script ── -->
                        <div class="rounded-xl border border-gray-800 bg-gray-900/30 p-4 space-y-3">
                            <h3 class="text-xs uppercase tracking-widest text-gray-500 flex items-center gap-1.5">
                                Event Script <HelpHint term="event_content" />
                            </h3>
                            <textarea v-model="editContent" rows="9"
                                class="w-full rounded-xl border bg-gray-950 px-4 py-3 text-sm text-gray-200 leading-relaxed focus:outline-none resize-none transition-colors"
                                :class="aiBorderClass('content')"
                                placeholder="Edit the event's screenplay content…"></textarea>

                            <div class="flex items-center gap-5 flex-wrap">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <div class="relative h-5 w-9 rounded-full transition-colors duration-200"
                                        :class="editRequiresChoice ? 'bg-primary-500' : 'bg-gray-700'"
                                        @click="editRequiresChoice = !editRequiresChoice; editDirty = true">
                                        <div class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform duration-200"
                                            :class="editRequiresChoice ? 'translate-x-4' : 'translate-x-0.5'"></div>
                                    </div>
                                    <span class="text-sm text-gray-300">Requires player choice</span>
                                    <HelpHint term="event_requires_choice" />
                                </label>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <label class="text-xs text-gray-500 inline-flex items-center gap-1">Beat type <HelpHint term="event_beat_type" /></label>
                                    <select v-model="editBeatType"
                                        class="rounded-lg border bg-gray-950 px-2 py-1 text-sm text-gray-300 focus:outline-none transition-colors"
                                        :class="aiBorderClass('beat_type')"
                                        @change="editDirty = true">
                                        <option value="">—</option>
                                        <option v-for="bt in BEAT_TYPES" :key="bt" :value="bt">{{ bt }}</option>
                                    </select>
                                    <SuggestionPills :state="aiState('beat_type')"
                                        @accept="acceptSuggestion('beat_type')"
                                        @undo="undoSuggestion('beat_type')" />
                                    <span v-if="aiState('beat_type') === 'clean' && focusedEvent.beat_type"
                                        class="text-xs text-gray-600"
                                        :title="focusedEvent.beat_moment ?? ''">from beat map</span>
                                </div>
                            </div>

                            <!-- Beat moment (editorial one-liner from session_architecture.beat_map) -->
                            <div v-if="editBeatMoment || aiState('beat_moment') !== 'clean'">
                                <label class="mb-1 block text-xs text-gray-500 flex items-center gap-2">
                                    Beat moment <HelpHint term="event_beat_moment" />
                                    <span class="text-gray-600">(editorial one-line description)</span>
                                    <SuggestionPills :state="aiState('beat_moment')"
                                        @accept="acceptSuggestion('beat_moment')"
                                        @undo="undoSuggestion('beat_moment')" />
                                </label>
                                <input v-model="editBeatMoment" type="text"
                                    class="w-full rounded-lg border bg-gray-950 px-3 py-1.5 text-sm text-gray-300 focus:outline-none transition-colors"
                                    :class="aiBorderClass('beat_moment')" />
                            </div>
                        </div>

                        <!-- ── SECTION 2: Event Metadata (objectives + attributes) ── -->
                        <div class="rounded-xl border border-gray-800 bg-gray-900/30 p-4 space-y-3">
                            <h3 class="text-xs uppercase tracking-widest text-gray-500 flex items-center gap-2">
                                Event Metadata
                            </h3>

                            <div>
                                <label class="mb-1 block text-xs text-gray-500 flex items-center gap-2">
                                    <span>Objectives <span class="text-gray-600">(past-tense factual summary)</span></span>
                                    <HelpHint term="event_objectives" />
                                    <SuggestionPills :state="aiState('objectives')"
                                        @accept="acceptSuggestion('objectives')"
                                        @undo="undoSuggestion('objectives')" />
                                </label>
                                <textarea v-model="editObjectives" rows="2"
                                    class="w-full rounded-lg border px-3 py-2 text-sm text-gray-200 bg-gray-950 focus:outline-none resize-none transition-colors"
                                    :class="aiBorderClass('objectives')"
                                    placeholder="e.g. Alice committed to following the White Rabbit into the hole despite uncertainty."></textarea>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs text-gray-500 flex items-center gap-2">
                                    <span>Attributes <span class="text-gray-600">(canonical objects, characters, locations)</span></span>
                                    <HelpHint term="event_attributes" />
                                    <SuggestionPills :state="aiState('attributes')"
                                        @accept="acceptSuggestion('attributes')"
                                        @undo="undoSuggestion('attributes')" />
                                </label>
                                <div class="flex flex-wrap gap-1.5 mb-2 min-h-[1.5rem] p-1 rounded-lg border transition-colors"
                                     :class="aiBorderClass('attributes', 'border-transparent')">
                                    <span v-for="(attr, i) in editAttributes" :key="attr"
                                        class="flex items-center gap-1 rounded-full border bg-gray-800 border-gray-700 px-2.5 py-0.5 text-xs text-gray-300">
                                        {{ attr }}
                                        <button class="text-gray-500 hover:text-red-400 transition-colors" @click="removeAttribute(i)">✕</button>
                                    </span>
                                    <span v-if="editAttributes.length === 0" class="text-xs text-gray-700 px-2">no attributes yet</span>
                                </div>
                                <div class="flex gap-2">
                                    <input v-model="attrInput" type="text"
                                        class="flex-1 rounded-lg border border-gray-700 bg-gray-950 px-3 py-1.5 text-xs text-gray-300 focus:border-primary-500 focus:outline-none"
                                        placeholder="Add attribute and press Enter…"
                                        @keydown.enter.prevent="addAttribute"
                                        @keydown.comma.prevent="addAttribute" />
                                    <button class="rounded-lg bg-gray-800 px-3 py-1.5 text-xs text-gray-400 hover:text-white transition-all" @click="addAttribute">Add</button>
                                </div>
                            </div>
                        </div>

                        <!-- ── SECTION 3: Session Choice Design ── -->
                        <div v-if="focusedEvent.session_number !== null && sessionAdaptations[focusedEvent.session_number]?.session_choice_design"
                            class="rounded-xl border border-gray-800 bg-gray-900/30 p-4 space-y-3">
                            <h3 class="text-xs uppercase tracking-widest text-gray-500">
                                Session {{ focusedEvent.session_number }} — Choice Design
                            </h3>

                            <div v-for="slot in choiceSlots" :key="slot"
                                class="rounded-lg border p-3.5 space-y-2.5 transition-colors bg-gray-950/50"
                                :class="aiBorderClass(`choice_${slot}`, 'border-gray-800')">

                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-medium text-gray-400">{{ choiceLabel(slot) }}</span>
                                    <SuggestionPills :state="aiState(`choice_${slot}`)"
                                        @accept="acceptSuggestion(`choice_${slot}`)"
                                        @undo="undoSuggestion(`choice_${slot}`)" />
                                    <span v-if="editChoiceSlots[slot]?.tracked_dimension" class="text-xs text-gray-600">· {{ editChoiceSlots[slot].tracked_dimension }}</span>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs text-gray-600 inline-flex items-center gap-1">Tracked Dimension <HelpHint term="choice_tracked_dimension" /></label>
                                    <input v-model="editChoiceSlots[slot].tracked_dimension" type="text"
                                        class="w-full rounded-lg border border-gray-700 bg-gray-900 px-2.5 py-1.5 text-xs text-gray-400 focus:border-primary-500 focus:outline-none"
                                        @input="choiceSlotsDirty = true; editDirty = true" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-600 inline-flex items-center gap-1">Choice Question <HelpHint term="choice_question" /></label>
                                    <input v-model="editChoiceSlots[slot].question" type="text"
                                        class="w-full rounded-lg border bg-gray-900 px-2.5 py-1.5 text-sm text-gray-200 focus:outline-none transition-colors"
                                        :class="aiBorderClass(`choice_${slot}`)"
                                        @input="choiceSlotsDirty = true; editDirty = true" />
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div v-for="opt in ['option_a', 'option_b', 'option_c'] as const" :key="opt">
                                        <label class="mb-1 block text-xs text-gray-600">{{ opt.replace('_', ' ').toUpperCase() }}</label>
                                        <textarea v-model="editChoiceSlots[slot][opt]" rows="3"
                                            class="w-full rounded-lg border bg-gray-900 px-2 py-1.5 text-xs text-gray-300 focus:outline-none resize-none transition-colors"
                                            :class="aiBorderClass(`choice_${slot}`)"
                                            @input="choiceSlotsDirty = true; editDirty = true">
                                        </textarea>
                                    </div>
                                </div>

                                <!-- Consequence per-option (AI-fillable, optional) -->
                                <div v-if="editConsequences[slot] || aiState(`consequence_${slot}`) !== 'clean'"
                                     class="rounded-md border bg-gray-900/30 p-2.5 space-y-1.5 transition-colors"
                                     :class="aiBorderClass(`consequence_${slot}`, 'border-gray-800')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] uppercase tracking-wider text-gray-500">World-state consequence per option</span>
                                        <HelpHint term="consequence_per_option" size="xs" />
                                        <SuggestionPills :state="aiState(`consequence_${slot}`)"
                                            @accept="acceptSuggestion(`consequence_${slot}`)"
                                            @undo="undoSuggestion(`consequence_${slot}`)" />
                                    </div>
                                    <div v-for="opt in ['option_a', 'option_b', 'option_c'] as const" :key="opt"
                                         class="flex items-start gap-2">
                                        <span class="mt-1.5 text-[10px] font-mono text-gray-700 w-3">{{ opt.slice(-1).toUpperCase() }}</span>
                                        <textarea v-model="editConsequences[slot][opt]" rows="2"
                                                  class="w-full rounded-md border border-gray-800 bg-gray-950 px-2 py-1 text-xs text-gray-400 focus:border-primary-500 focus:outline-none resize-none"
                                                  placeholder="World-state shift this option triggers…"
                                                  @input="consequencesDirty = true; editDirty = true"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── SECTION 4: Cross-session seed (AI-fillable) ── -->
                        <div v-if="editCrossSessionSeed || aiState('cross_session_seed') !== 'clean'"
                             class="rounded-xl border bg-gray-900/30 p-4 space-y-2 transition-colors"
                             :class="aiBorderClass('cross_session_seed', 'border-gray-800')">
                            <h3 class="text-xs uppercase tracking-widest text-gray-500 flex items-center gap-2">
                                Cross-session seed
                                <HelpHint term="cross_session_seed" />
                                <span v-if="editCrossSessionTarget" class="text-gray-600 normal-case tracking-normal">
                                    → Session {{ editCrossSessionTarget }}
                                </span>
                                <SuggestionPills :state="aiState('cross_session_seed')"
                                    @accept="acceptSuggestion('cross_session_seed')"
                                    @undo="undoSuggestion('cross_session_seed')" />
                            </h3>
                            <textarea v-model="editCrossSessionSeed" rows="3"
                                      class="w-full rounded-lg border bg-gray-950 px-3 py-2 text-sm text-gray-300 focus:outline-none resize-none transition-colors"
                                      :class="aiBorderClass('cross_session_seed')"
                                      placeholder="Planted anchor / emotional residue the next session references…"
                                      @input="crossSessionDirty = true"></textarea>
                            <p class="text-[11px] text-gray-600 leading-relaxed">
                                A short paragraph the next session's cold open will pick up. Keep the original
                                seed's vocabulary so downstream awareness stays aligned.
                            </p>
                        </div>

                        <!-- Preview / Playground is now a dedicated drawer (mounted at root); no inline panel here. -->
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

                        <!-- Session Close tab — fully editable structured UI -->
                        <template v-else-if="adaptationTab === 'close'">
                            <div v-if="!sessionAdaptation.session_close_design" class="text-gray-600 text-sm">
                                No session close design yet.
                            </div>

                            <div v-else class="space-y-5">

                                <!-- Resolution prose -->
                                <div class="rounded-xl border border-gray-800 bg-gray-900 p-5 space-y-2">
                                    <label class="block text-xs uppercase tracking-widest text-gray-500">Resolution Prose</label>
                                    <p class="text-xs text-gray-600">The closing prose the narrator delivers as the session resolves.</p>
                                    <textarea v-model="closeEdit.resolution_prose" rows="8"
                                        class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-200 leading-relaxed focus:border-primary-500 focus:outline-none resize-none"
                                        @input="closeDirty = true"></textarea>
                                </div>

                                <!-- Hook transition -->
                                <div class="rounded-xl border border-gray-800 bg-gray-900 p-5 space-y-2">
                                    <label class="block text-xs uppercase tracking-widest text-gray-500">Hook Transition</label>
                                    <p class="text-xs text-gray-600">The bridge from resolution into the session-end choice.</p>
                                    <textarea v-model="closeEdit.hook_transition" rows="4"
                                        class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-200 leading-relaxed focus:border-primary-500 focus:outline-none resize-none"
                                        @input="closeDirty = true"></textarea>
                                </div>

                                <!-- Session-end choice -->
                                <div class="rounded-xl border border-gray-800 bg-gray-900 p-5 space-y-3">
                                    <label class="block text-xs uppercase tracking-widest text-gray-500">Session-End Choice</label>
                                    <p class="text-xs text-gray-600">The retention hook that bridges into next session.</p>

                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Choice Question</label>
                                        <input v-model="closeEdit.choice_question" type="text"
                                            class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-1.5 text-sm text-gray-200 focus:border-primary-500 focus:outline-none"
                                            @input="closeDirty = true" />
                                    </div>

                                    <div v-for="opt in ['a', 'b', 'c'] as const" :key="opt"
                                        class="rounded-lg border border-gray-800 bg-gray-950/40 p-3 space-y-2">
                                        <div class="text-xs font-medium text-gray-400">Option {{ opt.toUpperCase() }}</div>
                                        <div>
                                            <label class="mb-1 block text-xs text-gray-600">Option text</label>
                                            <textarea
                                                v-model="closeEdit[`option_${opt}_text`]"
                                                rows="2"
                                                class="w-full rounded-lg border border-gray-700 bg-gray-950 px-2.5 py-1.5 text-xs text-gray-300 focus:border-primary-500 focus:outline-none resize-none"
                                                @input="closeDirty = true"></textarea>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-gray-600">Next session opens (carry-through)</label>
                                            <textarea
                                                v-model="closeEdit[`option_${opt}_next`]"
                                                rows="2"
                                                class="w-full rounded-lg border border-gray-700 bg-gray-950 px-2.5 py-1.5 text-xs text-gray-400 focus:border-primary-500 focus:outline-none resize-none"
                                                @input="closeDirty = true"></textarea>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Final Line</label>
                                        <input v-model="closeEdit.final_line" type="text"
                                            class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-1.5 text-sm text-gray-200 focus:border-primary-500 focus:outline-none"
                                            @input="closeDirty = true" />
                                    </div>
                                </div>

                                <!-- Stickiness audit (read-only badges) -->
                                <div v-if="closeStickiness" class="rounded-xl border border-gray-800/60 bg-gray-900/30 p-4 space-y-2">
                                    <label class="block text-xs uppercase tracking-widest text-gray-500">Stickiness Audit</label>
                                    <p class="text-xs text-gray-600">Editorial verification results (read-only; regenerated when the adaptation pipeline runs).</p>
                                    <div class="flex flex-wrap gap-2 pt-1">
                                        <div v-for="(verdict, key) in closeStickiness" :key="key"
                                            class="flex items-center gap-2 rounded-full border px-3 py-1 text-xs"
                                            :class="verdict === 'PASS'
                                                ? 'bg-emerald-950/30 border-emerald-700/30 text-emerald-300'
                                                : verdict === 'REVISE'
                                                    ? 'bg-yellow-950/30 border-yellow-700/30 text-yellow-300'
                                                    : 'bg-gray-800 border-gray-700 text-gray-400'">
                                            <span class="font-medium">{{ String(key).replace(/_/g, ' ') }}</span>
                                            <span>{{ verdict }}</span>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    class="rounded-lg px-4 py-2 text-sm font-medium transition-all disabled:opacity-40"
                                    :class="closeDirty ? 'bg-primary-600 hover:bg-primary-500 text-white' : 'bg-gray-800 text-gray-500'"
                                    :disabled="!closeDirty || savingClose"
                                    @click="saveClose">
                                    <span v-if="savingClose">Saving…</span>
                                    <span v-else>Save Session Close as Draft</span>
                                </button>
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
                        <div v-for="draft in activeDrafts" :key="draft.id"
                             class="flex flex-none items-center gap-1.5 rounded-lg bg-gray-900 border border-gray-800 px-2 py-1 text-xs hover:border-gray-600 transition-all">
                            <Link :href="`/writer/writer-lab/${story.id}/chapters/${chapter.id}/drafts/${draft.id}`"
                                class="flex items-center gap-1.5">
                                <span class="text-gray-400">{{ draft.type }}</span>
                                <span :class="['rounded px-1.5 py-0.5 text-xs', draftStatusColor(draft.status)]">
                                    {{ draft.status.replace('_', ' ') }}
                                </span>
                            </Link>
                            <button class="ml-1 rounded px-1 text-gray-700 hover:text-red-400 transition-colors"
                                    title="Discard this draft"
                                    @click.stop="discardDraft(draft.id)">✕</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Multi-event Playground drawer -->
        <PlaygroundDrawer v-if="playgroundOpen"
            :story-id="story.id"
            :chapter-id="chapter.id"
            :event-ids="playgroundEventIds"
            @close="closePlayground" />

        <!-- Collaboration notes drawer -->
        <NotesPanel v-if="notesOpen"
            :story-id="story.id"
            :chapter-id="chapter.id"
            :default-author="auth?.writer?.name ?? ''"
            :context-event-id="focusedEvent?.id ?? null"
            @close="closeNotes" />
    </div>
</template>

<style scoped>
.prose :deep(p) { margin-bottom: 0.75rem; }
</style>
