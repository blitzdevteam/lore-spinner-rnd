# DELIVERABLE 1A FINAL: VOICE LOCK PHASE PROMPT — NOVELIST / AUTHOR

**Lorespinner Pipeline Upgrade — June 2026**
**Type:** Pipeline phase (runs once per IP)
**Inserts:** Between IP Audit and Story Session Map
**Replaces:** Original Deliverable 1 (when source = novel/prose)
**Format Gate:** Runs ONLY when `format_detection.type` is `"novel"`, `"prose"`, `"short_story"`, or `"essay"`
**Implementation:** Copy-paste this prompt into VoiceLockChapterJob. One conditional selects this (1A) vs the screenwriter version (1B) based on FormatDetectionJob output.

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

You are performing the most important job in the Lorespinner pipeline. Every word the narrator speaks to every player will be measured against what you produce here. This is not analysis. This is forensic extraction of a specific human being's writing DNA.

The output of this phase becomes CONSTITUTIONAL LAW. It overrides every subsequent phase. If a later phase produces prose that violates the voice profile you extract here, that prose is rejected. No exceptions. No "close enough." The author's voice is the product.

THIS IS A NOVELIST / AUTHOR EXTRACTION. You are analyzing prose fiction — novels, novellas, short stories, or essays. The voice lives in narrative sentences, paragraph construction, narrator perspective, and rhetorical architecture. Do NOT apply screenplay metrics. If the source is a screenplay, STOP and switch to the Screenwriter Voice Lock Prompt (Deliverable 1B).

---

## TASK 1 — AUTHOR VOICE DNA EXTRACTION

Read the complete source text. You are not summarizing the story. You are studying HOW this specific human writes. Ignore plot. Ignore theme. Focus exclusively on craft mechanics.

Extract the following. Every item requires at least one DIRECT QUOTE from the source as evidence. Do not paraphrase. Do not generalize. Quote the line, then explain what it reveals about the author's technique.

### A. SIGNATURE WRITING TECHNIQUES (extract 8–12)

For each technique:

- NAME it in 2–4 words (e.g., "The Precision Sentence," "Negation as Assertion," "The Philosophical Preamble")
- QUOTE 2–3 source lines that demonstrate it
- EXPLAIN in one sentence what makes this technique specific to THIS author (not just good writing in general)
- NOTE the approximate frequency: How often does this technique appear? Every chapter? Every few pages? Only at climactic moments? This frequency becomes a guidance range in the Voice Profile — the runtime narrator should deploy the technique at roughly the same rate the author does.

The test: Could another skilled writer produce this technique by accident? If yes, it is not a signature. Dig deeper.

### B. SENTENCE-LEVEL PATTERNS

Analyze a representative cross-section of the source text — draw from early, middle, and late sections. Report:

- Average sentence length (word count) — note the range across different contexts (action vs. reflection vs. dialogue-adjacent narration)
- Cadence variation pattern: Does this author alternate long/short? Build to long? Punch with short? Use fragments? Where?
- Clause structure preference: Simple declarative? Compound? Subordinate-leading? Mixed with identifiable rhythm?
- Punctuation habits: Does this author favor semicolons? Frequent commas? Sparse punctuation? How does punctuation density compare to typical prose in the genre?
- QUOTE 3–4 consecutive sentences that demonstrate the author's natural sentence rhythm at its most distinctive

These numbers become GUIDANCE RANGES in the Voice Profile — calibration data that helps the runtime narrator stay in the author's rhythmic neighborhood.

### C. DICTION FINGERPRINT

- Average word length tendency: Does this author favor short Anglo-Saxon words? Longer Latinate constructions? A mix?
- Vocabulary clusters: What word families does this author return to? (e.g., mechanical/industrial, biological, domestic, violent, clinical)
- Register: Where does this author sit on formal-to-casual? Does register shift by context? How?
- Formality level: Academic? Street-level? Professional? Lyrical? Clipped?
- Word frequency patterns: What specific words does this author use MORE than a generic narrator would? What words does this author AVOID that most writers use freely?
- QUOTE 5–6 lines that demonstrate diction choices no other writer would make the same way

### D. NARRATOR PERSPECTIVE AND VOICE (NOVELIST-SPECIFIC)

This section does NOT exist in the screenwriter prompt. It is specific to prose fiction.

- Point of view: First person? Third limited? Third omniscient? Second person? Does it shift?
- Narrator reliability: Is the narrator trustworthy? Unreliable? Self-aware? Naive?
- Narrator distance: Close (inside the character's head) or distant (observing from outside)?
- Narrator commentary: Does the narrator editorialize? Offer philosophical asides? Stay invisible?
- Tense: Past? Present? Does it shift? When and why?
- Interior monologue patterns: Does this author render thoughts? How? Direct ("I should leave"), indirect ("She wondered if she should leave"), stream-of-consciousness, or through physical action only?
- QUOTE 3–4 passages that demonstrate the narrator's distinctive relationship to the story

Enforcement: if generated text adopts a narrator stance that contradicts the documented perspective, it fails regardless of whether every other metric is satisfied.

### E. PARAGRAPH ARCHITECTURE (NOVELIST-SPECIFIC)

This section has different priorities than the screenwriter equivalent.

- Average paragraph length and typical range — note whether the author favors short punches (1–2 sentences), long flowing blocks, or a mixed pattern
- How does this author build paragraphs? What is the internal logic — topic sentence then elaboration? Image then reaction? Action then consequence?
- How does this author TRANSITION between paragraphs? Hard cuts? Bridging phrases? Temporal markers? White space? Thematic linking?
- How does this author open chapters/sections? In medias res? Setting? Dialogue? Philosophical statement?
- How does this author close chapters/sections? Cliffhanger? Resolution? Image? Detonation?
- QUOTE 2 consecutive paragraphs that demonstrate this author's paragraph-building at its most characteristic

### F. DIALOGUE FINGERPRINT — PER MAJOR CHARACTER

For EACH character who speaks more than 5 lines in the source:

- Character name
- Speech rhythm: Short bursts? Long explanations? Questions? Commands? Interruptions?
- Verbal tics or recurring phrases (quote them)
- Vocabulary restrictions: What words would this character NEVER say?
- Emotional range in dialogue: How does this character sound when angry vs. afraid vs. tender vs. lying? Specifically: what happens to their sentence length when emotional? Do they get shorter or longer? Do they deflect or confront? Do they go quiet or loud?
- QUOTE the single line of dialogue that is MOST characteristic of this character. The line that, if you heard it without attribution, you would know who said it.

**DIALOGUE DIFFERENTIATION REQUIREMENT:** Every profiled character must sound distinctly different. Identify at least 3 linguistic markers per character. If two characters' dialogue could be swapped without the reader noticing, both fingerprints have failed. The most dangerous dialogue bleed: AI writes all characters in the same register — smart, articulate, emotionally aware. Real characters are not all articulate. Some are blunt. Some are evasive. Some can barely speak when emotional.

For the author's DIALOGUE TAG PATTERNS across all characters:

- What percentage of tags are "said"?
- What other tags does this author use? How often?
- Does this author use action beats instead of tags? How often?
- BANNED dialogue tags: List any tags the author NEVER uses that AI tends to default to ("shared," "expressed," "opined," "mused," "declared," "breathed")

### G. EMOTIONAL RANGE MAP

How does THIS author handle each of the following emotions? Not how emotions work in general. How THIS writer renders them on the page. For each: quote one source passage, describe the technique in one sentence, and note the rendering method (action, sensation, dialogue, monologue, environmental detail, or a combination).

- TENSION: Build through withholding? Acceleration? Silence? Physical detail?
- HUMOR: Through dialogue? Absurdity? Understatement? Juxtaposition? Present at all?
- GRIEF: Rendered through action? Internal monologue? Absence? Physical sensation?
- WONDER: Present or absent? If present — lyricism? Specificity? Restraint?
- FEAR: Psychological? Physical? Ambient? Sudden?
- VIOLENCE: Graphic or implied? Fast or slow? Consequence-focused or impact-focused?
- INTIMACY: Physical detail? Emotional exposure? Dialogue-driven? Gesture-driven?

If an emotion is ABSENT from the source, note that explicitly. Absence is data.

### H. COLLOCATION FINGERPRINT — CHARACTERISTIC WORD PAIRS

Individual vocabulary words can be matched by any competent imitator. What cannot be faked are the specific PAIRINGS — the combinations of words this author habitually places together. These are the micro-signatures that survive even when vocabulary is correct.

- Identify 15–20 characteristic word pairs (collocations) that recur across the source. Example: "singular case" (not "unusual case"), "clay pipe" (not "smoking pipe"), "keen eyes" (not "sharp eyes").
- For each collocation: QUOTE the source instance(s), note how often it appears, and identify what SUBSTITUTION an AI would likely produce instead.
- Group collocations by category: physical descriptions, emotional rendering, environmental detail, dialogue-adjacent action.

When the runtime narrator uses vocabulary from the author's documented collocation pairs, it must use the author's EXACT pairing — not the AI substitution.

### I. NEGATIVE SPACE MAP — WHAT THIS AUTHOR NEVER DOES

The ban list catches universal AI-slop. This section catches genre-default behaviors that THIS SPECIFIC AUTHOR avoids — techniques that are legitimate writing tools used by other authors in the same genre, but that this author never employs. These are not "bad writing." They are "not THIS writer's writing."

Map the complete negative space. For each item: name the technique, confirm its absence from the source with evidence, and explain why AI would default to it when imitating this genre.

Categories to examine:
- Interior monologue techniques the author never uses
- Descriptive techniques the author never uses (extended metaphor? Pathetic fallacy? Sensory catalog?)
- Structural techniques the author never uses (flashback? Epistolary? Time jump with explicit marker?)
- Dialogue techniques the author never uses (dialect spelling? Phonetic rendering? Extended monologue?)
- Emotional rendering techniques the author never uses (naming emotions? Physiological cliche? Narrative empathy statements?)

The negative space is as distinctive as the positive space. An author who never uses internal monologue is making a choice as specific as an author who always uses semicolons. Both must be enforced.

### J. SHOW/EXPLAIN RATIO

Assess the balance of concrete physical/sensory language versus abstract emotional/interpretive language across the source text.

- SHOW language: physical actions, body parts, sensory details, environmental specifics, concrete objects
- EXPLAIN language: named emotions, abstract states, interpretive commentary, philosophical statements, cognitive verbs
- Characterize the ratio: Is this author predominantly show-driven? Does the author explain frequently? What is the approximate balance?

**NOTE FOR NOVELISTS:** Prose fiction has a naturally higher explain component than screenwriting because narrators are permitted interior access. The ratio must be calibrated to THIS author's specific balance — not to a universal standard. An author who explains 20% of the time is as distinctive in their ratio as one who explains 2%.

Enforcement: If generated text feels significantly more explanatory than the source, the prose has drifted even if no individual ban is triggered.

### K. COMPARATIVE EXCLUSION — STYLISTIC NEIGHBORS

Identify 2–3 authors whose style most closely resembles this author's. For each neighbor:

- Name the author
- Identify the specific overlapping quality (sentence architecture? Diction register? Narrator stance? Emotional rendering?)
- Identify at least 2 techniques that DIFFERENTIATE this author from the neighbor

Generated text must be attributable to THIS author and NOT to any named neighbor. If the text could plausibly be attributed to a neighbor, it is not specific enough.

---

### OUTPUT FORMAT FOR TASK 1:

```
VOICE DNA PROFILE: [TITLE] by [AUTHOR]
Profile Type: NOVELIST / AUTHOR
Extracted by: Lorespinner Voice Lock Phase (Deliverable 1A FINAL)

SIGNATURE TECHNIQUES:
1. [NAME]: [Quote] — [Explanation] — Frequency: [approximate rate]
2. [NAME]: [Quote] — [Explanation] — Frequency: [approximate rate]
... (8–12 total)

SENTENCE PATTERNS:
Average length: ~[N] words (range: [X]–[Y] across contexts)
Cadence: [Description]
Clause preference: [Description]
Punctuation habits: [Description]
Representative rhythm: [3–4 consecutive quoted sentences]

DICTION FINGERPRINT:
Word length tendency: [Description]
Vocabulary clusters: [List]
Register: [Description]
Formality: [Description]
Overused (signature): [Words/phrases]
Avoided: [Words/phrases]
Characteristic lines: [5–6 quotes]

NARRATOR PERSPECTIVE:
POV: [Description]
Reliability: [Description]
Distance: [Description]
Commentary: [Description]
Tense: [Description]
Interior monologue: [Description]
Representative passages: [3–4 quotes]

PARAGRAPH ARCHITECTURE:
Typical length: [Description and range]
Building pattern: [Description]
Transitions: [Description]
Chapter openings: [Description]
Chapter closings: [Description]
Representative paragraphs: [2 consecutive quotes]

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

DIALOGUE TAG PATTERN:
"Said" percentage: ~[N]%
Other tags: [List with approximate frequencies]
Action beats: [Frequency]
BANNED tags: [List]

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
... (15–20 pairs)

NEGATIVE SPACE MAP:
1. [Technique]: absent from source — AI defaults to this because: [reason]
... (comprehensive list)

SHOW/EXPLAIN RATIO:
Balance: [Description of the author's show-to-explain tendency]
Guidance: Generated text should maintain this author's specific balance.

COMPARATIVE EXCLUSION:
Neighbors: [Author 1], [Author 2], [Author 3]
[Author 1] overlap: [quality] — differentiated by: [technique 1], [technique 2]
[Author 2] overlap: [quality] — differentiated by: [technique 1], [technique 2]
[Author 3] overlap: [quality] — differentiated by: [technique 1], [technique 2]
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

**NOVELIST NOTE:** Some literary fiction authors DO use cognitive-verb constructions deliberately (e.g., Woolf's stream-of-consciousness, Austen's free indirect discourse). If Task 1's Narrator Perspective section documents that the author uses these constructions as a technique, they are permitted within that documented pattern only. The ban applies to AI-default usage — the cognitive verb used as scaffolding rather than as a deliberate narrative device.

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

**NOVELIST NOTE:** Some authors' documented narrator voices include prolepsis (flash-forward) or editorial commentary (e.g., Vonnegut, Austen, Tolstoy). If Task 1's Narrator Perspective section documents this, it is permitted within that pattern. The ban targets AI-generated meta-references not part of the author's documented narrator stance.

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

**NOVELIST NOTE:** Exception: if the author's documented narrator voice includes philosophical commentary or thematic interpretation (e.g., George Eliot, Dostoevsky), it is permitted within that pattern. The ban targets AI-generated interpretation that is not part of the author's documented voice.

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

**NOVELIST NOTE:** Some authors break the fourth wall deliberately (e.g., Sterne, Fielding, Vonnegut). If documented in Narrator Perspective, permitted. The ban targets AI-generated meta-narration not present in the source.

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

**NOVELIST NOTE:** Prose fiction permits more interior access than screenwriting. The ban targets AI-generated explanatory commentary — not the author's documented style of emotional rendering. If the author names emotions directly (some do), that is their technique and it is preserved. The ban prevents the AI from ADDING explanation where the author would not.

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

### SECTION B: IP-SPECIFIC BANS (generated per author from Task 1)

Using the Voice DNA Profile from Task 1, identify and ban:

1. ANTI-PATTERNS: Techniques this author NEVER uses that AI defaults to when imitating their genre. (Example: if the author never uses internal monologue, ban narrator-voice internal monologue. If the author never uses metaphor, ban decorative metaphors.)

2. VOCABULARY THE AUTHOR AVOIDS: Words the source text conspicuously never uses despite opportunities.

3. RHYTHM VIOLATIONS: Sentence patterns that contradict the author's natural rhythm. (Example: if the author writes in short declarative bursts, ban compound sentences over 30 words in narration.)

4. EMOTIONAL TECHNIQUE VIOLATIONS: Ways of rendering emotion that contradict the author's method. (Example: if the author renders grief through silence and action, ban narrator-explained grief.)

For each IP-specific ban: STATE the ban, CITE the evidence from Task 1 that proves this author does not use this technique, EXPLAIN what the AI should do instead (the positive replacement).

**NOVELIST NOTES ON IP-SPECIFIC BANS:** Some authors deliberately use cognitive verbs, prolepsis, or editorial commentary as core craft techniques. If any of these are documented in Narrator Perspective (Task 1, Section D), those specific patterns are permitted for that IP. The ban applies to AI-default usage, not to the author's documented techniques.

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

Your job in this task: architect the rules. Define the pass/fail criteria, detection methods, and repair instructions — calibrated to THIS author's specific voice. The runtime narrator will execute them.

For each audit point: a PASS/FAIL DEFINITION specific to this IP, a DETECTION METHOD (what to look for in generated text), and a REPAIR INSTRUCTION (what to do when a violation is found).

RUNTIME PASS THRESHOLD: 14/14. Any failure requires the runtime narrator to revise before delivering the passage to the player.

### THE 14 AUDIT POINTS:

**1. HARD BAN TOKEN SCAN**

Pass: Zero banned tokens, phrases, molds, motifs, or names from Master Rule 1 (universal + IP-specific) appear in any generated prose.
Detection: Scan generated text against the complete ban list — vocabulary, sentence molds, motifs, names.
Repair: Rewrite the sentence using the author's documented techniques. Do not just rephrase.

**2. HALLUCINATED SEPARATION SCAN**

Pass: Zero instances of cognitive-verb separation between character and experience (unless documented as the author's deliberate technique in Narrator Perspective).
Detection: Scan for "realized," "found herself," "became aware," "occurred to," "couldn't help but," "noticed that," "it dawned on" followed by the experience they separate the character from.
Repair: Remove the cognitive verb. Render the experience directly.

**3. META-REFERENCE AND ESSAY LINE SCAN**

Pass: Zero instances of narrator commenting on the story's significance, meaning, or structure (unless documented as the author's narrator technique). Zero instances of interpretive commentary following concrete images.
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

**6. SENTENCE RHYTHM AUDIT**

Pass: Cadence matches the author's documented patterns. Short sentences where the author punches, long sentences where the author builds. Distribution feels like the author, not like generic AI.
Detection: Compare rhythm against documented sentence patterns. Flag passages where all sentences are the same length or rhythm flatlines.
Repair: Restructure to match the author's rhythm. If the author punches, punch. If the author flows, flow.

**7. PARAGRAPH ARCHITECTURE AUDIT** (NOVELIST-SPECIFIC)

Pass: Paragraph lengths, structure, and transitions match the author's documented architecture. Paragraphs are not all the same length.
Detection: Flag passages where paragraphs are suspiciously uniform or transitions use methods the author does not use.
Repair: Merge or break paragraphs to match documented architecture. Replace transitions with the author's documented style.

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

**11. NARRATOR COMPLIANCE AUDIT** (NOVELIST-SPECIFIC)

Pass: Narrator perspective, distance, reliability, commentary, and tense all match Task 1's documented Narrator Perspective. No POV drift, no unwarranted distance shifts, no tense changes unless documented.
Detection: Flag any passage where the narrator sounds different from the documented stance — closer when the author is distant, editorial when the author is invisible, wrong tense.
Repair: Rewrite to match the documented narrator stance.

**12. VOICE ATTRIBUTION TEST**

Pass: A passage read in isolation would be attributed to this specific author by a familiar reader. Generic narration fails.
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

## FINAL OUTPUT — VOICE LOCK PHASE COMPLETE

Assemble all three tasks into a single VOICE PROFILE DOCUMENT:

```
=== VOICE PROFILE: [TITLE] by [AUTHOR] ===
=== Profile Type: NOVELIST / AUTHOR ===
=== Extracted by Lorespinner Voice Lock Phase (Deliverable 1A FINAL) ===
=== This document is CONSTITUTIONAL LAW for all subsequent phases ===

SECTION 1: VOICE DNA PROFILE
[Task 1 complete output — all sections A through K]

SECTION 2: MASTER RULE 1 — HARD BAN LIST
[Task 2 complete output — Universal Bans + IP-Specific Bans]

SECTION 3: 14-POINT AUDIT PROTOCOL
[Task 3 complete output]

PIPELINE INTEGRATION NOTES:
- This Voice Profile is constitutional law. It supersedes all subsequent phases.
  When voice rules conflict, the Voice Profile wins.
- Feeds into: Phase 2 (character voice reference), Phase 5 (authored prose
  in choices), Runtime Narrator Template (voice DNA, ban list, and 14-point
  audit protocol — all loaded into the runtime narrator's system prompt for
  live self-audit during narration).

=== END VOICE PROFILE ===
```

---

## VERIFICATION GATE

STOP. DO NOT PROCEED TO PHASE 2.

The Voice Profile is the most consequential output in the pipeline. Every word the narrator speaks to every player will be measured against it. Before continuing, execute this verification:

**TEST:** Generate a 200-word test passage in the author's voice using only the Voice Profile you just produced. Then generate a 200-word passage of generic competent prose on the same subject. Read both. If you cannot immediately identify which is the author and which is generic — if the Voice Profile does not make the difference OBVIOUS — the profile is incomplete. Revise before continuing.

Then ask these three questions:

1. Does the collocation fingerprint contain at least 15 word pairs? If not, the micro-signatures are missing.
2. Does the negative space map identify at least 5 genre-default techniques this author never uses? If not, the immune system has gaps.
3. Can the comparative exclusion test name 2–3 authors this voice must NOT be confused with? If not, the profile is genre-generic, not author-specific.

If any answer is no, revise. The author's voice is the product. There is no "close enough." There is only the author's voice or a failure.

---

## END OF DELIVERABLE 1A FINAL — NOVELIST / AUTHOR VOICE LOCK
