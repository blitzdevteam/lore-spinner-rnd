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
 * filters rows by game id, sorts chronologically, and asserts two hard rules
 * per row so the next playtest produces deterministic pass/fail evidence
 * instead of vibe.
 *
 * Hard rules (Chaos Engine):
 *   1. session_number does NOT regress across turns.
 *   2. Generated choices differ from the immediately previous turn's choices
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
            $this->line('id:                      ' . $game->id);
            $this->line('story_id:                ' . $game->story_id);
            $this->line('model:                   ' . ($game->model ?? 'null'));
            $this->line('current_session_number:  ' . ($game->current_session_number ?? 'null'));
            $this->line('current_session_complete:' . ($game->current_session_complete ? 'true' : 'false'));
            $this->line('is_climactic_choice:     ' . ($game->is_climactic_choice ? 'true' : 'false'));
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

            $sessionNumber   = $row['session_number'] ?? null;
            $isContinue      = $row['is_continue'] ?? null;
            $isClimatic      = $row['is_climactic'] ?? null;
            $sessionComplete = $row['session_complete'] ?? null;
            $model           = $row['model'] ?? null;
            $playerInput     = $row['player_input'] ?? '';
            $responseBytes   = $row['response_bytes'] ?? null;
            $choices         = $row['choices'] ?? [];

            $this->line('  session_number:      ' . ($sessionNumber ?? 'null'));
            $this->line('  model:               ' . ($model ?? 'null'));
            $this->line('  is_continue:         ' . $this->bool($isContinue));
            $this->line('  is_climactic:        ' . $this->bool($isClimatic));
            $this->line('  session_complete:    ' . $this->bool($sessionComplete));
            $this->line('  response_bytes:      ' . ($responseBytes ?? 'null'));
            $this->line('  player_input:        ' . ($playerInput !== '' ? '"' . mb_substr($playerInput, 0, 120) . '"' : '(empty)'));

            if (! empty($choices)) {
                $this->line('  choices:');
                foreach ($choices as $idx => $choice) {
                    $letter = chr(ord('A') + $idx);
                    $this->line("    {$letter}) {$choice}");
                }
            }

            $violations = $this->assertHardRules(
                sessionNumber:   is_int($sessionNumber) ? $sessionNumber : null,
                choices:         $choices,
                previousChoices: $previousChoices,
            );

            if ($violations === []) {
                $this->line('  rules:               <fg=green>ALL GREEN (2/2)</>');
            } else {
                $totalViolations += count($violations);
                $this->line('  rules:               <fg=red>' . (2 - count($violations)) . '/2 PASS</>');
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

                    if (! str_contains($line, 'game.turn')) {
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
     * @param  array<int, string>  $choices
     * @param  array<int, string>|null  $previousChoices
     * @return list<string>
     */
    private function assertHardRules(
        ?int $sessionNumber,
        array $choices,
        ?array $previousChoices,
    ): array {
        $violations = [];

        // Rule 1: session_number must not regress between turns.
        static $lastSessionNumber = null;
        if ($sessionNumber !== null && $lastSessionNumber !== null && $sessionNumber < $lastSessionNumber) {
            $violations[] = "rule 1: session_number regressed ({$lastSessionNumber} -> {$sessionNumber})";
        }
        if ($sessionNumber !== null) {
            $lastSessionNumber = $sessionNumber;
        }

        // Rule 2: choices must not be an exact repeat of the previous turn.
        if ($previousChoices !== null && $previousChoices !== [] && $choices !== []) {
            $normalize = static fn (array $list): array => array_map(static fn ($c) => Str::squish((string) $c), $list);
            if ($normalize($choices) === $normalize($previousChoices)) {
                $violations[] = 'rule 2: choices exactly match the previous turn (no scene movement)';
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
