/**
 * Dictionary of plain-language definitions for every Writer Lab field/concept.
 *
 * Surfaced via the <HelpHint :term="..." /> component as a small "i" badge that
 * shows a hover popover. Early users see "i", more experienced users learn the
 * vocabulary and stop noticing them. The dictionary is centralised so we have
 * one source of truth for terminology, and so localisation is straightforward.
 *
 * Keys are stable identifiers — do NOT change them once shipped or you orphan
 * existing tooltips. New fields → new keys.
 */

export interface WriterLabHint {
    title: string;
    body:  string;
}

export const writerLabHints: Record<string, WriterLabHint> = {
    // ── Event card ─────────────────────────────────────────────────────────
    event_content: {
        title: 'Event Script',
        body:  'The narrative prose for this single event. The runtime narrator performs this text in second-person present tense. Keep it third-person source prose here.',
    },
    event_objectives: {
        title: 'Objectives',
        body:  'A past-tense sentence describing the observable state change that this event creates. Example: "Alice committed to following the White Rabbit into the hole despite uncertainty."',
    },
    event_attributes: {
        title: 'Attributes',
        body:  'Canonical objects, characters, and locations that appear in this event. The runtime uses these to track what exists in the world. Each entry is one category line like "Objects: pocket watch | golden key".',
    },
    event_beat_type: {
        title: 'Beat Type',
        body:  'The dramatic register of this event. SETUP plants information. ESCALATION raises tension. BREATH gives the player a pause. TWIST reframes context. RESOLUTION pays off a thread.',
    },
    event_beat_moment: {
        title: 'Beat Moment',
        body:  'A one-sentence editorial description of what this beat is about. Comes from the session\'s beat_map and helps locate this event in the larger arc.',
    },
    event_requires_choice: {
        title: 'Requires Player Choice',
        body:  'If true, the narrator will offer the player three options at the end of this event. If false, this is cinematic flow — the player can speak freely but won\'t see a forced branch.',
    },

    // ── Choice design ──────────────────────────────────────────────────────
    choice_tracked_dimension: {
        title: 'Tracked Dimension',
        body:  'The emotional or behavioural axis this choice tracks (e.g. trust, caution, defiance). Drives the world-state delta the runtime applies when the player picks an option.',
    },
    choice_question: {
        title: 'Choice Question',
        body:  'The player-facing question that summarises the moment of decision. Keep it short and grounded in the scene.',
    },
    choice_options: {
        title: 'Choice Options',
        body:  'Three behavioural paths the player can pick. Each should feel meaningfully different and earned by the surrounding script.',
    },
    consequence_per_option: {
        title: 'Per-Option Consequence',
        body:  'One sentence per option describing the world-state shift that path triggers. Affects what the narrator carries forward and what downstream sessions reference.',
    },

    // ── Cross-session ──────────────────────────────────────────────────────
    cross_session_seed: {
        title: 'Cross-Session Seed',
        body:  'A planted anchor (character beat, object, emotional residue) that the next session\'s cold open picks up. Edits to the current event sometimes break the seed — this is where the AI proposes a realignment.',
    },

    // ── Cold open / close ─────────────────────────────────────────────────
    cold_open: {
        title: 'Cold Open',
        body:  'The opening narration the runtime delivers when a session begins. Sets the tone, recalls the prior session\'s seed, and lands the player in the world.',
    },
    session_close_resolution: {
        title: 'Resolution Prose',
        body:  'The final descriptive paragraph that lands the session\'s arc before the session-end choice fires.',
    },
    session_close_hook: {
        title: 'Hook Transition',
        body:  'One or two sentences that emotionally bridge from this session\'s ending into the next session\'s opening. The seed the next session\'s cold open will pick up.',
    },
    session_end_choice: {
        title: 'Session-End Choice',
        body:  'The retention hook — a final choice presented at the end of the session whose options can change which session opens next.',
    },
    stickiness_audit: {
        title: 'Stickiness Audit',
        body:  'Read-only quality flags from the adaptation pipeline. They surface where the close lands or doesn\'t. Use them as a checklist while editing.',
    },

    // ── Draft + activate ──────────────────────────────────────────────────
    canonical_anchors: {
        title: 'Canonical Anchors',
        body:  'The facts from the source events that must survive in the rewritten content. The combine compressor uses this as a binding checklist — every anchor must appear in the rewrite.',
    },
    derived_objectives: {
        title: 'Derived Objectives',
        body:  'Objectives produced by re-running the original pipeline extractor against the combined or edited content. Same format and conventions as the originals.',
    },
    derived_attributes: {
        title: 'Derived Attributes',
        body:  'Attributes produced by the same pipeline extractor as above. Six-category structure with pipe-separated facts.',
    },
    activate: {
        title: 'Activate',
        body:  'Rewrites the live events table from this draft. Always snapshots the full chapter first so you can restore from Versions if needed.',
    },
    discard: {
        title: 'Discard Draft',
        body:  'Permanently deletes the draft. Activated drafts cannot be discarded — roll them back via Versions instead.',
    },
};

export const getHint = (term: string): WriterLabHint | null =>
    writerLabHints[term] ?? null;
