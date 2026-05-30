# Rollback: `merging-new-design` UI merge into `main`

This documents how to undo the homepage/library UI merge if production or local testing goes wrong. Chaos engine work on `main` is unchanged by rollback options below except where noted.

## Merge record

| Item | Value |
|------|--------|
| **Merge commit** (current `main` / `chaos-mode` after merge) | `8b37ae0` — *Merge branch 'merging-new-design' into main* |
| **`main` immediately before merge** (Chaos + launch gating, **old UI**) | `616d8ca` — *Gate story play behind launch list and session 1 narrator prompt* |
| **Design branch tip merged in** | `b86eb07` on `origin/merging-new-design` (remote was ahead of `dc9e685` at merge time) |
| **Shared ancestor** | `5bfcbef` |
| **Merged on** | 2026-05-30 |
| **Remote** | `origin/main`, `origin/chaos-mode` both pushed to `8b37ae0` |

### Conflict resolutions during merge (for manual re-check)

- `resources/js/pages/Stories/Show.vue` — new story-play UI + kept `isPlayable` / Coming Soon
- `resources/js/components/ContinueStories.vue` — kept **main** (`sessionLabel`)
- `resources/js/pages/User/Games/Show.vue` — kept **main** (Chaos gameplay)
- `.DS_Store` — kept **main**

---

## Option A — Revert the merge (recommended on shared `main`)

Creates a new commit that undoes the merge. Safe after others have pulled `8b37ae0`.

```bash
git checkout main
git pull origin main
git revert -m 1 8b37ae0
# resolve any revert conflicts, then:
git commit   # only if revert stopped for conflicts
git push origin main
```

`-m 1` keeps the first parent (`616d8ca` side = your pre-merge `main` line), drops the design-branch side.

Rebuild frontend after revert:

```bash
npm install
npm run build
```

---

## Option B — Reset `main` to pre-merge commit (destructive)

Only if **you** are the only one using `main` and nothing important exists only on `8b37ae0`.

```bash
git checkout main
git reset --hard 616d8ca
git push origin main --force-with-lease
```

This removes the merge commit from the branch history (not just its effects).

---

## Option C — Checkout old UI without moving `main`

Inspect or run the app as it was before the merge:

```bash
git checkout 616d8ca --detach
# or create a throwaway branch:
git checkout -b preview-pre-ui-merge 616d8ca
```

---

## Branch backups (other rollback anchors)

| Branch | Typical use | Tip (may be behind `main`) |
|--------|-------------|------------------------------|
| `story-guard` | Older snapshot before Chaos migration | `679a89c` (not updated at UI merge time) |
| `chaos-mode` | Was synced to `main`; now also at `8b37ae0` | Same as `main` after merge |
| `origin/merging-new-design` | Design-only line | `b86eb07` — UI without post-merge `main` commits |

To restore **Chaos + old UI** without the merge, use **`616d8ca`**, not `story-guard`.

---

## After rollback on server

1. Deploy the reverted or reset commit.
2. Run `npm run build` (or your CI build step).
3. Clear CDN/browser cache if assets look stale.

---

## Re-apply the UI later

```bash
git checkout main
git merge origin/merging-new-design
# or merge a specific commit: git cherry-pick <sha>
```

Expect the same conflict files as the first merge unless `main` diverged further.
