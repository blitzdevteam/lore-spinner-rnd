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
 *   php "Adaptation layer/debug/curt-fix-validation-runner.php" step11 [<GAME_ID>]
 *
 * step11 = direct NarrationAgent probe (bypasses controller; surfaces raw LLM exception or response).
 *
 * Game id resolution (first match wins): CLI arg → env CURT_FIX_VALIDATION_GAME_ID →
 * DEFAULT_VALIDATION_GAME_ID (active Alice validation game; Curt's JSON capture is CURT_GAME_LOG_GAME_ID).
 */

/** Historical `game_id` in `Adaptation layer/debug/curt-game-log.json` (Curt's session) — doc correlation only. */
const CURT_GAME_LOG_GAME_ID = '01kpv313jddy575ct6bv6cak4j';

/** Default game id for runbook copy-paste / runner fallback (active Alice validation game; not Curt's capture). */
const DEFAULT_VALIDATION_GAME_ID = '01kpe60znegetqss98x1kvxrb7';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php curt-fix-validation-runner.php {step4|step5|step5b|step9|step11} [<GAME_ID>]\n");
    fwrite(STDERR, "  step5  = reset game (deletes prompts; no first narration yet)\n");
    fwrite(STDERR, "  step5b = call GameController::begin() if no prompts (creates first narration; needs LLM keys)\n");
    fwrite(STDERR, "  step11 = direct NarrationAgent probe (bypasses controller; needs LLM keys)\n");
    fwrite(STDERR, "  GAME_ID: optional CLI arg, else env CURT_FIX_VALIDATION_GAME_ID, else " . DEFAULT_VALIDATION_GAME_ID . "\n");
    exit(64);
}

$step = strtolower($argv[1]);
$gameId = $argv[2] ?? getenv('CURT_FIX_VALIDATION_GAME_ID') ?: DEFAULT_VALIDATION_GAME_ID;
if (($argv[2] ?? null) === null && getenv('CURT_FIX_VALIDATION_GAME_ID') === false) {
    fwrite(STDERR, 'Using DEFAULT_VALIDATION_GAME_ID=' . DEFAULT_VALIDATION_GAME_ID . ' (Curt historical game_id in curt-game-log.json: ' . CURT_GAME_LOG_GAME_ID . "). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.\n");
}

if (! in_array($step, ['step4', 'step5', 'step5b', 'step9', 'step11'], true)) {
    fwrite(STDERR, "Unknown step: {$argv[1]}\n");
    exit(64);
}

define('LARAVEL_START', microtime(true));
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Actions\Game\CreateGameAction;
use App\Ai\Agents\NarrationAgent;
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
    $extract = (new \ReflectionClass($ctrl))->getMethod('extractAuthoredOptions');
    $extract->setAccessible(true);

    $session = $resolve->invoke($ctrl, $game, $game->currentEvent);
    echo 'session=' . ($session?->session_number ?? 'null') . PHP_EOL;
    echo 'choice_design_keys=' . implode(',', array_keys($session?->session_choice_design ?? [])) . PHP_EOL;

    $candidates = $extract->invoke($ctrl, (array) ($session?->session_choice_design ?? []));
    echo 'extracted_candidates=' . count($candidates) . PHP_EOL;
    foreach (array_slice($candidates, 0, 3) as $i => $c) {
        echo '  ['. $i .'] option=' . $c['option'] . ' choice_id=' . ($c['choice_id'] ?? 'null') . ' text="' . mb_substr($c['text'], 0, 80) . '"' . PHP_EOL;
    }

    foreach ([
        'Sprint after him and dive for the rabbit-hole',
        'Keep him in sight but slow just long enough to clock landmarks',
        'Shout into the void',
    ] as $input) {
        $result = $match->invoke($ctrl, $input, $session);
        echo PHP_EOL . 'INPUT: ' . $input . PHP_EOL;
        echo 'MATCH: ' . ($result ? json_encode($result) : '(none)') . PHP_EOL;
    }
    exit(0);
}

if ($step === 'step11') {
    // Direct NarrationAgent probe — bypasses both controllers so any LLM exception is fully visible.
    // This isolates the schema-strict / API-key / model-availability layer from the controller path.
    $miniSystemPrompt = <<<'PROMPT'
=== SYSTEM ROLE ===
You are LORESPINNER. This is a smoke-test of the structured-output schema. Produce valid output.

=== CURRENT EVENT ===
The player stands at the edge of a sleepy riverbank under heavy summer heat.

=== TURN STATE ===
This is TURN 1 of this event.

=== OUTPUT REQUIREMENT ===
Return the structured fields. For state_delta, use empty arrays/strings for unchanged categories.
Set input_classification = "opening" since this is turn 1. Choices = 3 short verb-led actions.
PROMPT;

    $userPrompt = "PLAYER'S ACTION:\nThe player begins.";

    echo 'agent_class=' . NarrationAgent::class . PHP_EOL;
    echo 'system_prompt_bytes=' . strlen($miniSystemPrompt) . PHP_EOL;

    try {
        $response = NarrationAgent::make(customInstructions: $miniSystemPrompt)->prompt($userPrompt);

        $responseHtml = (string) ($response['response'] ?? '');
        $stateDelta = is_array($response['state_delta'] ?? null) ? $response['state_delta'] : [];

        echo 'status=ok' . PHP_EOL;
        echo 'response_bytes=' . strlen($responseHtml) . PHP_EOL;
        echo 'response_first_300=' . mb_substr(strip_tags($responseHtml), 0, 300) . PHP_EOL;
        echo 'choices_count=' . count($response['choices'] ?? []) . PHP_EOL;
        echo 'input_classification=' . (string) ($response['input_classification'] ?? '(missing)') . PHP_EOL;
        echo 'state_delta_keys=' . implode(',', array_keys($stateDelta)) . PHP_EOL;
        exit(0);
    } catch (\Throwable $e) {
        echo 'status=fail' . PHP_EOL;
        echo 'exception_class=' . $e::class . PHP_EOL;
        echo 'exception_message=' . $e->getMessage() . PHP_EOL;
        $trace = $e->getTrace();
        if (! empty($trace[0])) {
            $f = $trace[0];
            echo 'first_frame=' . ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . PHP_EOL;
        }
        if ($e->getPrevious()) {
            echo 'previous_class=' . $e->getPrevious()::class . PHP_EOL;
            echo 'previous_message=' . $e->getPrevious()->getMessage() . PHP_EOL;
        }
        exit(1);
    }
}

exit(1);
