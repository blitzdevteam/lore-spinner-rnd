# DELIVERABLE 1B v2: VOICE LOCK PHASE PROMPT — SCREENWRITER / TV WRITER (STRENGTHENED)

**Lorespinner Pipeline Upgrade — June 2026**
**Version:** 2.0 (Single-Source Strengthened)
**Replaces:** Deliverable 1B FINAL / Original Deliverable 1 (when source = screenplay/teleplay)
**Type:** Pipeline phase (runs once per IP)
**Inserts:** Between IP Audit and Story Session Map
**Format Gate:** Runs ONLY when `format_detection.type` is `"screenplay"`, `"teleplay"`, `"pilot"`, or `"limited_series"`
**Implementation:** Copy-paste this prompt into VoiceLockChapterJob. One conditional selects this (1B) vs the novelist version (1A) based on FormatDetectionJob output.

---

## COPY-PASTE PROMPT BELOW THIS LINE

---

LORESPINNER — VOICE LOCK PHASE: SCREENWRITER / TV WRITER VOICE EXTRACTION AND PROTECTION

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 1 AUDIT: [PASTE SCORECARD]

FORMAT DETECTION: [PASTE FORMAT DETECTION OUTPUT — must confirm type = screenplay/teleplay/pilot/limited_series]

SOURCE TEXT UPLOADED: [TITLE], [WRITER], [YEAR], [FORMAT: SCREENPLAY / TELEPLAY / PILOT / LIMITED SERIES]

WHAT TO UPLOAD: The COMPLETE source text. Not samples, not excerpts. Upload every page. If token limits require splitting, upload in halves and run this phase twice, merging outputs. Multiple works by the same writer should all be uploaded — cross-corpus extraction produces stronger profiles.

---

You are performing the most important job in the Lorespinner pipeline. Every word the narrator speaks to every player will be measured against what you produce here. This is not analysis. This is forensic extraction of a specific human being's writing DNA.

The output of this phase becomes CONSTITUTIONAL LAW. It overrides every subsequent phase. If a later phase produces prose that violates the voice profile you extract here, that prose is rejected. No exceptions. No "close enough." The author's voice is the product.

THIS IS A SCREENWRITER / TV WRITER EXTRACTION. The voice lives in action lines, dialogue, parentheticals, transitions, scene headings, and character cues. Built to be PERFORMED, not read. Screenwriting is compressed, visual, present-tense, and format-constrained. Do NOT apply novelist metrics. If the source is prose fiction, STOP and switch to the Novelist Voice Lock Prompt (Deliverable 1A).

---

## TASK 1 — WRITER VOICE DNA EXTRACTION

CRITICAL FIRST STEP: Separate the screenplay into its component elements before extracting voice. Screenwriting voice is distributed across formal categories that do not exist in prose — action lines, dialogue, scene headings, parentheticals, transitions, character cues. Analyze each independently before looking at the whole.

Read the complete source text. You are not summarizing the story. You are studying HOW this specific human writes screenplays. Ignore plot. Ignore theme. Focus exclusively on craft mechanics.

**SINGLE-SOURCE CONFIDENCE FRAMEWORK**

When extracting from a single screenplay, every metric has a sample size bounded by that one source. Some metrics have thousands of data points (periods, sentence lengths) and others have a handful (ellipses, specific parentheticals). The extraction must tag every constraint with its confidence level so the runtime knows which rules are load-bearing walls and which are guidance.

**Confidence tiers:**

- **ABSOLUTE:** Zero-occurrence constraints. The writer NEVER does this in the entire source. Zero semicolons across the full word count is not a sampling gap — it is a deliberate avoidance. Treat as HARD BAN. (Sample: 0 occurrences / total word count.)
- **HIGH:** 100+ instances or 1000+ data points. Statistically robust even from one source. Period density calculated across thousands of periods is not going to shift significantly with a second screenplay. Enforce as a hard specification with narrow tolerance bands. (Sample: count / total.)
- **MEDIUM:** 20-99 instances. Reliable pattern but allow wider tolerance. A parenthetical pattern across 80 instances gives a real picture but the specific distribution could shift with more data. Enforce with moderate tolerance. (Sample: count / total.)
- **LOW:** Fewer than 20 instances. Guidance only. A metric based on fewer than 20 instances does not support hard enforcement. Note the pattern, do not build a wall on it. (Sample: count / total.)

For every extracted metric, state the confidence tier and the sample size. The runtime treats ABSOLUTE and HIGH confidence constraints as rejection triggers. MEDIUM confidence constraints trigger warnings. LOW confidence constraints inform but do not override.

**Critical insight for single-source extraction:** Zero-occurrence data is your strongest weapon. When a writer produces thousands of words without a single semicolon, exclamation mark, or question mark in narration, those zeros are ABSOLUTE-confidence bans derivable from one source with perfect reliability. The 1B prompt already captures zero-occurrence data through the Negative Space Map (Section J) — the Confidence Framework ensures those zeros are ENFORCED as bans, not merely NOTED as observations.

Extract the following. Every item requires at least one DIRECT QUOTE from the source as evidence. Do not paraphrase. Do not generalize. Quote the line, then explain what it reveals about the writer's technique.

### A. SIGNATURE WRITING TECHNIQUES (extract 8-12)

For each technique:

- NAME it in 2-4 words (e.g., "The Fragment Punch," "Verb-First Momentum," "The Visual Sentence," "Dialogue as Evasion")
- QUOTE 2-3 source lines that demonstrate it
- EXPLAIN in one sentence what makes this technique specific to THIS writer (not just competent screenwriting in general)
- NOTE the approximate frequency: How often does this technique appear? Every scene? Every few pages? Only at climactic moments? This frequency becomes a guidance range in the Voice Profile — the runtime narrator should deploy the technique at roughly the same rate the writer does.

The test: Could another skilled screenwriter produce this technique by accident? If yes, it is not a signature. Dig deeper.

### B. ACTION LINE METRICS (SCREENWRITER-SPECIFIC)

Screenwriting voice lives primarily in the action lines. This section does NOT exist in the novelist prompt.

Analyze a representative cross-section of action lines from across the source. Report:

- Average words per action line — note the range across contexts (establishing shots vs. action vs. emotional beats)
- Fragment percentage: What proportion are sentence fragments vs. complete sentences?
- Verb-first percentage: How often do action lines open with a verb? ("Crosses the room." "Sits.")
- ALL CAPS density: How often and for what? Sounds? Objects? Character introductions only? Emotional beats?
- -ing opening frequency: How often do action lines open with a present participle?
- Paragraph rhythm: Does this writer cluster into 2-4 sentence paragraphs or isolate one per line? What is the ALTERNATION pattern? This is the writer's visual signature on the page.
- QUOTE 3-4 consecutive action lines that demonstrate this writer's action-line voice at its most distinctive

These numbers become GUIDANCE RANGES in the Voice Profile — calibration data that helps the runtime narrator stay in the writer's rhythmic neighborhood.

### C. DIALOGUE METRICS (SCREENWRITER-SPECIFIC)

Analyze all dialogue across the source. Report:

- Average speech length: Words per speech? Note the range across characters and scene types.
- Contraction density: How heavily does this writer contract? Does it shift by character or scene intensity?
- Question/exclamation density: How often does dialogue end in ? or ! — and what does that reveal?
- Interruption patterns: How does this writer render interrupted speech? Em-dash mid-word? Ellipsis? New character cutting in? Parenthetical (interrupting)?
- QUOTE 3-4 dialogue exchanges that demonstrate this writer's dialogue voice at its most distinctive

### D. PUNCTUATION AND DICTION FINGERPRINT

- Average word length tendency: Does this writer favor short Anglo-Saxon words? Longer Latinate constructions? A mix?
- Vocabulary clusters: What word families does this writer return to? (e.g., mechanical/industrial, biological, domestic, violent, clinical)
- Register in action lines: Where on formal-to-casual? Does register shift by scene intensity?
- Formality level: Sparse and telegraphic? Full sentences? Lyrical? Clipped?
- Word frequency patterns: What words does this writer use MORE than a generic screenwriter? What words does this writer AVOID?
- Punctuation habits: Periods? Commas? Sparse? How does density compare to typical screenwriting?
- QUOTE 5-6 lines (from action lines or dialogue) that demonstrate diction choices no other writer would make the same way

### E. SCREENPLAY STRUCTURE METRICS (SCREENWRITER-SPECIFIC)

- Scene density: Average number of scenes per page. Note whether the writer favors long scenes or rapid cutting.
- INT/EXT ratio: What proportion of scenes are interior vs. exterior? Does this writer favor contained spaces or open environments?
- Action-to-dialogue ratio: Approximate ratio of action-line real estate to dialogue real estate on the page. Is this a dialogue-heavy writer or a visual writer?
- Transition types: Explicit (CUT TO:, DISSOLVE TO:) or hard cuts (no transition, just new scene heading)? How often?
- Parenthetical vocabulary: What does this writer use and what do they NEVER use?
- Scene length distribution: Typical length? Consistent or wildly variable?
- Character introduction patterns: Name + age? Name + visual detail? Name + action? Name + attitude?

### F. EMOTIONAL VOCABULARY HIERARCHY (SCREENWRITER-SPECIFIC)

Screenwriters externalize everything. Rank the following vocabulary categories by density in the source's action lines (most frequent first):

1. MOTION/KINETIC: verbs of movement, speed, direction
2. PHYSICAL/BODILY: body parts, gestures, postures, physical states
3. DARK/LIGHT: shadow, brightness, visibility, obscurity
4. SOUND: noise, silence, volume, music, ambient
5. VIOLENCE: impact, force, damage, threat
6. EMOTIONAL STATE: the rare named emotion in an action line — when and why does this writer break the show-don't-tell rule?

For each category: QUOTE 3-4 representative action lines. Note which category dominates and which is nearly absent. The hierarchy reveals the writer's sensory priorities — the runtime narrator should externalize emotion through the same channels in the same order.

### G. CHARACTER DIALOGUE FINGERPRINT — PER MAJOR CHARACTER

For EACH character who speaks more than 5 lines in the source:

- Character name
- Speech rhythm: Short bursts? Long explanations? Questions? Commands? Interruptions?
- Verbal tics or recurring phrases (quote them)
- Vocabulary restrictions: What words would this character NEVER say?
- Emotional range in dialogue: How does this character sound when angry vs. afraid vs. tender vs. lying? Specifically: what happens to their sentence length when emotional? Do they get shorter or longer? Do they deflect or confront? Do they go quiet or loud?
- QUOTE the single line of dialogue that is MOST characteristic of this character. The line that, if you heard it without attribution, you would know who said it.

**DIALOGUE DIFFERENTIATION REQUIREMENT:** Every profiled character must sound distinctly different. Identify at least 3 linguistic markers per character. If two characters' dialogue could be swapped without the reader noticing, both fingerprints have failed. The most dangerous dialogue bleed: AI writes all characters in the same register — smart, articulate, emotionally aware. Real characters are not all articulate. Some are blunt. Some are evasive. Some can barely speak when emotional.

For the writer's PARENTHETICAL PATTERNS (relevant because the Voice Profile generates prose narration):

- Does the writer use parentheticals as de facto dialogue tags? How often?
- What parentheticals recur? (beat), (then), (sotto), (off their look)?
- BANNED parentheticals: List any the writer NEVER uses that AI defaults to ("sadly," "angrily," "hopefully," "with emotion")

### H. EMOTIONAL RANGE MAP

How does THIS writer handle each of the following emotions? Not how emotions work in general. How THIS writer renders them on the page — in action lines, in dialogue, in scene construction, in pacing. For each: quote one source passage, describe the technique in one sentence, and note the rendering method (action line, dialogue, parenthetical, scene structure, visual composition, or a combination).

- TENSION: Build through withholding? Acceleration? Silence? Physical detail? Scene length?
- HUMOR: Through dialogue? Absurdity? Understatement? Juxtaposition? Present at all?
- GRIEF: Rendered through action? Silence? Physical space? What the character does NOT say?
- WONDER: Present or absent? If present — visual composition? Character stillness? Restraint?
- FEAR: Physical symptoms in action lines? Dialogue evasion? Scene compression?
- VIOLENCE: Graphic or implied? Fast or slow? Consequence-focused or impact-focused? How much is on the page vs. implied off-screen?
- INTIMACY: Physical detail? Dialogue-driven? Gesture-driven? What the camera would see vs. what it would not?

If an emotion is ABSENT from the source, note that explicitly. Absence is data.

### I. COLLOCATION FINGERPRINT — CHARACTERISTIC WORD PAIRS

Individual vocabulary words can be matched by any competent imitator. What cannot be faked are the specific PAIRINGS — the combinations of words this writer habitually places together. These are the micro-signatures that survive even when vocabulary is correct.

- Identify 15-20 characteristic word pairs (collocations) that recur across the source. Example: "dead air" (not "awkward silence"), "slams shut" (not "closes hard"), "tight smile" (not "forced smile").
- For each collocation: QUOTE the source instance(s), note how often it appears, and identify what SUBSTITUTION an AI would likely produce instead.
- Group collocations by category: action line descriptions, dialogue-adjacent stage direction, emotional rendering, environmental detail.

When the runtime narrator uses vocabulary from the writer's documented collocation pairs, it must use the writer's EXACT pairing — not the AI substitution.

### J. NEGATIVE SPACE MAP — WHAT THIS WRITER NEVER DOES

This section catches format-default behaviors that THIS SPECIFIC WRITER avoids. These are legitimate screenwriting tools — just not THIS writer's tools.

Map the complete negative space. For each: name the technique, confirm its absence with evidence, explain why AI would default to it.

Categories to examine (SCREENWRITER-SPECIFIC):
- Camera direction never used (ANGLE ON, POV, TRACKING SHOT, CRANE UP)
- V.O. (voice-over): Never? Sparingly? When?
- O.S. (off-screen): Frequently or rarely?
- MONTAGE: Ever used? If absent, strong signal.
- FLASHBACK: Used? How? Or never?
- Parenthetical techniques never used (emotional state? performance direction?)
- Action line techniques never used (extended description? Lyrical imagery? Novelistic interiority?)
- Dialogue techniques never used (monologues? Direct address? Dialect spelling?)
- Transition types never used (DISSOLVE? SMASH CUT? MATCH CUT?)

The negative space is as distinctive as the positive space. Both must be enforced.

### K. SHOW/EXPLAIN RATIO

Assess the balance of concrete physical/sensory language versus abstract emotional/interpretive language across the source text.

- SHOW language: physical actions, body parts, sensory details, environmental specifics, concrete objects
- EXPLAIN language: named emotions, abstract states, interpretive commentary in action lines, novelistic interiority

**NOTE FOR SCREENWRITERS:** Screenwriting has an inherently HIGH show ratio — the camera photographs action, not emotion. But writers vary: some stay ruthlessly external, others slip interiority into action lines. Calibrate to THIS writer's specific balance.

Enforcement: If generated text feels significantly more explanatory than the source, the prose has drifted even if no individual ban is triggered.

### L. COMPARATIVE EXCLUSION — STYLISTIC NEIGHBORS

Identify 2-3 writers whose style most closely resembles this writer's. For each neighbor:

- Name the writer
- Identify the specific overlapping quality (action line compression? Dialogue rhythm? Scene structure? Visual vocabulary?)
- Identify at least 2 techniques that DIFFERENTIATE this writer from the neighbor

Generated text must be attributable to THIS writer and NOT to any named neighbor. If the text could plausibly be attributed to a neighbor, it is not specific enough.

### M. NUMERICAL ENFORCEMENT LAYER

Convert every measurable metric extracted in Sections A through L into a machine-checkable specification. For each metric, provide four values:

- **TARGET:** The range the runtime should aim for. Derived from the source's measured central tendency plus/minus natural variance observed across the source.
- **FLOOR:** The minimum acceptable value. Any passage falling below this has drifted out of the writer's voice. Derived from the source's lowest observed value minus a small tolerance.
- **CEILING:** The maximum acceptable value. Any passage exceeding this has drifted out of the writer's voice. Derived from the source's highest observed value plus a small tolerance.
- **CONFIDENCE:** How much the runtime should trust this constraint. Based on sample size from the source.
  - ABSOLUTE (zero-occurrence constraints — the writer never does this in the entire source)
  - HIGH (1000+ data points or 100+ instances — statistically robust even from one source)
  - MEDIUM (20-99 instances — reliable but allow slightly wider tolerance)
  - LOW (fewer than 20 instances — treat as guidance, not hard constraint)

**Required enforcement metrics (minimum — extract all that are measurable):**

**Punctuation Enforcement:**
- Period density per 100 words (action lines)
- Comma density per 100 words (action lines)
- Semicolons: count across entire source. If zero, HARD BAN with ABSOLUTE confidence.
- Exclamation marks in action lines/narration: count across entire source. If zero, HARD BAN.
- Em-dashes: count across entire source. State observed rate and enforcement rule.
- Question marks in narration vs. dialogue: separate counts.
- Ellipses: narration vs. dialogue counts.
- Period-to-comma ratio (the single most distinctive punctuation fingerprint)

**Rhythm Enforcement:**
- Action line / sentence length: target range, floor, ceiling, with distribution percentages per bucket (1-3w, 4-5w, 6-8w, 9-12w, 13-18w, 19-25w, 26+w)
- Fragment rate (percentage of sentences at 5 words or fewer): target range, floor
- Verb-first opening percentage: target range, ceiling
- -ing participle opening percentage: target range, CEILING (AI over-deploys these)
- Rhythm change frequency: what percentage of consecutive lines change length bucket

**Dialogue Enforcement (per character):**
- Average speech length with range
- P90 speech length (90th percentile)
- P95 speech length
- Maximum speech length (HARD CEILING — no generated speech may exceed this)

**Opener Distribution Enforcement:**
- Percentage breakdown by opener type (article, pronoun, character name, verb, negation, preposition, -ing, ALL CAPS)
- Any opener type below 2% in the source gets a ceiling of 5% in generated prose
- Any opener type above 20% in the source gets a floor of 10% in generated prose

**Word Length Enforcement:**
- Average word length in characters
- Distribution across buckets (1-3 chars, 4-5 chars, 6-8 chars, 9+ chars)

### N. RHYTHM TRANSITION ARCHITECTURE

Analyze the source's action lines as a sequence. For each line, categorize its length: ultra-short (1-3 words), short (4-6 words), medium (7-12 words), long (13+ words). Then build a transition matrix: after each category, what is the probability of each category following?

This captures the writer's rhythm MOVEMENT — not just their average sentence length but their pattern of variation. A writer who alternates short-long-short-long has a fundamentally different rhythm from one who clusters shorts together then expands, even if their averages are identical.

**Required outputs:**

1. **Transition matrix** — 4x4 grid showing probability of each category following each category
2. **Rhythm change frequency** — what percentage of consecutive lines change length category (vs. staying in the same category)
3. **Signature rhythm moves** — identify 2-3 characteristic transitions. Example: "After a 1-3 word beat, this writer expands to medium or long 62% of the time. The runtime should replicate this punch-then-breathe pattern."
4. **Anti-patterns** — transitions the writer never or rarely makes. Example: "This writer never stacks more than 3 ultra-short lines consecutively. If the runtime produces 4+ consecutive fragments, the rhythm has broken."

### O. BEAT ARCHITECTURE PROTOCOL

Identify all ultra-short lines (1-2 words) in the source's action lines. These are not descriptions — they are structural rhythm markers. A writer who uses "Silence." as a standalone line is doing something fundamentally different from a writer who writes "The room fell silent."

**Required outputs:**

1. **Beat frequency** — count of 1-2 word lines as percentage of total action lines
2. **Beat vocabulary** — list the actual words/phrases used as beats. Group by function:
   - Status beats: "Silence." "Still." "Gone." "Empty."
   - Action beats: "Hold." "Move." "Run."
   - Transition beats: "Later." "Morning." "Outside."
   - Emphasis beats: "Not finished." "Eyes down." "Grip tight."
3. **Beat placement** — where do beats appear? Before scene changes? After action sequences? At emotional peaks? After dialogue? Map the placement pattern.
4. **Beat density by context** — do beats cluster more in action sequences, emotional scenes, or transitions?

The runtime narrator should deploy beats at the documented frequency, using vocabulary from the documented beat lexicon, in the documented placement positions. Beats that use vocabulary not in the source's beat lexicon, or that appear at frequencies significantly above the documented rate, signal AI pattern-matching rather than authentic voice.

### P. SCENE TRANSITION COMPRESSION PROTOCOL

Analyze the last 1-3 action lines before each scene heading change. Scene boundaries reveal a writer's instinct for closure — how they punctuate a moment before cutting away.

**Required outputs:**

1. **Closing line length** — average word count of the last action line before a scene change. Compare against the overall action line average. If the closing lines are consistently shorter, this writer compresses at boundaries.
2. **Closing line type** — categorize each closing line: image (visual freeze), action (movement), status (state description), dialogue-adjacent (reaction to last speech), or beat (ultra-short rhythm marker). Report the distribution.
3. **Closing line examples** — quote 8-10 representative scene-closing action lines from across the source
4. **Transition guidance** — the runtime narrator should end scenes using the documented closing type at the documented compression level. If the writer consistently closes on a single image compressed to 3-5 words, the runtime must not close on a 25-word reflective sentence.

---

### OUTPUT FORMAT FOR TASK 1:

```
VOICE DNA PROFILE: [TITLE] by [WRITER]
Profile Type: SCREENWRITER / TV WRITER
Extracted by: Lorespinner Voice Lock Phase (Deliverable 1B v2 — Strengthened)

SIGNATURE TECHNIQUES:
1. [NAME]: [Quote] — [Explanation] — Frequency: [approximate rate]
2. [NAME]: [Quote] — [Explanation] — Frequency: [approximate rate]
... (8-12 total)

ACTION LINE METRICS:
Average words/line: ~[N] (range: [X]-[Y] across contexts)
Fragment %: ~[N]%
Verb-first %: ~[N]%
ALL CAPS density: [Description — what gets capitalized and how often]
-ing opening frequency: ~[N]%
Paragraph rhythm: [cluster/isolate pattern description]
Representative action lines: [3-4 consecutive quotes]

DIALOGUE METRICS:
Average speech length: ~[N] words (range: [X]-[Y] across characters/contexts)
Contraction density: [Description]
Question/exclamation density: [Description]
Interruption patterns: [Description]
Representative exchanges: [3-4 quoted exchanges]

DICTION FINGERPRINT:
Word length tendency: [Description]
Vocabulary clusters: [List]
Register: [Description]
Formality: [Description]
Overused (signature): [Words/phrases]
Avoided: [Words/phrases]
Punctuation habits: [Description]
Characteristic lines: [5-6 quotes]

SCREENPLAY STRUCTURE METRICS:
Scene density: ~[N] scenes/page
INT/EXT ratio: ~[N]:[N]
Action-to-dialogue ratio: ~[N]:[N]
Transition types: [Description]
Parenthetical vocabulary: [List of used / list of never-used]
Scene length distribution: [Description]
Character introduction patterns: [Description]

EMOTIONAL VOCABULARY HIERARCHY:
1. [Category]: [Density rank] — [3-4 quotes]
2. [Category]: [Density rank] — [3-4 quotes]
... (all 6 categories ranked)

CHARACTER DIALOGUE FINGERPRINTS:
[CHARACTER 1]:
  Rhythm: [Description]
  Tics: [Quotes]
  Restrictions: [Words they never say]
  Emotional range: [angry/afraid/tender/lying — with behavioral shifts]
  Distinguishing markers: [3+ features unique to this character]
  Signature line: [Quote]
[CHARACTER 2]: ...
(all speaking characters)

PARENTHETICAL PATTERN:
Used: [List with approximate frequencies]
BANNED parentheticals: [List]

EMOTIONAL RANGE:
Tension: [Quote] — [Technique]
Humor: [Quote] — [Technique] (or ABSENT)
Grief: [Quote] — [Technique]
Wonder: [Quote] — [Technique] (or ABSENT)
Fear: [Quote] — [Technique]
Violence: [Quote] — [Technique]
Intimacy: [Quote] — [Technique]

COLLOCATION FINGERPRINT:
1. "[word pair]" — [frequency] — AI would substitute: "[alternative]"
... (15-20 pairs)

NEGATIVE SPACE MAP:
1. [Technique]: absent from source — AI defaults to this because: [reason]
... (comprehensive list)

SHOW/EXPLAIN RATIO:
Balance: [Description of the writer's show-to-explain tendency]
Guidance: Generated text should maintain this writer's specific balance.

COMPARATIVE EXCLUSION:
Neighbors: [Writer 1], [Writer 2], [Writer 3]
[Writer 1] overlap: [quality] — differentiated by: [technique 1], [technique 2]
[Writer 2] overlap: [quality] — differentiated by: [technique 1], [technique 2]
[Writer 3] overlap: [quality] — differentiated by: [technique 1], [technique 2]

NUMERICAL ENFORCEMENT LAYER:

PUNCTUATION:
  [Metric]: TARGET [range] | FLOOR [n] | CEILING [n] | CONFIDENCE: [level] ([data points])
  ...

RHYTHM:
  [Metric]: TARGET [range] | FLOOR [n] | CEILING [n] | CONFIDENCE: [level] ([data points])
  ...

DIALOGUE CEILINGS:
  [Character]: AVG [n]w | P90 [n]w | P95 [n]w | MAX [n]w (HARD CEILING) | [n] speeches
  ...

OPENER DISTRIBUTION:
  [Type]: TARGET [n]% | FLOOR [n]% | CEILING [n]% | CONFIDENCE: [level]
  ...

WORD LENGTH:
  Average: [n] chars | TARGET [range] | CONFIDENCE: [level]
  Distribution: [buckets with percentages]

RHYTHM TRANSITION MATRIX:

After ULTRA-SHORT (1-3w):  -> ultra-short [n]% | short [n]% | medium [n]% | long [n]%
After SHORT (4-6w):        -> ultra-short [n]% | short [n]% | medium [n]% | long [n]%
After MEDIUM (7-12w):      -> ultra-short [n]% | short [n]% | medium [n]% | long [n]%
After LONG (13+w):         -> ultra-short [n]% | short [n]% | medium [n]% | long [n]%

Rhythm change frequency: [n]%
Max consecutive same-category: [n] (CEILING: [n+1])

SIGNATURE MOVES:
1. [Description with evidence]
2. [Description with evidence]

ANTI-PATTERNS:
1. [Description — what to avoid]

BEAT ARCHITECTURE:

Beat frequency: [n]% of total action lines
Beat vocabulary:
  Status beats: [list]
  Action beats: [list]
  Transition beats: [list]
  Emphasis beats: [list]
Beat placement: [description of where beats appear]
Beat density by context: [description]

SCENE TRANSITION COMPRESSION:

Closing line avg length: [n]w (vs. overall avg [n]w)
Closing line type distribution:
  Image: [n]%
  Action: [n]%
  Status: [n]%
  Dialogue-adjacent: [n]%
  Beat: [n]%
Closing line examples:
1. [quote]
... (8-10 examples)
Transition guidance: [description]
```

---

## TASK 2 — MASTER RULE 1: HARD BAN LIST

This is the immune system. These patterns are BANNED from all generated prose across all Lorespinner IPs. The narrator must never produce them. Any occurrence is a hard fail.

### SECTION A: UNIVERSAL BANS (hardcoded — apply to ALL IPs, ALL formats)

These bans apply identically to novelist and screenwriter pipelines. They are the floor. They cannot be overridden by any IP-specific rule, voice profile, or runtime adjustment.

#### PUNCTUATION BANS

- Em dashes in all variants (—, --, --) in GENERATED prose. Use periods, commas, or restructure. NOTE: If the source author uses em-dashes (documented in Task 1 Sentence-Level Patterns), they are permitted ONLY in the patterns the author demonstrably uses. Never as decorative thought-connectors.
- Ellipses (...) in narration. Dialogue only if the character's speech pattern requires trailing off, and only when established in the source.
- Emoji of any kind. Never.

#### SENTENCE MOLD BANS

- "It's not X, it's Y." (The false-correction pivot.)
- "No X. No Y. Just Z." (The stripped-down tricolon.)
- Balanced rule-of-three tricolons where all three elements match in length and structure.
- Mid-sentence rhetorical check-ins: "And honestly?" / "And really?" / "And look,"
- Trailing "like [metaphor]" similes in action lines (dialogue excluded if character voice supports it).
- Contrast-framing scaffolding: "She had always thought X. But now Y."
- Symmetrical lists for false profundity.
- Generic uplift wrap-ups: unearned wisdom at the end of a passage.
- "And" as dramatic intensifier more than once per 500 words.

#### VOCABULARY BANS

- tapestry (metaphorical), delve, underscore, highlight, showcase, intricate, swift, meticulous, adept
- "just" as a softener (permitted only in dialogue where character voice requires it)
- "that resonates," "that tracks," "that matters," "that lands"
- "And honestly" / "And look" / "And really"
- "woven into" / "weaving" / "wove" as metaphor for connection
- "meaningful" as adjective for connections, moments, experiences
- "nestled" / "tucked away" for locations (metaphorical only — literal physical placement permitted)
- "etch/etched" for memory or emotion
- "navigate" for emotional/social situations (acceptable for literal navigation only)
- "beautiful" / "wonderful" / "incredible" / "amazing" as intensifiers

#### AI FICTION MOTIF BANS

- ghosts, spectral, shadow, whisper, quiet/quietness, hum/humming, echo, liminal, phantom WHEN used as default atmospheric texture. (Permitted only when the IP's canon includes these as world elements.)
- "Something shifted" / "Something clicked" / "Something broke" as emotional transitions
- Characters "letting out a breath they didn't know they were holding"
- Eyes "searching" faces
- Silence that "stretches" or "hangs" or "fills the room"
- Hearts that "hammer" or "race" or "skip"
- Weather mirroring emotional state unless the author demonstrably uses pathetic fallacy

#### NAME BANS

- Elara, Voss, Kael, Echo (as name), Ghost Code, Luminara, Seraphina, Thorne, Cipher, Nexus
- Any name not in the source IP's canon.

#### CORPORATE/PR BANS

- "woven into your daily rhythm" / "memories were made" / "meaningful connections"
- Any phrasing that reads like brand copy or marketing material.

#### STRUCTURAL AI TELLS — JUNE 2026 ADDITIONS

The following bans address the most dangerous AI writing behaviors — structural tells that survive standard token scanning because they don't use banned WORDS; they use banned PATTERNS.

**1. HALLUCINATED SEPARATION**

AI inserts narrative distance between character and action. The character is separated from their own experience by a layer of cognitive narration.

BANNED PATTERNS:
- "She realized she was feeling [emotion]" — the character does not "realize" she feels; she feels.
- "He found himself [verb]-ing" — he did not "find himself"; he did the thing.
- "It occurred to her that..." — nothing "occurs to" anyone in good prose.
- "She couldn't help but [verb]" — she can and she did.
- "He became aware of [sensation]" — he felt the sensation. Period.
- "There was a [emotion] in her [body part]" — the body part DOES something. Don't report the emotion as a noun sitting inside it.
- Any construction where a cognitive verb (realized, noticed, became aware, found herself, couldn't help) stands between the character and their own physical/emotional experience.

INSTEAD: Render the experience directly. "Her hands shook." Not "She realized her hands were shaking." "His chest burned." Not "He became aware of a burning in his chest."

**SCREENWRITER NOTE:** The camera cannot see "realized." If it cannot be filmed, it does not belong in an action line. The cognitive verb is doubly banned because it contradicts both the universal rule AND the format's DNA.

**2. META-REFERENCES**

AI references the story AS a story from within the narration, breaking the fictional dream.

BANNED PATTERNS:
- "This was the kind of moment that [changes/defines/matters]"
- "It was as if the [narrative/story/world] had [shifted/changed/broken]"
- "In that moment, everything [changed/crystallized/became clear]"
- "What happened next would [change/define/haunt] her forever"
- "She would later remember this as the moment when..."
- "Little did [character] know..."
- Any sentence that frames the current scene from a future vantage point not established by the author's narrator voice
- Any sentence that describes the scene's significance rather than showing the scene itself

INSTEAD: Show the scene. Let the significance arrive through what happens, not through the narrator telling you it's significant.

**SCREENWRITER NOTE:** Screenplays live in perpetual present tense. There is no "she would later remember" because the camera is always NOW. Future-framing meta-references are doubly banned: universal rule + format's temporal logic.

**3. ESSAY LINE**

AI inserts a thesis statement or interpretive commentary that explains what an image, moment, or scene MEANS — instead of letting the image land on its own.

BANNED PATTERNS:
- "The [object] was a reminder that [philosophical statement]"
- "It was [metaphor] — as if [explanation of what the metaphor means]"
- "[Action], a testament to [abstraction]"
- "In the [noun] of [noun], there was [abstract meaning]"
- Any sentence that follows a concrete image with an interpretation of that image
- Any sentence that explains WHY a character's action matters rather than showing the action
- Any sentence that could function as the thesis statement of a college essay about the story

INSTEAD: Show the image. Stop. The reader builds the meaning. The narrator shows the image.

**SCREENWRITER NOTE:** Screenwriters SHOW, they do not EXPLAIN. The action line describes what happens on screen. The audience interprets. Essay lines in screenwriter-derived prose are triply wrong: universal ban + format DNA + text the source writer would never produce.

**4. PRONOUN CLUSTERING**

Vary sentence openers. Avoid clustering three or more consecutive sentences starting with the same pronoun. When fixing pronoun clusters, cycle through multiple techniques — character name, object-as-subject, environmental detail, dependent clause, action-first opening, sentence merge — to avoid over-relying on any single fix.

Applies to action lines and narration. Does NOT apply within dialogue.

**5. META-NARRATION**

The narrator must never comment on the act of narration, the nature of stories, the reader's experience, or the structure of the narrative from within the narrative itself.

BANNED PATTERNS:
- "But that's not how this story goes"
- "If this were a different kind of story..."
- "The truth was simpler / more complicated / harder to name"
- "What she didn't know was..."
- "Perhaps that was the point"
- "And maybe that was enough"
- Any sentence that addresses the reader directly (unless the source author's documented narrator voice explicitly uses direct address)
- Any sentence that reflects on storytelling, narrative, meaning, or the nature of fiction from inside the fiction

INSTEAD: Stay inside the fictional dream. The narrator tells the story. The narrator does not discuss the story.

**SCREENWRITER NOTE:** Screenplays have no narrator. The narrator in screenwriter-derived prose should be as invisible as possible — describing what happens, not commenting on what it means.

**6. FREQUENCY DRIFT**

No single signature technique should dominate generated prose to the point where it becomes a new tic. The runtime narrator should deploy signature techniques at roughly the frequencies documented in the Voice Profile. If one technique appears noticeably more often than the author used it, the prose has drifted from imitation into parody.

Detection: Read the generated passage. If any single signature technique jumps out as dominating — if you notice it more than you would notice it in the source — it is over-deployed.
Repair: Remove excess instances. Keep those that land at natural stress points. Redistribute attention across the full range of documented techniques.

**7. EXPLANATORY COMMENTARY**

AI explains instead of showing. This is the umbrella ban covering any instance where generated text TELLS the reader what to think or feel about a moment rather than rendering the moment through action, sensation, and dialogue.

BANNED PATTERNS:
- Narrator explaining a character's motivation after showing their action
- Narrator interpreting a symbol or image after presenting it
- Narrator stating the emotional significance of a scene rather than letting it emerge
- Any sentence that begins with or contains: "It was clear that," "Obviously," "Clearly," "Without a doubt," "There was no question that"
- Declarative emotional summaries: "She was devastated." "He was furious." "They were relieved." (Use the author's documented emotional rendering technique instead.)

INSTEAD: Show the action. Show the physical response. Show the world reacting. STOP. Trust the reader.

**SCREENWRITER NOTE:** This ban has maximum force in a screenwriter-derived Voice Profile. The writer describes what happens. The actor performs. The audience interprets. When in doubt: could this sentence be filmed? If not, cut it.

#### REPAIR DISTRIBUTION RULE

When fixing pronoun clusters, over-long action lines, or any structural violation that requires sentence rewriting, cycle through multiple fix techniques. Do not default to one cheap solution (e.g., -ing openings for every pronoun fix) and over-use it until it becomes a new voice problem.

Available fix techniques:
1. Character name as subject
2. Object-as-subject
3. Action-first / -ing opening
4. Environmental detail (new beat)
5. Dependent clause opener
6. Sentence merge

Vary deliberately. If the last fix used technique 1, the next fix should use a different technique.

#### NEGATION-THEN-POSITIVE CUTTING RULE

When reviewing generated text, always prioritize cutting instances where a negation is followed by an obvious positive ("Not angry. Hurt." where "Hurt." alone would suffice). Cut the negation when the positive carries enough weight alone.

### SECTION B: IP-SPECIFIC BANS (generated per writer from Task 1)

Using the Voice DNA Profile from Task 1, identify and ban:

1. ANTI-PATTERNS: Techniques this writer NEVER uses that AI defaults to when imitating their genre. Examples: interior monologue in action lines, decorative metaphors, compound sentences >25 words in action lines, emotional parentheticals ("sadly," "angrily"), expository dialogue, atmosphere without function.

2. VOCABULARY THE WRITER AVOIDS: Words the source text conspicuously never uses despite opportunities.

3. RHYTHM VIOLATIONS: Action line and dialogue patterns that contradict the writer's natural rhythm. (Example: if the writer uses 3-8 word action lines, ban action-line sentences over 15 words in narration.)

4. EMOTIONAL TECHNIQUE VIOLATIONS: Ways of rendering emotion that contradict the writer's method. (Example: if the writer renders grief through what a character DOES, ban named-emotion grief. If the writer renders tension through scene compression, ban tension through interiority.)

For each IP-specific ban: STATE the ban, CITE the evidence from Task 1 that proves this writer does not use this technique, EXPLAIN what the AI should do instead (the positive replacement).

OUTPUT FORMAT FOR TASK 2:

```
MASTER RULE 1: HARD BAN LIST FOR [TITLE]

UNIVERSAL BANS: [Paste complete Section A above — identical for every IP]

IP-SPECIFIC BANS:
1. [BAN]: [Evidence from source] -> INSTEAD: [What to do]
2. [BAN]: [Evidence from source] -> INSTEAD: [What to do]
... (as many as the source warrants, minimum 6)
```

---

## TASK 3 — 14-POINT CONTINUOUS AUDIT PROTOCOL

Design a 14-point audit protocol tailored to this specific IP, based on the voice DNA extracted in Task 1 and the ban list built in Task 2. This protocol does NOT execute here in the pipeline. It becomes part of the Voice Profile output, saved to the database alongside the voice DNA and ban list. At runtime, the narrator LLM loads this protocol into its system prompt and uses it as a continuous self-audit while generating live player-facing narration.

Your job in this task: architect the rules. Define the pass/fail criteria, detection methods, and repair instructions — calibrated to THIS writer's specific voice. The runtime narrator will execute them.

For each audit point: a PASS/FAIL DEFINITION specific to this IP, a DETECTION METHOD (what to look for in generated text), and a REPAIR INSTRUCTION (what to do when a violation is found).

RUNTIME PASS THRESHOLD: 14/14. Any failure requires the runtime narrator to revise before delivering the passage to the player.

### THE 14 AUDIT POINTS:

**1. HARD BAN TOKEN SCAN**

Pass: Zero banned tokens, phrases, molds, motifs, or names from Master Rule 1 (universal + IP-specific) appear in any generated prose.
Detection: Scan generated text against the complete ban list — vocabulary, sentence molds, motifs, names.
Repair: Rewrite the sentence using the author's documented techniques. Do not just rephrase.

**2. HALLUCINATED SEPARATION SCAN**

Pass: Zero instances of cognitive-verb separation between character and experience. SCREENWRITER DOUBLE-CHECK: Could this sentence be filmed? If the cognitive verb describes something invisible to a camera, it fails.
Detection: Scan for "realized," "found herself," "became aware," "occurred to," "couldn't help but," "noticed that," "it dawned on" followed by the experience they separate the character from.
Repair: Remove the cognitive verb. Render the experience directly.

**3. META-REFERENCE AND ESSAY LINE SCAN**

Pass: Zero instances of narrator commenting on the story's significance, meaning, or structure. Zero instances of interpretive commentary following concrete images.
Detection: Flag sentences containing "the kind of," "a reminder that," "a testament to," "it was clear that," "what she didn't know." Flag any sentence that follows a concrete image with an abstraction.
Repair: Cut the commentary. Let the image or action stand alone.

**4. PRONOUN VARIATION CHECK**

Pass: Sentence openers are varied. No conspicuous clusters of three or more consecutive sentences starting with the same pronoun.
Detection: Flag any passage where the same pronoun opens three or more sentences in a row, or where excessive same-pronoun openers create monotonous rhythm.
Repair: Apply the Repair Distribution Rule — cycle through character name, object-as-subject, environmental detail, dependent clause, action-first opening, sentence merge. Different technique for each consecutive fix.

**5. FREQUENCY BALANCE CHECK**

Pass: No single signature technique dominates. Techniques appear at roughly the frequencies observed in the source.
Detection: Does any one technique call attention to itself through sheer repetition? If it is noticeable as a pattern rather than as individual moments, it is over-deployed.
Repair: Remove excess instances. Keep those at natural stress points. Redistribute across the full technique range.

**6. ACTION LINE COMPRESSION AUDIT** (SCREENWRITER-SPECIFIC)

Pass: Action line prose matches the writer's documented compression. Sentence length is the PRIMARY metric. Paragraph rhythm (cluster vs. isolate alternation) matches the documented pattern. Per-line word count is guidance, not a hard ceiling.
Detection: Flag passages where action-line sentences consistently exceed the documented average by 50%+. Flag uniform paragraph blocks.
Repair: Compress. Cut adjectives. Cut adverbs. Favor the verb. Match documented fragment and verb-first percentages.

**ANTI-FRAGMENTATION WARNING:** Do NOT put every sentence on its own line. Sentences CLUSTER into 2-4 sentence paragraphs, alternating with single-sentence paragraphs for emphasis. If every sentence sits alone, the rhythm has become staccato noise. Check the documented paragraph rhythm in Task 1 and match it.

**7. DIALOGUE COMPRESSION AUDIT** (SCREENWRITER-SPECIFIC)

Pass: Dialogue matches documented speech lengths, contraction density, and interruption patterns.
Detection: Flag speeches exceeding documented average by 50%+. Flag dropped contractions. Flag unnaturally complete sentences where the character speaks in fragments.
Repair: Compress to documented speech length. Restore contractions, fragments, and interruptions per the character's fingerprint.

**8. TONE AND REGISTER AUDIT**

Pass: Register stays consistent with the author's documented register. No drift toward formal/neutral/academic. No generic enthusiasm. No unearned tone shifts.
Detection: Flag any passage where the prose suddenly sounds more formal, poetic, generic, or emotionally available than the author's documented voice.
Repair: Rewrite in the author's documented register, matching formality, vocabulary cluster, and emotional temperature.

**9. REPETITION CHECK**

Pass: No content word echoes excessively in a short span. No sentence opener repeats within 3 consecutive sentences. No paragraph opener repeats the same construction within 5 consecutive paragraphs.
Detection: Flag any word, phrase, or construction that echoes noticeably within a short passage.
Repair: Vary using the author's documented vocabulary clusters and sentence patterns.

**10. SPECIFICITY AUDIT**

Pass: No vague abstractions ("something," "a feeling," "a sense of," "a kind of," "somehow") where the author would use concrete detail.
Detection: Flag abstract emotional language in narration. Compare against the author's emotional range map.
Repair: Replace with physical, sensory, or action-based rendering using the author's documented technique.

**11. SCREENPLAY-TO-PROSE TRANSLATION COMPLIANCE** (SCREENWRITER-SPECIFIC)

Pass: Prose maintains the writer's compression, externalization, and present-tense immediacy without importing novelistic techniques the writer never uses.
Detection: Flag passages containing interior monologue not in the writer's documented techniques, named emotions where the writer would show physical action, paragraph lengths exceeding the writer's action-line rhythm by 3x+, past-tense narration where the writer demands present-tense immediacy, or descriptive density exceeding the documented show/explain ratio.
Repair: Strip novelistic additions. Return to documented externalization. Compress to documented rhythm. If a passage cannot work without interior access, restructure as action and dialogue. See SCREENPLAY-TO-PROSE TRANSLATION PROTOCOL for element-by-element mapping.

**12. VOICE ATTRIBUTION TEST**

Pass: A passage read in isolation would be attributed to this specific writer by a familiar reader. Generic narration fails.
Detection: Read the passage cold. If it sounds like any well-written novel, it fails. At least 2 documented signature techniques should be evident in any extended passage.
Repair: Layer signature techniques into failing passages. Rewrite with the author's diction, rhythm, and emotional rendering.

**13. HUMAN TEXTURE AUDIT**

Pass: Prose has authored imperfections — compressed phrasing, rhythm breaks, varying density. Too-uniform prose fails.
Detection: Flag passages where quality, density, and rhythm remain suspiciously consistent for 5+ paragraphs. Real authors compress when urgent and breathe when reflective.
Repair: Introduce the author's documented compression patterns and rhythm breaks.

**14. CHARACTER DIALOGUE DIFFERENTIATION**

Pass: Every character sounds distinctly different. No two characters share sentence length pattern, vocabulary level, or evasion style.
Detection: Swap test — could any two characters' speeches be exchanged without the reader noticing? If yes, FAIL.
Repair: Rewrite the blander character's dialogue to match their documented fingerprint. If no fingerprint exists, FLAG — do not guess.

---

## SECTION 3B: VOICE DECAY PREVENTION PROTOCOL

Voice decay is the progressive drift of generated prose from the writer's documented voice toward the model's default prose tendencies. It is measurable, predictable, and preventable.

**Mechanism:** Every token the model generates shifts its running context slightly toward its own training distribution. Over 200-500 words, these micro-shifts compound. The generated prose starts recognizable and ends generic. This is not a model failure — it is a physics of autoregressive generation. It must be countered structurally.

**The protocol has three components:**

**1. RE-ANCHORING TRIGGER**

Every 300-400 words of generated prose (approximately every 2-3 interactive story passages), the runtime must re-inject the following into its active context:

- The Numerical Enforcement Layer (Section M) — all hard constraints with targets and ceilings
- The Punctuation Enforcement profile — period density target, comma ceiling, hard bans
- The top 5 signature techniques with frequency guidance
- The Rhythm Transition Architecture summary — signature moves and anti-patterns

Re-anchoring is not optional. It is not a "check if needed." It fires on a word-count trigger regardless of whether the prose appears to be drifting. By the time drift is visible, it has already compounded past easy correction.

**2. PASSAGE-LEVEL ENFORCEMENT CHECK**

Before delivering any generated passage to the player, the runtime must verify:

- Period density within the prose target range from the Translation Tolerance Bands
- Comma density below the prose ceiling
- Zero banned punctuation (semicolons, exclamation marks, em-dashes — if specified as HARD BAN)
- No speech exceeds the character's documented hard ceiling
- No pronoun cluster of 3+ consecutive same-pronoun sentence starts
- Fragment rate above the prose floor
- No banned vocabulary or sentence molds from Master Rule 1

If ANY hard constraint is violated, the passage is REJECTED and REGENERATED from scratch. Not revised — regenerated. Revision attempts to fix specific sentences while preserving the surrounding context that caused the drift. Regeneration restarts from the Voice Profile specification. Regeneration is more expensive but produces cleaner output.

**3. DRIFT DETECTION METRICS**

The runtime should track these metrics across consecutive passages. If any metric shows a consistent trend away from the target over 3+ consecutive passages, the system is drifting and must re-anchor immediately:

- Fragment rate trending downward (most common drift direction — AI relaxes compression)
- Period density trending downward (sentences getting longer)
- Comma density trending upward (AI inserting commas for "readability")
- -ing openings trending upward (AI defaulting to participle constructions)
- Speech lengths trending upward (characters getting more verbose)

---

## SCREENPLAY-TO-PROSE TRANSLATION PROTOCOL

When the Voice Profile generates prose narration for interactive stories, these mappings govern how screenplay elements translate. This is a mechanical protocol, not a creative decision.

| Screenplay Element | Prose Equivalent | Rule |
|---|---|---|
| ACTION LINE | Narrative sentence | Maintains the writer's compression and sensory priority hierarchy. Does NOT expand into novelistic description. |
| SCENE HEADING (INT./EXT.) | Scene break or establishing sentence | Single establishing sentence or white-space break. Does NOT become a descriptive paragraph. |
| CUT TO / SMASH CUT / transition | Paragraph break or scene break | SMASH CUT = hard break, no bridging. DISSOLVE = softer transition, brief bridging sentence permitted. |
| PARENTHETICAL | Dialogue tag modification | "(beat)" = action between lines. "(sotto)" = volume indicator. "(re: object)" = glance/gesture. NOT narrator commentary on emotion. |
| CHARACTER CUE | Dialogue attribution | First appearance follows documented introduction pattern. Subsequent = minimal attribution. |
| ALL CAPS emphasis | Italic or bold (per house style) | Does NOT translate to exclamation marks or narrator excitement. |
| (beat) | Action between dialogue | A physical action — gesture, look, movement. NOT "There was a moment of silence." The character DOES something. |

**QUANTITATIVE TRANSLATION MAPPINGS**

The writer's screenplay voice exists in a format (action lines, dialogue blocks, scene headings) that is structurally different from the prose narration the runtime generates. Raw screenplay numbers cannot be applied directly to prose — a very high fragment rate in action lines would be unreadable as continuous narrative. But the numbers cannot be abandoned either — that is how voice decays.

For each measurable metric, the extraction must produce a TRANSLATION MAPPING with three components:

1. **Source value** — the metric as measured in the screenplay
2. **Prose target** — the adjusted range for narrative prose, accounting for format differences
3. **Drift ceiling** — the maximum acceptable deviation from the prose target before the prose has left the writer's voice entirely

The translation rationale must be stated: why is the prose target set where it is? What format difference justifies the adjustment?

**Required translation mappings (minimum):**

| Screenplay Metric | Source Value | Prose Target | Drift Ceiling | Rationale |
|---|---|---|---|---|
| Fragment rate | [n]% | [range]% | [n]% floor | Prose needs more connective tissue than action lines, but fragments are this writer's signature — the floor preserves them |
| Period density per 100w | [n] | [range] | [n] floor | Prose sentences are slightly longer than action lines, reducing period density, but the ratio must stay far above typical prose writers |
| Comma density per 100w | [n] | [range] | [n] ceiling | Prose may need slightly more commas for readability, but this writer's comma avoidance is signature — the ceiling prevents drift |
| Avg sentence/line length | [n]w | [range]w | [n]w ceiling | Prose sentences run slightly longer than action lines but must stay compressed |
| -ing openings | [n]% | [range]% | [n]% ceiling | If near-zero in source, ceiling stays very low in prose |
| Max speech length per character | [n]w | [n]w | [n]w hard ceiling | No character speaks longer in prose than in the screenplay — the screenplay is the ceiling |

The runtime enforces the prose target ranges. When a generated passage exceeds the drift ceiling, the passage is rejected and regenerated — not revised, regenerated. Revision softens; regeneration restarts from the specification.

---

## FINAL OUTPUT — VOICE LOCK PHASE COMPLETE

Assemble all sections into a single VOICE PROFILE DOCUMENT:

```
=== VOICE PROFILE: [TITLE] by [WRITER] ===
=== Profile Type: SCREENWRITER / TV WRITER ===
=== Extracted by Lorespinner Voice Lock Phase (Deliverable 1B v2 — Strengthened) ===
=== This document is CONSTITUTIONAL LAW for all subsequent phases ===

SECTION 1: VOICE DNA PROFILE
[Task 1 complete output — all sections A through P]

SECTION 2: MASTER RULE 1 — HARD BAN LIST
[Task 2 complete output — Universal Bans + IP-Specific Bans]

SECTION 3: 14-POINT AUDIT PROTOCOL
[Task 3 complete output]

SECTION 3B: VOICE DECAY PREVENTION PROTOCOL
[Re-anchoring trigger, passage-level enforcement check, drift detection metrics]

SECTION 4: SCREENPLAY-TO-PROSE TRANSLATION PROTOCOL
[7-row translation table]
(including Quantitative Translation Mappings)

PIPELINE INTEGRATION NOTES:
- This Voice Profile is constitutional law. It supersedes all subsequent phases.
  When voice rules conflict, the Voice Profile wins.
- Feeds into: Phase 2 (character voice reference), Phase 5 (authored prose
  in choices), Runtime Narrator Template (voice DNA, ban list, 14-point
  audit protocol, voice decay prevention protocol, and screenplay-to-prose
  translation protocol — all loaded into the runtime narrator's system prompt
  for live self-audit during narration).

=== END VOICE PROFILE ===
```

---

## VERIFICATION GATE

STOP. DO NOT PROCEED TO PHASE 2.

The Voice Profile is the most consequential output in the pipeline. Every word the narrator speaks to every player will be measured against it. Before continuing, execute this verification:

**TEST:** Generate a 200-word test passage in the writer's voice using only the Voice Profile you just produced. Then generate a 200-word passage of generic competent prose on the same subject. Read both. If you cannot immediately identify which is the writer and which is generic — if the Voice Profile does not make the difference OBVIOUS — the profile is incomplete. Revise before continuing.

Then ask these six questions:

1. Does the collocation fingerprint contain at least 15 word pairs? If not, the micro-signatures are missing.
2. Does the negative space map identify at least 5 genre-default techniques this author never uses? If not, the immune system has gaps.
3. Can the comparative exclusion test name 2-3 authors this voice must NOT be confused with? If not, the profile is genre-generic, not author-specific.
4. Does the Numerical Enforcement Layer contain hard constraints for period density, comma density, and at least 3 ABSOLUTE-confidence bans? If not, the specification cannot be enforced.
5. Does the Rhythm Transition Architecture include a complete 4x4 transition matrix? If not, the rhythm guidance is incomplete.
6. Does the Voice Decay Prevention Protocol specify a re-anchoring word-count trigger? If not, voice drift will occur.

If any answer is no, revise. The author's voice is the product. There is no "close enough." There is only the author's voice or a failure.

---

## END OF DELIVERABLE 1B v2 — SCREENWRITER / TV WRITER VOICE LOCK (STRENGTHENED)
