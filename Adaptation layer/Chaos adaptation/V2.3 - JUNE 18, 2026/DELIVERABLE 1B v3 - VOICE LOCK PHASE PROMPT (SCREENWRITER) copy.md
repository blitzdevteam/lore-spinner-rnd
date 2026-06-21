# DELIVERABLE 1B v3: VOICE LOCK PHASE PROMPT — SCREENWRITER / TV WRITER

**Lorespinner Pipeline Upgrade — June 2026**
**Version:** 3.0
**Replaces:** Deliverable 1B FINAL and Deliverable 1B v2 (this single prompt supersedes and merges both). Also replaces Original Deliverable 1 when source = screenplay/teleplay.
**Type:** Pipeline phase (runs once per IP, at build time)
**Inserts:** Between IP Audit and Story Session Map
**Format Gate:** Runs ONLY when `format_detection.type` is `"screenplay"`, `"teleplay"`, `"pilot"`, or `"limited_series"`. If the source is prose fiction, STOP and use the Novelist Voice Lock Prompt (Deliverable 1A).
**Implementation:** Copy-paste this entire prompt into VoiceLockChapterJob. One conditional selects this (1B) vs the novelist version (1A) based on FormatDetectionJob output. No external tooling, linter, or dependency is required to run this prompt or to enforce its output at runtime.

---

## WHY THIS VERSION EXISTS — READ BEFORE RUNNING

Real-world testing of the prior versions measured voice decay: prose generated from the extracted profile started recognizable and drifted to roughly 25% voice fidelity by the tenth output. The root cause was not weak extraction. It was an enforcement model that cannot run.

Both prior versions assumed something or someone could COUNT generated prose at runtime — fragment percentages, punctuation densities, rhythm distributions — and reject output that missed a numeric target. Nothing in the live system can do this. An LLM cannot reliably count its own output; when asked, it produces a plausible number and reports "passed." There is no linter in the runtime. So every numeric runtime check was theater, and the voice drifted unchecked.

**v3 fixes this by changing what the runtime is asked to do.**

The single most important principle in this document:

> **A voice is held at runtime by IMITATION and by BINARY, LOCAL RULES — never by counting.**

This splits every voice constraint into two homes:

- **Discrete-and-local checks** belong at runtime. Searching for a specific character ("find every em-dash and delete it"), or spotting a local pattern ("three sentences in a row open with the same word"), is something an LLM does reliably, because it is a search-and-fix over visible tokens, not a statistic computed over hundreds of words.
- **Global-and-numeric checks** belong at build time only. "Is the fragment rate 41%?" cannot be answered live. These metrics are diagnostic — used here in extraction and in pre-ship QA, never handed to the live narrator as an instruction.

Everything below serves that split. The extraction still measures the writer richly — but its OUTPUT is reorganized into things the runtime can actually use: a set of locked prose **exemplars** the narrator imitates, and a short **Anchor Card** of binary/local rules the narrator re-reads every turn. The numbers stay in the profile for QA; they never become the runtime's job.

### A note on single-source extraction

In the product, a screenwriter sends Daniel ONE screenplay. The Voice Lock runs on that one script. This is not an edge case to tolerate — it is the permanent condition of every IP. This prompt is written for it.

The consequence: do not build hard enforcement on thin statistics. A single ~20,000-word screenplay gives reliable signal for two kinds of feature, and unreliable signal for everything else.

- **Reliable from one script:** zero-occurrence absences (the writer NEVER does X), and high-frequency habits (the writer does Y constantly). These become binary runtime rules.
- **Unreliable from one script:** the writer's natural VARIANCE, and any rate based on rare events. These inform the exemplars and build-time QA, but never become hard runtime rules.

The Confidence Framework in Task 1 enforces this discipline metric by metric.

### Backward-compatibility note (for the pipeline — non-breaking)

This prompt replaces 1B FINAL + 1B v2, and the upgrade is additive. **Section 1 (Voice DNA Profile) and Section 2 (Master Rule 1) keep their names and positions**, so existing downstream references in Phase 4 (Beat Architecture) and Phase 5 (Choice Design) that paste "Section 1" / "Section 2" remain valid unchanged. The new outputs (Voice Anchor, Anchor Card, Self-Check) are appended as new named sections and are consumed only by the Runtime Narrator Template (D8 v2). One move to note: the old 14/18-point audit is now the **Build-Time QA Protocol** section — if any job references it by number, update it, or (preferred) have the assembly map every section by its **header name**, not its index. An IP still built on the old 1B keeps working until you deliberately rebuild it on 1B v3.

---

## COPY-PASTE PROMPT BELOW THIS LINE

---

LORESPINNER — VOICE LOCK PHASE: SCREENWRITER / TV WRITER VOICE EXTRACTION AND PROTECTION

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 1 AUDIT: [PASTE SCORECARD]

FORMAT DETECTION: [PASTE FORMAT DETECTION OUTPUT — must confirm type = screenplay/teleplay/pilot/limited_series]

SOURCE TEXT UPLOADED: [TITLE], [WRITER], [YEAR], [FORMAT: SCREENPLAY / TELEPLAY / PILOT / LIMITED SERIES]

WHAT TO UPLOAD: The COMPLETE source text. Not samples, not excerpts. Upload every page. If a writer has supplied more than one screenplay, upload all of them — more source is always better. But this prompt assumes the normal case is a SINGLE screenplay and is built to extract a faithful voice from one script alone.

---

You are performing the most important job in the Lorespinner pipeline. Every word the narrator speaks to every player will be measured against what you produce here. This is not analysis. This is forensic extraction of a specific human being's writing DNA — and the construction of the concrete reference material the live narrator will imitate.

The output of this phase becomes CONSTITUTIONAL LAW. It overrides every subsequent phase. If a later phase produces prose that violates the voice profile you extract here, that prose is rejected. No exceptions. No "close enough." The writer's voice is the product.

THIS IS A SCREENWRITER / TV WRITER EXTRACTION. The voice lives in action lines, dialogue, parentheticals, transitions, scene headings, and character cues. Screenwriting is compressed, visual, present-tense, and format-constrained. The runtime, however, must generate continuous second-person present-tense PROSE. The gap between those two forms is where generic AI prose leaks in. Closing that gap is a primary job of this phase, and you close it with worked examples (Task 3), not with numeric tolerances.

You will produce SIX outputs, assembled at the end into one Voice Profile:

1. **TASK 1 — Voice DNA Profile** (who this writer is, measured and described)
2. **TASK 2 — Hard Ban List** (the immune system — binary prohibitions)
3. **TASK 3 — The Voice Anchor** (locked prose exemplars the runtime imitates) ← the centerpiece
4. **TASK 4 — The Anchor Card** (the short binary/local rule set re-read every turn at runtime)
5. **TASK 5 — Runtime Self-Check Protocol** (the discrete pre-delivery pass the narrator can actually perform)
6. **TASK 6 — Build-Time QA Protocol** (the full numeric audit, run before an IP ships — never at runtime)

---

## TASK 1 — WRITER VOICE DNA EXTRACTION

CRITICAL FIRST STEP: Separate the screenplay into its component elements before extracting voice. Screenwriting voice is distributed across formal categories that do not exist in prose — action lines, dialogue, scene headings, parentheticals, transitions, character cues. Analyze each independently before looking at the whole.

Read the complete source text. You are not summarizing the story. You are studying HOW this specific human writes screenplays. Ignore plot. Ignore theme. Focus exclusively on craft mechanics.

### SINGLE-SOURCE CONFIDENCE FRAMEWORK (tag every metric)

From one screenplay, some patterns have thousands of data points and some have a handful. Every extracted constraint must be tagged with a confidence tier, because the tier decides where the constraint is allowed to live.

- **ABSOLUTE** — Zero-occurrence constraints. The writer NEVER does this across the entire source. A zero across 20,000 words is not a sampling gap; it is deliberate avoidance. → Eligible to become a BINARY RUNTIME RULE (Anchor Card) and a HARD BAN (Task 2).
- **HIGH** — 100+ instances or 1000+ data points. Robust even from one source. A dominant, pervasive habit. → Eligible to become a binary/local runtime rule IF it can be expressed as a discrete or local check; otherwise it informs the exemplars and build-time QA.
- **MEDIUM** — 20–99 instances. A real pattern, but the exact rate could shift with more source. → Informs the exemplars and build-time QA only. NEVER a hard runtime rule.
- **LOW** — Fewer than 20 instances. Guidance only. → Noted for build-time awareness. Builds no wall anywhere.

State the tier and the approximate count for every metric you report. The rule that follows from this framework: **only ABSOLUTE and HIGH-confidence, discretely-expressible features may become runtime rules.** Everything else is diagnostic.

Zero-occurrence data is your strongest single-source weapon. When a writer produces 20,000 words without a semicolon, that zero is an ABSOLUTE-confidence ban you can trust completely from one script. Hunt these deliberately (Section J).

Extract the following. Every item requires at least one DIRECT QUOTE from the source as evidence. Do not paraphrase. Quote the line, then explain what it reveals. (These quotes are not decoration — the strongest ones are harvested in Task 3 as raw material for the exemplars.)

### A. SIGNATURE WRITING TECHNIQUES (extract 8–12)

For each technique:

- NAME it in 2–4 words (e.g., "The Fragment Punch," "Verb-First Momentum," "Dialogue as Evasion," "Object-as-Wound")
- QUOTE 2–3 source lines that demonstrate it — note whether from action, dialogue, or transition
- EXPLAIN in one sentence what makes this technique specific to THIS writer, not just competent screenwriting
- NOTE approximate frequency and CONFIDENCE TIER. This frequency is guidance for the exemplars, not a runtime target.

The test: Could another skilled screenwriter produce this technique by accident? If yes, it is not a signature. Dig deeper.

### B. ACTION LINE METRICS

Screenwriting voice lives primarily in the action lines. Analyze a representative cross-section. Report each with a confidence tier:

- Average words per action line — and the range across contexts (establishing vs. action vs. emotional beats)
- Fragment percentage (sentences of 5 words or fewer)
- Verb-first percentage (lines opening on a verb: "Crosses the room." "Sits.")
- ALL CAPS density — how often, and for what (sounds? objects? character intros? emphasis?)
- -ing opening frequency (present-participle openers)
- Paragraph rhythm: does this writer CLUSTER 2–4 sentences into paragraphs or ISOLATE one per line? What is the ALTERNATION pattern between clustered and isolated? This is the writer's visual signature, and it is the metric that matters most — far more than per-line word count, which is a formatting artifact.
- QUOTE 3–4 consecutive action lines that show the action-line voice at its most distinctive

These numbers calibrate the exemplars and feed build-time QA. They are NOT runtime targets.

### C. DIALOGUE METRICS

Analyze all dialogue. Report with confidence tiers:

- Average speech length, and range across characters and scene types
- Contraction density, and whether it shifts by character or intensity
- Question/exclamation tendency, and what it reveals
- Interruption patterns: how does this writer render interrupted speech? Em-dash? Double-hyphen? Ellipsis? A new character cutting in? Parenthetical?
- QUOTE 3–4 exchanges that show the dialogue voice at its most distinctive
- The single most important dialogue constraint for the runtime: the LONGEST speech any character gives. No generated speech should exceed the writer's demonstrated maximum. Capture it.

### D. PUNCTUATION AND DICTION FINGERPRINT

- Word-length tendency (short Anglo-Saxon vs. longer Latinate vs. mix)
- Vocabulary clusters — the word families this writer returns to (mechanical, biological, domestic, violent, clinical, etc.)
- Register in action lines, and whether it shifts by intensity
- Formality (sparse/telegraphic vs. full sentences vs. lyrical vs. clipped)
- Words this writer uses MORE than a generic screenwriter, and words this writer AVOIDS
- Punctuation habits, with counts and tiers. Pay special attention to em-dashes, semicolons, ellipses, exclamation marks — capture each as a count across the full source (these often yield ABSOLUTE or HIGH bans)
- QUOTE 5–6 lines (action or dialogue) demonstrating diction choices no other writer would make the same way

### E. SCREENPLAY STRUCTURE METRICS

- Scene density (scenes per page); long scenes vs. rapid cutting
- INT/EXT ratio; contained spaces vs. open environments
- Action-to-dialogue ratio; visual writer vs. dialogue writer
- Transition types: explicit (CUT TO:, DISSOLVE TO:) or hard cuts? How often?
- Parenthetical vocabulary: what this writer uses, and what they NEVER use
- Scene-length distribution; consistent or variable
- Character introduction pattern: name + age? + visual? + action? + attitude?

### F. EMOTIONAL VOCABULARY HIERARCHY

Screenwriters externalize emotion. Rank these categories by density in the action lines (most frequent first), each with a tier:

1. MOTION / KINETIC (movement, speed, direction)
2. PHYSICAL / BODILY (body parts, gestures, postures, states)
3. DARK / LIGHT (shadow, brightness, visibility)
4. SOUND (noise, silence, volume, ambient)
5. VIOLENCE (impact, force, damage, threat)
6. EMOTIONAL STATE (the rare named emotion — when and why does this writer break show-don't-tell?)

For each: QUOTE 3–4 representative lines. Note which dominates and which is nearly absent. The RANK ORDER is the durable, single-source-reliable finding (it rarely inverts) — capture it as a rule candidate: e.g., "named emotion stays the rarest channel; if a passage names more emotions than it shows bodies in motion, it has drifted." That is a local, checkable instruction.

### G. CHARACTER DIALOGUE FINGERPRINT — PER MAJOR CHARACTER

For EACH character who speaks more than 5 lines:

- Name
- Speech rhythm (short bursts? long explanations? questions? commands? interruptions?)
- Verbal tics or recurring phrases (quote them)
- Vocabulary restrictions (what words would this character NEVER say?)
- Emotional range: how do they sound angry vs. afraid vs. tender vs. lying? What happens to their sentence length under pressure — shorter or longer? Deflect or confront? Quiet or loud?
- QUOTE the single line most characteristic of this character — the one you'd identify without attribution. If no such line exists, the extraction is not deep enough.

**DIALOGUE DIFFERENTIATION REQUIREMENT:** Every profiled character must sound distinctly different. Identify at least 3 linguistic markers per character. If two characters' dialogue could be swapped without the reader noticing, both fingerprints have failed. The most dangerous bleed: AI writes every character in the same register — smart, articulate, emotionally aware. Real characters are not all articulate. Some are blunt. Some evade. Some can barely speak when emotional.

For the writer's PARENTHETICAL PATTERNS:
- Does the writer use parentheticals as de facto dialogue tags? How often?
- Which recur? (beat), (then), (low), (off their look)?
- BANNED parentheticals: any the writer NEVER uses that AI defaults to — "(sadly)," "(angrily)," "(desperately)." These are ABSOLUTE bans.

### H. EMOTIONAL RANGE MAP

How does THIS writer render each emotion on the page — in action, dialogue, scene construction, pacing? For each: quote one passage, name the technique in one sentence, note the rendering method.

- TENSION, HUMOR, GRIEF, WONDER, FEAR, VIOLENCE, INTIMACY

If an emotion is ABSENT from the source, say so. Absence is data, and it is single-source-reliable.

### I. COLLOCATION FINGERPRINT — CHARACTERISTIC WORD PAIRS

Individual words can be matched by any imitator. The specific PAIRINGS cannot. These micro-signatures survive even when vocabulary is correct.

- Identify 15–20 characteristic collocations. Example: "steps forward" (not "moves forward"), "jaw tightens" (not "jaw clenches"), "breath catches" (not "gasps").
- For each: QUOTE the source instance(s), note frequency, and name the SUBSTITUTION an AI would produce instead.
- Group by category (physical action, environmental, dialogue-adjacent).

This produces a paired list — correct collocation vs. banned AI substitute — that becomes a discrete runtime check: if the substitute appears where the writer's pairing would fit, replace it. That is a search-and-fix, which the runtime can do.

### J. NEGATIVE SPACE MAP — WHAT THIS WRITER NEVER DOES

This is the richest vein of ABSOLUTE-confidence rules from a single source. For each item: name the technique, confirm absence with evidence (zero instances across N words), explain why AI defaults to it.

Examine: camera direction (ANGLE ON, WE SEE, CLOSE ON); V.O.; O.S.; MONTAGE; FLASHBACK; INTERCUT; emotional parentheticals; extended/lyrical action description; interior monologue in action ("she thought," "she realized"); novelistic interiority; monologues; direct address; dialect spelling; specific transition types.

Every confirmed zero is an ABSOLUTE ban. List them so Task 2 and Task 4 can harvest them directly.

### K. SHOW / EXPLAIN BALANCE

Assess concrete physical/sensory language vs. abstract emotional/interpretive language. Screenwriting runs inherently high on SHOW, but writers vary — some stay ruthlessly external, others slip interiority into action lines. Describe THIS writer's balance and quote both a pure-show passage and (if any exist) the rare explain moments. The durable finding is directional ("overwhelmingly external"), and it becomes a local rule: an action-derived sentence that explains rather than shows has drifted.

### L. COMPARATIVE EXCLUSION — STYLISTIC NEIGHBORS

Identify 2–3 screenwriters whose style most resembles this writer's. For each: name them, name the overlapping quality, and name at least 2 techniques that DIFFERENTIATE this writer from the neighbor. Generated prose must be attributable to THIS writer and NOT to any named neighbor. This is a build-time/QA judgment test, not a runtime check.

---

### TASK 1 OUTPUT FORMAT

```
VOICE DNA PROFILE: [TITLE] by [WRITER]
Profile Type: SCREENWRITER / TV WRITER
Extracted by: Lorespinner Voice Lock Phase (Deliverable 1B v3)
Source: [N] screenplay(s), [total] words

SIGNATURE TECHNIQUES: 1–12, each: [Quote] — [why specific] — Freq + CONFIDENCE
ACTION LINE METRICS: each metric with value + CONFIDENCE; paragraph-rhythm alternation described; 3–4 consecutive representative quotes
DIALOGUE METRICS: each metric with CONFIDENCE; longest-speech ceiling captured; representative exchanges
DICTION FINGERPRINT: clusters, register, overused/avoided, punctuation counts + CONFIDENCE; 5–6 quotes
STRUCTURE METRICS: density, ratios, transitions, parenthetical vocabulary (used / never), intro pattern
EMOTIONAL VOCAB HIERARCHY: ranked 1–6 with densities; rank-order rule stated
CHARACTER FINGERPRINTS: per character — rhythm, tics, restrictions, pressure behavior, 3+ markers, signature line
PARENTHETICALS: used / BANNED
EMOTIONAL RANGE: 7 emotions, each quote + technique (or ABSENT)
COLLOCATIONS: 15–20 pairs — writer's pairing vs. banned AI substitute
NEGATIVE SPACE: comprehensive zero-list, each an ABSOLUTE ban
SHOW/EXPLAIN: directional balance + quotes
COMPARATIVE EXCLUSION: neighbors + differentiators
```

---

## TASK 2 — MASTER RULE 1: HARD BAN LIST

This is the immune system. Bans are the cleanest runtime tool because they are binary: a token or pattern is present or it is not. The narrator can search for and remove them. Any occurrence is a hard fail.

### SECTION A: UNIVERSAL BANS (hardcoded — identical for every IP, every format)

These are the floor. They cannot be overridden by any IP-specific rule or voice profile.

**PUNCTUATION**
- Em dashes in all variants (—, --) in GENERATED prose. Use periods, commas, or restructure. EXCEPTION: if the source writer demonstrably uses them (documented in Task 1 with a count), they are permitted ONLY in the exact patterns the writer uses, never as decorative thought-connectors.
- Ellipses (…) in narration. Dialogue only when a character's established speech pattern trails off.
- Emoji. Never.

**SENTENCE MOLDS**
- "It's not X, it's Y." (false-correction pivot)
- "No X. No Y. Just Z." (stripped-down tricolon)
- Balanced rule-of-three tricolons used as SMOOTH, CONNECTIVE rhetoric — the flowing kind: "offering restoration, offering mercy, offering the lie," or "the way objects are, the way the cold coffee is, the way the morning light is." CALIBRATION (critical — do not over-enforce): this ban targets the smooth connective triad, NOT the compressed fragment-punch triad. "Suit. Skin. Geometry." and "Catalog. Window. Bench." are fragment punches — short, hard, period-separated — and are on-voice for a compression writer. The test is smoothness, not the count of three: if the three elements flow together with connective tissue (repeated participles, "the way… the way…," parallel clauses sharing a verb), it is the AI tell — cut it. If they are clipped standalone fragments, they are voice — keep them. Because this distinction requires judgment, it is a build-time / model-judgment call, never a runtime auto-fail
- Mid-sentence rhetorical check-ins: "And honestly?" / "And really?" / "And look,"
- Trailing "like [metaphor]" similes in action lines (dialogue exempt if character voice supports it)
- Contrast scaffolding: "She had always thought X. But now Y."
- Symmetrical lists for false profundity
- Generic uplift wrap-ups: unearned wisdom at the end of a passage

**VOCABULARY**
- tapestry (metaphor), delve, underscore, highlight, showcase, intricate, swift, meticulous, adept
- "just" as a softener (dialogue-only where character voice requires)
- "that resonates / tracks / matters / lands"
- "woven into" / "weaving" as a metaphor for connection
- "meaningful" for connections, moments, experiences
- "nestled" / "tucked away" (metaphorical only; literal placement fine)
- "etch / etched" for memory or emotion
- "navigate" for emotional/social situations (literal navigation fine)
- "beautiful / wonderful / incredible / amazing" as intensifiers

**AI FICTION MOTIFS**
- ghosts, spectral, shadow, whisper, quiet, hum, echo, liminal, phantom as DEFAULT atmospheric texture (permitted only when the IP's canon includes them as world elements)
- "Something shifted / clicked / broke" as an emotional transition
- "letting out a breath they didn't know they were holding"
- eyes "searching" faces
- silence that "stretches" / "hangs" / "fills the room"
- hearts that "hammer" / "race" / "skip"
- weather mirroring emotion unless the writer demonstrably uses pathetic fallacy

**NAMES**
- Elara, Voss, Kael, Echo (as a name), Ghost Code, Luminara, Seraphina, Thorne, Cipher, Nexus
- Any name not in the source IP's canon

**CORPORATE / PR**
- "woven into your daily rhythm" / "memories were made" / "meaningful connections"
- Any phrasing that reads like brand copy

**STRUCTURAL AI TELLS** — these use banned PATTERNS, not banned words, so they survive token scanning. Each note marks whether it is a DISCRETE/LOCAL check (runtime-enforceable) or a JUDGMENT check (build-time/model review).

1. **HALLUCINATED SEPARATION** — cognitive narration between character and experience. Banned: "She realized she was feeling…," "He found himself [verb]-ing," "It occurred to her…," "She couldn't help but…," "He became aware of…," "There was a [emotion] in her [body part]." INSTEAD: render directly — "Her hands shook," not "She realized her hands were shaking." [DISCRETE — these are searchable lead-ins.] SCREENWRITER NOTE: the camera cannot film "realized."

2. **META-REFERENCES** — narration referencing the story AS a story. Banned: "This was the kind of moment that…," "In that moment, everything changed," "What happened next would…," "She would later remember…," "Little did she know…." INSTEAD: show the scene; let significance arrive through what happens. [DISCRETE — searchable phrases.]

3. **ESSAY LINE** — interpretive commentary explaining what an image means. Banned: "a reminder that…," "a testament to…," "as if [explanation]," any sentence that follows a concrete image with its interpretation. INSTEAD: show the image, stop. [PARTLY DISCRETE — lead-ins searchable; subtler cases are judgment.] SCREENWRITER NOTE: this is the deadliest ban for screenwriter voice.

4. **PRONOUN CLUSTERING** — three or more consecutive sentences opening with the same pronoun = HARD FAIL. Two consecutive = caution (allowed for deliberate rhythm; if chronic — more than ~5 instances per 1000 words — treat as fail at build-time QA). Applies to action/narration, NOT dialogue. [DISCRETE/LOCAL — the narrator can see its last three sentence openers.] Repair via the Repair Distribution Rule below.

5. **META-NARRATION** — the narrator commenting on narration, stories, or the reader. Banned: "But that's not how this story goes," "The truth was simpler / more complicated," "Perhaps that was the point," "And maybe that was enough," any direct address to the reader (unless the writer's documented voice uses it). INSTEAD: stay inside the fictional dream. [PARTLY DISCRETE.]

6. **FREQUENCY DRIFT** — a signature technique deployed so often it becomes a tic / parody. [JUDGMENT — this is a rate, so it is a BUILD-TIME QA check, not a runtime check. The runtime cannot measure its own frequency. Do not ask it to.]

7. **EXPLANATORY COMMENTARY** — telling the reader what to think or feel. Banned lead-ins: "It was clear that," "Obviously," "Clearly," "Without a doubt." Banned: declarative emotional summaries — "She was devastated," "He was furious." Use the writer's documented rendering instead. [PARTLY DISCRETE — lead-ins searchable; declarative summaries are judgment.]

**REPAIR DISTRIBUTION RULE** — when fixing pronoun clusters, over-long lines, or any structural violation, cycle through fix techniques; do not default to one (e.g., -ing openers) until it becomes a new tic. Available: (1) character name as subject, (2) object-as-subject, (3) action-first / -ing opening, (4) environmental detail / new beat, (5) dependent-clause opener, (6) sentence merge. Use a different technique than the last fix.

**NEGATION-THEN-POSITIVE CUTTING** — when a negation is followed by an obvious positive ("Not angry. Hurt."), cut the negation if the positive carries the weight alone. EXCEPTION: keep it when the reader genuinely needs the negated image to read the positive one (the writer's documented double-exposure construction, if any).

### SECTION B: IP-SPECIFIC BANS (generated per writer from Task 1)

From the Voice DNA Profile, generate bans in four categories. For each: STATE the ban, CITE the Task 1 evidence (with confidence tier), and give the positive replacement. Only ABSOLUTE and HIGH-confidence findings may become hard bans; MEDIUM/LOW become build-time cautions.

1. ANTI-PATTERNS — techniques this writer never uses that AI defaults to (interior monologue in action, decorative metaphor, compound 25+-word action lines, emotional parentheticals, expository dialogue, atmosphere without function)
2. VOCABULARY THE WRITER AVOIDS — words conspicuously absent despite opportunity
3. RHYTHM VIOLATIONS — patterns that contradict the writer's compression (expressed as a local rule wherever possible: e.g., "no action-line sentence over 20 words" if the source has effectively none)
4. STRUCTURAL VIOLATIONS — screenplay devices the writer never uses (WE SEE, V.O., O.S., MONTAGE, editorial transitions)

```
MASTER RULE 1: HARD BAN LIST FOR [TITLE]
UNIVERSAL BANS: [Section A verbatim]
IP-SPECIFIC BANS: 1..N — [BAN] | evidence + tier | INSTEAD: [replacement]   (minimum 6)
```

---

## TASK 3 — THE VOICE ANCHOR (the centerpiece)

This is the output that prevents decay. Everything in Tasks 1 and 2 DESCRIBES the voice. The Voice Anchor SHOWS it — in the exact form the runtime must produce. The live narrator imitates a present example far more faithfully than it follows an abstract description, and a fixed example cannot drift. This is also where you close the screenplay-to-prose format gap: you do the hard translation ONCE, here, under full scrutiny, instead of asking the runtime to improvise it every turn.

**What you are producing:** 6–8 short PROSE passages, written in SECOND-PERSON PRESENT TENSE (the runtime's native mode), each one a faithful translation of the writer's voice from screenplay form into interactive-story narration. These are locked. They ship into the runtime prompt verbatim and are re-read by the narrator every turn.

### How to build each exemplar

1. **Start from real source.** Choose a genuine moment from the screenplay — an action beat, a tense exchange, a quiet aftermath, an environmental establishing moment. Do not invent from nothing; translate something the writer actually wrote, so the voice is authentic, not your impression of it.

2. **Translate, do not transcribe.** Convert screenplay form to prose using the Screenplay-to-Prose Translation Protocol below. Preserve the writer's compression, fragment habit, paragraph-rhythm alternation (clustered vs. isolated), diction, collocations, emotional-rendering channel order, and dialogue ceilings. Shift the camera-eye to the player's body: "INT. CORRIDOR — Akira moves through" becomes "You move through the corridor."

3. **Obey every ban.** The exemplar must pass Task 2 cleanly. It is a model of correct output; a flaw here teaches the runtime the flaw.

4. **Stay off-episode.** Each exemplar should draw on a DIFFERENT moment than any single episode will reuse heavily, and the set as a whole must carry a label: *match the rhythm, diction, and compression of these passages; never reuse their imagery, lines, or content.* This prevents the runtime from plagiarizing the anchor instead of imitating its texture.

### Required coverage (the set must span the writer's range)

Produce at least one exemplar in each of these modes, so the runtime has a model for every situation it will face:

- **Cold tension / forward pressure** (a moment pulling the player ahead)
- **Physical action** (the writer's kinetic register, violence or movement)
- **Quiet / aftermath** (the writer's stillness — earned silence, low emotion)
- **Environmental establishing** (how the writer makes a space act)
- **Dialogue-bearing narration** (prose carrying a short exchange — demonstrating the dialogue ceiling and at least one character's fingerprint)
- **Emotional weight without naming emotion** (the writer's show-not-explain at its hardest)

Two of the eight may double up on whichever modes the writer is most distinctive in.

### Length and form

- Each exemplar: 90–150 words. Long enough to establish rhythm, short enough to re-read cheaply every turn.
- Write them as the narrator would actually deliver them — no commentary, no labels inside the prose itself.
- Above each, in a header line, note: the mode, the source moment it was translated from, and the 2–3 signature techniques it demonstrates. (This header is metadata for QA and for the assembly job; it is not delivered to the player.)

### Self-validation gate (run before locking)

For the exemplar set as a whole, confirm:
- Read any one exemplar cold. Could a reader familiar with this writer attribute it to them? If it reads like any competent novel, it has failed — rebuild it with more signature technique.
- Does the set obey every Universal and IP-Specific ban?
- Does the set demonstrate the collocations, the paragraph-rhythm alternation, and the emotional-channel order from Task 1?
- Do the dialogue-bearing exemplars respect the longest-speech ceiling?

If any answer is no, revise before locking. These passages are the single most load-bearing voice signal in the entire runtime. They are worth getting right.

```
THE VOICE ANCHOR: [TITLE] by [WRITER]
[For each of 6–8 exemplars:]
--- EXEMPLAR [n] | Mode: [mode] | Translated from: [source moment] | Demonstrates: [techniques] ---
[90–150 words of second-person present-tense prose in the writer's voice]

USAGE LABEL (ships to runtime): "Match the rhythm, diction, and compression of these passages. Never reuse their imagery, lines, or content."
```

---

## TASK 4 — THE ANCHOR CARD (re-read every turn at runtime)

The Voice Anchor shows the voice. The Anchor Card states the non-negotiable rules in the shortest possible form, so the runtime can re-read it every turn without burning the token budget. It is the distilled, binary/local core of Tasks 1–2.

**Eligibility is strict.** A rule may go on the Anchor Card ONLY if it is BOTH:
- ABSOLUTE or HIGH confidence (Task 1 tier), AND
- expressible as a DISCRETE or LOCAL check — a token to search for, or a pattern visible within a few sentences.

If a rule requires counting a rate over a passage, it does NOT go on the card. It goes to Build-Time QA (Task 6). This eligibility test is what keeps the runtime honest.

Produce 8–12 rules, each phrased as a direct command the narrator can act on in one read. Draw from: the writer's ABSOLUTE punctuation/structural bans, the collocation substitutions, the longest-speech ceiling, the emotional-channel rule, the pronoun-cluster rule, and the 1–2 most pervasive signature habits expressed locally.

Phrase them as actions, not statistics. Examples of the correct FORM (populate with THIS writer's actual values):

- "Output zero em-dashes and zero double-hyphens. After writing, search the passage for — and -- and delete every one."
- "No sentence runs longer than about 20 words. If one does, cut it in two."
- "Never let three sentences in a row open with the same word. Vary the opener."
- "No character speaks more than [N] words / [N] sentences before action interrupts. [Writer's ceiling from Task 1-C.]"
- "Use '[writer's collocation]' — never '[AI substitute].'" (the 4–6 highest-frequency pairs)
- "Name an emotion only as a last resort. Show the body first. If a passage names more feelings than it shows physical action, rewrite it."
- "No interior monologue. The camera cannot film 'she realized.' Cut every cognitive lead-in (realized, noticed, became aware, found himself)."
- "Triads must be fragment punches, never smooth rhetoric. 'Suit. Skin. Geometry.' stays. 'offering X, offering Y, offering Z' and 'the way A, the way B, the way C' do not — if three parallel elements flow together with connective tissue, break the pattern." (Phrase as guidance; this one is a judgment call, not a hard auto-fail.)
- "[The writer's 1–2 most pervasive HIGH-confidence habits, stated as a do-rule.]"

```
THE ANCHOR CARD: [TITLE] by [WRITER]
[8–12 binary/local commands, each ABSOLUTE/HIGH-confidence and discretely checkable]
```

---

## TASK 5 — RUNTIME SELF-CHECK PROTOCOL

This is the pre-delivery pass the live narrator runs on each passage BEFORE showing it to the player. It contains ONLY discrete/local checks — searches and local-pattern scans the narrator can actually perform. It deliberately contains no rate computations. It is short by design; a long checklist degrades into the same theater that failed before.

Design the protocol as a tight, ordered sequence the narrator executes silently each turn. Use this template, populated with the writer's specifics from the Anchor Card:

```
RUNTIME SELF-CHECK (run silently before delivering each passage):
1. SEARCH for — and --. Delete every one; restructure the sentence.
2. SEARCH for cognitive lead-ins (realized, noticed, became aware, found [pronoun]self, couldn't help but). Delete; render the experience directly.
3. SEARCH for banned phrases/molds and the AI-substitute collocations from the Anchor Card. Replace with the writer's pairing or cut.
4. SCAN the last three sentence openers: are any three consecutive the same word/pronoun? If so, vary one using a different Repair technique than last time.
5. SCAN each sentence length: is any sentence runaway-long (well past the writer's ceiling)? Cut it down.
6. SCAN dialogue: did any character exceed the speech ceiling? Compress.
7. GLANCE at the nearest Voice Anchor exemplar: does this passage share its texture — compression, paragraph rhythm, externalized emotion? If it reads smoother, more generic, more explanatory than the exemplar, rewrite toward the exemplar.
If any step triggers a fix, apply it, then deliver. Do not report the check to the player.
```

This protocol is designed to be reusable. If decay persists in testing, the identical checklist can be lifted into a separate second-pass "voice editor" model call with no rework — the steps are the same; only the executor changes from the narrator-in-line to a dedicated pass.

---

## TASK 6 — BUILD-TIME QA PROTOCOL (never runs at runtime)

This is the home for everything numeric. It runs ONCE per IP, on a batch of sample outputs generated from the assembled runtime prompt, BEFORE the IP ships. It is executed by a human reviewer, a batch evaluation, or the audit linter if and when one exists — never by the live narrator. Its purpose is to catch decay and miscalibration before players ever see output, and to validate that the Anchor and Card are doing their job.

Generate 8–12 sample passages from the candidate runtime prompt (mix of cold open, branch outcomes, freeform responses, a long sequence of 600+ continuous words to surface decay). Then assess:

**Quantitative checks (judgment-assisted or linter):**
- Fragment / sentence-length distribution vs. the Task 1 ranges (diagnostic — flag large divergence, not minor variance)
- Punctuation densities, especially the ABSOLUTE bans (em-dash, semicolon) — these must be at or near zero
- Longest generated speech vs. the captured ceiling
- Signature-technique frequency (Frequency Drift) — is any technique over-deployed into a tic?
- Decay test: compare the first 200 words and the last 200 words of the long continuous sample. If the tail is measurably smoother, longer-sentenced, or more explanatory than the head, decay is occurring — strengthen the Anchor re-assertion cadence in D8 or add the second-pass editor.

**Judgment checks (model or human):**
- Blind attribution: read a sample cold against a real source passage. Can a familiar reader tell which is which? If yes, fail.
- Comparative exclusion: does any sample read as one of the named neighbor writers?
- Character differentiation swap test across any dialogue-bearing samples.

Record results. If the IP fails, the fix is upstream — richer exemplars, sharper Anchor Card, or tuning D8's re-assertion — not a runtime numeric gate, which cannot exist.

---

## SCREENPLAY-TO-PROSE TRANSLATION PROTOCOL

These mappings govern how screenplay elements become prose narration. They are mechanical, and they are the rules you apply when building the Voice Anchor (Task 3). The Voice Anchor itself then becomes the runtime's living translation reference — the runtime imitates the worked examples rather than re-deriving these mappings.

| Screenplay Element | Prose Translation | Rule |
|---|---|---|
| Action line | Narrative sentence (2nd-person present) | Preserve compression and the writer's sensory-channel priority. Do NOT expand into novelistic description. |
| Scene heading (INT./EXT.) | Scene break or single establishing sentence | One sentence or a white-space break. Never a descriptive paragraph. Never "Chapter X." |
| CUT TO / SMASH CUT | Paragraph or scene break | Hard break, no bridging, no "Meanwhile." DISSOLVE may take one brief bridging sentence. |
| Parenthetical | Dialogue-tag modification | "(low)" → "she says, low." Never narrator commentary on emotion. |
| Character cue | Dialogue attribution | Minimal tags. "Says" dominant. No "exclaimed / declared / mused." |
| ALL CAPS emphasis | Italic/bold, sparingly | Never translates to exclamation marks or narrator excitement. |
| (beat) | A physical action between lines | The pause is a body doing something — a hand closes, eyes drop. Never "There was a moment of silence." |

The camera-to-body shift is constant: the screenplay photographs the protagonist from outside; the prose places the player inside the protagonist's body. "Akira's hands shake" becomes "Your hands shake."

---

## FINAL OUTPUT — VOICE LOCK PHASE COMPLETE

Assemble all six tasks into one VOICE PROFILE DOCUMENT. The ordering matters: the runtime-critical material (Anchor Card, Voice Anchor, Self-Check) is flagged so the assembly job knows it is load-bearing and must never be the first thing cut under token budget.

```
=== VOICE PROFILE: [TITLE] by [WRITER] ===
=== Profile Type: SCREENWRITER / TV WRITER ===
=== Extracted by Lorespinner Voice Lock Phase (Deliverable 1B v3) ===
=== Single-source extraction. This document is CONSTITUTIONAL LAW for all subsequent phases. ===

SECTION 1: VOICE DNA PROFILE              [Task 1 — full]
SECTION 2: MASTER RULE 1 — HARD BAN LIST  [Task 2 — Universal + IP-Specific]
SECTION 3: THE VOICE ANCHOR               [Task 3 — 6–8 locked exemplars]   ★ RUNTIME-CRITICAL — load verbatim, cut last
SECTION 4: THE ANCHOR CARD                [Task 4 — 8–12 binary/local rules] ★ RUNTIME-CRITICAL — re-assert every turn, never cut
SECTION 5: RUNTIME SELF-CHECK PROTOCOL    [Task 5]                           ★ RUNTIME-CRITICAL
SECTION 6: BUILD-TIME QA PROTOCOL         [Task 6 — never reaches runtime]
SECTION 7: SCREENPLAY-TO-PROSE PROTOCOL   [translation table]

PIPELINE INTEGRATION NOTES:
- This Voice Profile is constitutional law; when voice rules conflict, it wins.
- PIPELINE creates context (this phase). RUNTIME uses context (Deliverable 8). Never blurred:
  this phase DESIGNS the exemplars, rules, and checks; the runtime narrator IMITATES and EXECUTES them.
- Feeds into: Phase 2 (character voice reference), Phase 5 (authored prose in choices),
  Deliverable 8 Runtime Narrator Template.
- RUNTIME LOADING CONTRACT (for the D8 assembly job):
    * Section 3 (Voice Anchor) loads into D8 Section 4 VERBATIM. It is NOT compressed to
      technique names. Under token pressure it is the LAST voice material cut, not the first.
    * Section 4 (Anchor Card) is re-stated at the END of the runtime prompt and is re-read
      before each generation. It is never cut.
    * Section 5 (Self-Check) runs before each delivered passage.
    * Section 6 (QA) does NOT ship to runtime. It is a pre-launch gate only.
- No linter or external tool is required at runtime. All runtime enforcement is binary/local,
  performed by the narrator (or, optionally, a second-pass editor reusing Section 5).
=== END VOICE PROFILE ===
```

---

## VERIFICATION GATE

STOP. DO NOT PROCEED TO PHASE 2 until all of the following pass.

**Blind test:** Generate one 200-word second-person passage using only this Voice Profile, and one 200-word passage of generic competent prose on the same subject. If you cannot instantly tell which is the writer, the profile is incomplete — revise.

Then confirm:
1. Does the Voice Anchor contain 6–8 exemplars spanning the required modes, each passing every ban? If not, the runtime has nothing faithful to imitate.
2. Is every Anchor Card rule both ABSOLUTE/HIGH-confidence AND discretely checkable? Strike any rule that requires counting a rate — it does not belong at runtime.
3. Does the Negative Space Map yield at least 5 ABSOLUTE bans? If not, the single-source immune system has gaps.
4. Does the collocation fingerprint contain at least 15 pairs, each with its banned AI substitute?
5. Can comparative exclusion name 2–3 writers this voice must not be confused with?
6. Is the longest-speech ceiling captured and reflected in the Anchor Card?

If any answer is no, revise. The writer's voice is the product. There is no "close enough."

---

## END OF DELIVERABLE 1B v3 — SCREENWRITER / TV WRITER VOICE LOCK

