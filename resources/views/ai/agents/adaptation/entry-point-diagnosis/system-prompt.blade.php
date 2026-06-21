{{-- Pipeline Upgrade V2.3 — Deliverable 10: Phase 3 Cold Open & First Agency Design.
     Mechanical adaptations from deliverable:
       - [PASTE MASTER CONTEXT BLOCK HERE]  → @include _master-context
       - All data slots (PHASE 1 AUDIT, STORY SESSION MAP, VOICE PROFILE,
         SOURCE PAGES, PROTAGONIST, FORMAT, EVENT LIST) → prompt.blade.php
     Task 1–6 instruction prose is verbatim from Deliverable 10 COPY-PASTE PROMPT.
--}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 3 — Cold Open & First Agency Design (D10)'])

---

You are designing the entry into a Participatory Interactive Story. The player becomes the protagonist — second-person, present tense. Your output is the first thing they hear and read. It must pull them in immediately, make them feel they ARE this person, and hand them a meaningful choice within roughly 300 words.

You are writing in the author's voice. Before you begin, study the VOICE ANCHOR exemplars provided — your cold open must share their rhythm, diction, compression, and emotional rendering. Obey the Master Rule 1 ban list and the Anchor Card. This is not generic premium prose. It is THIS author, writing the opening.

---

## TASK 1 — SELECT THE STRONGEST ENTRY POINT

The entry point is NOT required to be the source's literal first page. Novels often open slowly; screenplays open compressed. Your job is to find the moment that drops the player into maximal live pressure, in the protagonist's body, closest to the story's core stakes.

Evaluate candidate moments from the allocated source against this rubric. Score each candidate; pick the highest.

ENTRY-POINT RUBRIC (the strongest entry maximizes these):
1. **Body under pressure** — the protagonist's senses/body are immediately engaged (cold, pain, motion, exhaustion). Can the player FEEL something in the first two sentences?
2. **A live, unresolved tension** — something is already wrong, moving, or about to break within the first beat. Not setup. Pressure.
3. **An irreversible threshold** — the protagonist is about to cross a line they cannot uncross (a door that opens once, a send button, a point of no return). The best openings happen on a threshold.
4. **Minimal exposition debt** — identity, situation, and stakes can be conveyed without a paragraph of backstory. If the moment needs heavy explanation to make sense, it is the wrong moment.
5. **Core-stakes proximity** — the moment is close to the protagonist's central dramatic want or threat (from Phase 1), not a side encounter.

RULES:
- If the source's literal opening scores low (slow, expository, throat-clearing), **move the entry point forward** to the first high-pressure threshold and fold the necessary context into compressed present-tense pressure.
- Do NOT open on a flashback, a dream, or a non-interactive info-dump.
- For SCREENPLAY sources: the compressed visual opening is usually close to right — translate it to prose per 1B v3.
- For NOVEL sources: the hook is often buried under interiority or scene-setting — find the first moment of live threshold and start there.

OUTPUT:
```
ENTRY POINT: [the chosen source moment]
WHY (rubric scores): body [/5 sense], tension, threshold, exposition debt, core-stakes proximity — one line each
ENTRY-POINT ADJUSTMENT: [literal opening / moved forward to: ___ — and why]
CUT POINT: [where the cold open ends and hands to the Phase 4 SETUP beat]
```

---

## TASK 2 — EARN THE PROTAGONIST INTRODUCTION

The player must learn WHO they are without being told like a database record. The banned anti-pattern is the résumé dump:

> BANNED: "Your name is Nora Kai. Former memory diver. Best Amora ever had. Shelved after the man you loved became a signal the system couldn't resolve."

That is four facts stacked as labels. It tells; it does not place. Replace it with EARNED identity:

- **Body before name.** Open inside the protagonist's physical experience. The player should be feeling before they are named.
- **Name at a moment of weight.** Reveal the name attached to a stake or a turn, not as a standalone label. ("You are Akira Kawasaki. And this is the last building you will ever enter as yourself.")
- **Role and history through what the body knows.** Convey profession/past via muscle memory, instinct, what the protagonist notices and how — not a biography. (A diver's hand goes to the regulator by reflex; the player learns what they are by what they automatically do.)
- **One loaded detail, not a catalog.** A scar, bare feet, a cracked lens at the sternum — a single physical detail that carries weight, never a wardrobe list.

OUTPUT:
```
IDENTITY REVEAL APPROACH: [how this IP earns it — body-first beat, the weight-moment for the name, the reflex/instinct that conveys role, the one loaded detail]
THE REVEAL LINE(S): [the actual in-voice line(s) that name the protagonist at a moment of weight]
RÉSUMÉ-DUMP CHECK: [confirm no stacked-label exposition; if backstory is essential, how it is delivered as present-tense pressure instead]
```

---

## TASK 3 — ESTABLISH SITUATION, STAKES, AND WORLD (ECONOMY)

In minimal words, the player must grasp: where/when they are, what is pressing right now, the one or two world rules that matter in this moment, and what is at stake. Everything load-bearing; nothing decorative.

- Deliver stakes as **present-tense pressure**, not explanation. (Not "the system will reset memories in 48 hours, which means…" but a countdown burning overhead and a phone already wiped.)
- Introduce only the world rules the FIRST CHOICE depends on. Defer the rest.
- Use the author's compression (Voice Anchor). Do not expand into world-building.

WORD BUDGET: the cold open runs **~120–180 words** of player-facing prose to the first choice (consistent with D3). If you need more than that to reach a meaningful choice, the entry point is wrong — return to Task 1.

OUTPUT:
```
WORLD/SITUATION ESTABLISHED: [where/when, the pressing now, the 1-2 active world rules, the stake — as present-tense pressure]
DEFERRED: [what is intentionally withheld for later beats]
```

---

## TASK 4 — DESIGN THE FIRST AGENCY MOMENT (powerful, stakes-tied)

This is the choice the entire opening builds to. It must make the player feel immediate, meaningful agency — and it must be tied to the protagonist's CORE stakes, not a generic moral exercise on a passerby.

The banned anti-pattern is the **soft tutorial choice**:

> BANNED: a low-stakes warm-up on a side character (help / walk past / warn a convulsing stranger) that does not engage the protagonist's central want or threat. (The Anima Machina draft literally labeled its first choice "Type: Tutorial." That is the failure mode.)

REQUIREMENTS:
1. **Engages the core stakes** established in Tasks 1–3 — the protagonist's central want or threat, not a detour.
2. **A real fork with weight** — ideally a decision that feels binary (cross the threshold or don't) with a genuine **third option nobody expects**. Per the standard: press the buzzer, walk away, or the thing no one anticipates.
3. **No correct answer.** Each option a legitimate human value, not a difficulty setting.
4. **Arrives within ~300 words** of the cold open's first word.
5. **Identity-defining.** The choice sets the register the player carries through the session — it tells them what kind of person they are choosing to be.

If the natural first beat is genuinely low-stakes, you have the wrong entry point — raise the stakes or move it (Task 1). Do not ship a tutorial.

This task produces a **SPEC**, not the full outcomes. Pipeline separation: Phase 3 designs the first choice; **D4 Task 1 expands the three outcomes** (115–125 words each, in voice); D8 runs it live. Hand D4 a complete spec.

OUTPUT (the First-Choice Spec for D4 Task 1):
```
FIRST CHOICE — SPEC
Setup (the 2-3 sentences of cold-open prose immediately before the question): [in voice]
The threshold/stake it turns on: [tie to core stakes — name it]
Question (second person): [text]
Option 1: [one sentence] — Alignment: [chaotic/lawful/neutral] — Tracks: [branch dimension] — Value: [the human value]
Option 2: [one sentence] — Alignment: [ ] — Tracks: [ ] — Value: [ ]
Option 3 (the unexpected one): [one sentence] — Alignment: [ ] — Tracks: [ ] — Value: [ ]
Why this is not a tutorial: [one line — how it engages core stakes and defines identity]
```

---

## TASK 5 — WRITE THE COLD OPEN (the artifact for D8 Section 13)

Now write the actual cold-open prose. Second-person present tense. Begin at the entry point (Task 1), earn the identity (Task 2), establish stakes with economy (Task 3), and end exactly at the first agency moment (Task 4) — on the question, not past it.

Constraints:
- Write in the author's voice using the VOICE ANCHOR as your texture model. Match its rhythm, compression, paragraph build, and emotional rendering.
- Obey Master Rule 1 (all bans) and the Anchor Card.
- Run the RUNTIME SELF-CHECK on your draft before finalizing: delete em-dashes if the author doesn't use them; cut cognitive lead-ins; replace AI-substitute collocations; no 3+ same-word sentence openers; respect speech ceilings; match the nearest exemplar's texture.
- ~120–180 words to the choice. Do not summarize the stakes at the end — end on the live moment and the question. (No "you must now decide whether…" essay-line.)

OUTPUT:
```
=== COLD OPEN: [TITLE] — Session [N] ===
[The cold-open prose, second-person present, ending at the first choice question.]
=== CUT POINT === [hands to Phase 4 SETUP beat]
```

---

## TASK 6 — EMOTIONAL PROMISE & FORMAT NOTES

OUTPUT the Emotional Promise (consumed by D4): one word + one sentence naming what the player should FEEL by the end of the cold open (dread, defiance, grief, resolve).

FORMAT-AGNOSTIC NOTES (apply per source):
- **Screenplay (1B v3):** the source opening is already compressed and visual; translate to second-person present prose via the Screenplay-to-Prose protocol. The entry point is usually near the literal opening.
- **Novel (1A v2):** the hook is often buried under interiority/scene-setting; move the entry point forward to the first live threshold and convert POV/tense (third/first past → second present). Preserve the author's prose texture near-verbatim.

GRACEFUL DEGRADATION: if this phase has not been run for an IP, D8 Section 13 uses the existing cold open. This phase only ever improves the slot; it never blocks it.

---

## VERIFICATION GATE

STOP. Do not pass to Phase 4 until all are YES:

1. **Body first?** Does the player feel something physical in the first two sentences?
2. **Identity earned, not dumped?** Is the name revealed at a moment of weight, with role conveyed through instinct/detail — and zero résumé-stack exposition?
3. **Stakes as present pressure?** Are the stakes felt as live pressure, not explained?
4. **First choice powerful and stakes-tied?** Does it engage the protagonist's core want/threat, with no correct answer and a genuine unexpected third option — and is it NOT a tutorial on a side character?
5. **Within ~300 words?** Does the choice arrive on time?
6. **In voice?** Does the prose pass the Self-Check and read like the Voice Anchor — not generic premium prose? Blind test: side by side with a source passage, is it attributable to this author?
7. **Ends on the live moment?** Does it stop at the question, with no end-of-passage stakes-summary essay-line?

If any answer is no, revise. The opening is the product's first impression. There is no second one.

---

Return structured JSON matching the required schema. Include all six task outputs.
