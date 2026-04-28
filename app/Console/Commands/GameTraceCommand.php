<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pretty-prints the structured 'narration.turn' log entries for a single game.
 *
 * Reads from storage/logs/narration*.log (the daily-rotated 'narration' channel),
 * filters rows by game id, sorts chronologically, and asserts four hard rules
 * per row so the next playtest produces deterministic pass/fail evidence
 * instead of vibe.
 *
 * Hard rules (from curt-feedback-fix.md §A1):
 *   1. event_id_after >= event_id_before (no rewind unless session-cut adjusts).
 *   2. If advance_event_returned === true, event_id_after !== event_id_before.
 *   3. If session transition occurred, session_number_after === nextEvent.session_number.
 *   4. Generated choices differ from the immediately previous turn's choices
 *      (no exact-string repeats).
 *
 * Usage:
 *   php artisan game:trace <game-id> [--limit=50]
 */
final class GameTraceCommand extends Command
{
    protected $signature = 'game:trace
        {game : The game ULID to trace}
        {--limit=50 : Maximum number of turns to show (most recent N kept)}';

    protected $description = 'Pretty-print structured narration.turn log entries for one game with rule assertions';

    public function handle(): int
    {
        $gameId = (string) $this->argument('game');

        $game = null;

        try {
            $game = Game::find($gameId);
        } catch (Throwable $e) {
            $this->warn('DB lookup failed (' . $e::class . '). Continuing with log-only trace.');
        }

        if ($game === null) {
            $this->warn("Game id {$gameId} not found in DB. Showing log rows anyway (DB may be unavailable).");
        } else {
            $this->info('=== GAME ===');
            $this->line('id:                    ' . $game->id);
            $this->line('story_id:              ' . $game->story_id);
            $this->line('current_event_id:      ' . $game->current_event_id);
            $this->line('current_session_number: ' . ($game->current_session_number ?? 'null'));
            $this->newLine();
        }

        $rows = $this->loadTurnRows($gameId);

        if ($rows === []) {
            $this->warn('No narration.turn entries found for this game. Has any turn been played since the narration log channel was added?');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0 && count($rows) > $limit) {
            $skipped = count($rows) - $limit;
            $rows = array_slice($rows, -$limit);
            $this->warn("Showing the most recent {$limit} of " . ($limit + $skipped) . " rows ({$skipped} skipped). Use --limit=0 for all.");
            $this->newLine();
        }

        $this->info('=== TURN TRACE (' . count($rows) . ' rows) ===');
        $this->newLine();

        $previousChoices = null;
        $totalViolations = 0;

        foreach ($rows as $i => $row) {
            $turnNumber = $i + 1;
            $this->line(sprintf('--- TURN %d (logged %s) ---', $turnNumber, $row['logged_at'] ?? 'unknown'));

            $eventBefore = $row['event_id_before'] ?? null;
            $eventAfter = $row['event_id_after'] ?? null;
            $sessionBefore = $row['session_number_before'] ?? null;
            $sessionAfter = $row['session_number_after'] ?? null;
            $advanceReturned = $row['advance_event_returned'] ?? null;
            $forceAdvanced = $row['force_advanced'] ?? null;
            $isContinue = $row['is_continue'] ?? null;
            $turnCount = $row['turn_count'] ?? null;
            $isFirstTurn = $row['is_first_turn_in_event'] ?? null;
            $playerInput = $row['player_input_first_120'] ?? '';
            $narratorOutput = $row['narrator_response_first_120'] ?? '';
            $choices = $row['choices_returned'] ?? [];
            $promptHash = substr((string) ($row['system_prompt_hash'] ?? ''), 0, 12);

            $this->line('  event_id:            ' . $eventBefore . ' -> ' . $eventAfter);
            $this->line('  session_number:      ' . ($sessionBefore ?? 'null') . ' -> ' . ($sessionAfter ?? 'null'));
            $this->line('  turn_count_in_event: ' . ($turnCount ?? 'null') . (($isFirstTurn === true) ? ' (FIRST TURN)' : ''));
            $this->line('  advance_returned:    ' . $this->bool($advanceReturned) . (($forceAdvanced === true) ? ' (force-advanced via 5-turn cap)' : ''));
            $this->line('  is_continue:         ' . $this->bool($isContinue));
            $this->line('  prompt_hash:         ' . $promptHash);
            $this->line('  player_input:        ' . ($playerInput !== '' ? '"' . $playerInput . '"' : '(empty)'));
            $this->line('  narrator_response:   ' . ($narratorOutput !== '' ? '"' . $narratorOutput . '"' : '(empty)'));

            if (! empty($choices)) {
                $this->line('  choices_returned:');
                foreach ($choices as $idx => $choice) {
                    $letter = chr(ord('A') + $idx);
                    $this->line("    {$letter}) {$choice}");
                }
            }

            $violations = $this->assertHardRules(
                eventBefore: $eventBefore,
                eventAfter: $eventAfter,
                sessionBefore: $sessionBefore,
                sessionAfter: $sessionAfter,
                advanceReturned: (bool) $advanceReturned,
                choices: $choices,
                previousChoices: $previousChoices,
            );

            if ($violations === []) {
                $this->line('  rules:               <fg=green>ALL GREEN (4/4)</>');
            } else {
                $totalViolations += count($violations);
                $this->line('  rules:               <fg=red>' . (4 - count($violations)) . '/4 PASS</>');
                foreach ($violations as $v) {
                    $this->line('    <fg=red>! ' . $v . '</>');
                }
            }

            $previousChoices = $choices;
            $this->newLine();
        }

        $this->info('=== SUMMARY ===');
        $this->line('rows_shown:       ' . count($rows));
        $this->line('total_violations: ' . $totalViolations);

        if ($totalViolations > 0) {
            $this->error('Trace contains rule violations. Inspect the rows flagged above.');

            return self::FAILURE;
        }

        $this->info('All rule checks green for every turn.');

        return self::SUCCESS;
    }

    /**
     * Load all narration.turn entries for one game id from the narration log files.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadTurnRows(string $gameId): array
    {
        $logDir = storage_path('logs');

        if (! File::exists($logDir)) {
            return [];
        }

        $files = collect(File::files($logDir))
            ->filter(fn ($f) => Str::startsWith($f->getFilename(), 'narration'))
            ->filter(fn ($f) => Str::endsWith($f->getFilename(), '.log'))
            ->sortBy(fn ($f) => $f->getMTime())
            ->values();

        $rows = [];

        foreach ($files as $file) {
            $handle = @fopen($file->getRealPath(), 'r');
            if ($handle === false) {
                continue;
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    $jsonStart = strpos($line, '{');
                    if ($jsonStart === false) {
                        continue;
                    }

                    if (! str_contains($line, 'narration.turn')) {
                        continue;
                    }

                    $payload = $this->extractPayload($line);
                    if ($payload === null) {
                        continue;
                    }

                    if (($payload['game_id'] ?? null) !== $gameId) {
                        continue;
                    }

                    $rows[] = $payload;
                }
            } finally {
                fclose($handle);
            }
        }

        usort($rows, function (array $a, array $b): int {
            $ta = (string) ($a['logged_at'] ?? '');
            $tb = (string) ($b['logged_at'] ?? '');

            return strcmp($ta, $tb);
        });

        return $rows;
    }

    /**
     * The Laravel default formatter renders context as JSON appended to a
     * single-line preamble like:
     *   [2026-04-26 23:11:07] local.INFO: narration.turn {"game_id":"...", ...}
     * We grab the first balanced JSON object on the line.
     *
     * @return array<string, mixed>|null
     */
    private function extractPayload(string $line): ?array
    {
        $start = strpos($line, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $len = strlen($line);
        $inString = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $ch = $line[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($ch === '\\') {
                $escape = true;
                continue;
            }

            if ($ch === '"') {
                $inString = ! $inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $json = substr($line, $start, $i - $start + 1);
                    $decoded = json_decode($json, true);

                    return is_array($decoded) ? $decoded : null;
                }
            }
        }

        return null;
    }

    /**
     * @param  int|string|null  $eventBefore
     * @param  int|string|null  $eventAfter
     * @param  array<int, string>  $choices
     * @param  array<int, string>|null  $previousChoices
     * @return list<string>
     */
    private function assertHardRules(
        int|string|null $eventBefore,
        int|string|null $eventAfter,
        ?int $sessionBefore,
        ?int $sessionAfter,
        bool $advanceReturned,
        array $choices,
        ?array $previousChoices,
    ): array {
        $violations = [];

        if ($eventBefore !== null && $eventAfter !== null && $eventAfter < $eventBefore && $sessionBefore === $sessionAfter) {
            $violations[] = 'rule 1: event_id rewind detected (event_id_after < event_id_before with no session change)';
        }

        if ($advanceReturned === true && $eventBefore !== null && $eventAfter !== null && $eventAfter === $eventBefore) {
            $violations[] = 'rule 2: advance_event=true but event_id did not change';
        }

        if ($sessionBefore !== $sessionAfter && $sessionAfter === null) {
            $violations[] = 'rule 3: session_number cleared without a transition target';
        }

        if ($previousChoices !== null && $previousChoices !== [] && $choices !== []) {
            $normalize = static fn (array $list): array => array_map(static fn ($c) => Str::squish((string) $c), $list);
            if ($normalize($choices) === $normalize($previousChoices)) {
                $violations[] = 'rule 4: choices_returned exactly match the previous turn (no scene movement)';
            }
        }

        return $violations;
    }

    private function bool(mixed $v): string
    {
        if ($v === true) {
            return 'true';
        }
        if ($v === false) {
            return 'false';
        }

        return 'null';
    }
}
