# Writer Lab — Architecture

## Boundary

```
┌──────────────────────────────────────────────────────────────┐
│   Writer Lab (this subsystem)                                │
│                                                              │
│   ┌────────────┐    ┌───────────────────┐                    │
│   │  Vue UI    │ ── │ Writer*           │                    │
│   │ (Chapter,  │    │ Controllers       │                    │
│   │  Versions, │    │ (Auth, Draft,     │                    │
│   │  Draft)    │    │  Version, Lab)    │                    │
│   └────────────┘    └────────┬──────────┘                    │
│                              │                               │
│                              ▼                               │
│         writer_lab_drafts ◄──┼──► writer_lab_versions         │
│                              │                               │
└──────────────────────────────┼──────────────────────────────┘
                               │ on activate
                               ▼
       ┌─────────────────────────────────────────────────┐
       │   Live runtime tables (unchanged contract)       │
       │     events  •  session_adaptations  •  stories   │
       └─────────────────────────────────────────────────┘
                               │
                               ▼
           User\Game\GameController + PromptController
           (the unchanged runtime narrator)
```

The lab **writes** to `events` and `session_adaptations`. The runtime narrator
**reads** from them. There is no runtime overlay, no draft-aware narrator,
no parallel path. The boundary is the activate step.

## Authentication

A dedicated `writer` guard sits next to the existing `web` (player) and
`creator` guards.

- **Table:** `writers` (`id, name, email, password, timestamps`).
- **Model:** `App\Models\Writer extends Authenticatable`.
- **Guard registration:** `config/auth.php` adds `'writer' => ['driver' => 'session', 'provider' => 'writers']`.
- **Routes:** `routes/routes/writer.php` (mounted from `routes/web.php`) under the `/writer` prefix.
- **Shared actions:** `CreateAuthenticatableGuardAction` and `LoginAuthenticatableGuardAction` know about the `'writer'` slot and return `User|Creator|Writer`.
- **UI:** `HomeHeaderProfile.vue` exposes both Player and Writer-Lab login/register links in an "Account" dropdown.

This keeps the player auth flow untouched. Writers never collide with player
sessions.

## Controllers

| Controller                                | Purpose                                            |
|-------------------------------------------|----------------------------------------------------|
| `Writer\Authentication\RegisterController` | Writer sign-up                                     |
| `Writer\Authentication\LoginController`    | Writer sign-in                                     |
| `Writer\Authentication\LogoutController`   | Writer sign-out                                    |
| `Writer\WriterLab\WriterLabController`     | Story list, chapter detail (left+right panels)     |
| `Writer\WriterLab\DraftController`         | All draft operations (combine/split/reorder/edit) + AI endpoints (preview, analyse-impact, suggest-choices) + activate |
| `Writer\WriterLab\VersionController`       | Snapshot list + restore                            |

## AI Agents

| Agent                       | Model     | Temperature | Fires when                                                |
|-----------------------------|-----------|-------------|-----------------------------------------------------------|
| `EventCombinerAgent`        | gpt-5.2   | 0.55        | Writer hits **Combine** on 2+ events                      |
| `ChoiceAlignmentAgent`      | gpt-5.2   | 0.45        | Legacy choice-only suggestion (kept for narrow flows)     |
| `ScriptChangeImpactAgent`   | gpt-5.2   | 0.35        | Writer rewrote the script of one event AND clicked Analyse |

`ScriptChangeImpactAgent` is the primary AI in the inline-edit path. It
analyses every adaptation layer at once and returns structured suggestions
that populate the same fields the writer is already looking at.

## Vue pages

| Page                              | Purpose                                                          |
|-----------------------------------|------------------------------------------------------------------|
| `pages/WriterLab/Index.vue`       | Story list                                                       |
| `pages/WriterLab/Show.vue`        | Chapter list for a story                                         |
| `pages/WriterLab/Chapter.vue`     | The main editor (left list + right context-sensitive panel)      |
| `pages/WriterLab/Draft.vue`       | Single-draft detail page (full preview + activate)               |
| `pages/WriterLab/Versions.vue`    | Snapshot list + restore button                                   |
| `pages/WriterLab/Authentication/Login.vue`,`Register.vue` | Writer auth screens             |

The bulk of editorial flow lives in `Chapter.vue` — it is intentionally
self-sufficient. The writer rarely navigates away unless restoring a version.
