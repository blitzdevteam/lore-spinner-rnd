# DELIVERABLE 1: VOICE LOCK PHASE PROMPT

**Lorespinner Pipeline Upgrade — May 2026**
**Type:** New pipeline phase (runs once per IP)
**Inserts:** Between Phase 1 (IP Audit) and Phase 2 (Story Session Map)
**Replaces:** Nothing (net-new)
**Implementation:** Copy-paste this prompt. Add one new job slot to the pipeline chain after Phase 1.

---

## COPY-PASTE PROMPT BELOW THIS LINE

---

LORESPINNER — VOICE LOCK PHASE: AUTHOR VOICE EXTRACTION AND PROTECTION

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 1 AUDIT: [PASTE SCORECARD]
FORMAT DETECTION: [PASTE FORMAT DETECTION OUTPUT]
SOURCE TEXT UPLOADED: [TITLE], [AUTHOR/WRITER], [YEAR], [FORMAT]

WHAT TO UPLOAD: The COMPLETE source text. Voice extraction requires the full range of the author's writing — not samples, not excerpts. Upload every page. If token limits require splitting, upload in halves and run this phase twice, merging outputs.

---

You are performing the most important job in the Lorespinner pipeline. Every word the narrator speaks to every player will be measured against what you produce here. This is not analysis. This is forensic extraction of a specific human being's writing DNA.

The output of this phase becomes CONSTITUTIONAL LAW. It overrides every subsequent phase. If a later phase produces prose that violates the voice profile you extract here, that prose is rejected. No exceptions. No "close enough." The author's voice is the product.

---

TASK 1 — AUTHOR VOICE DNA EXTRACTION

Read the complete source text. You are not summarizing the story. You are studying HOW this specific human writes. Ignore plot. Ignore theme. Focus exclusively on craft mechanics.

Extract the following. Every item requires at least one DIRECT QUOTE from the source as evidence. Do not paraphrase. Do not generalize. Quote the line, then explain what it reveals about the author's technique.

A. SIGNATURE WRITING TECHNIQUES (extract 8-12)

For each technique:
- NAME it in 2-4 words (e.g., "Blunt declarative hits," "Sensory compression," "Dialogue as evasion")
- QUOTE 2-3 source lines that demonstrate it
- EXPLAIN in one sentence what makes this technique specific to THIS author (not just good writing in general)

The test: Could another skilled writer produce this technique by accident? If yes, it is not a signature. Dig deeper.

B. SENTENCE-LEVEL PATTERNS

Analyze a representative sample of at least 40 sentences from across the source. Report:
- Average sentence length (word count)
- Cadence variation pattern: Does this author alternate long/short? Build to long? Punch with short? Use fragments? Where?
- Clause structure preference: Simple declarative? Compound? Subordinate-leading? Mixed with identifiable rhythm?
- QUOTE 3-4 consecutive sentences that demonstrate the author's natural sentence rhythm at its most distinctive

C. DICTION FINGERPRINT

- Vocabulary clusters: What word families does this author return to? (e.g., mechanical/industrial, biological, domestic, violent, clinical)
- Register: Where does this author sit on formal-to-casual? Does register shift by context? How?
- Formality level: Academic? Street-level? Professional? Lyrical? Clipped?
- Word frequency patterns: What specific words does this author use MORE than a generic narrator would? What words does this author AVOID that most writers use freely?
- QUOTE 5-6 lines that demonstrate diction choices no other writer would make the same way

D. DIALOGUE FINGERPRINT — PER MAJOR CHARACTER

For EACH character who speaks more than 5 lines in the source:
- Character name
- Speech rhythm: Short bursts? Long explanations? Questions? Commands? Interruptions?
- Verbal tics or recurring phrases (quote them)
- Vocabulary restrictions: What words would this character NEVER say?
- Emotional range in dialogue: How does this character sound when angry vs. afraid vs. tender vs. lying?
- QUOTE the single line of dialogue that is MOST characteristic of this character. The line that, if you heard it without attribution, you would know who said it.

E. EMOTIONAL RANGE MAP

How does THIS author handle each of the following emotions? Not how emotions work in general. How THIS writer renders them on the page. For each: quote one source passage and describe the technique in one sentence.

- TENSION: Does this author build tension through withholding? Acceleration? Silence? Physical detail?
- HUMOR: Does humor arrive through dialogue? Absurdity? Understatement? Juxtaposition? Is it present at all?
- GRIEF: Rendered through action? Internal monologue? Absence? Physical sensation?
- WONDER: Present or absent? If present, how — lyricism? Specificity? Restraint?
- FEAR: Psychological? Physical? Ambient? Sudden?
- VIOLENCE: Graphic or implied? Fast or slow? Consequence-focused or impact-focused?
- INTIMACY: Physical detail? Emotional exposure? Dialogue-driven? Gesture-driven?

If an emotion is ABSENT from the source, note that explicitly. Absence is data.

F. PARAGRAPH ARCHITECTURE

How does this author build paragraphs?
- Short punches (1-2 sentences)?
- Long flowing blocks?
- Mixed with identifiable pattern?
- How does this author TRANSITION between paragraphs? Hard cuts? Bridging phrases? White space?
- QUOTE 2 consecutive paragraphs that demonstrate this author's paragraph-building at its most characteristic

OUTPUT FORMAT FOR TASK 1:

```
VOICE DNA PROFILE: [TITLE] by [AUTHOR]
Extracted by: Lorespinner Voice Lock Phase

SIGNATURE TECHNIQUES:
1. [NAME]: [Quote] — [Explanation]
2. [NAME]: [Quote] — [Explanation]
... (8-12 total)

SENTENCE PATTERNS:
Average length: [N] words
Cadence: [Description]
Clause preference: [Description]
Representative rhythm: [3-4 consecutive quoted sentences]

DICTION FINGERPRINT:
Vocabulary clusters: [List]
Register: [Description]
Formality: [Description]
Overused (signature): [Words/phrases]
Avoided: [Words/phrases]
Characteristic lines: [5-6 quotes]

CHARACTER DIALOGUE FINGERPRINTS:
[CHARACTER 1]:
  Rhythm: [Description]
  Tics: [Quotes]
  Restrictions: [Words they never say]
  Emotional range: [Description]
  Signature line: [Quote]
[CHARACTER 2]: ...
(all speaking characters)

EMOTIONAL RANGE:
Tension: [Quote] — [Technique]
Humor: [Quote] — [Technique] (or ABSENT)
Grief: [Quote] — [Technique]
Wonder: [Quote] — [Technique] (or ABSENT)
Fear: [Quote] — [Technique]
Violence: [Quote] — [Technique]
Intimacy: [Quote] — [Technique]

PARAGRAPH ARCHITECTURE:
Pattern: [Description]
Transitions: [Description]
Representative paragraphs: [2 consecutive quotes]
```

---

TASK 2 — MASTER RULE 1: HARD BAN LIST

This is the immune system. These patterns are BANNED from all generated prose across all Lorespinner IPs. The narrator must never produce them. The editorial verification phase must scan for them. Any occurrence is a hard fail.

SECTION A: UNIVERSAL BANS (hardcoded — apply to ALL IPs, ALL authors)

These are AI writing tells. They appear when models imitate creative prose without authorial constraint. They are never acceptable regardless of the IP or author.

PUNCTUATION BANS:
- Em dashes in all variants (—, --, –). Use periods, commas, or restructure the sentence.
- Ellipses (...) in narration. Dialogue only if the character's speech pattern requires trailing off, and only when established in the source.
- Emoji of any kind. Never. Not in narration, not in dialogue, not in stage directions.

SENTENCE MOLD BANS:
- "It's not X, it's Y." (The false-correction pivot. Sounds profound. Is scaffolding.)
- "No X. No Y. Just Z." (The stripped-down tricolon. Rhythmically addictive for AI. Instantly recognizable.)
- Balanced rule-of-three tricolons where all three elements are the same length and grammatical structure
- Mid-sentence rhetorical check-ins: "And honestly?" / "And really?" / "And look,"
- Trailing "like [metaphor]" similes in action lines (dialogue excluded — characters may speak in similes if their voice profile supports it)
- Contrast-framing scaffolding: sentences that exist only to set up a reversal ("She had always thought X. But now Y.")
- Symmetrical lists or mirrored clauses used for false profundity
- Generic uplift wrap-ups: sentences that land wisdom, poignancy, or hope at the end of a passage without earning it through prior action
- Sentences beginning with "And" as a dramatic intensifier more than once per 500 words

VOCABULARY BANS:
- tapestry, delve, underscore, highlight, showcase, intricate, swift, meticulous, adept
- "just" as a softener (permitted only in dialogue where the character's voice requires it)
- "that resonates," "that tracks," "that matters," "that lands"
- "And honestly" / "And look" / "And really"
- "woven into" / "weaving" / "wove" as default metaphor for connection or complexity
- "meaningful" as an adjective for connections, moments, or experiences
- "nestled" / "tucked away" for describing locations
- "etch/etched" for describing memory or emotion
- "navigate" for describing emotional or social situations (acceptable for literal navigation only)

AI FICTION MOTIF BANS:
- ghosts, spectral, shadow, whisper, quiet/quietness, hum/humming, echo, liminal, phantom WHEN used as default atmospheric texture. (Permitted only when the IP's canon specifically includes these as world elements, confirmed in StoryGuard Layer 1.)
- "Something shifted" / "Something clicked" / "Something broke" as emotional transitions
- Characters "letting out a breath they didn't know they were holding"
- Eyes "searching" faces
- Silence that "stretches" or "hangs" or "fills the room"
- Hearts that "hammer" or "race" or "skip" (use the author's actual physiological vocabulary from the voice DNA)
- Any weather that mirrors emotional state unless the author demonstrably uses pathetic fallacy in the source

NAME BANS:
- Elara, Voss, Kael, Echo (as character name), Ghost Code, Luminara, Seraphina, Thorne, Cipher, Nexus (as character or location names)
- Any name not present in the source IP's canon. The narrator does not invent names. Every name must trace to the source or to the StoryGuard-approved world extension rules.

CORPORATE/PR BANS:
- "woven into your daily rhythm"
- "memories were made"
- "meaningful connections"
- Any phrasing that reads like brand copy, app store description, or marketing material

SECTION B: IP-SPECIFIC BANS (generated per author from Task 1)

Using the Voice DNA Profile from Task 1, identify and ban:

1. ANTI-PATTERNS: Techniques this author NEVER uses that AI defaults to when imitating their genre. (Example: if the author never uses internal monologue, ban narrator-voice internal monologue. If the author never uses metaphor, ban decorative metaphors.)

2. VOCABULARY THE AUTHOR AVOIDS: Words the source text conspicuously never uses despite opportunities to do so. (Example: if the author writes action scenes without ever using "suddenly," ban "suddenly.")

3. RHYTHM VIOLATIONS: Sentence patterns that contradict the author's natural rhythm. (Example: if the author writes in short declarative bursts, ban compound sentences over 30 words in narration.)

4. EMOTIONAL TECHNIQUE VIOLATIONS: Ways of rendering emotion that contradict the author's method. (Example: if the author renders grief through silence and action, ban narrator-explained grief. If the author renders tension through physical detail, ban tension rendered through interior monologue.)

For each IP-specific ban:
- STATE the ban
- CITE the evidence from Task 1 that proves this author does not use this technique
- EXPLAIN what the AI should do instead (the positive replacement)

OUTPUT FORMAT FOR TASK 2:

```
MASTER RULE 1: HARD BAN LIST FOR [TITLE]

UNIVERSAL BANS: [Paste complete Section A above — this is hardcoded and identical for every IP]

IP-SPECIFIC BANS:
1. [BAN]: [Evidence from source] → INSTEAD: [What to do]
2. [BAN]: [Evidence from source] → INSTEAD: [What to do]
... (as many as the source warrants, minimum 6)
```

---

TASK 3 — 14-POINT CONTINUOUS AUDIT PROTOCOL

This protocol runs at PIPELINE TIME during Phase 8 (Editorial Verification). It does NOT run at runtime. At runtime, only the hard bans and positive voice markers from Tasks 1-2 are active in the system prompt. This is a quality gate, not a generation constraint. The distinction matters for cost and output quality.

For each audit point, provide:
- A CLEAR PASS/FAIL DEFINITION specific to this IP
- A DETECTION METHOD (what to scan for)
- A REPAIR INSTRUCTION (what to do when a violation is found)

THE 14 AUDIT POINTS:

1. HARD BAN TOKEN SCAN
Pass: Zero banned tokens, phrases, molds, motifs, or names from Master Rule 1 (universal + IP-specific) appear in any generated prose.
Detection: String-match scan against the complete ban list.
Repair: Replace banned element. Do not rephrase — rewrite the sentence using the author's documented techniques from the Voice DNA Profile.

2. RHYTHMIC NEATNESS SCAN
Pass: No passages contain three or more consecutive sentences of identical length (within 3 words) or identical grammatical structure.
Detection: Word-count and parse-structure comparison across consecutive sentences.
Repair: Vary sentence length and structure to match the author's cadence variation pattern documented in Task 1.

3. TRAILING SIMILE SCAN
Pass: Zero "like [metaphor]" constructions in action lines. Dialogue excluded.
Detection: Pattern match for "like" followed by a noun phrase in non-dialogue prose.
Repair: Cut the simile. Let the action line end on the action. If comparison is essential, use the author's documented comparison technique from Task 1.

4. TONE AUDIT
Pass: No passage drifts toward formal/neutral register that contradicts the author's documented register. No generic enthusiasm. No sudden tone shifts unearned by story events.
Detection: Read each passage against the author's register description from Task 1. Flag any passage where the register shifts without narrative cause.
Repair: Rewrite in the author's documented register. Match the formality level, vocabulary cluster, and emotional temperature from the Voice DNA Profile.

5. REPETITION AUDIT
Pass: No word appears more than twice in the same 200-word block (common articles/prepositions excluded). No sentence opening repeats within 3 consecutive sentences. No paragraph opening repeats the same construction within 5 consecutive paragraphs.
Detection: Frequency scan within rolling windows.
Repair: Vary using the author's documented vocabulary clusters and sentence patterns.

6. SPECIFICITY AUDIT
Pass: No passage contains vague abstractions ("something," "a feeling," "a sense of") where the author's source text would use concrete physical detail.
Detection: Flag abstract emotional language in narration. Compare against the author's emotional range map from Task 1.
Repair: Replace with physical, sensory, or action-based rendering using the author's documented technique for that emotion.

7. SENTENCE RHYTHM AUDIT
Pass: The cadence variation pattern matches the author's documented pattern from Task 1. Short sentences appear where the author uses short sentences. Long sentences appear where the author builds.
Detection: Compare sentence-length distribution in generated prose against the baseline distribution from the source.
Repair: Restructure to match the author's rhythm. If the author punches, punch. If the author flows, flow.

8. COHERENCE AUDIT
Pass: No passage contains an abrupt transition, random topic jump, or unearned emotional turn.
Detection: Read each paragraph transition. Flag any jump that is not motivated by the prior sentence.
Repair: Add bridging action or cut the incoherent passage entirely.

9. DEPTH AUDIT
Pass: No passage skims the surface of a moment that the story structure marks as significant. Branching choice outcomes and emotional turning points must render consequence, intention, and sensory detail.
Detection: Compare word count and specificity of significant moments against bridge narration. Significant moments should be denser.
Repair: Expand with physical detail, character reaction, and consequence. Use the author's technique for the relevant emotion from Task 1.

10. ACCURACY AUDIT
Pass: Zero invented facts, unverifiable claims, or details that contradict the source IP's established canon.
Detection: Cross-reference every proper noun, location, object, and stated fact against the source text and StoryGuard canon layers.
Repair: Remove invented facts. Replace with source-verified details.

11. VOICE AUDIT
Pass: The prose could be attributed to this specific author by a reader familiar with their work. Generic narration that could belong to any competent writer fails this test.
Detection: Read the cold open and three random narrative outcomes aloud. Apply the "attribution test": if the passage sounds like it could appear in any well-written story, it fails. It must sound like THIS author.
Repair: Rewrite using the signature techniques documented in Task 1. Layer at least 2 of the 8-12 signature techniques into any failing passage.

12. CREATIVITY AUDIT
Pass: No passage relies on default genre tropes, boilerplate mood-setting, or AI-poetic cliches. Every image, comparison, and atmospheric detail must feel authored.
Detection: Flag any image or mood that could appear in a different story of the same genre without modification.
Repair: Replace with imagery specific to this world, this character, this moment. Use the author's vocabulary clusters and emotional techniques.

13. HUMAN TEXTURE AUDIT
Pass: The prose has imperfections that feel authored — compressed phrasing, unexpected tangents, a rhythm break that serves the moment. Too-perfect prose fails. Every paragraph reading at the same quality level, the same density, the same rhythm fails.
Detection: Flag any passage where the quality is suspiciously uniform. Real authors write some passages tighter than others. They compress when urgent. They breathe when reflective. They break their own rules when the moment demands it.
Repair: Introduce the author's documented compression patterns and rhythm breaks. If the author punches during tension, tighten. If the author expands during wonder, let it breathe.

14. BIAS AUDIT
Pass: No stereotyped generalizations about any group. No algorithmic smoothing that rounds off character edges into generic likability. Characters retain their documented flaws, biases, and rough edges as established in the source.
Detection: Flag any character behavior that has been softened, sanitized, or made more generically sympathetic than the source warrants.
Repair: Restore the character's documented behavioral range from the Voice DNA dialogue fingerprint. Characters are who the author wrote them to be.

OUTPUT FORMAT FOR TASK 3:

```
14-POINT AUDIT PROTOCOL FOR [TITLE]

WHEN THIS RUNS: Phase 8 Editorial Verification (pipeline time only — NOT runtime)
PASS THRESHOLD: 14/14. Any failure returns to the generating phase for revision.

[For each of the 14 points, output the IP-specific pass definition, detection method, and repair instruction as specified above]
```

---

FINAL OUTPUT — VOICE LOCK PHASE COMPLETE

Assemble all three tasks into a single VOICE PROFILE DOCUMENT with this structure:

```
=== VOICE PROFILE: [TITLE] by [AUTHOR] ===
=== Extracted by Lorespinner Voice Lock Phase ===
=== This document is CONSTITUTIONAL LAW for all subsequent phases ===

SECTION 1: VOICE DNA PROFILE
[Task 1 complete output]

SECTION 2: MASTER RULE 1 — HARD BAN LIST
[Task 2 complete output]

SECTION 3: 14-POINT AUDIT PROTOCOL
[Task 3 complete output]

=== END VOICE PROFILE ===
```

This document feeds into:
- Phase 2 (Story Session Map) — for character voice reference
- Phase 5 (Choice Design) — for authored prose in choice outcomes
- Phase 8 (Editorial Verification) — for the voice audit and 14-point protocol
- The Runtime Narrator Template — Sections 1 (Narrator Identity), 3 (Character Reference), and 4 (Voice Profile) are populated directly from this output

STOP. The Voice Profile is the most consequential output in the pipeline. Every word the narrator speaks to every player will be measured against it. Before proceeding to Phase 2, review the Voice DNA extraction for completeness, the ban list for coverage, and the audit protocol for enforceability. If the author's voice cannot be clearly distinguished from generic AI narration using this document alone, revise before continuing.

---

## END OF DELIVERABLE 1
