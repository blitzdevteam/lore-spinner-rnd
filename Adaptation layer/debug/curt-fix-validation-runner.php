<?php

declare(strict_types=1);

/**
 * Curt fix validation — steps that used to rely on `php artisan tinker <<'TINKER'`.
 * Heredocs break when a host UI sends the whole snippet as one line; this script
 * boots Laravel once and runs the same logic.
 *
 * From project root (inside the app container):
 *   php "Adaptation layer/debug/curt-fix-validation-runner.php" step4 [<GAME_ID>]
 *   php "Adaptation layer/debug/curt-fix-validation-runner.php" step5 [<GAME_ID>]
 *   php "Adaptation layer/debug/curt-fix-validation-runner.php" step5b [<GAME_ID>]
 *   php "Adaptation layer/debug/curt-fix-validation-runner.php" step9 [<GAME_ID>]
 *
 * Game id resolution (first match wins): CLI arg → env CURT_FIX_VALIDATION_GAME_ID →
 * DEFAULT_VALIDATION_GAME_ID (active Alice validation game; Curt's JSON capture is CURT_GAME_LOG_GAME_ID).
 */

/** Historical `game_id` in `Adaptation layer/debug/curt-game-log.json` (Curt's session) — doc correlation only. */
const CURT_GAME_LOG_GAME_ID = '01kpv313jddy575ct6bv6cak4j';

/** Default game id for runbook copy-paste / runner fallback (active Alice validation game; not Curt's capture). */
const DEFAULT_VALIDATION_GAME_ID = '01kpe60znegetqss98x1kvxrb7';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php curt-fix-validation-runner.php {step4|step5|step5b|step9} [<GAME_ID>]\n");
    fwrite(STDERR, "  step5  = reset game (deletes prompts; no first narration yet)\n");
    fwrite(STDERR, "  step5b = call GameController::begin() if no prompts (creates first narration; needs LLM keys)\n");
    fwrite(STDERR, "  GAME_ID: optional CLI arg, else env CURT_FIX_VALIDATION_GAME_ID, else " . DEFAULT_VALIDATION_GAME_ID . "\n");
    exit(64);
}

$step = strtolower($argv[1]);
$gameId = $argv[2] ?? getenv('CURT_FIX_VALIDATION_GAME_ID') ?: DEFAULT_VALIDATION_GAME_ID;
if (($argv[2] ?? null) === null && getenv('CURT_FIX_VALIDATION_GAME_ID') === false) {
    fwrite(STDERR, 'Using DEFAULT_VALIDATION_GAME_ID=' . DEFAULT_VALIDATION_GAME_ID . ' (Curt historical game_id in curt-game-log.json: ' . CURT_GAME_LOG_GAME_ID . "). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.\n");
}

if (! in_array($step, ['step4', 'step5', 'step5b', 'step9'], true)) {
    fwrite(STDERR, "Unknown step: {$argv[1]}\n");
    exit(64);
}

define('LARAVEL_START', microtime(true));
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Actions\Game\CreateGameAction;
use App\Http\Controllers\User\Game\PromptController;
use App\Http\Controllers\User\GameController;
use App\Models\Game;

$game = Game::with(['story', 'currentEvent'])->find($gameId);
if ($game === null) {
    fwrite(STDERR, "No game found for id: {$gameId}\n");
    exit(1);
}

if ($step === 'step4') {
    $ref = new \ReflectionMethod(PromptController::class, 'renderSystemPrompt');
    $ref->setAccessible(true);
    $resolveSession = new \ReflectionMethod(PromptController::class, 'resolveSessionAdaptation');
    $resolveSession->setAccessible(true);

    $ctrl = new PromptController();
    $session = $resolveSession->invoke($ctrl, $game, $game->currentEvent);

    $rendered = $ref->invoke(
        $ctrl,
        $game->story,
        $game->currentEvent,
        $game->prompts()->where('event_id', $game->currentEvent->id)->count(),
        $session,
        $game->world_state ?? [],
        null
    );

    $probes = [
        'PERSISTENT WORLD STATE',
        'TURN STATE',
        'AUTHORED-CHOICE ROUTING',
        'state_delta',
        'objects_acquired',
        'SESSION COLD OPEN',
    ];
    foreach ($probes as $needle) {
        $needle = (string) $needle;
        echo (str_contains($rendered, $needle) ? 'ok   ' : 'MISS ') . $needle . PHP_EOL;
    }
    echo 'rendered_bytes=' . strlen($rendered) . PHP_EOL;
    echo 'session_resolved=' . ($session?->session_number ?? 'null') . PHP_EOL;
    echo 'world_state_object_count=' . count($game->world_state['objects'] ?? []) . PHP_EOL;
    exit(0);
}

if ($step === 'step5') {
    $action = app(CreateGameAction::class);
    $story = $game->story;
    $firstChapter = $story->chapters()->orderBy('position')->first();
    $firstEvent = $firstChapter?->events()->orderBy('position')->first();
    if ($firstEvent === null) {
        fwrite(STDERR, "No first event for story.\n");
        exit(1);
    }
    $startEvent = $action->resolveStartEvent($story, $firstEvent);

    $game->prompts()->delete();
    $game->update([
        'current_event_id' => $startEvent->id,
        'current_session_number' => null,
        'current_beat_type' => null,
        'branching_choices_taken' => null,
        'tracked_dimensions' => null,
        'branch_resolution_log' => null,
        'world_state' => null,
    ]);
    echo 'reset to event=' . $startEvent->id . PHP_EOL;
    echo 'note: events.id is numeric in many seeds; first narration is NOT created until step5b or POST user/games/{id}/begin.' . PHP_EOL;
    exit(0);
}

if ($step === 'step5b') {
    $game->refresh();
    if ($game->prompts()->exists()) {
        echo 'skip_first_narration: prompts already exist (count=' . $game->prompts()->count() . ')' . PHP_EOL;
        $first = $game->prompts()->orderBy('created_at')->first();
        echo 'first_response_first_400=' . PHP_EOL;
        echo mb_substr(strip_tags((string) $first->response), 0, 400) . PHP_EOL;
        exit(0);
    }

    app(GameController::class)->begin($game);
    $game->refresh();

    $first = $game->prompts()->orderBy('created_at')->first();
    if ($first === null) {
        fwrite(STDERR, "begin() did not create a prompt row (check logs / LLM config).\n");
        exit(1);
    }

    echo 'first_prompt_created=yes prompt_id=' . $first->id . PHP_EOL;
    echo 'first_response_first_400=' . PHP_EOL;
    echo mb_substr(strip_tags((string) $first->response), 0, 400) . PHP_EOL;
    exit(0);
}

if ($step === 'step9') {
    $ctrl = new PromptController();
    $resolve = (new \ReflectionClass($ctrl))->getMethod('resolveSessionAdaptation');
    $resolve->setAccessible(true);
    $match = (new \ReflectionClass($ctrl))->getMethod('matchAuthoredChoice');
    $match->setAccessible(true);

    $session = $resolve->invoke($ctrl, $game, $game->currentEvent);
    echo 'session=' . ($session?->session_number ?? 'null') . PHP_EOL;
    echo 'choice_design_keys=' . implode(',', array_keys($session?->session_choice_design ?? [])) . PHP_EOL;

    foreach ([
        'I drink from the DRINK ME bottle',
        'try the golden key on the smallest door',
        'shout into the void',
    ] as $input) {
        $result = $match->invoke($ctrl, $input, $session);
        echo PHP_EOL . 'INPUT: ' . $input . PHP_EOL;
        echo 'MATCH: ' . ($result ? json_encode($result) : '(none)') . PHP_EOL;
    }
    exit(0);
}

exit(1);
