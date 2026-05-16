# Chaos Mode — Process Log

**Created:** 2026-05-16  
**Branch baseline:** `e141b61` (HeroBanner: frosted glass card on UI block)  
**Status:** MVP — ready for testing

---

## Summary

Chaos Mode is a completely isolated experimental runtime that recreates the "Curt's Claude test" experience directly in the LoreSpinner app.

**Core difference from the production runtime:**
- The production runtime is event-locked (one event = one scene container, advance_event is a narrator responsibility, 5-turn force-advance cap, 75+ prohibition-style constraints in the system prompt)
- Chaos Mode is session-free: the narrator is a confident Wonderland voice, not a rule-enforcer. It gets the full Alice world packet and roams freely. Advancement is natural, not mechanical. There is no turn cap.

**Philosophy:**
> The narrator is not an employee of Wonderland's copyright department. It is Lewis Carroll, returned to guide one more curious visitor through the rabbit-hole.

---

## What Was Built

### New files (all revertable by deletion)

| File | Purpose |
|------|---------|
| `app/Http/Controllers/ChaosMode/ChaosModeController.php` | Handles GET /chaos-mode, POST /chaos-mode/start, POST /chaos-mode/turn |
| `app/Ai/Agents/Chaos/ChaosNarrationAgent.php` | GPT-5.2, temperature 1.0, chaos schema |
| `app/Ai/Agents/Chaos/ChaosNarrationAgentGpt41.php` | GPT-4.1 variant |
| `app/Ai/Agents/Chaos/ChaosNarrationAgentClaudeOpus.php` | Claude Opus variant |
| `app/Ai/Agents/Chaos/ChaosNarrationAgentClaudeSonnet.php` | Claude Sonnet variant |
| `resources/views/ai/agents/chaos/system-prompt.blade.php` | The open narrator prompt (Carroll voice, full world packet, no event-locking) |
| `resources/views/ai/agents/chaos/turn-prompt.blade.php` | Conversation history + player action format |
| `resources/js/pages/ChaosMode.vue` | Full-page game UI (dark Carroll aesthetic, no layout dependencies) |
| `chaos-mode/process-log.md` | This file |

### Modified files

| File | Change |
|------|--------|
| `routes/web.php` | Added `/chaos-mode` route group (clearly commented, easy to remove) |
| `resources/js/pages/Index.vue` | Added Chaos Mode banner between hero/continue and stories section |

---

## Architecture

### Server is stateless
The server does **not** store session state. The client (Vue) owns:
- Full conversation history (capped at last 12 turns sent to API)
- World state (`size_condition`, `items`, `location`, `notes`)
- Scene note

Each POST sends all context. The server renders the system prompt + turn prompt, calls the agent, and returns the result. This mirrors how WriterLab Playground works.

### No DB changes
Chaos Mode does not create Game records, Prompt records, or any other DB rows. It is a pure AI experience backed by PHP sessions (none) and client state (all).

### Model selection
The client sends `model` in each request. The controller switches between 4 agent classes:

| Model ID | Class |
|----------|-------|
| `gpt-5.2` | `ChaosNarrationAgent` (default) |
| `gpt-4.1` | `ChaosNarrationAgentGpt41` |
| `claude-opus-4-5` | `ChaosNarrationAgentClaudeOpus` |
| `claude-sonnet-4-5` | `ChaosNarrationAgentClaudeSonnet` |

Model is locked once the adventure starts (selector is disabled after first response).

### Simplified schema
Chaos Mode uses a simplified structured output schema compared to the production `NarrationAgent`:

| Field | Production NarrationAgent | Chaos Mode |
|-------|--------------------------|------------|
| `response` | HTML string | HTML string |
| `choices` | 3 strings | 3 strings (labeled "suggestions") |
| `advance_event` | boolean (narrator decides) | replaced by `advance_scene` |
| `input_classification` | 6 types | **removed** |
| `mapped_choice_id` | string | **removed** |
| `mapped_option` | string | **removed** |
| `state_delta` | 10-field object | replaced by `world_update` (4 fields) |
| `scene_note` | **not present** | **added** |

### What the prompt does differently
The chaos system prompt:
1. **Contains the full Alice story arc** (all 7 chapters, compressed) — gives the AI narrative confidence without passing the raw script
2. **Has no EVENT ADVANCEMENT SIGNAL section** — the model never deliberates about advancing; it just narrates
3. **Has no turn count pressure** — no PACING warnings, no FINAL TURN ultimatums
4. **Has no FORBIDDEN list** — replaced with 3 positive rules (absorb everything, narrative gravity not walls, pacing follows Alice's energy)
5. **Has a minimal hard bans list** — only style bans (no em dashes, no AI mood words, no explaining Wonderland's logic)
6. **Uses Carroll's prose markers explicitly** — parenthetical asides, absurdist logic taken seriously, verbatim dialogue, "Down, down, down." compression

---

## How to Test

1. Navigate to **http://localhost/chaos-mode** (or home page → Chaos Mode banner)
2. Select a model (GPT-5.2 is default and most tested)
3. Click "Begin the Adventure ♦"
4. Wait for the opening narration (the hall of doors)
5. Try any action — including off-script ones (ride a flamingo, argue with a door, refuse to shrink)
6. Notice: the narrator absorbs the action and responds in Carroll's voice
7. Try the choice pills AND free text — both work

**Comparison test:**
- Open a normal Alice game in the production runtime
- Open Chaos Mode in another tab
- Give the same off-script action to both
- Compare how each handles the detour

**Model comparison test:**
- Start with GPT-5.2 (default)
- Restart and try Claude Opus
- Compare prose quality and Carroll fidelity

---

## Known Limitations / Notes

1. **Claude model IDs may need adjustment** — model slugs `claude-opus-4-5` and `claude-sonnet-4-5` follow expected Anthropic naming conventions but should be verified against `config/prism.php` or the Anthropic API. If they fail, update the `#[Model(...)]` attribute in the agent file.

2. **No rate limiting** — Chaos Mode is publicly accessible without auth. Add middleware if cost is a concern before wider release.

3. **No persistence** — refreshing the page resets the adventure. This is intentional for MVP. Could add localStorage persistence later.

4. **Alice story in DB not used for events** — The story record (`alices-adventures-in-wonderland`) is loaded for title/metadata only. Events are not queried because we don't want to create a Game record. The world packet is baked into the system prompt.

5. **Conversation history cap** — Last 12 turns are sent to the API. Earlier turns are dropped. This keeps context budget manageable. Could be made configurable.

---

## How to Revert

**Option A: Remove Chaos Mode entirely**

```bash
# Delete all chaos mode files
rm -rf "app/Http/Controllers/ChaosMode"
rm -rf "app/Ai/Agents/Chaos"
rm -rf "resources/views/ai/agents/chaos"
rm "resources/js/pages/ChaosMode.vue"

# Revert the two modified files
git checkout -- routes/web.php
git checkout -- resources/js/pages/Index.vue
```

**Option B: Git revert to baseline**

The pre-chaos-mode baseline is commit `e141b61`.

```bash
# Create a revert commit (safe — does not lose history)
git revert HEAD..e141b61
```

Or to hard reset (destructive — only if you are sure):
```bash
git checkout e141b61 -- routes/web.php resources/js/pages/Index.vue
git rm -r app/Http/Controllers/ChaosMode app/Ai/Agents/Chaos
git rm -r resources/views/ai/agents/chaos resources/js/pages/ChaosMode.vue
git rm -r chaos-mode/
git commit -m "revert: remove chaos mode"
```

---

## What to Try Next (post-MVP)

- Session persistence via localStorage
- Export/share a playthrough
- Integrate the Session Packet idea: load Alice's actual DB events (objectives only) per chapter as compressed beat context
- Pacing mode toggle (the `balanced` and `guided` modes discussed in prior analysis)
- Apply chaos mode prompt philosophy to the production runtime (Phase 1 of the broader plan: prompt surgery on `system-prompt.blade.php`)
