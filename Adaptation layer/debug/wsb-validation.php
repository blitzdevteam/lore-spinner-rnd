<?php

declare(strict_types=1);

/*
 * WS-B validation harness — DB-tolerant.
 *
 * Exercises the four pure helpers introduced in PromptController in WS-B
 * (matchAuthoredChoice, applyStateDelta, mergeTrackedDimensions,
 * appendBranchingChoice, appendBranchResolutionLog, resolveCurrentBeatType)
 * via a 6-turn Alice scenario and asserts the cumulative world_state shape.
 *
 * Run from project root: php "Adaptation layer/debug/wsb-validation.php"
 */

define('LARAVEL_START', microtime(true));
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\User\Game\PromptController;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;

$controller = new PromptController();
$ref = new ReflectionClass($controller);

$invoke = function (string $method, array $args) use ($controller, $ref) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($controller, $args);
};

$failures = [];
$assert = function (bool $cond, string $label) use (&$failures): void {
    echo ($cond ? '  ok   ' : '  FAIL ') . $label . PHP_EOL;
    if (! $cond) {
        $failures[] = $label;
    }
};

echo "=== WS-B validation: Alice 6-turn scenario ===\n";

// Synthesize a session_choice_design like the adaptation layer produces.
$fakeSessionAdaptation = new SessionAdaptation();
$fakeSessionAdaptation->forceFill([
    'session_number' => 1,
    'session_status' => SessionAdaptationStatusEnum::COMPLETED,
    'session_choice_design' => [
        'choices' => [
            ['option' => 'A', 'choice_id' => 'C1', 'text' => 'Try the golden key on the smallest door'],
            ['option' => 'B', 'choice_id' => 'C1', 'text' => 'Drink from the bottle labeled DRINK ME'],
            ['option' => 'C', 'choice_id' => 'C1', 'text' => 'Look around the long hallway carefully'],
        ],
    ],
    'session_architecture' => [
        'beats' => [
            ['type' => 'cold_open'],
            ['type' => 'rising_curiosity'],
            ['type' => 'first_branch'],
            ['type' => 'consequence'],
            ['type' => 'session_hook'],
        ],
    ],
]);

// --- matchAuthoredChoice ---
echo "\n[1] matchAuthoredChoice\n";
$m1 = $invoke('matchAuthoredChoice', ['I drink from the DRINK ME bottle', $fakeSessionAdaptation]);
$assert($m1 !== null && $m1['option'] === 'B', 'verbatim-ish input maps to option B');

$m2 = $invoke('matchAuthoredChoice', ['try the golden key on the smallest door', $fakeSessionAdaptation]);
$assert($m2 !== null && $m2['option'] === 'A', 'lowercased verbatim maps to option A');

$m3 = $invoke('matchAuthoredChoice', ['shout into the void', $fakeSessionAdaptation]);
$assert($m3 === null, 'unrelated input does not match any option');

$m4 = $invoke('matchAuthoredChoice', ['', $fakeSessionAdaptation]);
$assert($m4 === null, 'empty input does not match');

$m5 = $invoke('matchAuthoredChoice', ['drink from the bottle', $fakeSessionAdaptation]);
$assert($m5 !== null && $m5['option'] === 'B', 'short paraphrase matches via token overlap');

// --- 6-turn Alice scenario, applyStateDelta cumulatively ---
echo "\n[2] applyStateDelta cumulative buildup (6 turns)\n";
$worldState = [];

$turns = [
    // Turn 1: cold open — Alice notices a small bottle.
    [
        'objects_acquired' => [],
        'objects_lost' => [],
        'objects_transformed' => [],
        'conditions_added' => [],
        'conditions_removed' => [],
        'location_changed' => 'long hallway with the small door',
        'knowledge_gained' => ['The door is locked.'],
        'relationship_changes' => [],
        'tracked_path_update' => [],
        'flags_set' => ['entered_long_hallway'],
    ],
    // Turn 2: picks up the bottle.
    [
        'objects_acquired' => [
            ['name' => 'bottle labeled DRINK ME', 'qualifier' => 'full', 'contains' => ['mysterious cordial']],
        ],
        'objects_lost' => [],
        'objects_transformed' => [],
        'conditions_added' => [],
        'conditions_removed' => [],
        'location_changed' => '',
        'knowledge_gained' => [],
        'relationship_changes' => [],
        'tracked_path_update' => [['dimension' => 'curiosity_vs_caution', 'path' => 'curiosity']],
        'flags_set' => [],
    ],
    // Turn 3: drinks (option B route) — bottle drained, Alice shrinks.
    [
        'objects_acquired' => [],
        'objects_lost' => [],
        'objects_transformed' => [
            ['name' => 'bottle labeled DRINK ME', 'new_qualifier' => 'drained'],
        ],
        'conditions_added' => [
            ['name' => 'small', 'note' => 'shrunk to ten inches'],
        ],
        'conditions_removed' => [],
        'location_changed' => '',
        'knowledge_gained' => ['The bottle made you smaller.'],
        'relationship_changes' => [],
        'tracked_path_update' => [['dimension' => 'curiosity_vs_caution', 'path' => 'curiosity']],
        'flags_set' => [],
    ],
    // Turn 4: picks up the golden key from the table.
    [
        'objects_acquired' => [
            ['name' => 'small golden key', 'qualifier' => 'in hand', 'contains' => []],
        ],
        'objects_lost' => [],
        'objects_transformed' => [],
        'conditions_added' => [],
        'conditions_removed' => [],
        'location_changed' => '',
        'knowledge_gained' => [],
        'relationship_changes' => [],
        'tracked_path_update' => [],
        'flags_set' => [],
    ],
    // Turn 5: tries key on smallest door — door opens (relationship to "Garden" added as knowledge).
    [
        'objects_acquired' => [],
        'objects_lost' => [],
        'objects_transformed' => [],
        'conditions_added' => [],
        'conditions_removed' => [],
        'location_changed' => 'doorway to a small garden',
        'knowledge_gained' => ['A small garden lies beyond the door.'],
        'relationship_changes' => [],
        'tracked_path_update' => [['dimension' => 'curiosity_vs_caution', 'path' => 'curiosity']],
        'flags_set' => ['unlocked_smallest_door'],
    ],
    // Turn 6: tries to squeeze through but is too large — condition shifts.
    [
        'objects_acquired' => [],
        'objects_lost' => [],
        'objects_transformed' => [],
        'conditions_added' => [
            ['name' => 'frustrated', 'note' => 'cannot fit through doorway'],
        ],
        'conditions_removed' => ['small'],
        'location_changed' => '',
        'knowledge_gained' => [],
        'relationship_changes' => [],
        'tracked_path_update' => [],
        'flags_set' => [],
    ],
];

foreach ($turns as $i => $delta) {
    $worldState = $invoke('applyStateDelta', [$worldState, $delta]);
    echo '  turn ' . ($i + 1) . ' applied; objects=' . count($worldState['objects'])
        . ' conditions=' . count($worldState['conditions'])
        . ' knowledge=' . count($worldState['knowledge'])
        . " location=" . ($worldState['location'] ?? '(null)') . PHP_EOL;
}

$assert(isset($worldState['objects']['bottle labeled DRINK ME']), 'bottle object retained across turns');
$assert(($worldState['objects']['bottle labeled DRINK ME']['qualifier'] ?? null) === 'drained', 'bottle qualifier transformed to drained');
$assert(isset($worldState['objects']['small golden key']), 'golden key retained');
$assert(! isset($worldState['conditions']['small']), 'small condition was removed in turn 6');
$assert(isset($worldState['conditions']['frustrated']), 'frustrated condition added in turn 6');
$assert($worldState['location'] === 'doorway to a small garden', 'last location_changed wins');
$assert(in_array('The door is locked.', $worldState['knowledge'], true), 'cumulative knowledge T1 retained');
$assert(in_array('The bottle made you smaller.', $worldState['knowledge'], true), 'cumulative knowledge T3 retained');
$assert(in_array('A small garden lies beyond the door.', $worldState['knowledge'], true), 'cumulative knowledge T5 retained');
$assert(in_array('entered_long_hallway', $worldState['flags'], true), 'flag from T1 retained');
$assert(in_array('unlocked_smallest_door', $worldState['flags'], true), 'flag from T5 retained');

// --- mergeTrackedDimensions ---
echo "\n[3] mergeTrackedDimensions accumulates path votes\n";
$tracked = [];
foreach ($turns as $delta) {
    $tracked = $invoke('mergeTrackedDimensions', [$tracked, $delta['tracked_path_update']]);
}
$assert(($tracked['curiosity_vs_caution'] ?? null) !== null, 'curiosity_vs_caution dimension recorded');
$assert(count($tracked['curiosity_vs_caution']) === 3, 'three curiosity votes accumulated across turns 2,3,5');
$assert(count(array_unique($tracked['curiosity_vs_caution'])) === 1
    && $tracked['curiosity_vs_caution'][0] === 'curiosity', 'all three votes are "curiosity"');

// --- appendBranchingChoice (idempotent per session+event) ---
echo "\n[4] appendBranchingChoice idempotency\n";
$existing = [];
$fakeEvent = new Event();
$fakeEvent->forceFill(['id' => 42, 'session_number' => 1]);
$fakeEvent2 = new Event();
$fakeEvent2->forceFill(['id' => 43, 'session_number' => 1]);

$existing = $invoke('appendBranchingChoice', [
    $existing, $fakeSessionAdaptation, $fakeEvent, 'B', 'C1', 'I drink the bottle', 'authored_choice', false,
]);
$existing = $invoke('appendBranchingChoice', [
    $existing, $fakeSessionAdaptation, $fakeEvent, 'B', 'C1', 'I drink the bottle (refined)', 'authored_choice', true,
]);
$assert(count($existing) === 1, 'second call updates existing entry rather than duplicating');
$assert(($existing[0]['advanced'] ?? null) === true, 'second call overwrote advanced=true');

$existing = $invoke('appendBranchingChoice', [
    $existing, $fakeSessionAdaptation, $fakeEvent2,
    'A', 'C2', 'I take the door on the left', 'authored_choice', true,
]);
$assert(count($existing) === 2, 'different event creates a new entry');

$existing = $invoke('appendBranchingChoice', [
    $existing, $fakeSessionAdaptation, $fakeEvent, '', null, 'random freeform', 'freeform', false,
]);
$assert(count($existing) === 2, 'empty option does not append');

// --- appendBranchResolutionLog ---
echo "\n[5] appendBranchResolutionLog grows monotonically and trims\n";
$log = [];
for ($i = 0; $i < 250; $i++) {
    $log = $invoke('appendBranchResolutionLog', [
        $log, 1, 42, 43, 'freeform', null, null, true,
    ]);
}
$assert(count($log) === 200, 'branch_resolution_log trims to 200 entries');

// --- resolveCurrentBeatType ---
echo "\n[6] resolveCurrentBeatType walks the beat map on advance\n";
$b1 = $invoke('resolveCurrentBeatType', [$fakeSessionAdaptation, true, null]);
$assert($b1 === 'cold_open', 'starts at cold_open when current is null and advancing');

$b2 = $invoke('resolveCurrentBeatType', [$fakeSessionAdaptation, true, 'cold_open']);
$assert($b2 === 'rising_curiosity', 'advances cold_open → rising_curiosity');

$b3 = $invoke('resolveCurrentBeatType', [$fakeSessionAdaptation, false, 'rising_curiosity']);
$assert($b3 === 'rising_curiosity', 'no advance → unchanged');

$bend = $invoke('resolveCurrentBeatType', [$fakeSessionAdaptation, true, 'session_hook']);
$assert($bend === 'session_hook', 'past last beat clamps to last');

echo "\n=== summary ===\n";
echo 'failures: ' . count($failures) . PHP_EOL;
foreach ($failures as $f) {
    echo '  - ' . $f . PHP_EOL;
}

exit(empty($failures) ? 0 : 1);
