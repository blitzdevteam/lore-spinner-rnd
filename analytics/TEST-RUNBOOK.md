# Analytics Test Runbook

**Last updated:** 2026-06-06  
**Test suite location:** `tests/Feature/Analytics/`  
**Helpers:** `tests/Support/Analytics/`  
**Result log:** `storage/logs/analytics-tests.log`

---

## What these tests cover

| File | Validates |
|------|-----------|
| `AnalyticsBaselineTest.php` | `App\Support\Analytics` config, baseline date, 14-day abandoned cutoff |
| `AnalyticsStoryCycleTest.php` | Session rows preserved per `story_cycle_number`; multiple `game_completions` per game |
| `AnalyticsMetricsTest.php` | Incomplete vs Abandoned, completion events vs unique, replay events vs unique replayers, retention cohort anchor |
| `AnalyticsDashboardTest.php` | `StoryAnalyticsPage` query outputs; self-cleaning fixture removal |

---

## Self-cleaning design

Each test:

1. **Creates** isolated fixtures via `AnalyticsTestContext` (users, stories, games, sessions, completions, resets).
2. **Asserts** expected metric behaviour.
3. **Logs** a JSON snapshot to `storage/logs/analytics-tests.log` (persists after cleanup).
4. **Deletes** only tracked fixture IDs in `afterEach()` — child tables first, then games, stories, users.

`RefreshDatabase` also runs for all Feature tests (full migrate per test class), so the test DB is always clean. Explicit cleanup is belt-and-suspenders and supports reviewing what was removed.

---

## Prerequisites

- PHP 8.4+
- Composer dependencies installed
- PostgreSQL test database configured

### Database

`phpunit.xml` sets `DB_DATABASE=testing`. Ensure that database exists and is reachable.

**With Laravel Sail:**

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --database=pgsql --env=testing
```

Or let `RefreshDatabase` migrate automatically on first test run.

**Without Sail** — match your `.env.testing` or export:

```bash
export DB_CONNECTION=pgsql
export DB_DATABASE=testing
```

---

## Run commands

### All analytics tests

```bash
php artisan test tests/Feature/Analytics
```

**Sail:**

```bash
./vendor/bin/sail artisan test tests/Feature/Analytics
```

### Single file

```bash
php artisan test tests/Feature/Analytics/AnalyticsMetricsTest.php
```

### Single test by name

```bash
php artisan test --filter="counts active incomplete games separately from abandoned games"
```

### With verbose output

```bash
php artisan test tests/Feature/Analytics -v
```

---

## Review test results log

After a run, inspect the persisted metric snapshots:

```bash
cat storage/logs/analytics-tests.log
```

**Tail latest entry:**

```bash
tail -n 40 storage/logs/analytics-tests.log
```

**Clear log before a fresh run (optional):**

```bash
rm -f storage/logs/analytics-tests.log
```

Each log block contains:

- ISO timestamp
- Test name
- JSON payload (counts, rates, tracked fixture summary)

---

## Expected passing output

```
PASS  Tests\Feature\Analytics\AnalyticsBaselineTest
PASS  Tests\Feature\Analytics\AnalyticsStoryCycleTest
PASS  Tests\Feature\Analytics\AnalyticsMetricsTest
PASS  Tests\Feature\Analytics\AnalyticsDashboardTest

Tests:    11 passed
```

Exact test count may grow as scenarios are added.

---

## Troubleshooting

### `SQLSTATE[08006] Connection refused` / `pgsql` host not found

Docker/Sail is not running. Start Sail or point `DB_HOST` to a live PostgreSQL instance.

### Migration errors on `current_story_cycle_number` or `game_completions`

Analytics migrations not applied to the **testing** database:

```bash
./vendor/bin/sail artisan migrate --env=testing
```

### `Class "Tests\Support\Analytics\..." not found`

```bash
composer dump-autoload
```

### Tests pass but log file empty

First test in a run creates the file. Ensure `storage/logs` is writable:

```bash
chmod -R 775 storage/logs
```

### Fixture cleanup verification

The test `leaves no tracked fixtures in database after cleanup` explicitly asserts `games` count is 0 for the created game ID after `cleanup()`.

---

## Adding new analytics tests

1. Use `AnalyticsTestContext::make()` in `beforeEach`.
2. Call `$this->ctx->cleanup()` in `afterEach` (already in existing files).
3. Log snapshots with `AnalyticsTestLogger::log('descriptive_name', $payload)`.
4. Anchor dates on or after `2026-06-01` via `$this->ctx->afterBaseline()` unless testing abandoned/inactivity edge cases.
5. Use `Carbon::setTestNow()` for time-sensitive abandoned tests; always `Carbon::setTestNow()` reset at end.

---

## CI integration (future)

```yaml
- name: Analytics tests
  run: php artisan test tests/Feature/Analytics
```

Upload `storage/logs/analytics-tests.log` as a CI artifact if you want metric snapshots in PR checks.
