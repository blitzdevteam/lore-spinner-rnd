# DELIVERABLE 1A v2: VOICE LOCK PHASE PROMPT — NOVELIST / AUTHOR

**Lorespinner Pipeline Upgrade — June 2026**
**Version:** 2.0
**Replaces:** Deliverable 1A FINAL (when source = novel/prose)
**Type:** Pipeline phase (runs once per IP, at build time)
**Inserts:** Between IP Audit and Story Session Map
**Format Gate:** Runs ONLY when `format_detection.type` is `"novel"`, `"prose"`, `"short_story"`, or `"essay"`. If the source is a screenplay, STOP and use the Screenwriter Voice Lock Prompt (Deliverable 1B v3).
**Implementation:** Copy-paste this prompt into VoiceLockChapterJob. One conditional selects this (1A) vs the screenwriter version (1B) based on FormatDetectionJob output. No linter or external dependency is required to run this prompt or to enforce its output at runtime.

---

## WHY THIS VERSION EXISTS — READ BEFORE RUNNING

1A FINAL produces strong novelist extractions — its DNA capture and ban generation are unchanged here and remain the working core. v2 adds the missing piece that prevents voice decay at runtime, and brings 1A into line with the screenwriter prompt (1B v3) so a single Runtime Narrator Template (Deliverable 8 v2) can serve both pipelines.

The principle, identical across both formats:

> **A voice is held at runtime by IMITATION and by BINARY, LOCAL RULES — never by counting.**

An LLM cannot reliably count its own output, and there is no linter in the runtime. So numeric targets cannot be enforced live; they are diagnostic only. This version therefore splits every constraint into two homes:

- **Discrete-and-local checks** (search for a token and remove it; spot a pattern visible within a few sentences) belong at runtime. The narrator can actually perform these.
- **Global-and-numeric checks** (rates, distributions) belong at build time only — in this extraction and in pre-ship QA.

v2 adds three outputs to the existing three, so the runtime gets material it can use: a locked set of prose **exemplars** the narrator imitates (Task 4), a short **Anchor Card** of binary/local rules re-read every turn (Task 5), and a **Runtime Self-Check** the narrator can actually run (Task 6). The existing 14-point audit is preserved, repositioned as the **Build-Time QA Protocol** (Task 3) — its proper home, since it is numeric and judgment-based.

### Novelist note — the format gap is smaller, but it is not zero

A screenplay must be translated into prose; a novel is already prose, so 1A does not face that compression gap. But the runtime narrates in **second-person present tense** ("You step into the room…"), while most novels are first- or third-person past tense. So the novelist Voice Anchor still requires one conversion — POV and tense — done once, here, and locked. Everything else (sentence rhythm, paragraph architecture, diction, narrator flavor) is preserved near-verbatim from the source, because it is already in the target prose medium. This makes the novelist anchor easier and more faithful than the screenwriter's: the exemplars are real source passages, lightly converted, not rebuilt.

### Single-source note

In the product, one work is uploaded per IP. For novels this is far richer than for screenplays (a novel runs 60,000–150,000+ words vs. a ~20,000-word screenplay), so single-source statistical thinness is much less severe. The Confidence Framework below still governs which findings are reliable enough to become runtime rules.

### Backward-compatibility note (for the pipeline — non-breaking)

This upgrade is additive. **Section 1 (Voice DNA Profile) and Section 2 (Master Rule 1) are unchanged in name and position**, so every existing downstream reference — Phase 4 Beat Architecture and Phase 5 Choice Design both paste "Section 1" / "Section 2" — remains valid with no change. The new outputs (Voice Anchor, Anchor Card, Self-Check) are appended as new named sections and are consumed only by the Runtime Narrator Template (D8 v2). The one move to be aware of: the old 14-point audit, formerly "Section 3," is now the **Build-Time QA Protocol** section. If any job references the audit by section number, update it — or, preferred, have the assembly map every section by its **header name**, not its index. An IP still built on the old 1A keeps working; rebuild it on 1A v2 only when you choose to.

---

## COPY-PASTE PROMPT BELOW THIS LINE

---

LORESPINNER — VOICE LOCK PHASE: NOVELIST / AUTHOR VOICE EXTRACTION AND PROTECTION

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 1 AUDIT: [PASTE SCORECARD]

FORMAT DETECTION: [PASTE FORMAT DETECTION OUTPUT — must confirm type = novel/prose/short_story/essay]

SOURCE TEXT UPLOADED: [TITLE], [AUTHOR], [YEAR], [FORMAT: NOVEL / SHORT STORY / ESSAY]

WHAT TO UPLOAD: The COMPLETE source text. Not samples, not excerpts. Upload every page. If token limits require splitting, upload in halves and run this phase twice, merging outputs. Multiple works by the same author should all be uploaded — cross-corpus extraction produces stronger profiles.

---

You are performing the most important job in the Lorespinner pipeline. Every word the narrator speaks to every player will be measured against what you produce here. This is not analysis. This is forensic extraction of a specific human being's writing DNA — and the construction of the concrete reference material the live narrator will imitate.

The output of this phase becomes CONSTITUTIONAL LAW. It overrides every subsequent phase. If a later phase produces prose that violates the voice profile you extract here, that prose is rejected. No exceptions. No "close enough." The author's voice is the product.

THIS IS A NOVELIST / AUTHOR EXTRACTION. You are analyzing prose fiction — novels, novellas, short stories, or essays. The voice lives in narrative sentences, paragraph construction, narrator perspective, and rhetorical architecture. Do NOT apply screenplay metrics. If the source is a screenplay, STOP and switch to Deliverable 1B v3.

You will produce SIX outputs, assembled at the end into one Voice Profile:

1. **TASK 1 — Voice DNA Profile**
2. **TASK 2 — Hard Ban List** (binary prohibitions)
3. **TASK 3 — Build-Time QA Protocol** (the 14-point audit — runs pre-ship, never at runtime)
4. **TASK 4 — The Voice Anchor** (locked prose exemplars the runtime imitates) ← the centerpiece
5. **TASK 5 — The Anchor Card** (binary/local rules re-read every turn at runtime)
6. **TASK 6 — Runtime Self-Check Protocol** (the discrete pre-delivery pass the narrator performs)

---

## TASK 1 — AUTHOR VOICE DNA EXTRACTION

Read the complete source text. You are not summarizing the story. You are studying HOW this specific human writes. Ignore plot. Ignore theme. Focus exclusively on craft mechanics.

**CONFIDENCE TAGGING:** For every measurable metric, tag the reliability of the finding so Tasks 5 and 6 know what may become a runtime rule:
- **ABSOLUTE** — zero-occurrence across the whole source (the author NEVER does this). Eligible to become a binary runtime rule and a hard ban.
- **HIGH** — pervasive, hundreds of instances. Eligible for a runtime rule if discretely expressible; otherwise informs exemplars and QA.
- **MEDIUM** — a real but not dominant pattern. Informs exemplars and QA only; never a hard runtime rule.
- **LOW** — rare. Guidance only.

Extract the following. Every item requires at least one DIRECT QUOTE as evidence. (The strongest quotes are reused in Task 4 as exemplar seeds.)

### A. SIGNATURE WRITING TECHNIQUES (extract 8–12)
For each: NAME it (2–4 words); QUOTE 2–3 lines; EXPLAIN in one sentence what makes it specific to THIS author; NOTE frequency + confidence tier. Test: could another skilled writer produce it by accident? If yes, dig deeper.

### B. SENTENCE-LEVEL PATTERNS
- Average sentence length and range across contexts; cadence variation (alternate long/short? build? punch? fragments — where?); clause-structure preference; punctuation habits (semicolons? comma density? sparse?). QUOTE 3–4 consecutive sentences at the author's most distinctive rhythm. Tag confidence. These are GUIDANCE for the exemplars and QA, not runtime targets.

### C. DICTION FINGERPRINT
- Word-length tendency; vocabulary clusters; register and shifts; formality; words used MORE / AVOIDED vs. a generic narrator. QUOTE 5–6 lines of diction no other writer would make the same way.

### D. NARRATOR PERSPECTIVE AND VOICE (NOVELIST-SPECIFIC)
- POV (1st / 3rd limited / 3rd omniscient / 2nd / shifting); reliability; distance (close vs. observing); commentary (editorial? philosophical asides? invisible?); tense; interior-monologue patterns (direct / indirect / stream-of-consciousness / action-only). QUOTE 3–4 passages of the narrator's distinctive relationship to the story.
- **Enforcement + carve-out:** If generated text adopts a stance contradicting the documented perspective, it fails. AND — critically — record here any techniques this author uses *deliberately* that AI is normally banned from (cognitive verbs / free indirect discourse, prolepsis/flash-forward, direct address, philosophical commentary). These documented techniques are PERMITTED for this IP and must be carved out of the bans in Task 2 and the Anchor Card in Task 5.

### E. PARAGRAPH ARCHITECTURE (NOVELIST-SPECIFIC)
- Average paragraph length and range (short punches / long blocks / mixed); internal build logic (topic-then-elaboration? image-then-reaction? action-then-consequence?); transitions between paragraphs (hard cuts / bridges / temporal markers / white space / thematic links); chapter/section openings and closings. QUOTE 2 consecutive paragraphs at the author's most characteristic.

### F. DIALOGUE FINGERPRINT — PER MAJOR CHARACTER
For EACH character with more than 5 lines: name; speech rhythm; verbal tics (quote); vocabulary restrictions; emotional range (what happens to sentence length under pressure — shorter/longer, deflect/confront, quiet/loud); the single most characteristic line. Also capture the **longest speech** the character gives (the runtime speech ceiling).
**DIFFERENTIATION REQUIREMENT:** every character distinct; ≥3 markers each; swap test must fail to swap. AI's danger is writing all characters in one articulate register — real characters are blunt, evasive, or barely verbal under strain.
DIALOGUE TAG PATTERN: "said" percentage; other tags + frequency; action-beat usage; BANNED tags the author never uses ("mused," "declared," "breathed," "opined").

### G. EMOTIONAL RANGE MAP
For TENSION, HUMOR, GRIEF, WONDER, FEAR, VIOLENCE, INTIMACY: quote one passage, name the technique, note the rendering method. Absence is data — note it.

### H. COLLOCATION FINGERPRINT — CHARACTERISTIC WORD PAIRS
15–20 collocations the author habitually uses ("singular case," not "unusual case"; "keen eyes," not "sharp eyes"). For each: quote, frequency, and the AI substitute it must never be replaced by. Group by category. This produces the correct-pairing-vs-AI-substitute list used as a discrete runtime check.

### I. NEGATIVE SPACE MAP — WHAT THIS AUTHOR NEVER DOES
The richest vein of ABSOLUTE bans. For each: name the technique, confirm absence with evidence, explain why AI defaults to it. Examine interior-monologue techniques, descriptive techniques (extended metaphor? pathetic fallacy? sensory catalog?), structural techniques (flashback? epistolary? marked time-jump?), dialogue techniques (dialect spelling? phonetic rendering?), emotional rendering (naming emotions? physiological cliché?). Every confirmed zero is an ABSOLUTE ban.

### J. SHOW/EXPLAIN BALANCE
Characterize concrete/sensory vs. abstract/interpretive language. NOTE: prose fiction runs a naturally higher explain component than screenwriting (narrators have interior access) — calibrate to THIS author's balance, not a universal standard. An author who explains 20% is as distinctive as one who explains 2%. The durable finding is directional and becomes a local rule: generated text significantly more explanatory than the source has drifted.

### K. COMPARATIVE EXCLUSION — STYLISTIC NEIGHBORS
2–3 authors who most resemble this one; for each, the overlapping quality and ≥2 differentiators. Generated text must be attributable to THIS author and not a neighbor. (Build-time/QA judgment test.)

```
TASK 1 OUTPUT FORMAT (carried from 1A FINAL, with CONFIDENCE tags added):
SIGNATURE TECHNIQUES | SENTENCE PATTERNS | DICTION FINGERPRINT | NARRATOR PERSPECTIVE (+ documented-exception list) |
PARAGRAPH ARCHITECTURE | CHARACTER DIALOGUE FINGERPRINTS (+ longest-speech ceiling) | DIALOGUE TAG PATTERN |
EMOTIONAL RANGE | COLLOCATION FINGERPRINT (pair vs. AI substitute) | NEGATIVE SPACE MAP (ABSOLUTE bans) |
SHOW/EXPLAIN BALANCE | COMPARATIVE EXCLUSION
```

---

## TASK 2 — MASTER RULE 1: HARD BAN LIST

The immune system. Bans are binary — present or not — which makes them the cleanest runtime tool. Any occurrence is a hard fail.

### SECTION A: UNIVERSAL BANS (hardcoded — identical for every IP, every format)

**PUNCTUATION**
- Em dashes (—, --) in GENERATED prose. EXCEPTION: if the author demonstrably uses them (documented in Task 1-B with confidence), permitted ONLY in the author's patterns — never as decorative thought-connectors. (Note: many novelists DO use em-dashes; this is an author-derived rule, not a blanket zero — see the Anchor Card.)
- Ellipses (…) in narration. Dialogue only when a character's established speech trails off.
- Emoji. Never.

**SENTENCE MOLDS**
- "It's not X, it's Y." (false-correction pivot)
- "No X. No Y. Just Z." (stripped-down tricolon)
- Balanced rule-of-three tricolons used as SMOOTH, CONNECTIVE rhetoric ("offering A, offering B, offering C"). CALIBRATION: this targets the smooth flowing triad, not compressed fragment punches; if three elements flow together with connective tissue it is the AI tell — if they are clipped standalone fragments and the author uses them, they are voice. Judgment call, not a runtime auto-fail.
- Mid-sentence rhetorical check-ins ("And honestly?" / "And really?").
- Trailing "like [metaphor]" similes (unless the author's documented style).
- Contrast scaffolding ("She had always thought X. But now Y.").
- Symmetrical lists for false profundity; generic uplift wrap-ups.

**VOCABULARY**
- tapestry (metaphor), delve, underscore, highlight, showcase, intricate, swift, meticulous, adept
- "just" as softener (dialogue-only where voice requires); "that resonates / tracks / matters / lands"; "woven into / weaving" as connection metaphor; "meaningful" for moments/connections; "nestled / tucked away" (metaphorical); "etch / etched" for memory or emotion; "navigate" for emotional situations; "beautiful / wonderful / incredible / amazing" as intensifiers.

**AI FICTION MOTIFS**
- ghosts/spectral/shadow/whisper/quiet/hum/echo/liminal/phantom as DEFAULT atmosphere (permitted only if canon includes them); "Something shifted/clicked/broke"; "letting out a breath they didn't know they were holding"; eyes "searching" faces; silence that "stretches/hangs/fills the room"; hearts that "hammer/race/skip"; weather mirroring emotion unless the author uses pathetic fallacy.

**NAMES**
- Elara, Voss, Kael, Echo (as name), Ghost Code, Luminara, Seraphina, Thorne, Cipher, Nexus; any name not in canon.

**CORPORATE / PR**
- "woven into your daily rhythm" / "memories were made" / "meaningful connections"; any brand-copy phrasing.

**STRUCTURAL AI TELLS** — banned PATTERNS. Each tagged DISCRETE/LOCAL (runtime) or JUDGMENT (build-time). Every one carries the NOVELIST CARVE-OUT: if Task 1-D documents the author using the technique deliberately, it is PERMITTED within that documented pattern; the ban targets AI-default scaffolding only.

1. **HALLUCINATED SEPARATION** — cognitive narration between character and experience ("She realized she was feeling…," "He found himself…," "It occurred to her…," "became aware of…"). INSTEAD render directly. [DISCRETE — searchable lead-ins.] CARVE-OUT: free indirect discourse / stream-of-consciousness authors (documented) are exempt.
2. **META-REFERENCES** — "the kind of moment that…," "she would later remember…," "little did she know…." [DISCRETE.] CARVE-OUT: documented prolepsis (Vonnegut, Austen, Tolstoy).
3. **ESSAY LINE** — interpretive commentary on an image's meaning ("a reminder that…," "a testament to…"). [PARTLY DISCRETE.] CARVE-OUT: documented philosophical narrators (Eliot, Dostoevsky).
4. **PRONOUN CLUSTERING** — 3+ consecutive sentences opening with the same pronoun = HARD FAIL; 2 = caution. Applies to narration, not dialogue. [DISCRETE/LOCAL.]
5. **META-NARRATION** — narrator commenting on storytelling ("But that's not how this story goes," "Perhaps that was the point"). [PARTLY DISCRETE.] CARVE-OUT: documented fourth-wall authors (Sterne, Fielding).
6. **FREQUENCY DRIFT** — a signature technique over-deployed into a tic. [JUDGMENT — rate-based, so BUILD-TIME QA only, never a runtime check.]
7. **EXPLANATORY COMMENTARY** — "It was clear that," "Obviously," "Clearly"; declarative emotion summaries ("She was devastated"). [PARTLY DISCRETE.] CARVE-OUT: if the author names emotions directly as their documented technique, that is preserved; the ban prevents AI from ADDING explanation the author would not.

**REPAIR DISTRIBUTION RULE** — cycle fix techniques (character name / object-as-subject / action-first / environmental beat / dependent clause / sentence merge); never default to one until it becomes a new tic.
**NEGATION-THEN-POSITIVE CUTTING** — cut a negation followed by an obvious positive when the positive stands alone.

### SECTION B: IP-SPECIFIC BANS (generated per author from Task 1)
Four categories — each: STATE the ban, CITE Task 1 evidence + confidence, give the positive replacement. Only ABSOLUTE/HIGH become hard bans; MEDIUM/LOW become build-time cautions.
1. ANTI-PATTERNS (techniques the author never uses that AI defaults to)
2. VOCABULARY THE AUTHOR AVOIDS
3. RHYTHM VIOLATIONS (expressed locally where possible)
4. EMOTIONAL TECHNIQUE VIOLATIONS
NOVELIST NOTE: documented cognitive verbs / prolepsis / editorial commentary (Task 1-D) are permitted for this IP; bans apply to AI-default usage only.

```
MASTER RULE 1: HARD BAN LIST FOR [TITLE]
UNIVERSAL BANS: [Section A verbatim]
IP-SPECIFIC BANS: 1..N — [BAN] | evidence + tier | INSTEAD: [replacement]   (minimum 6)
DOCUMENTED EXCEPTIONS (permitted for this IP): [from Task 1-D]
```

---

## TASK 3 — BUILD-TIME QA PROTOCOL (the 14-point audit — runs pre-ship, never at runtime)

This is the 1A FINAL 14-point audit, unchanged in content but corrected in placement. It does NOT run as a live narrator self-audit — an LLM cannot count its own output, so a runtime "14/14" check is theater. It runs ONCE per IP, on a batch of sample outputs generated from the assembled runtime prompt, BEFORE the IP ships, executed by a human reviewer, a batch evaluation, or a linter if one exists. Its job is to catch decay and miscalibration before any player sees output.

Design the 14 points tailored to this IP. For each: PASS/FAIL definition, detection method, repair instruction.

1. **HARD BAN TOKEN SCAN** — zero banned tokens/phrases/molds/motifs/names.
2. **HALLUCINATED SEPARATION SCAN** — zero cognitive-verb separation (unless documented technique).
3. **META-REFERENCE / ESSAY LINE SCAN** — zero significance-commentary; zero interpretation following images.
4. **PRONOUN VARIATION** — no 3+ consecutive same-pronoun openers.
5. **FREQUENCY BALANCE** — no signature technique over-deployed.
6. **SENTENCE RHYTHM** — cadence matches documented patterns; no flatline.
7. **PARAGRAPH ARCHITECTURE** (novelist) — lengths, build, transitions match Task 1-E; not uniform.
8. **TONE AND REGISTER** — no drift to formal/neutral/academic; no generic enthusiasm.
9. **REPETITION** — no excessive content-word echo; no opener repeats in 3 sentences / 5 paragraphs.
10. **SPECIFICITY** — no vague abstraction where the author would use concrete detail.
11. **NARRATOR COMPLIANCE** (novelist) — POV, distance, reliability, commentary, tense match Task 1-D.
12. **VOICE ATTRIBUTION** — a cold passage is attributable to this author; ≥2 signature techniques per extended passage.
13. **HUMAN TEXTURE** — authored imperfections; not suspiciously uniform for 5+ paragraphs.
14. **CHARACTER DIALOGUE DIFFERENTIATION** — swap test fails to swap.

Add a DECAY TEST: generate one 600+-word continuous sample; compare its first 200 and last 200 words. If the tail is smoother, longer-sentenced, or more explanatory than the head, decay is occurring — strengthen the Anchor re-assertion in D8 or add a second-pass editor. Record results. Failures are fixed upstream (richer exemplars, sharper Anchor Card), not by a runtime numeric gate, which cannot exist.

---

## TASK 4 — THE VOICE ANCHOR (the centerpiece)

This is the output that prevents decay. Tasks 1–2 DESCRIBE the voice; the Voice Anchor SHOWS it, in the exact form the runtime must produce. The live narrator imitates a present example far more faithfully than it follows a description, and a fixed example cannot drift.

**What you are producing:** 6–8 short PROSE passages in SECOND-PERSON PRESENT TENSE — the runtime's native mode — each a faithful conversion of the author's voice into interactive-story narration. These are locked and ship verbatim into the runtime prompt.

**The novelist conversion (this is the only translation novels need):** the source is already prose, so you preserve sentence rhythm, paragraph architecture, diction, collocations, and narrator flavor near-verbatim. You convert only POV and tense: first/third-person past becomes second-person present. "Holmes leaned back and closed his eyes" becomes "You lean back. You close your eyes." Keep everything else as close to the source as the conversion allows — the closer to the author's actual words, the more authentic the anchor.

### How to build each exemplar
1. **Start from a real source passage.** Choose a genuine moment — atmosphere, tension, a quiet beat, a dialogue exchange, an action moment.
2. **Convert POV + tense only.** Apply the rule above. Do not paraphrase, smooth, or "improve" the prose — the author's words are the target.
3. **Honor the documented narrator stance.** If Task 1-D documents interiority, free indirect discourse, or editorial commentary as the author's technique, preserve it (carved out of the bans). If the author is external and invisible, keep the exemplar external.
4. **Obey every ban** (minus the documented carve-outs). The exemplar is a model of correct output.
5. **Stay off-episode.** Draw on moments different from any episode's heavy-use material, and carry the label below.

### Required coverage (span the author's range)
At least one exemplar in each mode: **atmosphere / establishing**; **rising tension or suspense**; **quiet / reflective beat**; **dialogue-bearing narration** (demonstrating a character fingerprint and the speech ceiling); **action or event**; **the author's emotional register at its most characteristic** (whatever that is — wit, dread, melancholy). Two of eight may double up on the author's most distinctive mode.

### Length and form
- Each exemplar: 90–150 words.
- Header line (metadata, not delivered to the player): mode, source passage converted, and the 2–3 signature techniques it demonstrates.

### Self-validation gate (before locking)
- Read any exemplar cold: attributable to this author? If it reads like any competent novel, rebuild with more signature technique.
- Obeys every ban (minus documented carve-outs)?
- Demonstrates the collocations, paragraph architecture, narrator stance, and emotional rendering from Task 1?
- POV is second-person, tense is present, throughout?

```
THE VOICE ANCHOR: [TITLE] by [AUTHOR]
--- EXEMPLAR [n] | Mode: [mode] | Converted from: [source passage] | Demonstrates: [techniques] ---
[90–150 words, second-person present, near-verbatim author voice]
USAGE LABEL (ships to runtime): "Match the rhythm, diction, paragraph build, and narrator stance of these passages. Never reuse their imagery, lines, or content — imitate their texture."
```

---

## TASK 5 — THE ANCHOR CARD (re-read every turn at runtime)

The distilled, binary/local core of Tasks 1–2, short enough to re-read every turn. A rule qualifies ONLY if it is BOTH ABSOLUTE/HIGH confidence AND a discrete or local check. Rate-based rules do not qualify — they go to Build-Time QA (Task 3).

Produce 8–12 rules, each a direct command. Draw from the author's ABSOLUTE structural/punctuation bans, the collocation substitutions, the longest-speech ceiling, the narrator-stance rule, the pronoun-cluster rule, and the 1–2 most pervasive signature habits expressed locally. **Respect the documented carve-outs** — if the author uses em-dashes, cognitive verbs, or editorial commentary, the card states the author's actual pattern, not a blanket ban.

Examples of the correct FORM (populate with THIS author's values):
- "Punctuation: match the author's documented habit — [e.g., em-dashes permitted in the author's pattern / semicolons frequent]. Do not add AI-default punctuation outside it."
- "Narrator stays [POV + distance + tense from Task 1-D]. Do not shift closer/farther or change tense."
- "Never let three sentences in a row open with the same word/pronoun. Vary the opener."
- "No character speaks more than [N] words before narration returns. [Author's ceiling.]"
- "Use '[author's collocation]' — never '[AI substitute].'" (the 4–6 highest-frequency pairs)
- "Render emotion the author's way: [documented method]. Do not add 'It was clear that…' or declarative emotion summaries unless the author does."
- "[Author's 1–2 most pervasive HIGH-confidence habits, as a do-rule.]"
- "Triads must be the author's kind, never smooth filler rhetoric ('offering A, offering B, offering C')." (guidance, not auto-fail)

```
THE ANCHOR CARD: [TITLE] by [AUTHOR]
[8–12 binary/local commands, ABSOLUTE/HIGH, discretely checkable, with documented carve-outs honored]
```

---

## TASK 6 — RUNTIME SELF-CHECK PROTOCOL

The pre-delivery pass the live narrator runs on each passage. ONLY discrete/local checks — no rate computations. Short by design.

```
RUNTIME SELF-CHECK (run silently before delivering each passage):
1. SEARCH for em-dashes/double-hyphens. If the author does NOT use them (Task 1), delete and restructure. If the author does, confirm usage matches the documented pattern.
2. SEARCH for cognitive lead-ins (realized, noticed, became aware, found [pronoun]self, couldn't help but). Delete unless documented as the author's technique; render directly.
3. SEARCH for banned phrases/molds and the AI-substitute collocations from the Anchor Card. Replace with the author's pairing or cut.
4. SCAN the last three sentence openers: any three consecutive the same word/pronoun? Vary one (different Repair technique than last time).
5. CHECK narrator stance: still the documented POV, distance, and tense (second-person present for the runtime)? Fix any drift.
6. SCAN dialogue: did any character exceed the speech ceiling? Compress.
7. GLANCE at the nearest Voice Anchor exemplar: does this passage share its texture and narrator stance? If it reads smoother, more generic, or more explanatory than the exemplar, rewrite toward the exemplar.
Apply fixes, then deliver. Do not report the check to the player.
```

Reusable: if decay persists in testing, this identical checklist lifts into a separate second-pass "voice editor" model call with no rework.

---

## FINAL OUTPUT — VOICE LOCK PHASE COMPLETE

```
=== VOICE PROFILE: [TITLE] by [AUTHOR] ===
=== Profile Type: NOVELIST / AUTHOR ===
=== Extracted by Lorespinner Voice Lock Phase (Deliverable 1A v2) ===
=== This document is CONSTITUTIONAL LAW for all subsequent phases ===

SECTION 1: VOICE DNA PROFILE              [Task 1 — full, with confidence tags + documented exceptions]
SECTION 2: MASTER RULE 1 — HARD BAN LIST  [Task 2 — Universal + IP-Specific + Documented Exceptions]
SECTION 3: THE VOICE ANCHOR               [Task 4 — 6–8 locked exemplars]   ★ RUNTIME-CRITICAL — load verbatim, cut last
SECTION 4: THE ANCHOR CARD                [Task 5 — 8–12 binary/local rules] ★ RUNTIME-CRITICAL — re-assert every turn, never cut
SECTION 5: RUNTIME SELF-CHECK PROTOCOL    [Task 6]                           ★ RUNTIME-CRITICAL
SECTION 6: BUILD-TIME QA PROTOCOL         [Task 3 — 14 points; never reaches runtime]

PIPELINE INTEGRATION NOTES:
- Constitutional law; when voice rules conflict, this wins.
- PIPELINE creates context (this phase). RUNTIME uses context (Deliverable 8 v2). This phase DESIGNS the
  exemplars, rules, and checks; the runtime narrator IMITATES and EXECUTES them. Never blurred.
- Feeds into: Phase 2 (character voice reference), Phase 5 (authored prose in choices), Deliverable 8 v2.
- RUNTIME LOADING CONTRACT (for the D8 v2 assembly job) — IDENTICAL shape to 1B v3, so one template serves both:
    * Section 3 (Voice Anchor) → D8 Section 4A, VERBATIM, cut LAST under token pressure, floor of 5 exemplars.
    * Section 4 (Anchor Card) → D8 Section 18, re-asserted before each generation, never cut.
    * Section 5 (Self-Check) → D8 Section 18, run before each delivered passage.
    * Section 6 (QA) does NOT ship to runtime.
- Source form: NOVELIST IP — the episode source text (D8 Section 12) is already prose; the runtime renders it in
  second-person present using the Voice Anchor texture. No screenplay-to-prose translation applies.
- No linter or external tool required at runtime. All runtime enforcement is binary/local.
=== END VOICE PROFILE ===
```

---

## VERIFICATION GATE

STOP. DO NOT PROCEED TO PHASE 2 until all pass.

**Blind test:** one 200-word second-person-present passage from this profile vs. one generic passage on the same subject. If you cannot instantly tell which is the author, revise.

1. Does the Voice Anchor contain 6–8 exemplars spanning the required modes, in second-person present, each passing every ban (minus documented carve-outs)?
2. Is every Anchor Card rule both ABSOLUTE/HIGH AND discretely checkable, with documented exceptions honored (no blanket ban on a technique the author actually uses)?
3. Does the Negative Space Map yield at least 5 ABSOLUTE bans?
4. Does the collocation fingerprint contain at least 15 pairs, each with its AI substitute?
5. Can comparative exclusion name 2–3 authors this voice must not be confused with?
6. Is the longest-speech ceiling captured and reflected in the Anchor Card?
7. Are the author's documented narrator techniques (cognitive verbs / prolepsis / commentary, if any) carved out of the bans so they are preserved, not stripped?

If any answer is no, revise. The author's voice is the product. There is no "close enough."

---

## END OF DELIVERABLE 1A v2 — NOVELIST / AUTHOR VOICE LOCK

