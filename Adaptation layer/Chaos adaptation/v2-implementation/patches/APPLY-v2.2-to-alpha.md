# Apply V2.2 patch to alpha repo

**Source commit:** `8b758a6` on `lore-spinner-rnd` (2026-06-13)  
**Patch file:** `v2.2-chaos-adaptation-integration.patch` (same content as `0001-feat-integrate-V2.2-chaos-adaptation-pipeline.patch`)

## Prerequisites

Target repo should be on a branch close to the same baseline as R&D `main` before `8b758a6`, or expect manual conflict resolution. The patch touches jobs, agents, Blade views, and docs under `Adaptation layer/`.

## Option A — Preserve commit (recommended)

From the **alpha repo root**:

```bash
git am --3way "path/to/v2.2-chaos-adaptation-integration.patch"
```

If conflicts occur:

```bash
# fix conflicted files, then:
git add -A
git am --continue
```

Abort:

```bash
git am --abort
```

## Option B — Apply as uncommitted changes

```bash
git apply --3way "path/to/v2.2-chaos-adaptation-integration.patch"
git status
# review, then commit with your own message
```

Dry-run first:

```bash
git apply --check "path/to/v2.2-chaos-adaptation-integration.patch"
```

## After apply

1. Run static validation:
   ```bash
   php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step3
   php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step_v22
   php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step4
   ```
2. Deploy and re-adapt **Wizard of Oz** + **Sherlock** per `validation/pipeline-upgrade-v2-2-validation-runbook.md`.

## Regenerate patch from R&D

If `8b758a6` is amended or you need a fresh export:

```bash
cd lore-spinner-rnd
git format-patch -1 8b758a6 -o "Adaptation layer/Chaos adaptation/v2-implementation/patches"
```
