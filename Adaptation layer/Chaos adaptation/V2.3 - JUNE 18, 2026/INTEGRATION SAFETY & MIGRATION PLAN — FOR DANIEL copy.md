# Integration Safety & Migration Plan

**To:** Daniel — Pipeline Engineering
**From:** Thomas Wittmer / Lorespinner Interactive
**Date:** June 18, 2026
**Re:** How to roll out the voice-decay fix (1A v2, 1B v3, D8 v2) — and the upcoming Cold Open phase — without risk to the live platform. Change inventory, the one real wiring step, staged rollout, pre-flight checks, and rollback.

---

## THE BOTTOM LINE

Nothing here can take the platform down for days. Two structural facts bound the risk:

1. **Build-time vs. runtime is a firewall.** Most of these deliverables run **once per IP at build time** and compile a *static* runtime prompt. Changing a build-time prompt does **not** alter any IP already built and live. Only the runtime template (D8) touches live gameplay, and even that is compiled per IP into a static artifact. So no change cascades across all IPs — it affects only IPs you deliberately rebuild.
2. **These are prompt swaps, not code rewrites.** Every deliverable is prompt text pasted into an existing job. The only genuinely new wiring is the D8 assembly job mapping a few new slots. Everything else is swap-the-text. Reversal is: point the job back at the prior prompt. Minutes, not days.

The deliverables have also been **hardened to fail safe**: if an IP's Voice Lock output predates the upgrade, the runtime template omits the new sections and runs exactly as v1 did. So you can deploy D8 v2 even before rebuilding every IP.

Roll out **one IP at a time, behind a canary**, keep the old prompts as fallback, and the worst case is a single IP reverting in minutes.

---

## CHANGE INVENTORY

| Deliverable | Type | Job it touches | New wiring required | Break risk | Mitigation |
|---|---|---|---|---|---|
| **1B v3** (Screenwriter Voice Lock) | Build-time, per IP | VoiceLockChapterJob (screenplay branch) | None — paste replaces 1B FINAL + 1B v2 | Low | Sections 1–2 unchanged; new sections additive |
| **1A v2** (Novelist Voice Lock) | Build-time, per IP | VoiceLockChapterJob (prose branch) | None — paste replaces 1A FINAL | Low | Sections 1–2 unchanged; new sections additive |
| **D8 v2** (Runtime Template) | Runtime, per IP (compiled) | Runtime assembly job | **Yes — map 4 new slots** | Medium | Fail-safe omit + name-based mapping (below) |
| **Cold Open phase** (next, not yet built) | Build-time, per IP | New job before Beat Architecture | New job + one new D8 slot | Medium | Built additively; pre-specified contract (below) |
| **D4 Task 1 first-choice upgrade** (next) | Build-time, per IP | Choice Design job | None — prompt-content swap | Low | Additive guidance only |
| **D8 forward-pull ending tweak** (next) | Runtime | Runtime template | None — prompt-content swap | Low | Narration-rule wording only |

**Not changed by any of this** (so they cannot break): FormatDetection routing, StoryGuard layers, persistent-state schema, choice mechanics, consequence maps, world reactivity, Social Echo. The change surface is confined to **voice** and (next) the **cold open**.

---

## THE ONE REAL NEW WIRING: D8 ASSEMBLY SLOT MAPPING

This is the only thing that requires engineering attention. Everything else is paste-and-go.

The runtime assembly job must map four new inputs from the Voice Lock output into D8 v2:

| D8 v2 slot | Source (Voice Lock output) | Rule |
|---|---|---|
| `{{VOICE_ANCHOR}}` → Section 4A | "THE VOICE ANCHOR" section | Load **verbatim**. Cut **last** under token pressure; floor of 5 exemplars. |
| `{{ANCHOR_CARD}}` → Section 18 | "THE ANCHOR CARD" section | Verbatim. Re-asserted at end of prompt. Never cut. |
| `{{RUNTIME_SELF_CHECK}}` → Section 18 | "RUNTIME SELF-CHECK" section | Verbatim. Never cut. |
| `{{SPEECH_CEILING}}` → Section 3 | Per-character longest speech (Task 1) | Per character; omit if absent. |

**Two rules make this safe:**

- **Map by header NAME, not position.** The Voice Lock output gained sections, so indices shifted (the old 14-point audit is no longer the third section). Locate each input by its header string. **This is the single most important thing to confirm before deploying — if the assembly currently parses by position, that is the one place to fix.**
- **Omit, never emit.** If a section is absent (old-build IP), omit the sub-block entirely. The assembly must never write a literal `{{VOICE_ANCHOR}}` token into a live prompt. Omission yields exact v1 behavior.

---

## FAIL-SAFE BEHAVIOR (why deploying D8 v2 can't break an old IP)

D8 v2 was written to degrade, not break:

- IP **rebuilt** on 1A v2 / 1B v3 → has Voice Anchor + Anchor Card + Self-Check → full voice protection.
- IP **not yet rebuilt** (old Voice Lock output) → those sections are absent → assembly omits them → narrator runs on technique descriptions + ban list = **identical to v1**.

So you can deploy D8 v2 as the shared template first, with zero IP rebuilt, and observe no behavior change. Then rebuild IPs one at a time to turn on the new protection. The two changes are decoupled.

---

## STAGED ROLLOUT (canary — never big-bang)

1. **Pick one low-stakes IP** (a test IP, or a clone of a live one). Do not start on your flagship.
2. **Re-run Voice Lock** (1A v2 for prose / 1B v3 for screenplay) → produces the new-shape Voice Profile with the three new sections.
3. **Assemble the runtime prompt with D8 v2.** Run the Pre-Flight Checklist below.
4. **Run Build-Time QA** on 8–12 sample outputs (the decay checklist from the QA Finding doc): em-dashes ~0, no smooth-triad/"the kind of"/essay-line drift, tail compression matches the head.
5. **Smoke-test a live session** end to end on the canary IP.
6. **Promote** only after 3–5 pass. Then expand IP by IP, re-running QA each time.
7. **Keep the old prompts in place** as the fallback throughout the rollout.

Existing live IPs keep running on their already-built prompts the entire time. They are not touched until you rebuild them.

---

## PRE-FLIGHT CHECKLIST (assert all TRUE before any prompt goes live)

- [ ] Assembly parses Voice Lock sections by **header name**, not position. *(Confirm once; highest-risk item.)*
- [ ] **No literal `{{...}}` token** remains anywhere in the assembled prompt.
- [ ] Total character count **< 65,000**.
- [ ] Section 13 (Cold Open) is **populated**.
- [ ] Master Rule 1 ban list is present.
- [ ] **Either** full mode (Voice Anchor ≥5 exemplars AND Anchor Card + Self-Check in Section 18) **or** clean fallback (all four v2 slots omitted). **Never a half-filled state.**
- [ ] Token-budget trim order implemented: Tier 3 state → compress Section 12 → trim 4B descriptions → reduce Voice Anchor toward floor of 5. Never cut Anchor Card / Self-Check / below 5 exemplars.

If any box is false, do not promote.

---

## ROLLBACK (one step, minutes)

If a rebuilt IP misbehaves: point its runtime job back at the **prior prompt version** and rebuild that IP. No logic rework, no platform-wide action. Because artifacts are per-IP and static, rollback is isolated to the one IP.

---

## FUTURE-CHANGE CONTRACT (so the Cold Open phase — and anything after — stays safe)

Every new pipeline change from here follows the same rules, which is what keeps integration boring:

1. **Additive only.** Never rename or re-position an existing consumed section (Voice DNA, Master Rule 1, Cold Open). New outputs are new named sections.
2. **One named slot.** A new phase outputs into an existing named D8 slot, or a clearly new one. No silent format changes to shared payloads.
3. **Fail safe.** If the new output is absent, the consumer omits it and behaves as before.
4. **Canary first.** Validate on one IP via the checklist before expanding.

**Cold Open phase, pre-specified to fit:** it will populate the existing `{{PHASE_3_COLD_OPEN_PROSE}}` slot (D8 Section 13) that D3, D4, and D8 already reference — so it slots into wiring that already exists, rather than adding new plumbing. It will be format-agnostic (driven by 1A v2 / 1B v3 output) and will degrade to the current ad-hoc cold open if the new phase hasn't run for an IP. Net new wiring: a single optional build-time job feeding a slot that is already consumed.

---

## END OF PLAN
