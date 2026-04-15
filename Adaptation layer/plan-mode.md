You are working inside the Lorespinner codebase.

Your task is to PLAN and EXECUTE the introduction of the new Adaptation Layer without interrupting the existing runtime gameplay system.

This is a live-codebase change. Be careful, incremental, and production-minded.

PRIMARY GOAL

Implement the adaptation system as a separate, responsible layer that prepares stories for interactive play, while preserving the current runtime narration flow.

The runtime must continue to work during and after this change.

NON-NEGOTIABLE RULES

1. Do not break or block the current gameplay runtime.
2. Do not remove or rewrite the current narrator flow unless absolutely required.
3. Treat the new adaptation layer as an additive system first.
4. Prefer new agents, new jobs, new prompt templates, new storage artifacts, and new directories over risky rewrites.
5. Keep backward compatibility for stories that only use the old pipeline.
6. At the end, write a detailed markdown process log in the adaptation directory explaining:
   - what was changed
   - what was added
   - what was intentionally left unchanged
   - any risks, assumptions, and next steps

CONTEXT

The codebase already has AI-driven extractors and agents.
The current system already supports:
- chapter extraction
- event extraction
- objective/attribute extraction
- system prompt generation
- runtime narration

The new target is to add an adaptation layer driven by prompt-based AI phases.

You should use the existing architectural style where possible:
- Laravel AI agents
- queued jobs
- prompt templates / views
- structured JSON outputs
- stored artifacts tied to stories

TARGET ADAPTATION PHASES

Implement support for these phases as a new adaptation pipeline:

Phase 0:
- Format Detection

Phase 1:
- IP Audit

Phase 2:
- Entry Point Diagnosis

Phase 3:
- Session Beat Architecture

Phase 4:
- Choice Design

Phase 5:
- Downstream Consequence Mapping

Phase 6:
- Session Close and Retention Hook

Phase 7:
- Editorial Verification Checklist

IMPORTANT IMPLEMENTATION PRINCIPLE

Do not try to fully replace the runtime logic in one pass.

First make the adaptation layer real and executable as a separate pipeline.
Then connect it safely to the existing story preparation workflow.
Only make runtime consume adaptation artifacts where it is clearly safe.

DELIVERABLES

You are expected to do real implementation work, not just planning notes.

At minimum, complete the following:

1. Create an adaptation directory / module structure appropriate for this codebase.
2. Add the new prompt templates for the adaptation phases.
3. Add new agent classes for the adaptation phases.
4. Add structured output schemas for each adaptation phase.
5. Add jobs for running each phase.
6. Add a storage strategy for adaptation artifacts.
7. Wire the new pipeline into story preparation in a non-breaking way.
8. Ensure existing stories can still function without adaptation artifacts.
9. Add any needed guards, fallbacks, status fields, or feature-flag style checks.
10. Write a process log markdown file in the adaptation directory at the end.

WORKING STYLE

Work in this order:

STEP 1 — Inspect first
Before editing, inspect the existing architecture and identify:
- where current AI agents live
- where jobs live
- where prompt templates live
- how structured outputs are defined
- where story/chapter/event statuses are managed
- where new adaptation artifacts can be stored safely

STEP 2 — Plan
Create a short internal implementation plan before making major edits.
The plan should prioritize:
- additive changes
- low-risk integration
- backward compatibility
- runtime safety

STEP 3 — Implement incrementally
Implement in small safe steps.
After each major step, verify that you have not broken the current flow.

STEP 4 — Preserve runtime safety
Do not force the runtime to depend on adaptation artifacts unless guards exist.
If adaptation data is missing, current runtime should still behave exactly as before.

STEP 5 — Log everything
When done, create a markdown file in the adaptation directory named something like:

PROCESS_LOG.md

This log must include:
- summary of the goal
- architecture decisions
- files created
- files modified
- migrations or schema changes
- prompts added
- agents added
- jobs added
- storage decisions
- integration decisions
- backward compatibility notes
- runtime safety notes
- unfinished items / future work

IMPLEMENTATION PREFERENCES

Prefer introducing new items such as:
- app/Ai/Agents/Adaptation/...
- app/Jobs/Adaptation/...
- resources/views/ai/agents/adaptation/...
- app/Support/Adaptation/... or equivalent
- story JSON columns or a dedicated adaptation table, whichever fits best with the codebase

Do not overengineer if the codebase is not ready for it.
Simple, readable, extensible implementation is better than perfect abstraction.

PROMPT SOURCE

Use the adaptation prompt companion and the adaptation prompt system as the source of truth for the new phase prompts.

Preserve the structure of the phases:
- format detection
- master context
- phase-by-phase adaptation prompts
- expected outputs

If needed, convert long freeform prompt outputs into structured schemas that fit the existing Laravel AI style, but do not lose the intent of the original phases.

RUNTIME SAFETY REQUIREMENTS

You must explicitly protect the current runtime by doing the following:

- Do not change PromptController or GameController behavior unless needed
- If you touch runtime-facing code, use null-safe / presence checks
- Do not assume adaptation artifacts exist for older stories
- Keep existing NarrationAgent flow intact
- Keep existing chapter/event extraction intact unless a change is truly necessary
- If integration is partial, make it opt-in or guarded by existence checks / status checks

QUALITY BAR

Your work should leave the repo in a better state, not a half-switched state.

Before finishing:
- check for syntax errors
- check imports and namespaces
- check references to new classes
- make sure jobs and agents match existing conventions
- make sure new code is discoverable and documented
- make sure the process log is written

END CONDITION

Do not stop after only writing a plan.
Do the implementation work.
When implementation is complete, write the process log markdown file in the adaptation directory.

FINAL OUTPUT EXPECTATION

At the end of your run:
1. The adaptation layer should exist in code in a safe additive form.
2. The old runtime should still be usable.
3. A markdown process log should exist in the adaptation directory documenting the full execution.