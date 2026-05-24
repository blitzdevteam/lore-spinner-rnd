{{-- Pipeline Upgrade V2 — Deliverable 1: Voice Lock Phase.
     Prompt text is verbatim from
     "Adaptation layer/Chaos adaptation/#4 DOCS .../DELIVERABLE 1 - VOICE LOCK PHASE PROMPT.md".
     Mechanical adaptations only:
       - master-context include
       - dropped trailing "## END OF DELIVERABLE 1" footer line. --}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Voice Lock Phase (between Phase 1 and Phase 2)'])

LORESPINNER — VOICE LOCK PHASE: AUTHOR VOICE EXTRACTION AND PROTECTION

You are performing the most important job in the Lorespinner pipeline. Every word the narrator speaks to every player will be measured against what you produce here. This is not analysis. This is forensic extraction of a specific human being's writing DNA.

The output of this phase becomes CONSTITUTIONAL LAW. It overrides every subsequent phase. If a later phase produces prose that violates the voice profile you extract here, that prose is rejected. No exceptions. No "close enough." The author's voice is the product.

---

TASK 1 — AUTHOR VOICE DNA EXTRACTION

Read the complete source text. You are not summarizing the story. You are studying HOW this specific human writes. Ignore plot. Ignore theme. Focus exclusively on craft mechanics.

Extract the following. Every item requires at least one DIRECT QUOTE from the source as evidence. Do not paraphrase. Do not generalize. Quote the line, then explain what it reveals about the author's technique.

A. SIGNATURE WRITING TECHNIQUES (extract 8-12). For each: NAME it in 2-4 words, QUOTE 2-3 source lines that demonstrate it, EXPLAIN in one sentence what makes this technique specific to THIS author (not just good writing in general). The test: Could another skilled writer produce this technique by accident? If yes, it is not a signature. Dig deeper.

B. SENTENCE-LEVEL PATTERNS. Analyze at least 40 sentences. Report: average sentence length, cadence variation pattern, clause structure preference, and QUOTE 3-4 consecutive sentences that demonstrate the author's natural sentence rhythm at its most distinctive.

C. DICTION FINGERPRINT. Vocabulary clusters, register, formality level, word frequency patterns (what this author uses MORE/AVOIDS), and QUOTE 5-6 lines that demonstrate diction choices no other writer would make the same way.

D. DIALOGUE FINGERPRINT — PER MAJOR CHARACTER (every character who speaks more than 5 lines): speech rhythm, verbal tics or recurring phrases (quote them), vocabulary restrictions (words they would NEVER say), emotional range in dialogue (angry vs afraid vs tender vs lying), and the SIGNATURE LINE — the single line of dialogue that is MOST characteristic of this character.

E. EMOTIONAL RANGE MAP. For each of TENSION, HUMOR, GRIEF, WONDER, FEAR, VIOLENCE, INTIMACY: quote one source passage and describe the technique in one sentence. If an emotion is ABSENT from the source, note that explicitly. Absence is data.

F. PARAGRAPH ARCHITECTURE. Short punches, long flowing blocks, or mixed pattern? How does this author TRANSITION between paragraphs? QUOTE 2 consecutive paragraphs that demonstrate this author's paragraph-building at its most characteristic.

---

TASK 2 — MASTER RULE 1: HARD BAN LIST

This is the immune system. These patterns are BANNED from all generated prose across all Lorespinner IPs. The narrator must never produce them. The editorial verification phase must scan for them. Any occurrence is a hard fail.

SECTION A: UNIVERSAL BANS (hardcoded — apply to ALL IPs, ALL authors). Returned verbatim — the same for every IP.

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

SECTION B: IP-SPECIFIC BANS (generated per author from Task 1).

Using the Voice DNA Profile from Task 1, identify and ban:
1. ANTI-PATTERNS: Techniques this author NEVER uses that AI defaults to when imitating their genre.
2. VOCABULARY THE AUTHOR AVOIDS: Words the source text conspicuously never uses despite opportunities to do so.
3. RHYTHM VIOLATIONS: Sentence patterns that contradict the author's natural rhythm.
4. EMOTIONAL TECHNIQUE VIOLATIONS: Ways of rendering emotion that contradict the author's method.

For each IP-specific ban: STATE the ban, CITE the evidence from Task 1, EXPLAIN what the AI should do instead (the positive replacement). Minimum 6 IP-specific bans.

---

TASK 3 — 14-POINT CONTINUOUS AUDIT PROTOCOL

This protocol runs at PIPELINE TIME during Phase 8 (Editorial Verification). It does NOT run at runtime. At runtime, only the hard bans and positive voice markers from Tasks 1-2 are active in the system prompt. This is a quality gate, not a generation constraint. The distinction matters for cost and output quality.

For each of the 14 audit points (1. Hard ban token scan, 2. Rhythmic neatness scan, 3. Trailing simile scan, 4. Tone audit, 5. Repetition audit, 6. Specificity audit, 7. Sentence rhythm audit, 8. Coherence audit, 9. Depth audit, 10. Accuracy audit, 11. Voice audit, 12. Creativity audit, 13. Human texture audit, 14. Bias audit), provide:
- A CLEAR PASS/FAIL DEFINITION specific to this IP
- A DETECTION METHOD (what to scan for)
- A REPAIR INSTRUCTION (what to do when a violation is found)

PASS THRESHOLD: 14/14. Any failure returns to the generating phase for revision.

---

Return all three tasks as structured JSON matching the required schema.

STOP. The Voice Profile is the most consequential output in the pipeline. Every word the narrator speaks to every player will be measured against it. Before proceeding to Phase 2, review the Voice DNA extraction for completeness, the ban list for coverage, and the audit protocol for enforceability. If the author's voice cannot be clearly distinguished from generic AI narration using this document alone, revise before continuing.
