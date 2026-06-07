# Microsoft Clarity — Integration Guide for Lore Spinner Analytics

**Last updated:** 2026-06-06  
**Status:** Tracking live; API integration not yet built  
**Related docs:** [ANALYTICS.md](./ANALYTICS.md) · [TEST-RUNBOOK.md](./TEST-RUNBOOK.md)

---

## Table of Contents

1. [What Clarity Adds](#what-clarity-adds)
2. [Current Setup](#current-setup)
3. [Data Export API Reference](#data-export-api-reference)
4. [Dimensions vs Metrics](#dimensions-vs-metrics)
5. [How Clarity Fits Our Dashboard](#how-clarity-fits-our-dashboard)
6. [What We Can Build](#what-we-can-build)
7. [Recommended Integration Architecture](#recommended-integration-architecture)
8. [Daily API Budget (10 calls/day)](#daily-api-budget-10-callsday)
9. [Report Catalog (for upcoming requests)](#report-catalog-for-upcoming-requests)
10. [Implementation Checklist](#implementation-checklist)
11. [Risks, Limits, and Workarounds](#risks-limits-and-workarounds)
12. [References](#references)

---

## What Clarity Adds

Lore Spinner already tracks **product analytics** in PostgreSQL: visits (session dedup), signups, story starts, session progression, completions, replays, and retention. That data answers *what players did inside the product*.

Microsoft Clarity adds **behavioral web analytics** on top:

| Clarity strength | Our DB analytics |
|------------------|------------------|
| Session recordings & heatmaps (Clarity UI) | Not available |
| Rage clicks, dead clicks, quick backs | Not tracked |
| Scroll depth & engagement time per page | Not tracked |
| Traffic by OS, device, browser, country | Partially (`page_views.path` only) |
| UTM / campaign / channel attribution | Planned in ANALYTICS.md, not built |
| Script errors & error clicks on pages | Not tracked |
| Popular pages by session count | Only first path per session/day |

Clarity is **complementary**, not a replacement. Gameplay funnels, story completion, and replay metrics should stay on our database. Clarity fills the gap between "someone visited" and "the UI frustrated them."

---

## Current Setup

| Item | Value |
|------|-------|
| Project ID (tracking script) | `x2x3tavro1` |
| Script location | `resources/views/app.blade.php` (Inertia/Vue pages) |
| Coverage | Public app shell — home, stories, auth, gameplay pages |
| Not covered | Filament admin (`/manager`, `/creator`) unless added separately |

**API token:** Generate in Clarity → **Settings → Data Export → Generate new API token**. Store in `.env` as `CLARITY_API_TOKEN` (never commit).

Official docs: [Clarity Data Export API](https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-data-export-api)

---

## Data Export API Reference

### Endpoint

```
GET https://www.clarity.ms/export-data/api/v1/project-live-insights
```

Returns dashboard metrics as JSON for the **last 1–3 days only** (rolling window from time of request, UTC).

### Authentication

```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

Token scope: `Data.Export` (JWT issued from project settings).

### Query parameters

| Parameter | Required | Values | Notes |
|-----------|----------|--------|-------|
| `numOfDays` | Yes | `1`, `2`, or `3` | Last 24h / 48h / 72h |
| `dimension1` | No | See [input dimensions](#input-dimensions) | Primary breakdown |
| `dimension2` | No | Same | Secondary breakdown |
| `dimension3` | No | Same | Tertiary breakdown |

Max **3 dimensions** per request. Dimensions are **inputs** — they slice the data. Metric names (e.g. `Traffic`, `Popular Pages`) appear in the **response**, not as dimension parameters.

### Example request

```bash
curl --location \
  'https://www.clarity.ms/export-data/api/v1/project-live-insights?numOfDays=2&dimension1=URL&dimension2=Device' \
  --header 'Content-Type: application/json' \
  --header "Authorization: Bearer $CLARITY_API_TOKEN"
```

### Example response shape

```json
[
  {
    "metricName": "Traffic",
    "information": [
      {
        "totalSessionCount": "9554",
        "totalBotSessionCount": "8369",
        "distinctUserCount": "189733",
        "pagesPerSessionPercentage": 1.0931,
        "OS": "Other"
      },
      {
        "totalSessionCount": "291942",
        "totalBotSessionCount": "31076",
        "distinctUserCount": "212836",
        "pagesPerSessionPercentage": 2.2609,
        "OS": "Android"
      }
    ]
  }
]
```

Each top-level object is one **metric**. The `information` array holds rows; dimension keys (e.g. `"OS"`, `"URL"`, `"Device"`) appear alongside numeric fields on each row.

Field names in responses may vary slightly by metric (strings vs numbers). Normalize on ingest.

### HTTP status codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad request (invalid params) |
| 401 | Missing/invalid auth header |
| 403 | Token not authorized for project |
| 429 | Daily 10-request quota exceeded |

**Note:** GitHub issues report occasional **500 with empty body** on otherwise valid tokens (auth works; export endpoint fails). Treat as transient; retry with backoff; fall back to Clarity dashboard CSV export if persistent.

---

## Dimensions vs Metrics

### Input dimensions (request parameters only)

These are the **only** valid `dimension1` / `dimension2` / `dimension3` values:

- `Browser`
- `Device`
- `Country` (docs also say `Country/Region`)
- `OS`
- `Source`
- `Medium`
- `Campaign`
- `Channel`
- `URL`

**Common mistake:** Passing `Popular Pages` as a dimension — that is a **metric name in the response**, not an input dimension. Use `dimension1=URL` and read the `Popular Pages` metric from the JSON.

### Output metrics (returned in `metricName`)

Reported metrics include (non-exhaustive; API may return more):

| Metric | Useful for |
|--------|------------|
| `Traffic` | Session counts, bots, pages/session |
| `Popular Pages` | Top URLs by traffic |
| `Engagement Time` | Time on page / session |
| `Scroll Depth` | How far users scroll |
| `Dead Click Count` | Clicks on non-interactive elements |
| `Rage Click Count` | Repeated rapid clicks (frustration) |
| `Quickback Click` | Back navigation shortly after landing |
| `Excessive Scroll` | Erratic scroll behavior |
| `Script Error Count` | JS errors |
| `Error Click Count` | Clicks that triggered errors |

Dimensions can also appear nested in metric rows (`Page Title`, `Referrer URL`, etc.) depending on the breakdown requested.

---

## How Clarity Fits Our Dashboard

### `/manager/analytics` today

| Widget | Source | Clarity overlap |
|--------|--------|-----------------|
| **Platform Metrics** (KPI cards) | PostgreSQL | **Visits** ≈ Clarity `Traffic` — different counting (we dedupe by Laravel session/day; Clarity uses its own session model + bot filtering) |
| **Conversion Funnel** | PostgreSQL | No overlap — Clarity does not know signups, story starts, or completions |
| **Retention** | `user_activity_days` | No overlap — Clarity has no logged-in cohort retention |

### Natural split

```
┌─────────────────────────────────────────────────────────────┐
│  Lore Spinner Analytics Dashboard (/manager/analytics)      │
├──────────────────────────────┬──────────────────────────────┤
│  Product funnel (PostgreSQL) │  UX & traffic (Clarity API)  │
│  Visits → Signups → Starts   │  Top pages, engagement time  │
│  → S1 → Complete → Replay    │  Rage/dead clicks by URL     │
│  Retention cohorts           │  Device / OS / country mix   │
│  Story analytics (per story) │  Campaign / channel attribution│
└──────────────────────────────┴──────────────────────────────┘
```

### Correlation opportunities

When both systems cover the same period:

1. **Visit → Signup gap** — Compare Clarity sessions on `/` and `/register` vs our signup count.
2. **Story page → Start gap** — Clarity sessions on `/stories/{slug}` vs `games.created_at` for that story.
3. **Gameplay UX** — High rage clicks on `/games/{id}` pages while session drop-off rises in Story Analytics → UI friction hypothesis.
4. **Marketing** — `Source` / `Medium` / `Campaign` dimensions vs signup funnel (once we add UTM to `page_views`, Clarity can validate channel quality).

---

## What We Can Build

### Tier 1 — High value, fits API limits (recommended first)

| Feature | API call pattern | Dashboard placement |
|---------|------------------|---------------------|
| **Traffic overview (72h)** | `numOfDays=3`, no dimensions | New stat row or sidebar on Analytics Dashboard |
| **Top pages** | `numOfDays=3`, `dimension1=URL` | Table widget below funnel |
| **Device & OS mix** | 2 calls: `dimension1=Device`, `dimension1=OS` | Small breakdown chart |
| **Friction hotspots** | `numOfDays=3`, `dimension1=URL` → parse rage/dead/error metrics | Highlight table: URL + rage count + engagement time |
| **Acquisition channels** | `numOfDays=3`, `dimension1=Channel` or `Source` | Marketing section (pairs with future UTM work) |

### Tier 2 — Deeper UX (Clarity UI + selective API)

| Feature | Approach |
|---------|----------|
| Session recordings | Link out to Clarity dashboard (no API for recordings) |
| Heatmaps | Clarity UI only |
| Per-story landing performance | Filter API rows where `URL` contains `/stories/` |
| Gameplay page health | Filter `URL` contains `/games/` |

### Tier 3 — Not suitable for API alone

| Need | Why API fails | Alternative |
|------|---------------|-------------|
| 30/90-day trends | Max 3 days per call | Scheduled daily snapshots into DB |
| Historical rage-click trends | No long window | Store daily aggregates from each sync |
| Funnel completion rates | Clarity doesn't track game state | Keep PostgreSQL |
| Per-user journey | Privacy + no user ID in export | Our `user_activity_days` + optional Clarity custom tags (future) |

---

## Recommended Integration Architecture

### 1. Config

```php
// config/analytics.php (proposed additions)
'clarity' => [
    'enabled' => env('CLARITY_ENABLED', false),
    'api_token' => env('CLARITY_API_TOKEN'),
    'project_id' => env('CLARITY_PROJECT_ID', 'x2x3tavro1'),
    'sync_num_of_days' => 3,
    'cache_ttl_minutes' => 360, // avoid burning quota on page refreshes
],
```

### 2. Service class

```
app/Support/Clarity/ClarityClient.php       — HTTP client, auth, error handling
app/Support/Clarity/ClarityLiveInsights.php   — typed wrapper for project-live-insights
app/Support/Clarity/ClarityMetricParser.php   — normalize JSON → DTOs
```

Responsibilities:

- Validate `numOfDays` ∈ {1,2,3} and dimension whitelist
- Log 429 and stop further calls until next UTC day
- Parse `metricName` + `information[]` into stable PHP structures
- Never call API synchronously from Filament widget render (use cache)

### 3. Scheduled sync (recommended)

```
app/Console/Commands/SyncClarityInsights.php
```

Run **once daily** (e.g. 06:00 UTC) via Laravel scheduler:

1. Execute a fixed set of ≤10 API requests (see budget below)
2. Upsert into `clarity_insight_snapshots` (new table) or `cache` + JSON column
3. Filament widgets read **only from local storage**

Suggested table:

```sql
clarity_insight_snapshots (
    id,
    fetched_at,           -- when we called the API
    num_of_days,          -- 1|2|3
    dimension_signature,  -- e.g. "URL" or "URL+Device"
    response_json,        -- raw or normalized
    created_at
)
```

This turns the 3-day API window into a **rolling archive** we control.

### 4. Filament widgets (proposed)

| Class | Purpose |
|-------|---------|
| `ClarityTrafficWidget` | Sessions, bot %, pages/session (72h) |
| `ClarityTopPagesWidget` | Table: URL, sessions, engagement time |
| `ClarityFrictionWidget` | URLs sorted by rage + dead clicks |
| `ClarityAcquisitionWidget` | Channel / source breakdown |

Register on `AnalyticsDashboard::getWidgets()` **below** existing KPI/funnel/retention widgets, with a section heading: **"Web UX (Microsoft Clarity)"**.

### 5. Optional: link to Clarity UI

Add a header action on the dashboard:

```
View recordings & heatmaps → https://clarity.microsoft.com/projects/view/{project_id}/dashboard
```

---

## Daily API Budget (10 calls/day)

Plan requests as a **fixed daily bundle**. Do not call the API on dashboard page load.

### Suggested default bundle (8 calls — leaves 2 for manual/debug)

| # | Request | Purpose |
|---|---------|---------|
| 1 | `numOfDays=3` | Global traffic metrics |
| 2 | `numOfDays=3&dimension1=URL` | Popular pages + per-URL friction metrics |
| 3 | `numOfDays=3&dimension1=Device` | Device breakdown |
| 4 | `numOfDays=3&dimension1=OS` | OS breakdown |
| 5 | `numOfDays=3&dimension1=Country` | Geo breakdown |
| 6 | `numOfDays=3&dimension1=Channel` | Acquisition channel |
| 7 | `numOfDays=3&dimension1=Source` | Traffic source |
| 8 | `numOfDays=3&dimension1=URL&dimension2=Device` | Top pages × device (mobile gameplay UX) |

Reserve calls 9–10 for ad-hoc reports or `Medium` / `Campaign` when running campaigns.

### Caching rules

- **Minimum cache:** 6 hours if reading live API (emergency only)
- **Preferred:** 24 hours via scheduled sync
- **On 429:** Serve stale cache + show "Clarity quota exhausted until UTC midnight"

---

## Report Catalog (for upcoming requests)

Use this section when scoping reports. Each item notes data source and feasibility.

### Traffic & acquisition

| Report | Clarity API | Our DB | Notes |
|--------|-------------|--------|-------|
| Sessions last 24h / 72h | ✅ Traffic metric | ✅ `page_views` | Compare for sanity; expect differences |
| Top landing pages | ✅ URL dimension | ✅ `page_views.path` | Clarity richer (engagement, clicks) |
| Traffic by country | ✅ Country | ❌ | Clarity only unless we add GeoIP |
| Traffic by device/OS | ✅ Device, OS | ❌ | Useful for mobile gameplay issues |
| Campaign performance | ✅ Campaign, Medium, Source | 🔜 UTM on `page_views` | Cross-check when UTM ships |
| Bot vs human sessions | ✅ `totalBotSessionCount` | ❌ | Filter bots in Clarity view |

### UX quality & friction

| Report | Clarity API | Our DB | Notes |
|--------|-------------|--------|-------|
| Pages with most rage clicks | ✅ URL + Rage Click Count | ❌ | Priority fix list for frontend |
| Dead click hotspots | ✅ URL + Dead Click Count | ❌ | Often broken buttons / wrong affordances |
| Quick back rate by URL | ✅ Quickback Click | ❌ | Weak landing or misleading entry |
| Avg engagement time by page | ✅ Engagement Time | ❌ | Pair with story drop-off |
| Scroll depth on long pages | ✅ Scroll Depth | ❌ | Home, story detail, game UI |
| JS errors by page | ✅ Script Error Count | ❌ | Tie to deploys |

### Product funnel (stay on PostgreSQL)

| Report | Clarity | Our DB |
|--------|---------|--------|
| Visit → Signup → Start → Complete | ❌ | ✅ Funnel widget |
| Session 1–N completion by story | ❌ | ✅ Story Analytics |
| Retention D1/D7/D30 | ❌ | ✅ Retention widget |
| Replay rate | ❌ | ✅ KPI + Story Analytics |

### Combined / executive reports

| Report | How to build |
|--------|--------------|
| **Weekly health summary** | DB funnel + Clarity 72h friction top 5 + traffic delta |
| **Story launch checklist** | Story Analytics starts/completions + Clarity URL filter `/stories/{slug}` |
| **Mobile vs desktop gameplay** | Clarity `URL+Device` for `/games/*` + DB session duration by device proxy (if we log device later) |
| **Marketing ROI sketch** | Clarity Channel/Source sessions → DB signups (same date window) |

---

## Implementation Checklist

### Phase A — Foundation

- [ ] Add `CLARITY_API_TOKEN` and `CLARITY_ENABLED` to `.env.example`
- [ ] Implement `ClarityClient` with dimension whitelist and quota tracking
- [ ] Manual artisan command: `php artisan clarity:fetch --days=3 --dimension=URL`
- [ ] Verify token against live project (confirm not hitting 500 bug)

### Phase B — Persistence

- [ ] Migration: `clarity_insight_snapshots`
- [ ] Scheduled command: daily sync bundle (8 calls)
- [ ] Alert/log on 429 or repeated failures

### Phase C — Dashboard

- [ ] `ClarityTrafficWidget` + `ClarityTopPagesWidget` on Analytics Dashboard
- [ ] "Data as of {fetched_at}" footer + link to Clarity UI
- [ ] Feature flag: hide section when `CLARITY_ENABLED=false`

### Phase D — Story Analytics cross-link (optional)

- [ ] On Story Analytics page, optional Clarity strip: sessions on story URL (from latest snapshot)
- [ ] Flag stories where DB drop-off is high **and** Clarity rage clicks are high

### Phase E — Future

- [ ] Clarity [custom tags](https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-tags) for `user_id`, `story_id`, `game_id` (privacy review required)
- [ ] Filament admin tracking script (separate project or same project with URL filter)
- [ ] MCP server for ad-hoc natural-language queries in dev ([Clarity MCP](https://learn.microsoft.com/en-us/clarity/third-party-integrations/clarity-mcp-server))

---

## Risks, Limits, and Workarounds

| Limit | Impact | Workaround |
|-------|--------|------------|
| **10 requests / project / day** | Cannot refresh dashboard on every load | Daily scheduled sync + cache |
| **1–3 day window only** | No native 30/90-day Clarity charts | Accumulate daily snapshots locally |
| **1,000 rows max, no pagination** | Large sites truncate URL breakdowns | Filter by path prefix; prioritize `/games/`, `/stories/` |
| **UTC timezone** | Misalignment with local reporting | Convert in UI; align sync to UTC boundary |
| **Bot traffic included in metrics** | Inflated sessions | Use `totalBotSessionCount`; compare to `distinctUserCount` |
| **No user-level export** | Cannot join to `users.id` | Custom tags (future) or URL-level correlation only |
| **Inertia SPA navigation** | Clarity tracks full loads; client navigations may differ from `page_views` | Both systems partial on SPA — document known gap |
| **Filament not tracked** | Admin behavior invisible | Add script to Filament panel provider if needed |
| **API instability (500)** | Empty exports | Retry; monitor; CSV manual export up to 100k sessions from Clarity UI |

### Manual CSV export (when API is not enough)

Clarity dashboard: **Filters → Download → Custom Download** (up to 100,000 sessions). Use for one-off deep dives; not suitable for automated dashboard.

---

## References

- [Clarity Data Export API (Microsoft Learn)](https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-data-export-api)
- [Clarity MCP Server](https://learn.microsoft.com/en-us/clarity/third-party-integrations/clarity-mcp-server) — AI/ad-hoc querying; same 10/day limit
- [GitHub: dimension vs metric clarification (#630)](https://github.com/microsoft/clarity/issues/630)
- [GitHub: API 500 reports (#1085)](https://github.com/microsoft/clarity/issues/1085)
- Internal: [ANALYTICS.md](./ANALYTICS.md) — product metrics source of truth
- Tracking script: `resources/views/app.blade.php` (project `x2x3tavro1`)

---

## Quick reference card

```
Endpoint:  GET https://www.clarity.ms/export-data/api/v1/project-live-insights
Auth:      Authorization: Bearer {CLARITY_API_TOKEN}
Window:    numOfDays = 1 | 2 | 3  (last 24h / 48h / 72h, UTC)
Slice by:  dimension1, dimension2, dimension3  (max 3)
           Browser | Device | Country | OS | Source | Medium | Campaign | Channel | URL
Quota:     10 calls / project / day
Rows:      max 1,000 per response, no pagination
Strategy:  sync daily → store locally → Filament reads cache/DB
```

When you request a report, specify **time window**, **breakdown** (URL, device, channel, etc.), and whether you need **product metrics (DB)**, **UX metrics (Clarity)**, or **both**.
