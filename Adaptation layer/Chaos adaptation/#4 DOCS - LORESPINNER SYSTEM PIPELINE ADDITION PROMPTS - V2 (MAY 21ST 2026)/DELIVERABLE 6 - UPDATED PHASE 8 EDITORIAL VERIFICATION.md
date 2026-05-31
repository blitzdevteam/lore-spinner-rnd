# DELIVERABLE 6: UPDATED PHASE 8 — EDITORIAL VERIFICATION

**Lorespinner Pipeline Upgrade — May 2026**
**Type:** Updated pipeline phase (runs per episode)
**Replaces:** Current Phase 8 prompt (Logic Companion pages 25-28)
**Implementation:** Swap the existing Phase 8 prompt with this one. Expands from 10 questions to 23 across three sections.

---

## COPY-PASTE PROMPT — REPLACES EXISTING PHASE 8

---

LORESPINNER — PHASE 8: EDITORIAL VERIFICATION CHECKLIST

[PASTE MASTER CONTEXT BLOCK HERE]

COMPLETE SESSION DESIGN: [PASTE ALL PER-SESSION PHASE OUTPUTS — 3 through 7 — IN ORDER]
STORY SESSION MAP: [PASTE PHASE 2 OUTPUT — for cross-session verification]
VOICE PROFILE: [PASTE COMPLETE VOICE LOCK OUTPUT — all three sections]
PERSISTENT STATE SCHEMA: [PASTE PHASE 2 TASK 6 OUTPUT]
STORYGUARD CANON EXTRACTION: [PASTE PHASE 2 TASK 8 OUTPUT]

You are performing the final editorial gate. This checklist catches failures invisible at the phase level — they only surface when the full session is read as continuous experience. Read the COMPLETE session design as a player would experience it before answering any question.

Run each question. Return PASS or REVISE. If REVISE: name the specific element that fails, cite the phase, and provide one concrete revision instruction.

---

## SECTION A: DESIGN AUDIT (10 questions — existing, preserved)

QUESTION 1: WHERE DOES THE REAL DRAMATIC ENERGY BEGIN? DID WE START THERE?
Pass condition: The cold open starts at the moment of genuine dramatic tension. No passive setup precedes the first paragraph.
VERDICT: [PASS / REVISE]
IF REVISE: [Which phase? What to fix?]

QUESTION 2: WHAT IS THE EMOTIONAL PROMISE OF THE FIRST PARAGRAPH?
Pass condition: A user can name it in one word after reading sentence one.
VERDICT: [PASS / REVISE]
Emotional promise as written: [One word]

QUESTION 3: IS THE FIRST MEANINGFUL BRANCHING CHOICE REACHED WITHIN 300 WORDS?
Pass condition: Branching Choice #1 arrives at or before the 300-word mark.
VERDICT: [PASS / REVISE]
Word count to Choice #1: [NUMBER]

QUESTION 4: ARE ALL FOUR BRANCHING CHOICES GENUINELY CONSEQUENTIAL?
Pass condition: Each choice changes what the story tracks — not just the emotional register. Each has a named downstream payoff in a future session. Verify against Phase 6 consequence maps and Phase 2 cross-session payoff plan.
VERDICT: [PASS / REVISE]
If any choice lacks a named future-session payoff: [Name the choice and the missing payoff]

QUESTION 5: CAN A NEW USER FEEL THE STAKES WITHIN 60 SECONDS?
Pass condition: Internal stakes (what the protagonist wants and why) are established before the first external choice arrives.
VERDICT: [PASS / REVISE]
Stakes as established: [One sentence]

QUESTION 6: DOES A DECISION MADE EARLY HAVE VISIBLE IMPACT LATER?
Pass condition: At least one choice from this session has a named, specific payoff in a future session — a line, object, character reaction, or scene entry that reflects the earlier choice.
VERDICT: [PASS / REVISE]
Earliest payoff identified: [Choice #, future session moment, what it shows]

QUESTION 7: IS THERE A BREATH BEAT BETWEEN MINUTES 10-13?
Pass condition: A humor, absurdity, or wonder beat exists. Tension is deliberately released before the Twist beat compounds it.
VERDICT: [PASS / REVISE]
Breath beat timing: [Minute marker]
Breath beat moment: [Name it]

QUESTION 8: DOES AT LEAST ONE CHOICE PRESENT A GENUINE MORAL GRAY AREA?
Pass condition: The moral-weight branching choice has no objectively correct answer. Each option reflects a legitimate human value.
VERDICT: [PASS / REVISE]
Three values in tension: [List them]
Could a thoughtful user defend all three? [YES / NO]

QUESTION 9: WHAT EMOTIONAL STATE DOES THE USER CARRY OUT?
Pass condition: The user exits carrying ANTICIPATION (unresolved decision from session-end hook). Not RESOLUTION (fully closed) or CONFUSION (unclear).
VERDICT: [PASS / REVISE]
Emotional state as designed: [ANTICIPATION / RESOLUTION / CONFUSION]

QUESTION 10: WOULD A FRIEND IMMEDIATELY ASK "WHAT DID YOU CHOOSE?"
Pass condition: At least one choice is talkable — specific enough, morally weighted enough, surprising enough that a user would bring it up unprompted.
VERDICT: [PASS / REVISE]
Most talkable choice: [#]
Why: [One sentence]

---

## SECTION B: VOICE AUDIT (6 questions — NEW)

These questions enforce the Voice Profile from the Voice Lock Phase. They are the pipeline-time execution of voice protection.

QUESTION 11: HARD BAN SCAN
Pass condition: ZERO banned tokens, phrases, molds, motifs, or names from Master Rule 1 (universal AND IP-specific) appear in ANY generated prose — cold open, narrative bridges, choice outcomes, posture shift adjustments, resolution prose. Every word.
Detection method: String-match scan against the complete ban list from Voice Lock Section 2.
VERDICT: [PASS / REVISE]
If REVISE: [List every banned element found, its location, and the specific line. Each is a hard fail.]

QUESTION 12: TRAILING SIMILE SCAN
Pass condition: ZERO "like [metaphor]" constructions in action lines. Dialogue excluded only if the character's Voice Profile supports simile use.
Detection method: Pattern match for "like" followed by noun phrase in non-dialogue prose.
VERDICT: [PASS / REVISE]
If REVISE: [List every occurrence]

QUESTION 13: SENTENCE MOLD SCAN
Pass condition: ZERO instances of the following in any generated prose:
- "It's not X, it's Y." (false-correction pivot)
- "No X. No Y. Just Z." (stripped-down tricolon)
- Balanced rule-of-three tricolons where all elements match length and structure
- Contrast-framing scaffolding ("She had always thought X. But now Y.")
Detection method: Pattern scan for each mold type.
VERDICT: [PASS / REVISE]
If REVISE: [List every occurrence with location]

QUESTION 14: VOICE AUTHENTICITY TEST
Pass condition: Read the cold open and three random narrative outcomes ALOUD. Apply the attribution test: could a reader familiar with this author identify the voice? If the prose sounds like it could appear in any well-written story by any writer, it fails. It must sound like THIS author.
Reference: Voice Lock Section 1 — Signature Techniques and Sentence Patterns.
Test method: Identify at least 2 of the author's 8-12 signature techniques in the cold open. Identify at least 1 per random outcome sampled.
VERDICT: [PASS / REVISE]
Signature techniques detected: [List which ones, where]
If REVISE: [Which passages fail the attribution test? What is missing?]

QUESTION 15: FULL 14-POINT AUDIT
Pass condition: Run ALL 14 audit points from Voice Lock Section 3 against all generated prose. Report results per point.
This is where the 14-point protocol executes. It runs here, at pipeline time, not at runtime.

```
Audit point                    | PASS / FLAG
-------------------------------|------------
1. Hard ban token scan         | [Already covered in Q11 — confirm PASS]
2. Rhythmic neatness scan      | [PASS / FLAG: location]
3. Trailing simile scan        | [Already covered in Q12 — confirm PASS]
4. Tone audit                  | [PASS / FLAG: location]
5. Repetition audit            | [PASS / FLAG: location]
6. Specificity audit           | [PASS / FLAG: location]
7. Sentence rhythm audit       | [PASS / FLAG: location]
8. Coherence audit             | [PASS / FLAG: location]
9. Depth audit                 | [PASS / FLAG: location]
10. Accuracy audit             | [PASS / FLAG: location]
11. Voice audit                | [Already covered in Q14 — confirm PASS]
12. Creativity audit           | [PASS / FLAG: location]
13. Human texture audit        | [PASS / FLAG: location]
14. Bias audit                 | [PASS / FLAG: location]
```

VERDICT: [PASS (14/14) / REVISE (list all flags)]

QUESTION 16: DICTION FINGERPRINT CHECK
Pass condition: Protagonist's internal voice matches the author's diction patterns from Voice Lock. NPC dialogue matches character fingerprints from Voice Lock.
Test method: Sample 3 passages of protagonist narration and 1 dialogue passage per major NPC. Compare vocabulary, register, and rhythm against the Voice Lock diction fingerprint and dialogue fingerprints.
VERDICT: [PASS / REVISE]
If REVISE: [Which character's voice drifts? How?]

---

## SECTION C: STORYGUARD AND STATE COMPLIANCE (6 questions — NEW)

These questions enforce world integrity, canon protection, and persistent state accuracy.

QUESTION 17: CANON INTEGRITY
Pass condition: No choices, outcomes, narrative bridges, or posture shift adjustments introduce elements outside this IP's canon as defined in StoryGuard Layer 1.
Test method: Cross-reference every proper noun, object, creature, technology, and location mentioned in generated prose against the Layer 1 Canon Rules. Anything not in the canon is a violation.
VERDICT: [PASS / REVISE]
If REVISE: [List every canon violation with location]

QUESTION 18: CHARACTER TRUTH
Pass condition: No NPC behavior in any generated prose violates the Character Rules from StoryGuard Layer 3.
Test method: For each NPC appearance in generated prose, verify: Does their dialogue match their Voice Lock fingerprint? Do their actions stay within their behavioral boundaries? Do they react consistently with their disposition level in the persistent state?
VERDICT: [PASS / REVISE]
If REVISE: [Which character breaks truth? Where? What boundary is violated?]

QUESTION 19: WORLD REACTIVITY
Pass condition: The World Reactivity Rules from Phase 2 Task 7 are ACTIVE and VISIBLE in the generated content. The world must FEEL responsive to the player's behavioral patterns, not just track them silently.
Test method: For each World Reactivity Rule, identify at least one moment in the session design where the rule could fire. Verify that the consequence maps and narration bridges include space for the reactive behavior.
VERDICT: [PASS / REVISE]
If REVISE: [Which rules are dormant? Where should they manifest?]

QUESTION 20: PERSISTENT STATE CONSISTENCY
Pass condition: All state changes specified in Phase 5 (Persistent State Changes per choice) appear accurately in Phase 6 (World State Deltas). No state change is specified in one phase and missing from the other.
Test method: Cross-reference every Phase 5 state change against the corresponding Phase 6 delta. Check: inventory, NPC dispositions, environmental flags, emotional ledger entries, alignment shifts.
VERDICT: [PASS / REVISE]
If REVISE: [List every mismatch between Phase 5 and Phase 6]

QUESTION 21: WORLD NOTICED SIGNALS
Pass condition: Every significant persistent state change has an in-world acknowledgment written in Phase 5. Every signal is: (a) in the author's voice, (b) grounded in the scene's physical reality, (c) never meta or gamey, (d) subtle enough to reward attention without breaking immersion.
Test method: List every World Noticed Signal from Phase 5. Verify each against the four criteria.
VERDICT: [PASS / REVISE]
If REVISE: [Which signals fail? Which criterion?]

QUESTION 22: SOCIAL ECHO DEFINING LINES
Pass condition: Every branching choice has 3 defining lines (one per path) written in Phase 5 Task 8. Each line is: (a) in the author's voice, (b) under 20 words, (c) provocative without spoiling, (d) would create curiosity in someone who has not played.
Test method: Read each defining line. Apply the "friend test" — would reading this line on a share card make a non-player ask "What happened?"
VERDICT: [PASS / REVISE]
If REVISE: [Which lines fail? Which criterion?]

QUESTION 23: ALIGNMENT BALANCE
Pass condition: The three options per branching choice genuinely span chaotic/lawful/neutral. No choice accidentally offers three options in the same register. No alignment is systematically advantaged or punished.
Test method: For each branching choice, read all three options blind (without seeing the alignment tags). Could a player naturally be drawn to any of the three? Does one option read as obviously "correct" or "wrong"? If yes, the alignment balance fails.
VERDICT: [PASS / REVISE]
If REVISE: [Which choice is unbalanced? Which alignment is over/under-represented?]

---

## FINAL VERDICT

```
SECTION A: DESIGN AUDIT
  Q1  — Entry point:            [PASS / REVISE]
  Q2  — Emotional promise:      [PASS / REVISE]
  Q3  — First choice timing:    [PASS / REVISE]
  Q4  — Consequential choices:  [PASS / REVISE]
  Q5  — Stakes within 60 sec:   [PASS / REVISE]
  Q6  — Early decision impact:  [PASS / REVISE]
  Q7  — Breath beat:            [PASS / REVISE]
  Q8  — Moral gray area:        [PASS / REVISE]
  Q9  — Exit emotional state:   [PASS / REVISE]
  Q10 — Talkability:            [PASS / REVISE]

SECTION B: VOICE AUDIT
  Q11 — Hard ban scan:          [PASS / REVISE]
  Q12 — Trailing simile scan:   [PASS / REVISE]
  Q13 — Sentence mold scan:     [PASS / REVISE]
  Q14 — Voice authenticity:     [PASS / REVISE]
  Q15 — 14-point audit:         [PASS / REVISE]
  Q16 — Diction fingerprint:    [PASS / REVISE]

SECTION C: STORYGUARD + STATE
  Q17 — Canon integrity:        [PASS / REVISE]
  Q18 — Character truth:        [PASS / REVISE]
  Q19 — World reactivity:       [PASS / REVISE]
  Q20 — State consistency:      [PASS / REVISE]
  Q21 — World Noticed signals:  [PASS / REVISE]
  Q22 — Defining lines:          [PASS / REVISE]
  Q23 — Alignment balance:      [PASS / REVISE]

TOTAL PASSING: [N] / 23
```

PRODUCTION STATUS:
- 23/23: GREEN LIGHT. This session is ready for production.
- 20-22/23: AMBER. Fix flagged items. Re-run failed questions only.
- Below 20: RED. Return to flagged phases. Full re-run after revision.

REVISION INSTRUCTIONS (for any REVISE verdicts):
[List each failed question, the phase it belongs to, and the single most important revision]

---

OUTPUT FORMAT — Return the full checklist table and final verdict as a single Phase 8 document.

PRODUCTION GATE: A session that does not pass 23/23 is not ready. Partial passes are not acceptable for launch. Return, revise, re-verify. The player deserves a world that remembers, a voice that is real, and a story that holds together. That is the minimum.

---

## END OF DELIVERABLE 6
