<?php

declare(strict_types=1);

use App\Http\Controllers\ChaosMode\ChaosModeController;

function mergeChaosModeState(array $previous, array $delta): array
{
    $controller = new ChaosModeController();
    $method = (new ReflectionClass($controller))->getMethod('mergeStateDelta');

    return $method->invoke($controller, $previous, $delta);
}

it('preserves keyed world memory entries when the narrator reports only changed deltas', function () {
    $previous = [
        'location' => 'Riverbank by the hedge and rabbit-hole',
        'conditions' => ['Ordinary girl size'],
        'items' => [],
        'object_states' => [
            'little_golden_key: resting on the glass table',
            'drink_me_bottle: sealed and untouched',
        ],
        'relationship_updates' => [
            'White Rabbit: startled but not yet personally afraid',
            'Caterpillar: not met',
        ],
        'world_flags' => [
            'alice_current_size_state: ordinary_girl_size',
            'garden_access_state: visible_but_inaccessible',
            'tear_pool_state: absent',
            'wonderland_reality_frame: threshold',
        ],
        'knowledge' => ['The White Rabbit owns a pocket watch'],
        'notes' => ['The rabbit-hole is the active portal'],
        'player_style' => ['Curious pursuit under pressure'],
        'unresolved_promises' => ['Return the Rabbit watch if taken'],
        'emotional_ledger' => [
            ['category' => 'key_successes_failures', 'entry' => 'Alice followed the Rabbit.'],
        ],
    ];

    $delta = [
        'location' => 'Lamp-lit hall of locked doors',
        'conditions' => ['Shrunk to about ten inches high'],
        'items' => [],
        'object_states' => [
            'drink_me_bottle: shattered against the wall; shrinking effect confirmed',
        ],
        'relationship_updates' => [
            'White Rabbit: more frightened after Alice grabbed at his watch',
        ],
        'world_flags' => [
            'alice_current_size_state: tiny',
            'wonderland_reality_frame: inside_dream_logic',
        ],
        'knowledge' => [
            'The White Rabbit owns a pocket watch',
            'A small taste of the DRINK ME bottle is enough to make Alice shrink',
        ],
        'notes' => ['The DRINK ME bottle has been destroyed'],
        'player_style' => ['Attempts to seize control by removing access to key objects'],
        'unresolved_promises' => [],
        'emotional_ledger_entries' => [
            [
                'category' => 'key_successes_failures',
                'entry' => 'Alice smashed the bottle after drinking from it.',
            ],
        ],
    ];

    $merged = mergeChaosModeState($previous, $delta);

    expect($merged['location'])->toBe('Lamp-lit hall of locked doors')
        ->and($merged['conditions'])->toBe(['Shrunk to about ten inches high'])
        ->and($merged['items'])->toBe([])
        ->and($merged['unresolved_promises'])->toBe([])
        ->and($merged['object_states'])->toBe([
            'little_golden_key: resting on the glass table',
            'drink_me_bottle: shattered against the wall; shrinking effect confirmed',
        ])
        ->and($merged['relationship_updates'])->toBe([
            'White Rabbit: more frightened after Alice grabbed at his watch',
            'Caterpillar: not met',
        ])
        ->and($merged['world_flags'])->toBe([
            'alice_current_size_state: tiny',
            'garden_access_state: visible_but_inaccessible',
            'tear_pool_state: absent',
            'wonderland_reality_frame: inside_dream_logic',
        ])
        ->and($merged['knowledge'])->toBe([
            'The White Rabbit owns a pocket watch',
            'A small taste of the DRINK ME bottle is enough to make Alice shrink',
        ])
        ->and($merged['notes'])->toBe([
            'The rabbit-hole is the active portal',
            'The DRINK ME bottle has been destroyed',
        ])
        ->and($merged['player_style'])->toBe([
            'Curious pursuit under pressure',
            'Attempts to seize control by removing access to key objects',
        ])
        ->and($merged['emotional_ledger'])->toBe([
            ['category' => 'key_successes_failures', 'entry' => 'Alice followed the Rabbit.'],
            ['category' => 'key_successes_failures', 'entry' => 'Alice smashed the bottle after drinking from it.'],
        ]);
});

it('treats empty keyed delta arrays as no change instead of clearing persistent memory', function () {
    $previous = [
        'location' => 'Lamp-lit hall',
        'conditions' => ['Tiny'],
        'items' => ['little golden key'],
        'object_states' => ['little_golden_key: held by Alice'],
        'relationship_updates' => ['White Rabbit: frightened'],
        'world_flags' => ['tear_pool_state: absent'],
        'knowledge' => ['The key fits the little door'],
        'notes' => ['The bottle is broken'],
        'player_style' => ['Controls important objects'],
        'unresolved_promises' => [],
        'emotional_ledger' => [],
    ];

    $merged = mergeChaosModeState($previous, [
        'location' => '',
        'conditions' => ['Tiny', 'Frustrated'],
        'items' => ['little golden key'],
        'object_states' => [],
        'relationship_updates' => [],
        'world_flags' => [],
        'knowledge' => [],
        'notes' => [],
        'player_style' => [],
        'unresolved_promises' => [],
        'emotional_ledger_entries' => [],
    ]);

    expect($merged['location'])->toBe('Lamp-lit hall')
        ->and($merged['object_states'])->toBe(['little_golden_key: held by Alice'])
        ->and($merged['relationship_updates'])->toBe(['White Rabbit: frightened'])
        ->and($merged['world_flags'])->toBe(['tear_pool_state: absent'])
        ->and($merged['knowledge'])->toBe(['The key fits the little door'])
        ->and($merged['notes'])->toBe(['The bottle is broken'])
        ->and($merged['player_style'])->toBe(['Controls important objects']);
});
