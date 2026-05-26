<?php

/**
 * Chaos Mode Deep Probe.
 *
 * Boots the full Laravel app, authenticates a user, starts a Chaos Mode
 * session for Alice (or any slug), runs scripted turns, and dumps EVERY
 * structured field after each step — world_state, alignment_scaffold,
 * symbolic_memory, session_memory, conversation_history, is_climactic_choice,
 * defining_choice_id/line, tier state, choices, response text, etc.
 *
 * Usage:
 *   php "Adaptation layer/Chaos adaptation/v2-implementation/validation/chaos-mode-deep-probe.php" [story_slug] [model] [turns] [user_id]
 *
 * Defaults:
 *   story_slug  alices-adventures-in-wonderland
 *   model       gpt-5.2
 *   turns       4
 *   user_id     (first user in DB)
 *
 * What this probes end-to-end:
 *   1.  ChaosModeController::start()  — cold open, world_state seed, alignment scaffold
 *   2.  ChaosModeController::turn()   — state delta merge, alignment accumulation,
 *       symbolic/session memory growth, is_climactic_choice, defining_choice_id/line
 *   3.  Tiered world-state loader     — Tier 3 fires on turn AFTER climactic
 *   4.  Conversation history shape    — role/text pairs grow correctly
 *   5.  session_complete flag         — only flips when dramatic question resolves
 *   6.  ChaosNarrationSchema shape    — all required fields returned by the LLM
 */

declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ChaosMode\ChaosModeController;
use App\Models\ChaosSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// ── args ──────────────────────────────────────────────────────────────────────
$slug   = $argv[1] ?? 'alices-adventures-in-wonderland';
$model  = $argv[2] ?? 'gpt-5.2';
$turns  = max(1, (int) ($argv[3] ?? 4));
$userId = isset($argv[4]) ? (int) $argv[4] : null;

// ── auth ──────────────────────────────────────────────────────────────────────
$user = $userId ? User::findOrFail($userId) : User::first();
if (! $user) {
    echo "FAIL  No users found in DB — create a user before running this probe.\n";
    exit(1);
}
Auth::loginUsingId($user->id);

// ── scripted player actions ────────────────────────────────────────────────────
// Each action is deliberately designed to trigger specific structured output
// fields — NOT to passively follow the plot. Purpose per turn:
//
//  1  Grab the White Rabbit's pocket watch → object_states[watch], relationship_updates[White Rabbit], unresolved_promises
//  2  Smash the DRINK ME bottle after one sip → object_states[bottle destroyed], conditions[size effect], world_flags
//  3  Drag the Caterpillar off his mushroom → relationship_updates[Caterpillar], emotional_ledger[forcing confrontation]
//  4  Pocket the golden key and hide it → items[key], object_states[key hidden], unresolved_promises[White Rabbit's key]
//  5  Overturn the Mad Hatter's tea table mid-sentence → object_states[tea table], relationship_updates[Hatter+March Hare], emotional_ledger
//  6  Strike the Queen's flamingo into the hedge and refuse to play → relationship_updates[Queen of Hearts], is_climactic_choice candidate, emotional_ledger[defiance]
//  7  Promise the Cheshire Cat the watch in exchange for the way out → unresolved_promises[watch bargain], relationship_updates[Cheshire Cat]
//  8  Accuse the Knave of Hearts directly in court by name → world_flags[court testimony], relationship_updates[Knave+Queen], is_climactic_choice + defining_choice_id candidate
$actions = [
    1 => 'Grab the White Rabbit\'s pocket watch right out of his waistcoat and refuse to give it back until he explains why he is late',
    2 => 'Take one sip from the DRINK ME bottle then hurl it against the wall so no one else can use it — you want to control the size change yourself',
    3 => 'Seize the Caterpillar bodily off his mushroom and hold him at eye level until he tells you who you actually are',
    4 => 'Pocket the golden key and tuck it deep inside your dress so the White Rabbit cannot reclaim it when he returns',
    5 => 'Flip the Mad Hatter\'s entire tea table over mid-sentence — cups, teapot, cakes and all — and demand he answer the riddle straight',
    6 => 'Knock the Queen of Hearts\' flamingo mallet into the rose hedge and announce loudly that her rules are nonsense and you will not play',
    7 => 'Strike a bargain with the Cheshire Cat: promise him the White Rabbit\'s watch in exchange for showing you the way out of Wonderland',
    8 => 'Stand up in the middle of the trial and accuse the Knave of Hearts directly by name, using everything you have witnessed as evidence',
];

// ── helpers ───────────────────────────────────────────────────────────────────
function hr(string $title = ''): void
{
    $line = str_repeat('─', 78);
    echo "\n{$line}\n";
    if ($title !== '') {
        echo "  {$title}\n";
        echo "{$line}\n";
    }
}

function label(string $key, mixed $value, int $indent = 2): void
{
    $pad    = str_repeat(' ', $indent);
    $prefix = $pad . str_pad($key . ':', 30);

    if (is_bool($value)) {
        echo $prefix . ($value ? 'true' : 'false') . "\n";
    } elseif (is_int($value) || is_float($value)) {
        echo $prefix . $value . "\n";
    } elseif (is_string($value)) {
        $short = mb_substr(preg_replace('/\s+/', ' ', strip_tags($value)), 0, 120);
        echo $prefix . ($short !== '' ? $short : '(empty)') . "\n";
    } elseif (is_null($value)) {
        echo $prefix . "(null)\n";
    } elseif (is_array($value)) {
        if (empty($value)) {
            echo $prefix . "(empty array)\n";
        } else {
            echo $prefix . count($value) . " item(s)\n";
            foreach (array_slice($value, 0, 6) as $item) {
                if (is_string($item)) {
                    echo $pad . '  • ' . mb_substr($item, 0, 110) . "\n";
                } elseif (is_array($item)) {
                    $cat   = $item['category'] ?? '';
                    $entry = $item['entry'] ?? '';
                    echo $pad . "  • [{$cat}] " . mb_substr($entry, 0, 100) . "\n";
                }
            }
            if (count($value) > 6) {
                echo $pad . '  … ' . (count($value) - 6) . " more\n";
            }
        }
    }
}

function dumpWorldState(array $ws): void
{
    label('location',              $ws['location'] ?? '');
    label('conditions',            $ws['conditions'] ?? []);
    label('items',                 $ws['items'] ?? []);
    label('object_states',         $ws['object_states'] ?? []);
    label('relationship_updates',  $ws['relationship_updates'] ?? []);
    label('world_flags',           $ws['world_flags'] ?? []);
    label('knowledge',             $ws['knowledge'] ?? []);
    label('notes',                 $ws['notes'] ?? []);
    label('player_style',          $ws['player_style'] ?? []);
    label('unresolved_promises',   $ws['unresolved_promises'] ?? []);
    label('emotional_ledger',      $ws['emotional_ledger'] ?? []);
}

function dumpSession(ChaosSession $s): void
{
    hr('DB STATE  chaos_sessions row');
    label('session_id',            $s->id);
    label('story_session_number',  $s->story_session_number);
    label('turn_count',            $s->turn_count);
    label('model',                 $s->model);
    label('session_complete',      $s->session_complete);
    label('is_climactic_choice',   $s->is_climactic_choice);
    label('defining_choice_id',    $s->defining_choice_id ?? '');
    label('defining_choice_line',  $s->defining_choice_line ?? '');

    echo "\n  ── alignment_scaffold ──\n";
    $a = (array) ($s->alignment_scaffold ?? []);
    $total = ($a['chaotic'] ?? 0) + ($a['lawful'] ?? 0) + ($a['neutral'] ?? 0);
    echo "    chaotic={$a['chaotic']}  lawful={$a['lawful']}  neutral={$a['neutral']}  (total={$total})\n";

    echo "\n  ── world_state ──\n";
    dumpWorldState((array) ($s->world_state ?? []));

    echo "\n  ── symbolic_memory ──\n";
    $sym = trim((string) ($s->symbolic_memory ?? ''));
    echo '  ' . ($sym !== '' ? mb_substr($sym, 0, 400) : '(null)') . "\n";

    echo "\n  ── session_memory ──\n";
    $mem = trim((string) ($s->session_memory ?? ''));
    echo '  ' . ($mem !== '' ? mb_substr($mem, 0, 400) : '(null)') . "\n";

    $history = (array) ($s->conversation_history ?? []);
    echo "\n  ── conversation_history ── " . count($history) . " turn(s)\n";
    foreach ($history as $i => $turn) {
        $role = str_pad((string) ($turn['role'] ?? '?'), 8);
        $text = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($turn['text'] ?? '')))), 0, 100);
        echo "    [{$i}] {$role} {$text}\n";
    }
}

function dumpApiResponse(array $resp): void
{
    hr('LLM RESPONSE  (structured output)');
    echo "\n  ── narration (first 400 chars) ──\n";
    $text = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($resp['response'] ?? '')))), 0, 400);
    echo "  {$text}\n";

    echo "\n  ── choices (3 expected) ──\n";
    foreach ((array) ($resp['choices'] ?? []) as $i => $c) {
        echo "    " . ($i + 1) . ". {$c}\n";
    }

    echo "\n  ── structured fields ──\n";
    label('session_complete',      $resp['session_complete'] ?? false);
    label('is_climactic_choice',   $resp['is_climactic_choice'] ?? false);
    label('defining_choice_id',    $resp['defining_choice_id'] ?? '');
    label('defining_choice_line',  $resp['defining_choice_line'] ?? '');

    echo "\n  ── alignment_tally_delta ──\n";
    $d = (array) ($resp['alignment_tally_delta'] ?? []);
    echo "    chaotic+={$d['chaotic']}  lawful+={$d['lawful']}  neutral+={$d['neutral']}\n";

    echo "\n  ── state_delta summary ──\n";
    $sd = (array) ($resp['state_delta'] ?? []);
    label('location',             $sd['location'] ?? '');
    label('conditions',           $sd['conditions'] ?? []);
    label('items',                $sd['items'] ?? []);
    label('object_states',        $sd['object_states'] ?? []);
    label('relationship_updates', $sd['relationship_updates'] ?? []);
    label('world_flags',          $sd['world_flags'] ?? []);
    label('knowledge',            $sd['knowledge'] ?? []);
    label('notes',                $sd['notes'] ?? []);
    label('player_style',         $sd['player_style'] ?? []);
    label('unresolved_promises',  $sd['unresolved_promises'] ?? []);
    label('emotional_ledger_entries', $sd['emotional_ledger_entries'] ?? []);

    echo "\n  ── memory updates ──\n";
    label('symbolic_memory_update', $resp['symbolic_memory_update'] ?? '');
    label('session_memory_update',  $resp['session_memory_update'] ?? '');
}

function checkSchemaCompleteness(array $resp, int $turnNum): void
{
    $required = [
        'response', 'choices', 'session_complete', 'state_delta',
        'alignment_tally_delta', 'is_climactic_choice',
        'defining_choice_id', 'defining_choice_line',
        'symbolic_memory_update', 'session_memory_update',
    ];
    $stateKeys = [
        'location', 'conditions', 'items', 'object_states',
        'relationship_updates', 'world_flags', 'knowledge', 'notes',
        'player_style', 'unresolved_promises', 'emotional_ledger_entries',
    ];

    hr('SCHEMA CHECK  turn ' . $turnNum);
    $allOk = true;
    foreach ($required as $key) {
        $ok = array_key_exists($key, $resp);
        echo '  ' . ($ok ? 'ok   ' : 'MISS ') . $key . "\n";
        if (! $ok) {
            $allOk = false;
        }
    }
    $sd = (array) ($resp['state_delta'] ?? []);
    foreach ($stateKeys as $key) {
        $ok = array_key_exists($key, $sd);
        echo '  ' . ($ok ? 'ok   ' : 'MISS ') . "state_delta.{$key}\n";
        if (! $ok) {
            $allOk = false;
        }
    }
    $atd = (array) ($resp['alignment_tally_delta'] ?? []);
    foreach (['chaotic', 'lawful', 'neutral'] as $key) {
        $ok = array_key_exists($key, $atd);
        echo '  ' . ($ok ? 'ok   ' : 'MISS ') . "alignment_tally_delta.{$key}\n";
        if (! $ok) {
            $allOk = false;
        }
    }
    $choiceCount = count((array) ($resp['choices'] ?? []));
    echo '  ' . ($choiceCount === 3 ? 'ok   ' : "WARN ") . "choices count={$choiceCount} (expected 3)\n";
    echo "\n  " . ($allOk ? '✔ Schema complete' : '✘ Schema incomplete — see MISS lines above') . "\n";
}

// ── make an authenticated controller request ──────────────────────────────────
function makeRequest(string $uri, string $method, array $data): Request
{
    $request = Request::create($uri, $method, $data);
    $request->setContainer(app());

    return $request;
}

// ── boot ──────────────────────────────────────────────────────────────────────
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║  CHAOS MODE DEEP PROBE                                                      ║\n";
echo "║  story_slug = {$slug}\n";
echo "║  model      = {$model}\n";
echo "║  turns      = {$turns}\n";
echo "║  user       = {$user->email} (id={$user->id})\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";

// ── story pre-check ──────────────────────────────────────────────────────────
$story = \App\Models\Story::where('slug', $slug)->with('adaptation.sessionAdaptations')->first();
if (! $story) {
    echo "\nFAIL  Story '{$slug}' not found.\n";
    exit(1);
}
$adaptation = $story->adaptation;
if (! $adaptation) {
    echo "\nFAIL  Story has no adaptation — run stories:run-adaptation first.\n";
    exit(1);
}
$totalSessions = $adaptation->sessionAdaptations->count();
$s1 = $adaptation->sessionAdaptations->firstWhere('session_number', 1);

hr('PRE-CHECK');
label('story',                  $story->title);
label('adaptation_status',      $adaptation->adaptation_status->value);
label('total_sessions',         $totalSessions);
label('session_1_prompt_bytes', $s1 ? strlen((string) $s1->runtime_narrator_prompt) : 0);
label('session_1_status',       $s1 ? $s1->session_status->value : 'missing');
if (! $s1 || empty($s1->runtime_narrator_prompt)) {
    echo "\nFAIL  Session 1 has no assembled runtime_narrator_prompt. Re-run adaptation pipeline.\n";
    exit(1);
}
echo "\n  ok  Story is V2-ready.\n";

// ── controller ───────────────────────────────────────────────────────────────
$controller = app(ChaosModeController::class);

// ── START ─────────────────────────────────────────────────────────────────────
hr('CHAOS MODE START');
echo "\n  Calling POST /chaos-mode/start (model={$model}) ...\n";

$startRequest = makeRequest('/chaos-mode/start', 'POST', [
    'story_slug' => $slug,
    'model'      => $model,
]);

$startTime = microtime(true);
$startResponse = $controller->start($startRequest);
$elapsed = round((microtime(true) - $startTime), 2);

$startData = json_decode($startResponse->getContent(), true) ?? [];
$httpStatus = $startResponse->getStatusCode();

echo "  HTTP {$httpStatus}  ({$elapsed}s)\n";

if ($httpStatus !== 200) {
    echo "\n  ERROR: " . ($startData['error'] ?? 'unknown') . "\n";
    exit(1);
}

$sessionId = $startData['session_id'] ?? null;
if (! $sessionId) {
    echo "\n  FAIL  No session_id in response.\n";
    exit(1);
}

echo "  session_id = {$sessionId}\n";

// Dump API response
dumpApiResponse($startData);

// Schema check
checkSchemaCompleteness($startData, 0);

// DB state
$chaosSession = ChaosSession::findOrFail($sessionId);
dumpSession($chaosSession);

$prevClimactic = $chaosSession->is_climactic_choice;

// ── TURNS ─────────────────────────────────────────────────────────────────────
for ($t = 1; $t <= $turns; $t++) {
    $action = $actions[$t] ?? "Continue exploring";

    hr("TURN {$t}  player action");
    echo "\n  \"{$action}\"\n";
    echo "  (prev is_climactic_choice={$prevClimactic} → Tier 3 will " . ($prevClimactic ? 'LOAD' : 'not load') . " this turn)\n";

    $turnRequest = makeRequest('/chaos-mode/turn', 'POST', [
        'session_id'    => $sessionId,
        'player_action' => $action,
        'model'         => $model,
    ]);

    $t0 = microtime(true);
    $turnResponse = $controller->turn($turnRequest);
    $elapsed = round((microtime(true) - $t0), 2);

    $turnData = json_decode($turnResponse->getContent(), true) ?? [];
    $status   = $turnResponse->getStatusCode();
    echo "\n  HTTP {$status}  ({$elapsed}s)\n";

    if ($status !== 200) {
        echo "\n  ERROR: " . ($turnData['error'] ?? 'unknown') . "\n";
        echo "  Stopping probe.\n";
        break;
    }

    dumpApiResponse($turnData);
    checkSchemaCompleteness($turnData, $t);

    $chaosSession->refresh();
    dumpSession($chaosSession);

    // Tier 3 check
    if ($prevClimactic) {
        hr("TIER 3 CHECK  (prev turn was climactic)");
        echo "\n  The system prompt on THIS turn should have included PERSISTENT STATE — TIER 3.\n";
        echo "  Verify: grep -A5 \"TIER 3\" storage/logs/narration-*.log | tail -10\n";
        echo "  (Probe cannot introspect the assembled prompt sent to LLM — check logs manually.)\n";
    }

    if ((bool) ($turnData['session_complete'] ?? false)) {
        echo "\n  ── SESSION COMPLETE ── session_complete=true returned by LLM\n";
        break;
    }

    $prevClimactic = $chaosSession->is_climactic_choice;
}

// ── FINAL SUMMARY ─────────────────────────────────────────────────────────────
hr('FINAL SUMMARY');
$chaosSession->refresh();
$ws       = (array) ($chaosSession->world_state ?? []);
$al       = (array) ($chaosSession->alignment_scaffold ?? []);
$history  = (array) ($chaosSession->conversation_history ?? []);
$ledger   = (array) ($ws['emotional_ledger'] ?? []);

echo "\n  session_id          = {$sessionId}\n";
echo "  turns_completed     = {$chaosSession->turn_count}\n";
echo "  session_complete    = " . ($chaosSession->session_complete ? 'true' : 'false') . "\n";
echo "  is_climactic_choice = " . ($chaosSession->is_climactic_choice ? 'true' : 'false') . "\n";
echo "  defining_choice_id  = " . ($chaosSession->defining_choice_id ?? '(none)') . "\n";
echo "\n  Alignment scaffold:\n";
echo "    chaotic={$al['chaotic']}  lawful={$al['lawful']}  neutral={$al['neutral']}\n";
echo "\n  World-state totals:\n";

foreach ([
    'conditions', 'items', 'object_states', 'relationship_updates',
    'world_flags', 'knowledge', 'notes', 'player_style',
    'unresolved_promises',
] as $k) {
    $count = count((array) ($ws[$k] ?? []));
    echo "    " . str_pad($k . ':', 26) . " {$count}\n";
}
echo "    " . str_pad('emotional_ledger:', 26) . " " . count($ledger) . "\n";
echo "\n  conversation_history = " . count($history) . " turn(s)\n";
$sym = trim((string) ($chaosSession->symbolic_memory ?? ''));
echo "  symbolic_memory      = " . ($sym !== '' ? mb_strlen($sym) . " chars — \"" . mb_substr($sym, 0, 100) . "…\"" : '(empty)') . "\n";
$mem = trim((string) ($chaosSession->session_memory ?? ''));
echo "  session_memory       = " . ($mem !== '' ? mb_strlen($mem) . " chars — \"" . mb_substr($mem, 0, 100) . "…\"" : '(empty)') . "\n";

echo "\n  Check narration logs:\n";
echo "    grep \"chaos.start\\|chaos.turn\" storage/logs/narration-*.log | tail -20\n";
echo "\n  Check Tier 3 trigger:\n";
echo "    grep -A5 \"TIER 3\" storage/logs/narration-*.log | tail -15\n";

echo "\n=== chaos-mode-deep-probe complete ===\n\n";
